<?php
/**
 * admin/services.php — Grooming Services CRUD.
 *
 * List all services (active + inactive) with category/price/duration.
 * Add/Edit via Bootstrap modals; delete with smart soft-delete fallback
 * when bookings exist for the service.
 */
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(ROLE_ADMIN);

// ─── POST handler ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = clean($_POST['action'] ?? '');

    if ($action === 'create') {
        $name        = clean($_POST['name'] ?? '');
        $description = clean($_POST['description'] ?? '');
        $price       = (float) ($_POST['price'] ?? 0);
        $duration    = (int)   ($_POST['duration'] ?? 0);
        $category    = clean($_POST['category'] ?? 'basic');
        $active      = isset($_POST['active']) ? 1 : 0;

        if (!in_array($category, ['basic','premium','spa','specialty'], true)) {
            $category = 'basic';
        }
        if ($name === '' || $price <= 0 || $duration <= 0) {
            flash('danger', 'Please fill all required fields with valid values.');
            redirect(APP_URL . '/admin/services.php');
        }

        db_insert('grooming_services', [
            'id'          => gen_id('gs_'),
            'name'        => $name,
            'description' => $description,
            'price'       => $price,
            'duration'    => $duration,
            'category'    => $category,
            'image'       => null,
            'active'      => $active,
        ]);
        flash('success', 'Service "' . $name . '" created successfully.');
        redirect(APP_URL . '/admin/services.php');
    }

    if ($action === 'update') {
        $id          = clean($_POST['id'] ?? '');
        $name        = clean($_POST['name'] ?? '');
        $description = clean($_POST['description'] ?? '');
        $price       = (float) ($_POST['price'] ?? 0);
        $duration    = (int)   ($_POST['duration'] ?? 0);
        $category    = clean($_POST['category'] ?? 'basic');
        $active      = isset($_POST['active']) ? 1 : 0;

        if (!in_array($category, ['basic','premium','spa','specialty'], true)) {
            $category = 'basic';
        }
        if ($id === '' || $name === '' || $price <= 0 || $duration <= 0) {
            flash('danger', 'Invalid input. Please review the form.');
            redirect(APP_URL . '/admin/services.php');
        }

        $exists = db_select_one('SELECT id FROM grooming_services WHERE id = ? LIMIT 1', [$id]);
        if (!$exists) {
            flash('danger', 'Service not found.');
            redirect(APP_URL . '/admin/services.php');
        }

        db_execute(
            'UPDATE grooming_services
                SET name = ?, description = ?, price = ?, duration = ?, category = ?, active = ?
              WHERE id = ?',
            [$name, $description, $price, $duration, $category, $active, $id]
        );
        flash('success', 'Service "' . $name . '" updated successfully.');
        redirect(APP_URL . '/admin/services.php');
    }

    if ($action === 'delete') {
        $id = clean($_POST['id'] ?? '');
        if ($id === '') {
            flash('danger', 'Invalid service id.');
            redirect(APP_URL . '/admin/services.php');
        }

        $svc = db_select_one('SELECT id, name FROM grooming_services WHERE id = ? LIMIT 1', [$id]);
        if (!$svc) {
            flash('danger', 'Service not found.');
            redirect(APP_URL . '/admin/services.php');
        }

        $bookingCount = (int) db_scalar(
            'SELECT COUNT(*) FROM grooming_bookings WHERE service_id = ?',
            [$id]
        );

        if ($bookingCount > 0) {
            db_execute('UPDATE grooming_services SET active = 0 WHERE id = ?', [$id]);
            flash('info', 'Service "' . $svc['name'] . '" has ' . $bookingCount . ' booking(s) — deactivated instead of deleted.');
        } else {
            db_execute('DELETE FROM grooming_services WHERE id = ?', [$id]);
            flash('success', 'Service "' . $svc['name'] . '" deleted successfully.');
        }
        redirect(APP_URL . '/admin/services.php');
    }

    flash('danger', 'Unknown action.');
    redirect(APP_URL . '/admin/services.php');
}

// ─── Data: all services (including inactive) ────────────────────────
$services = db_select(
    'SELECT id, name, description, price, duration, category, active, created_at
       FROM grooming_services
      ORDER BY category ASC, name ASC'
);

$categoryBadgeMap = [
    'basic'      => 'bg-purple-soft text-purple',
    'premium'    => 'bg-info text-dark',
    'spa'        => 'bg-success',
    'specialty'  => 'bg-warning text-dark',
];

$pageTitle = 'Grooming Services';
include __DIR__ . '/../includes/header.php';
?>
<!-- Page header -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 fade-in">
    <div>
        <h1 class="h3 mb-1 fw-bold text-purple">
            <i class="fas fa-scissors me-2"></i>Grooming Services
        </h1>
        <p class="text-muted mb-0">Manage your grooming catalogue (<?= count($services) ?> services).</p>
    </div>
    <button type="button" class="btn btn-grad btn-sm" data-bs-toggle="modal" data-bs-target="#svcCreateModal">
        <i class="fas fa-plus me-1"></i>Add Service
    </button>
</div>

