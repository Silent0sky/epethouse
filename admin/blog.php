<?php
/**
 * admin/blog.php — Blog posts management (CRUD).
 *
 * Lists all blog_posts joined with their author (users). Admins can create,
 * edit and delete posts. Slug is auto-generated via slugify() and made unique
 * by appending -2, -3, etc. Tags are stored as JSON.
 */
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(ROLE_ADMIN);
$pageTitle = 'Blog Posts';

// ─── POST handler ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = clean($_POST['action'] ?? '');

    if ($action === 'create' || $action === 'update') {
        $id       = clean($_POST['id'] ?? '');
        $title    = clean($_POST['title'] ?? '');
        $slug     = trim($_POST['slug'] ?? '');
        $excerpt  = clean($_POST['excerpt'] ?? '');
        $content  = $_POST['content'] ?? '';          // rich text allowed (not stripped)
        $category = clean($_POST['category'] ?? 'General');
        $readTime = max(1, (int) ($_POST['read_time'] ?? 3));
        $tags     = clean($_POST['tags'] ?? '');
        $published = isset($_POST['published']) ? 1 : 0;

        if ($title === '' || $excerpt === '' || $content === '') {
            flash('danger', 'Title, excerpt and content are required.');
            redirect(APP_URL . '/admin/blog.php');
        }

        // Auto slug from title if blank
        if ($slug === '') {
            $slug = slugify($title);
        } else {
            $slug = slugify($slug);
        }

        // Ensure unique slug (excluding current id when editing)
        $base   = $slug;
        $suffix = 1;
        while (true) {
            if ($action === 'update') {
                $exists = db_select_one(
                    'SELECT id FROM blog_posts WHERE slug = ? AND id <> ? LIMIT 1',
                    [$slug, $id]
                );
            } else {
                $exists = db_select_one(
                    'SELECT id FROM blog_posts WHERE slug = ? LIMIT 1',
                    [$slug]
                );
            }
            if (!$exists) break;
            $suffix++;
            $slug = $base . '-' . $suffix;
        }

        // Image upload (optional)
        $imagePath = null;
        if (!empty($_FILES['image']['name'])) {
            $up = upload_file('image', 'blog');
            if ($up['ok']) {
                $imagePath = $up['url'];
            } else {
                flash('danger', 'Image upload failed: ' . $up['error']);
                redirect(APP_URL . '/admin/blog.php');
            }
        }

        // Tags → JSON array
        $tagArr = array_filter(array_map('trim', explode(',', $tags)), fn($t) => $t !== '');
        $tagsJson = json_encode($tagArr, JSON_UNESCAPED_UNICODE) ?: null;

        if ($action === 'create') {
            $newId = gen_id('bp_');
            db_insert('blog_posts', [
                'id'        => $newId,
                'author_id' => $u['id'],
                'title'     => $title,
                'slug'      => $slug,
                'excerpt'   => $excerpt,
                'content'   => $content,
                'category'  => $category,
                'image'     => $imagePath,
                'published' => $published,
                'read_time' => $readTime,
                'tags'      => $tagsJson,
            ]);
            flash('success', 'Blog post created successfully.');
        } else {
            $existing = db_select_one('SELECT id, image FROM blog_posts WHERE id = ? LIMIT 1', [$id]);
            if (!$existing) {
                flash('danger', 'Post not found.');
                redirect(APP_URL . '/admin/blog.php');
            }
            $fields = [
                'title'     => $title,
                'slug'      => $slug,
                'excerpt'   => $excerpt,
                'content'   => $content,
                'category'  => $category,
                'published' => $published,
                'read_time' => $readTime,
                'tags'      => $tagsJson,
            ];
            if ($imagePath !== null) {
                $fields['image'] = $imagePath;
            }
            $set = [];
            $params = [];
            foreach ($fields as $k => $v) {
                $set[] = "`{$k}` = ?";
                $params[] = $v;
            }
            $params[] = $id;
            db_execute('UPDATE blog_posts SET ' . implode(', ', $set) . ' WHERE id = ?', $params);
            flash('success', 'Blog post updated successfully.');
        }
        redirect(APP_URL . '/admin/blog.php');
    }

    if ($action === 'delete') {
        $id = clean($_POST['id'] ?? '');
        db_execute('DELETE FROM blog_posts WHERE id = ?', [$id]);
        flash('success', 'Blog post deleted.');
        redirect(APP_URL . '/admin/blog.php');
    }

    flash('danger', 'Invalid request.');
    redirect(APP_URL . '/admin/blog.php');
}

