<?php
/**
 * admin/products.php — Products CRUD.
 *
 * Manage shop products: name, description, price (with optional strikethrough
 * original price), category, stock, featured flag, in-stock flag, tags and
 * image upload. Delete blocked when an order_items row references the product.
 */
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(ROLE_ADMIN);

// ─── POST handler ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = clean($_POST['action'] ?? '');

    if ($action === 'create') {
        $name          = clean($_POST['name'] ?? '');
        $description   = clean($_POST['description'] ?? '');
        $price         = (float) ($_POST['price'] ?? 0);
        $originalPrice = ($_POST['original_price'] ?? '') !== '' ? (float) $_POST['original_price'] : null;
        $category      = clean($_POST['category'] ?? 'general');
        $stockQty      = (int)   ($_POST['stock_qty'] ?? 0);
        $featured      = isset($_POST['featured']) ? 1 : 0;
        $inStock       = isset($_POST['in_stock']) ? 1 : 0;
        $tagsInput     = clean($_POST['tags'] ?? '');
        $tags          = $tagsInput !== ''
            ? json_encode(array_map('trim', explode(',', $tagsInput)))
            : null;

        if ($name === '' || $price <= 0) {
            flash('danger', 'Name and a valid price are required.');
            redirect(APP_URL . '/admin/products.php');
        }

        // Handle image upload (optional)
        $image = null;
        if (!empty($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $up = upload_file('image', 'profile_photo');
            if ($up['ok']) {
                $image = $up['url'];
            } else {
                flash('danger', 'Image upload failed: ' . $up['error']);
                redirect(APP_URL . '/admin/products.php');
            }
        }

        db_insert('products', [
            'id'             => gen_id('pr_'),
            'name'           => $name,
            'description'    => $description,
            'price'          => $price,
            'original_price' => $originalPrice,
            'category'       => $category,
            'image'          => $image,
            'rating'         => 0.0,
            'review_count'   => 0,
            'in_stock'       => $inStock,
            'stock_qty'      => $stockQty,
            'featured'       => $featured,
            'tags'           => $tags,
        ]);
        flash('success', 'Product "' . $name . '" created successfully.');
        redirect(APP_URL . '/admin/products.php');
    }

    if ($action === 'update') {
        $id            = clean($_POST['id'] ?? '');
        $name          = clean($_POST['name'] ?? '');
        $description   = clean($_POST['description'] ?? '');
        $price         = (float) ($_POST['price'] ?? 0);
        $originalPrice = ($_POST['original_price'] ?? '') !== '' ? (float) $_POST['original_price'] : null;
        $category      = clean($_POST['category'] ?? 'general');
        $stockQty      = (int)   ($_POST['stock_qty'] ?? 0);
        $featured      = isset($_POST['featured']) ? 1 : 0;
        $inStock       = isset($_POST['in_stock']) ? 1 : 0;
        $tagsInput     = clean($_POST['tags'] ?? '');
        $tags          = $tagsInput !== ''
            ? json_encode(array_map('trim', explode(',', $tagsInput)))
            : null;

        if ($id === '' || $name === '' || $price <= 0) {
            flash('danger', 'Invalid input. Please review the form.');
            redirect(APP_URL . '/admin/products.php');
        }

        $exists = db_select_one('SELECT id, image FROM products WHERE id = ? LIMIT 1', [$id]);
        if (!$exists) {
            flash('danger', 'Product not found.');
            redirect(APP_URL . '/admin/products.php');
        }

        // Image upload (optional on update)
        $image = $exists['image'];
        if (!empty($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $up = upload_file('image', 'profile_photo');
            if ($up['ok']) {
                $image = $up['url'];
            } else {
                flash('danger', 'Image upload failed: ' . $up['error']);
                redirect(APP_URL . '/admin/products.php');
            }
        }

        db_execute(
            'UPDATE products
                SET name = ?, description = ?, price = ?, original_price = ?,
                    category = ?, stock_qty = ?, featured = ?, in_stock = ?,
                    tags = ?, image = ?
              WHERE id = ?',
            [$name, $description, $price, $originalPrice, $category,
             $stockQty, $featured, $inStock, $tags, $image, $id]
        );
        flash('success', 'Product "' . $name . '" updated successfully.');
        redirect(APP_URL . '/admin/products.php');
    }

    if ($action === 'delete') {
        $id = clean($_POST['id'] ?? '');
        if ($id === '') {
            flash('danger', 'Invalid product id.');
            redirect(APP_URL . '/admin/products.php');
        }

        $prod = db_select_one('SELECT id, name FROM products WHERE id = ? LIMIT 1', [$id]);
        if (!$prod) {
            flash('danger', 'Product not found.');
            redirect(APP_URL . '/admin/products.php');
        }

        $orderCount = (int) db_scalar(
            'SELECT COUNT(*) FROM order_items WHERE product_id = ?',
            [$id]
        );

        if ($orderCount > 0) {
            flash('danger', 'Cannot delete: product "' . $prod['name'] . '" has ' . $orderCount . ' order(s). Set in_stock=0 instead.');
            redirect(APP_URL . '/admin/products.php');
        }

        db_execute('DELETE FROM products WHERE id = ?', [$id]);
        flash('success', 'Product "' . $prod['name'] . '" deleted successfully.');
        redirect(APP_URL . '/admin/products.php');
    }

    flash('danger', 'Unknown action.');
    redirect(APP_URL . '/admin/products.php');
}

// ─── Data ───────────────────────────────────────────────────────────
$products = db_select(
    'SELECT id, name, description, price, original_price, category, image,
            stock_qty, featured, in_stock, tags, created_at
       FROM products
      ORDER BY created_at DESC'
);

$pageTitle = 'Products';
include __DIR__ . '/../includes/header.php';
?>
<!-- Page header -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 fade-in">
    <div>
        <h1 class="h3 mb-1 fw-bold text-purple">
            <i class="fas fa-box me-2"></i>Products
        </h1>
        <p class="text-muted mb-0">Manage shop inventory (<?= count($products) ?> products).</p>
    </div>
    <button type="button" class="btn btn-grad btn-sm" data-bs-toggle="modal" data-bs-target="#prdCreateModal">
        <i class="fas fa-plus me-1"></i>Add Product
    </button>
</div>

<!-- Products table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list me-2 text-purple"></i>All Products</span>
        <span class="badge bg-purple-soft text-purple"><?= count($products) ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (!$products): ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <p class="mb-3">No products yet. Add your first product.</p>
                <button type="button" class="btn btn-grad btn-sm" data-bs-toggle="modal" data-bs-target="#prdCreateModal">
                    <i class="fas fa-plus me-1"></i>Add Product
                </button>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0 table-hover">
                <thead><tr>
                    <th>Image</th><th>Name</th><th>Category</th>
                    <th class="text-end">Price</th><th class="text-center">Stock</th>
                    <th class="text-center">Featured</th><th class="text-center">Status</th>
                    <th class="text-end">Actions</th>
                </tr></thead>
                <tbody>
                <?php foreach ($products as $p):
                    $tagList = json_array($p['tags'] ?? null);
                ?>
                    <tr>
                        <td>
                            <?php if (!empty($p['image'])): ?>
                                <img src="<?= APP_URL . '/' . e($p['image']) ?>" alt=""
                                     style="width:50px;height:50px;object-fit:cover;border-radius:8px;">
                            <?php else: ?>
                                <div style="width:50px;height:50px;border-radius:8px;"
                                     class="bg-purple-soft d-flex align-items-center justify-content-center">
                                    <i class="fas fa-paw fa-2x text-purple"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-600"><?= e($p['name']) ?></div>
                            <?php if (!empty($tagList)): ?>
                                <small class="text-muted">
                                    <?php foreach (array_slice($tagList, 0, 3) as $t): ?>
                                        <span class="badge bg-light text-secondary me-1">#<?= e($t) ?></span>
                                    <?php endforeach; ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-purple-soft text-purple text-capitalize"><?= e($p['category']) ?></span></td>
                        <td class="text-end">
                            <div class="fw-600"><?= money($p['price']) ?></div>
                            <?php if (!empty($p['original_price']) && (float)$p['original_price'] > (float)$p['price']): ?>
                                <small class="text-muted text-decoration-line-through"><?= money($p['original_price']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ((int)$p['stock_qty'] <= 0): ?>
                                <span class="badge bg-danger"><?= (int)$p['stock_qty'] ?></span>
                            <?php elseif ((int)$p['stock_qty'] < 10): ?>
                                <span class="badge bg-warning text-dark"><?= (int)$p['stock_qty'] ?></span>
                            <?php else: ?>
                                <span class="badge bg-light text-dark border"><?= (int)$p['stock_qty'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ((int)$p['featured'] === 1): ?>
                                <i class="fas fa-star text-warning" title="Featured"></i>
                            <?php else: ?>
                                <i class="far fa-star text-muted" title="Not featured"></i>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ((int)$p['in_stock'] === 1): ?>
                                <span class="badge bg-success">In Stock</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Out of Stock</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal" data-bs-target="#prdEditModal_<?= e($p['id']) ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger"
                                    data-bs-toggle="modal" data-bs-target="#prdDeleteModal_<?= e($p['id']) ?>">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ─── Create Modal ──────────────────────────────────────────────── -->
<div class="modal fade" id="prdCreateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="post" action="<?= APP_URL ?>/admin/products.php" class="modal-content" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-header bg-purple-soft">
                <h5 class="modal-title text-purple"><i class="fas fa-plus me-2"></i>Add Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Product Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required maxlength="150">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Category <span class="text-danger">*</span></label>
                        <input type="text" name="category" class="form-control" required maxlength="60" placeholder="e.g. Food, Toys">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="3" class="form-control" maxlength="2000"></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Price (₹) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-rupee-sign"></i></span>
                            <input type="number" name="price" step="0.01" min="0" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Original Price (₹)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-rupee-sign"></i></span>
                            <input type="number" name="original_price" step="0.01" min="0" class="form-control">
                        </div>
                        <small class="text-muted">Optional — for discounts.</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Stock Quantity</label>
                        <input type="number" name="stock_qty" min="0" class="form-control" value="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tags (comma-separated)</label>
                        <input type="text" name="tags" class="form-control" placeholder="dog, puppy, organic">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Product Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input type="checkbox" name="featured" value="1" class="form-check-input" id="prdCreateFeatured">
                            <label for="prdCreateFeatured" class="form-check-label">
                                <i class="fas fa-star text-warning me-1"></i>Featured product
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input type="checkbox" name="in_stock" value="1" class="form-check-input" id="prdCreateInStock" checked>
                            <label for="prdCreateInStock" class="form-check-label">In stock (available for purchase)</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-grad"><i class="fas fa-save me-1"></i>Create Product</button>
            </div>
        </form>
    </div>
</div>

<!-- ─── Edit + Delete Modals per row ──────────────────────────────── -->
<?php foreach ($products as $p):
    $tagStr = implode(', ', json_array($p['tags'] ?? null));
?>
<!-- Edit Modal -->
<div class="modal fade" id="prdEditModal_<?= e($p['id']) ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="post" action="<?= APP_URL ?>/admin/products.php" class="modal-content" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= e($p['id']) ?>">
            <div class="modal-header bg-purple-soft">
                <h5 class="modal-title text-purple"><i class="fas fa-edit me-2"></i>Edit Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Product Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required maxlength="150"
                               value="<?= e($p['name']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Category <span class="text-danger">*</span></label>
                        <input type="text" name="category" class="form-control" required maxlength="60"
                               value="<?= e($p['category']) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="3" class="form-control" maxlength="2000"><?= e($p['description']) ?></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Price (₹) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-rupee-sign"></i></span>
                            <input type="number" name="price" step="0.01" min="0" class="form-control" required
                                   value="<?= e($p['price']) ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Original Price (₹)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-rupee-sign"></i></span>
                            <input type="number" name="original_price" step="0.01" min="0" class="form-control"
                                   value="<?= e($p['original_price'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Stock Quantity</label>
                        <input type="number" name="stock_qty" min="0" class="form-control"
                               value="<?= e($p['stock_qty']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tags (comma-separated)</label>
                        <input type="text" name="tags" class="form-control" value="<?= e($tagStr) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Product Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <?php if (!empty($p['image'])): ?>
                            <small class="text-muted">Current: <a href="<?= APP_URL . '/' . e($p['image']) ?>" target="_blank">view</a> — upload a new image to replace.</small>
                        <?php else: ?>
                            <small class="text-muted">No image currently set.</small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input type="checkbox" name="featured" value="1" class="form-check-input"
                                   id="prdFeatured_<?= e($p['id']) ?>" <?= (int)$p['featured'] === 1 ? 'checked' : '' ?>>
                            <label for="prdFeatured_<?= e($p['id']) ?>" class="form-check-label">
                                <i class="fas fa-star text-warning me-1"></i>Featured product
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input type="checkbox" name="in_stock" value="1" class="form-check-input"
                                   id="prdInStock_<?= e($p['id']) ?>" <?= (int)$p['in_stock'] === 1 ? 'checked' : '' ?>>
                            <label for="prdInStock_<?= e($p['id']) ?>" class="form-check-label">In stock (available for purchase)</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-grad"><i class="fas fa-save me-1"></i>Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="prdDeleteModal_<?= e($p['id']) ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="<?= APP_URL ?>/admin/products.php" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= e($p['id']) ?>">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Delete Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">Are you sure you want to delete the product:</p>
                <p class="fw-600 fs-5 text-purple mb-3"><?= e($p['name']) ?>?</p>
                <div class="alert alert-warning small mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    Products that have been ordered cannot be deleted. If this product has orders, deletion will be blocked — set <strong>in_stock=0</strong> instead.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="fas fa-trash-alt me-1"></i>Delete Product</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