<!-- Services table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list me-2 text-purple"></i>All Services</span>
        <span class="badge bg-purple-soft text-purple"><?= count($services) ?></span>
    </div>
    <div class="card-body p-0">
        <?php if (!$services): ?>
            <div class="empty-state">
                <i class="fas fa-scissors"></i>
                <p class="mb-3">No services yet. Add your first grooming service.</p>
                <button type="button" class="btn btn-grad btn-sm" data-bs-toggle="modal" data-bs-target="#svcCreateModal">
                    <i class="fas fa-plus me-1"></i>Add Service
                </button>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0 table-hover">
                <thead><tr>
                    <th>Name</th><th>Category</th><th class="text-end">Price</th>
                    <th class="text-center">Duration</th><th class="text-center">Status</th>
                    <th class="text-end">Actions</th>
                </tr></thead>
                <tbody>
                <?php foreach ($services as $s): ?>
                    <tr>
                        <td>
                            <div class="fw-600 text-purple"><?= e($s['name']) ?></div>
                            <small class="text-muted"><?= e(mb_strimwidth($s['description'] ?? '', 0, 80, '…')) ?></small>
                        </td>
                        <td>
                            <span class="badge <?= $categoryBadgeMap[$s['category']] ?? 'bg-secondary' ?> text-capitalize">
                                <?= e($s['category']) ?>
                            </span>
                        </td>
                        <td class="text-end fw-600"><?= money($s['price']) ?></td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark border">
                                <i class="far fa-clock me-1"></i><?= (int)$s['duration'] ?> min
                            </span>
                        </td>
                        <td class="text-center">
                            <?php if ((int)$s['active'] === 1): ?>
                                <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><i class="fas fa-pause-circle me-1"></i>Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal" data-bs-target="#svcEditModal_<?= e($s['id']) ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger"
                                    data-bs-toggle="modal" data-bs-target="#svcDeleteModal_<?= e($s['id']) ?>">
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
<div class="modal fade" id="svcCreateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="post" action="<?= APP_URL ?>/admin/services.php" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-header bg-purple-soft">
                <h5 class="modal-title text-purple"><i class="fas fa-plus me-2"></i>Add Grooming Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Service Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required maxlength="150">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Category <span class="text-danger">*</span></label>
                        <select name="category" class="form-select">
                            <option value="basic">Basic</option>
                            <option value="premium">Premium</option>
                            <option value="spa">Spa</option>
                            <option value="specialty">Specialty</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="3" class="form-control" maxlength="1000"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Price (₹) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-rupee-sign"></i></span>
                            <input type="number" name="price" step="0.01" min="0" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Duration (minutes) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="far fa-clock"></i></span>
                            <input type="number" name="duration" min="1" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input type="checkbox" name="active" value="1" class="form-check-input" id="svcCreateActive" checked>
                            <label for="svcCreateActive" class="form-check-label">Active (visible to customers)</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-grad"><i class="fas fa-save me-1"></i>Create Service</button>
            </div>
        </form>
    </div>
</div>

<!-- ─── Edit + Delete Modals per row ──────────────────────────────── -->
<?php foreach ($services as $s): ?>
<!-- Edit Modal -->
<div class="modal fade" id="svcEditModal_<?= e($s['id']) ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="post" action="<?= APP_URL ?>/admin/services.php" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= e($s['id']) ?>">
            <div class="modal-header bg-purple-soft">
                <h5 class="modal-title text-purple"><i class="fas fa-edit me-2"></i>Edit Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Service Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required maxlength="150"
                               value="<?= e($s['name']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Category <span class="text-danger">*</span></label>
                        <select name="category" class="form-select">
                            <?php foreach (['basic','premium','spa','specialty'] as $cat): ?>
                                <option value="<?= e($cat) ?>" <?= $cat === $s['category'] ? 'selected' : '' ?>>
                                    <?= e(ucfirst($cat)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="3" class="form-control" maxlength="1000"><?= e($s['description']) ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Price (₹) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-rupee-sign"></i></span>
                            <input type="number" name="price" step="0.01" min="0" class="form-control" required
                                   value="<?= e($s['price']) ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Duration (minutes) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="far fa-clock"></i></span>
                            <input type="number" name="duration" min="1" class="form-control" required
                                   value="<?= e($s['duration']) ?>">
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input type="checkbox" name="active" value="1" class="form-check-input"
                                   id="svcActive_<?= e($s['id']) ?>" <?= (int)$s['active'] === 1 ? 'checked' : '' ?>>
                            <label for="svcActive_<?= e($s['id']) ?>" class="form-check-label">Active (visible to customers)</label>
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
<div class="modal fade" id="svcDeleteModal_<?= e($s['id']) ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="<?= APP_URL ?>/admin/services.php" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= e($s['id']) ?>">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Delete Service</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">Are you sure you want to delete the service:</p>
                <p class="fw-600 fs-5 text-purple mb-3"><?= e($s['name']) ?>?</p>
                <div class="alert alert-warning small mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    If this service has existing grooming bookings, it will be <strong>deactivated</strong> instead of permanently deleted to preserve booking history.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="fas fa-trash-alt me-1"></i>Delete / Deactivate</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