// ─── Data: list + optional edit row ──────────────────────────────────
include __DIR__ . '/../includes/header.php';
$editId = $_GET['edit'] ?? null;
$editRow = null;
if ($editId) {
    $editRow = db_select_one('SELECT * FROM blog_posts WHERE id = ? LIMIT 1', [$editId]);
}

$posts = db_select(
    'SELECT bp.id, bp.title, bp.slug, bp.category, bp.published, bp.read_time,
            bp.created_at, bp.image, bp.tags,
            u.name AS author
       FROM blog_posts bp
       JOIN users u ON u.id = bp.author_id
      ORDER BY bp.created_at DESC'
);

/** Render tags as small pills. */
function blog_tags(?string $json): string
{
    $arr = json_array($json);
    if (!$arr) return '<span class="text-muted">—</span>';
    $out = '';
    foreach (array_slice($arr, 0, 3) as $t) {
        $out .= '<span class="badge bg-purple-soft text-purple me-1">' . e($t) . '</span>';
    }
    if (count($arr) > 3) {
        $out .= '<span class="badge bg-light text-muted">+' . (count($arr) - 3) . '</span>';
    }
    return $out;
}
?>
<!-- Page header -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 fade-in">
    <div>
        <h1 class="h3 mb-1 fw-bold text-purple">
            <i class="fas fa-newspaper me-2"></i>Blog Posts
        </h1>
        <p class="text-muted mb-0">Write and manage articles published in the store blog.</p>
    </div>
    <button type="button" class="btn btn-grad btn-sm mt-2 mt-md-0" data-bs-toggle="modal" data-bs-target="#postModal" id="newPostBtn">
        <i class="fas fa-plus me-1"></i> Add Post
    </button>
</div>

<!-- List -->
<div class="card">
    <div class="card-header"><i class="fas fa-list me-2 text-purple"></i>All Posts (<?= count($posts) ?>)</div>
    <div class="card-body p-0">
        <?php if (!$posts): ?>
            <div class="empty-state">
                <i class="fas fa-feather"></i>
                <p class="mb-0">No blog posts yet. Click "Add Post" to create your first article.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0 table-hover">
                <thead><tr>
                    <th>Title</th><th>Author</th><th>Category</th>
                    <th>Tags</th><th>Published</th><th>Read</th>
                    <th>Created</th><th class="text-end">Actions</th>
                </tr></thead>
                <tbody>
                <?php foreach ($posts as $p): ?>
                    <tr>
                        <td>
                            <div class="fw-600"><?= e($p['title']) ?></div>
                            <small class="text-muted">/<?= e($p['slug']) ?></small>
                        </td>
                        <td>
                            <span class="ph-avatar me-1" style="width:24px;height:24px;font-size:0.65rem;background:var(--ph-purple-100);color:var(--ph-purple-dark);"><?= e(initials($p['author'])) ?></span>
                            <?= e($p['author']) ?>
                        </td>
                        <td><span class="badge bg-light text-dark border"><?= e($p['category']) ?></span></td>
                        <td><?= blog_tags($p['tags']) ?></td>
                        <td>
                            <?php if ((int)$p['published'] === 1): ?>
                                <span class="badge bg-success"><i class="fas fa-check me-1"></i>Yes</span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><i class="fas fa-eye-slash me-1"></i>No</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-purple-soft text-purple"><?= (int)$p['read_time'] ?> min</span></td>
                        <td class="small text-muted"><?= e(fmt_date($p['created_at'])) ?></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary edit-post-btn"
                                    data-id="<?= e($p['id']) ?>"
                                    data-title="<?= e($p['title']) ?>"
                                    data-slug="<?= e($p['slug']) ?>"
                                    data-excerpt="<?= e($p['excerpt']) ?>"
                                    data-content="<?= e($p['content']) ?>"
                                    data-category="<?= e($p['category']) ?>"
                                    data-read_time="<?= (int)$p['read_time'] ?>"
                                    data-tags="<?= e(implode(', ', json_array($p['tags']))) ?>"
                                    data-published="<?= (int)$p['published'] ?>"
                                    data-image="<?= e($p['image'] ?? '') ?>"
                                    data-bs-toggle="modal" data-bs-target="#postModal">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="post" action="<?= APP_URL ?>/admin/blog.php" class="d-inline"
                                  onsubmit="return confirm('Delete this post? This cannot be undone.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= e($p['id']) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ─── Create / Edit modal ─────────────────────────────────────────── -->
