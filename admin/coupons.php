<?php
/**
 * admin/coupons.php — Coupons CRUD.
 *
 * Code stored UPPERCASE; uniqueness is enforced both by a DB unique key
 * (uq_coupon_code) and by an explicit pre-check that flashes a friendly
 * danger message on duplicate. Type can be percentage or flat.
 */
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(ROLE_ADMIN);

// ─── POST handler ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = clean($_POST['action'] ?? '');

    if ($action === 'create') {
        $code         = strtoupper(clean($_POST['code'] ?? ''));
        $type         = clean($_POST['type'] ?? 'percentage');
        $discount     = (float) ($_POST['discount'] ?? 0);
        $minOrder     = (float) ($_POST['min_order'] ?? 0);
        $maxDiscount  = ($_POST['max_discount'] ?? '') !== '' ? (float) $_POST['max_discount'] : null;
        $expiresAt    = trim($_POST['expires_at'] ?? '');
        $usageLimit   = ($_POST['usage_limit'] ?? '') !== '' ? (int) $_POST['usage_limit'] : null;
        $active       = isset($_POST['active']) ? 1 : 0;

        if (!in_array($type, ['percentage','flat'], true)) {
            $type = 'percentage';
        }
        if ($code === '' || $discount <= 0) {
            flash('danger', 'Coupon code and a valid discount are required.');
            redirect(APP_URL . '/admin/coupons.php');
        }
        if ($type === 'percentage' && $discount > 100) {
            flash('danger', 'Percentage discount cannot exceed 100%.');
            redirect(APP_URL . '/admin/coupons.php');
        }
        $expiresDb = $expiresAt !== '' ? $expiresAt . ' 23:59:59' : null;

        // Uniqueness pre-check
        if (db_select_one('SELECT id FROM coupons WHERE code = ? LIMIT 1', [$code])) {
            flash('danger', 'Coupon code "' . $code . '" already exists. Choose a different code.');
            redirect(APP_URL . '/admin/coupons.php');
        }

        db_insert('coupons', [
            'id'           => gen_id('cp_'),
            'code'         => $code,
            'discount'     => $discount,
            'type'         => $type,
            'min_order'    => $minOrder,
            'max_discount' => $maxDiscount,
            'expires_at'   => $expiresDb,
            'active'       => $active,
            'usage_limit'  => $usageLimit,
            'usage_count'  => 0,
        ]);
        flash('success', 'Coupon "' . $code . '" created successfully.');
        redirect(APP_URL . '/admin/coupons.php');
    }

    if ($action === 'update') {
        $id           = clean($_POST['id'] ?? '');
        $code         = strtoupper(clean($_POST['code'] ?? ''));
        $type         = clean($_POST['type'] ?? 'percentage');
        $discount     = (float) ($_POST['discount'] ?? 0);
        $minOrder     = (float) ($_POST['min_order'] ?? 0);
        $maxDiscount  = ($_POST['max_discount'] ?? '') !== '' ? (float) $_POST['max_discount'] : null;
        $expiresAt    = trim($_POST['expires_at'] ?? '');
        $usageLimit   = ($_POST['usage_limit'] ?? '') !== '' ? (int) $_POST['usage_limit'] : null;
        $active       = isset($_POST['active']) ? 1 : 0;

        if (!in_array($type, ['percentage','flat'], true)) {
            $type = 'percentage';
        }
        if ($id === '' || $code === '' || $discount <= 0) {
            flash('danger', 'Invalid input. Please review the form.');
            redirect(APP_URL . '/admin/coupons.php');
        }
        if ($type === 'percentage' && $discount > 100) {
            flash('danger', 'Percentage discount cannot exceed 100%.');
            redirect(APP_URL . '/admin/coupons.php');
        }

        $exists = db_select_one('SELECT id FROM coupons WHERE id = ? LIMIT 1', [$id]);
        if (!$exists) {
            flash('danger', 'Coupon not found.');
            redirect(APP_URL . '/admin/coupons.php');
        }

        // Uniqueness excluding self
        if (db_select_one('SELECT id FROM coupons WHERE code = ? AND id <> ? LIMIT 1', [$code, $id])) {
            flash('danger', 'Coupon code "' . $code . '" is used by another coupon.');
            redirect(APP_URL . '/admin/coupons.php');
        }

        $expiresDb = $expiresAt !== '' ? $expiresAt . ' 23:59:59' : null;

        db_execute(
            'UPDATE coupons
                SET code = ?, type = ?, discount = ?, min_order = ?,
                    max_discount = ?, expires_at = ?, usage_limit = ?, active = ?
              WHERE id = ?',
            [$code, $type, $discount, $minOrder, $maxDiscount,
             $expiresDb, $usageLimit, $active, $id]
        );
        flash('success', 'Coupon "' . $code . '" updated successfully.');
        redirect(APP_URL . '/admin/coupons.php');
    }

    if ($action === 'delete') {
        $id = clean($_POST['id'] ?? '');
        if ($id === '') {
            flash('danger', 'Invalid coupon id.');
            redirect(APP_URL . '/admin/coupons.php');
        }
        $cp = db_select_one('SELECT id, code FROM coupons WHERE id = ? LIMIT 1', [$id]);
        if (!$cp) {
            flash('danger', 'Coupon not found.');
            redirect(APP_URL . '/admin/coupons.php');
        }
        db_execute('DELETE FROM coupons WHERE id = ?', [$id]);
        flash('success', 'Coupon "' . $cp['code'] . '" deleted successfully.');
        redirect(APP_URL . '/admin/coupons.php');
    }

    flash('danger', 'Unknown action.');
    redirect(APP_URL . '/admin/coupons.php');
}

