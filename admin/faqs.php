<?php
/**
 * admin/faqs.php — FAQ CRUD.
 *
 * List, create, edit and delete FAQs shown on the public FAQ page.
 * Each FAQ has a question, answer, category, sort order and active flag.
 */
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(ROLE_ADMIN);
$pageTitle = 'FAQs';

// ─── POST handler ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = clean($_POST['action'] ?? '');

    if ($action === 'create' || $action === 'update') {
        $id       = clean($_POST['id'] ?? '');
        $question = clean($_POST['question'] ?? '');
        $answer   = clean($_POST['answer'] ?? '');
        $category = clean($_POST['category'] ?? 'General');
        $sortOrder= (int) ($_POST['sort_order'] ?? 0);
        $active   = isset($_POST['active']) ? 1 : 0;

        if ($question === '' || $answer === '') {
            flash('danger', 'Question and answer are required.');
            redirect(APP_URL . '/admin/faqs.php');
        }

        if ($action === 'create') {
            db_insert('faqs', [
                'id'         => gen_id('f_'),
                'question'   => $question,
                'answer'     => $answer,
                'category'   => $category,
                'sort_order' => $sortOrder,
                'active'     => $active,
            ]);
            flash('success', 'FAQ created.');
        } else {
            $existing = db_select_one('SELECT id FROM faqs WHERE id = ? LIMIT 1', [$id]);
            if (!$existing) {
                flash('danger', 'FAQ not found.');
                redirect(APP_URL . '/admin/faqs.php');
            }
            db_execute(
                'UPDATE faqs SET question = ?, answer = ?, category = ?, sort_order = ?, active = ? WHERE id = ?',
                [$question, $answer, $category, $sortOrder, $active, $id]
            );
            flash('success', 'FAQ updated.');
        }
        redirect(APP_URL . '/admin/faqs.php');
    }

    if ($action === 'delete') {
        $id = clean($_POST['id'] ?? '');
        db_execute('DELETE FROM faqs WHERE id = ?', [$id]);
        flash('success', 'FAQ deleted.');
        redirect(APP_URL . '/admin/faqs.php');
    }

    if ($action === 'toggle') {
        $id = clean($_POST['id'] ?? '');
        db_execute('UPDATE faqs SET active = 1 - active WHERE id = ?', [$id]);
        flash('success', 'FAQ visibility toggled.');
        redirect(APP_URL . '/admin/faqs.php');
    }

    flash('danger', 'Invalid request.');
    redirect(APP_URL . '/admin/faqs.php');
}

// ─── Data ────────────────────────────────────────────────────────────
include __DIR__ . '/../includes/header.php';
$faqs = db_select(
    'SELECT id, question, answer, category, sort_order, active
       FROM faqs
      ORDER BY sort_order ASC, category ASC, id ASC'
);

// Group by category for display
$byCategory = [];
foreach ($faqs as $f) {
    $byCategory[$f['category']][] = $f;
}
?>
<!-- Page header -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 fade-in">
    <div>
        <h1 class="h3 mb-1 fw-bold text-purple">
            <i class="fas fa-question-circle me-2"></i>FAQs
        </h1>
        <p class="text-muted mb-0">Manage frequently asked questions shown on the site.</p>
    </div>
    <button type="button" class="btn btn-grad btn-sm mt-2 mt-md-0" data-bs-toggle="modal" data-bs-target="#faqModal" id="newFaqBtn">
        <i class="fas fa-plus me-1"></i> Add FAQ
    </button>
</div>

<!-- Summary -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="card text-center">
            <div class="card-body py-3">
                <div class="text-purple fs-3 fw-bold"><?= count($faqs) ?></div>
                <div class="small text-muted">Total FAQs</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card text-center">
            <div class="card-body py-3">
                <div class="text-success fs-3 fw-bold"><?= count(array_filter($faqs, fn($f) => (int)$f['active'] === 1)) ?></div>
                <div class="small text-muted">Active</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card text-center">
            <div class="card-body py-3">
                <div class="text-secondary fs-3 fw-bold"><?= count($byCategory) ?></div>
                <div class="small text-muted">Categories</div>
            </div>
        </div>
    </div>
</div>