<div class="modal fade" id="postModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" action="<?= APP_URL ?>/admin/blog.php" enctype="multipart/form-data" id="postForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="postAction" value="create">
                <input type="hidden" name="id" id="postId" value="">
                <div class="modal-header">
                    <h5 class="modal-title text-purple"><i class="fas fa-feather me-2"></i><span id="postModalTitle">Add Blog Post</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="fTitle" class="form-control" required maxlength="200">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Category</label>
                            <input type="text" name="category" id="fCategory" class="form-control" value="General" maxlength="60" list="catList">
                            <datalist id="catList">
                                <option value="General">
                                <option value="Pet Care">
                                <option value="Nutrition">
                                <option value="Training">
                                <option value="Health">
                                <option value="Adoption Stories">
                                <option value="News">
                            </datalist>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Slug <small class="text-muted">(auto if blank)</small></label>
                            <input type="text" name="slug" id="fSlug" class="form-control" maxlength="220" placeholder="auto-generated-from-title">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Read time (min)</label>
                            <input type="number" name="read_time" id="fReadTime" class="form-control" value="3" min="1" max="60">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Excerpt <span class="text-danger">*</span></label>
                            <textarea name="excerpt" id="fExcerpt" class="form-control" rows="2" maxlength="400" required placeholder="A short 1–2 sentence summary shown in post cards."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Content <span class="text-danger">*</span></label>
                            <textarea name="content" id="fContent" class="form-control" rows="10" required placeholder="Full article body. HTML allowed."></textarea>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Tags <small class="text-muted">(comma-separated)</small></label>
                            <input type="text" name="tags" id="fTags" class="form-control" placeholder="dogs, training, tips">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Featured image</label>
                            <input type="file" name="image" accept="image/*" class="form-control">
                            <div id="currentImage" class="small text-muted mt-1"></div>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" name="published" id="fPublished" value="1" class="form-check-input">
                                <label for="fPublished" class="form-check-label">Publish immediately (visible on site)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link text-muted" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-grad"><i class="fas fa-save me-1"></i>Save Post</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const modal       = document.getElementById('postModal');
    const form        = document.getElementById('postForm');
    const actionInp   = document.getElementById('postAction');
    const idInp       = document.getElementById('postId');
    const titleInp    = document.getElementById('fTitle');
    const slugInp     = document.getElementById('fSlug');
    const excerptInp  = document.getElementById('fExcerpt');
    const contentInp  = document.getElementById('fContent');
    const categoryInp = document.getElementById('fCategory');
    const readTimeInp = document.getElementById('fReadTime');
    const tagsInp     = document.getElementById('fTags');
    const publishedInp= document.getElementById('fPublished');
    const modalTitle  = document.getElementById('postModalTitle');
    const currentImg  = document.getElementById('currentImage');

    // Reset to "create" mode when opened via Add button
    document.getElementById('newPostBtn').addEventListener('click', () => {
        form.reset();
        actionInp.value = 'create';
        idInp.value = '';
        categoryInp.value = 'General';
        readTimeInp.value = 3;
        publishedInp.checked = false;
        modalTitle.textContent = 'Add Blog Post';
        currentImg.textContent = '';
    });

    // Populate when opened via Edit button
    modal.addEventListener('show.bs.modal', function (ev) {
        const trigger = ev.relatedTarget;
        if (!trigger || !trigger.classList.contains('edit-post-btn')) return;
        actionInp.value = 'update';
        idInp.value       = trigger.dataset.id;
        titleInp.value    = trigger.dataset.title || '';
        slugInp.value     = trigger.dataset.slug || '';
        excerptInp.value  = trigger.dataset.excerpt || '';
        contentInp.value  = trigger.dataset.content || '';
        categoryInp.value = trigger.dataset.category || 'General';
        readTimeInp.value = trigger.dataset.read_time || 3;
        tagsInp.value     = trigger.dataset.tags || '';
        publishedInp.checked = trigger.dataset.published === '1';
        modalTitle.textContent = 'Edit Blog Post';
        currentImg.textContent = trigger.dataset.image
            ? 'Current image: ' + trigger.dataset.image
            : '';
    });
})();
</script>
<?php include __DIR__ . '/../includes/footer.php';