// ─── Data ───────────────────────────────────────────────────────────
$coupons = db_select(
    'SELECT id, code, discount, type, min_order, max_discount, expires_at,
            active, usage_limit, usage_count, created_at
       FROM coupons
      ORDER BY created_at DESC'
);

$pageTitle = 'Coupons';
include __DIR__ . '/../includes/header.php';
?>
<!-- Page header -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 fade-in">
    <div>
        <h1 class="h3 mb-1 fw-bold text-purple">
            <i class="fas fa-ticket-alt me-2"></i>Coupons
        </h1>
        <p class="text-muted mb-0"><?= count($coupons) ?> coupons configured.</p>
    </div>
    <button type="button" class="btn btn-grad btn-sm" data-bs-toggle="modal" data-bs-target="#cpnCreateModal">
        <i class="fas fa-plus me-1"></i>Add Coupon
    </button>
</div>

<!-- Coupons table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list me-2 text-purple"></i>All Coupons</span>
        <span class="badge bg-purple-soft text-purple"><?= count($coupons) ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (!$coupons): ?>
            <div class="empty-state">
                <i class="fas fa-ticket-alt"></i>
                <p class="mb-3">No coupons yet. Create your first discount code.</p>
                <button type="button" class="btn btn-grad btn-sm" data-bs-toggle="modal" data-bs-target="#cpnCreateModal">
                    <i class="fas fa-plus me-1"></i>Add Coupon
                </button>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0 table-hover">
                <thead><tr>
                    <th>Code</th><th>Type</th><th class="text-end">Discount</th>
                    <th class="text-end">Min Order</th><th class="text-end">Max Disc.</th>
                    <th class="text-center">Usage</th><th>Expires</th>
                    <th class="text-center">Status</th><th class="text-end">Actions</th>
                </tr></thead>
                <tbody>
                <?php foreach ($coupons as $c):
                    $expired = !empty($c['expires_at']) && strtotime($c['expires_at']) < time();
                ?>
                    <tr>
                        <td><code class="bg-purple-soft text-purple px-2 py-1 rounded fw-600"><?= e($c['code']) ?></code></td>
                        <td>
                            <?php if ($c['type'] === 'percentage'): ?>
                                <span class="badge bg-info text-dark"><i class="fas fa-percent me-1"></i>Percentage</span>
                            <?php else: ?>
                                <span class="badge bg-primary"><i class="fas fa-rupee-sign me-1"></i>Flat</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-600">
                            <?php if ($c['type'] === 'percentage'): ?>
                                <?= e(number_format((float)$c['discount'], 2)) ?>%
                            <?php else: ?>
                                <?= money($c['discount']) ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-end"><?= money($c['min_order']) ?></td>
                        <td class="text-end"><?= $c['max_discount'] !== null ? money($c['max_discount']) : '—' ?></td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark border">
                                <?= (int)$c['usage_count'] ?><?= $c['usage_limit'] !== null ? '/' . (int)$c['usage_limit'] : '' ?>
                            </span>
                        </td>
                        <td class="small <?= $expired ? 'text-danger' : 'text-muted' ?>">
                            <?= $c['expires_at'] ? e(fmt_date($c['expires_at'])) : '—' ?>
                            <?php if ($expired): ?>
                                <br><small class="text-danger">Expired</small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ((int)$c['active'] === 1 && !$expired): ?>
                                <span class="badge bg-success">Active</span>
                            <?php elseif ((int)$c['active'] === 1 && $expired): ?>
                                <span class="badge bg-secondary">Expired</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal" data-bs-target="#cpnEditModal_<?= e($c['id']) ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger"
                                    data-bs-toggle="modal" data-bs-target="#cpnDeleteModal_<?= e($c['id']) ?>">
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
<div class="modal fade" id="cpnCreateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="post" action="<?= APP_URL ?>/admin/coupons.php" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-header bg-purple-soft">
                <h5 class="modal-title text-purple"><i class="fas fa-plus me-2"></i>Add Coupon</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Coupon Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control font-monospace text-uppercase" required maxlength="40"
                               placeholder="e.g. SUMMER25" oninput="this.value = this.value.toUpperCase();">
                        <small class="text-muted">Stored uppercase. Must be unique.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Type <span class="text-danger">*</span></label>
                        <select name="type" class="form-select">
                            <option value="percentage">Percentage (%)</option>
                            <option value="flat">Flat amount (₹)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Discount <span class="text-danger">*</span></label>
                        <input type="number" name="discount" step="0.01" min="0" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Min Order (₹)</label>
                        <input type="number" name="min_order" step="0.01" min="0" class="form-control" value="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Max Discount (₹)</label>
                        <input type="number" name="max_discount" step="0.01" min="0" class="form-control">
                        <small class="text-muted">Optional (cap for %).</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Expires At</label>
                        <input type="date" name="expires_at" class="form-control">
                        <small class="text-muted">Optional — blank = never expires.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Usage Limit</label>
                        <input type="number" name="usage_limit" min="1" class="form-control">
                        <small class="text-muted">Optional — blank = unlimited.</small>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input type="checkbox" name="active" value="1" class="form-check-input" id="cpnCreateActive" checked>
                            <label for="cpnCreateActive" class="form-check-label">Active (customers can apply this coupon)</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-grad"><i class="fas fa-save me-1"></i>Create Coupon</button>
            </div>
        </form>
    </div>