<!-- List -->
<div class="card">
    <div class="card-header"><i class="fas fa-list me-2 text-purple"></i>All FAQs (<?= count($faqs) ?>)</div>
    <div class="card-body p-0">
        <?php if (!$faqs): ?>
            <div class="empty-state">
                <i class="fas fa-question-circle"></i>
                <p class="mb-0">No FAQs yet. Click "Add FAQ" to create one.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0 table-hover">
                <thead><tr>
                    <th>Question</th><th>Category</th><th>Order</th>
                    <th>Active</th><th class="text-end">Actions</th>
                </tr></thead>
                <tbody>
                <?php foreach ($faqs as $f): ?>
                    <tr>
                        <td>
                            <div class="fw-600"><?= e(mb_strimwidth($f['question'], 0, 80, '…')) ?></div>
                            <small class="text-muted"><?= e(mb_strimwidth($f['answer'], 0, 90, '…')) ?></small>
                        </td>
                        <td><span class="badge bg-purple-soft text-purple"><?= e($f['category']) ?></span></td>
                        <td><span class="badge bg-light text-dark border"><?= (int) $f['sort_order'] ?></span></td>
                        <td>
                            <?php if ((int)$f['active'] === 1): ?>
                                <span class="badge bg-success"><i class="fas fa-check me-1"></i>Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><i class="fas fa-pause me-1"></i>Hidden</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary edit-faq-btn"
                                    data-id="<?= e($f['id']) ?>"
                                    data-question="<?= e($f['question']) ?>"
                                    data-answer="<?= e($f['answer']) ?>"
                                    data-category="<?= e($f['category']) ?>"
                                    data-sort_order="<?= (int)$f['sort_order'] ?>"
                                    data-active="<?= (int)$f['active'] ?>"
                                    data-bs-toggle="modal" data-bs-target="#faqModal"
                                    title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="post" action="<?= APP_URL ?>/admin/faqs.php" class="d-inline"
                                  onsubmit="return confirm('Delete this FAQ? This cannot be undone.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= e($f['id']) ?>">
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
<div class="modal fade" id="faqModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" action="<?= APP_URL ?>/admin/faqs.php" id="faqForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="faqAction" value="create">
                <input type="hidden" name="id" id="faqId" value="">
                <div class="modal-header">
                    <h5 class="modal-title text-purple"><i class="fas fa-question-circle me-2"></i><span id="faqModalTitle">Add FAQ</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Question <span class="text-danger">*</span></label>
                            <input type="text" name="question" id="fQuestion" class="form-control" required maxlength="300" placeholder="e.g. Do you offer home delivery?">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Answer <span class="text-danger">*</span></label>
                            <textarea name="answer" id="fAnswer" class="form-control" rows="5" required placeholder="Write a clear, helpful answer…"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <input type="text" name="category" id="fCategory" class="form-control" value="General" maxlength="60" list="faqCatList">
                            <datalist id="faqCatList">
                                <option value="General">
                                <option value="Orders">
                                <option value="Payments">
                                <option value="Delivery">
                                <option value="Grooming">
                                <option value="Boarding">
                                <option value="Account">
                                <option value="Refunds">
                            </datalist>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Sort order</label>
                            <input type="number" name="sort_order" id="fSort" class="form-control" value="0" min="0" max="9999">
                            <small class="text-muted">Lower numbers appear first.</small>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" name="active" id="fActive" value="1" class="form-check-input" checked>
                                <label for="fActive" class="form-check-label">Active (visible on the public FAQ page)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link text-muted" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-grad"><i class="fas fa-save me-1"></i>Save FAQ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const modal       = document.getElementById('faqModal');
    const form        = document.getElementById('faqForm');
    const actionInp   = document.getElementById('faqAction');
    const idInp       = document.getElementById('faqId');
    const modalTitle  = document.getElementById('faqModalTitle');
    const fields = {
        question: document.getElementById('fQuestion'),
        answer:   document.getElementById('fAnswer'),
        category: document.getElementById('fCategory'),
        sort:     document.getElementById('fSort'),
        active:   document.getElementById('fActive'),
    };

    document.getElementById('newFaqBtn').addEventListener('click', () => {
        form.reset();
        actionInp.value = 'create';
        idInp.value = '';
        fields.category.value = 'General';
        fields.sort.value = 0;
        fields.active.checked = true;
        modalTitle.textContent = 'Add FAQ';
    });

    modal.addEventListener('show.bs.modal', function (ev) {
        const trigger = ev.relatedTarget;
        if (!trigger || !trigger.classList.contains('edit-faq-btn')) return;
        actionInp.value = 'update';
        idInp.value            = trigger.dataset.id;
        fields.question.value  = trigger.dataset.question || '';
        fields.answer.value    = trigger.dataset.answer || '';
        fields.category.value  = trigger.dataset.category || 'General';
        fields.sort.value      = trigger.dataset.sort_order || 0;
        fields.active.checked  = trigger.dataset.active === '1';
        modalTitle.textContent = 'Edit FAQ';
    });
})();
</script>
<?php include __DIR__ . '/../includes/footer.php';