</div>

<!-- ─── Edit + Delete Modals per row ──────────────────────────────── -->
<?php foreach ($coupons as $c):
    $expDate = !empty($c['expires_at']) ? substr($c['expires_at'], 0, 10) : '';
?>
<!-- Edit Modal -->
<div class="modal fade" id="cpnEditModal_<?= e($c['id']) ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="post" action="<?= APP_URL ?>/admin/coupons.php" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= e($c['id']) ?>">
            <div class="modal-header bg-purple-soft">
                <h5 class="modal-title text-purple"><i class="fas fa-edit me-2"></i>Edit Coupon</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Coupon Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control font-monospace text-uppercase" required maxlength="40"
                               value="<?= e($c['code']) ?>" oninput="this.value = this.value.toUpperCase();">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Type <span class="text-danger">*</span></label>
                        <select name="type" class="form-select">
                            <option value="percentage" <?= $c['type'] === 'percentage' ? 'selected' : '' ?>>Percentage (%)</option>
                            <option value="flat" <?= $c['type'] === 'flat' ? 'selected' : '' ?>>Flat amount (₹)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Discount <span class="text-danger">*</span></label>
                        <input type="number" name="discount" step="0.01" min="0" class="form-control" required
                               value="<?= e($c['discount']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Min Order (₹)</label>
                        <input type="number" name="min_order" step="0.01" min="0" class="form-control"
                               value="<?= e($c['min_order']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Max Discount (₹)</label>
                        <input type="number" name="max_discount" step="0.01" min="0" class="form-control"
                               value="<?= e($c['max_discount'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Expires At</label>
                        <input type="date" name="expires_at" class="form-control" value="<?= e($expDate) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Usage Limit</label>
                        <input type="number" name="usage_limit" min="1" class="form-control"
                               value="<?= e($c['usage_limit'] ?? '') ?>">
                        <small class="text-muted">Used: <?= (int)$c['usage_count'] ?> time(s).</small>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input type="checkbox" name="active" value="1" class="form-check-input"
                                   id="cpnActive_<?= e($c['id']) ?>" <?= (int)$c['active'] === 1 ? 'checked' : '' ?>>
                            <label for="cpnActive_<?= e($c['id']) ?>" class="form-check-label">Active (customers can apply this coupon)</label>
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
<div class="modal fade" id="cpnDeleteModal_<?= e($c['id']) ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="<?= APP_URL ?>/admin/coupons.php" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= e($c['id']) ?>">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Delete Coupon</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">Are you sure you want to delete the coupon:</p>
                <p class="fw-600 fs-5 mb-3"><code class="bg-purple-soft text-purple px-2 py-1 rounded"><?= e($c['code']) ?></code>?</p>
                <div class="alert alert-warning small mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    This action is permanent. Past orders that already used this coupon will not be affected.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="fas fa-trash-alt me-1"></i>Delete Coupon</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
