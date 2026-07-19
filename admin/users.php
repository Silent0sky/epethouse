<?php
/**
 * admin/users.php — User management.
 *
 * List all users with role filter, add/edit modals (incl. ADMIN role),
 * toggle-active button, optional password reset. Customer-facing signup
 * is in /register.php; here admins can create ANY role.
 */
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(ROLE_ADMIN);

// ─── POST handler ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = clean($_POST['action'] ?? '');

    if ($action === 'create') {
        $name    = clean($_POST['name'] ?? '');
        $email   = strtolower(trim($_POST['email'] ?? ''));
        $phone   = preg_replace('/\D/', '', $_POST['phone'] ?? '');
        $pass    = $_POST['password'] ?? '';
        $role    = clean($_POST['role'] ?? ROLE_CUSTOMER);
        $address = clean($_POST['address'] ?? '');

        if (!in_array($role, [ROLE_ADMIN, ROLE_CUSTOMER, ROLE_GROOMER, ROLE_DELIVERY], true)) {
            $role = ROLE_CUSTOMER;
        }

        // Validation
        if (strlen($name) < 2) {
            flash('danger', 'Please enter a valid name.');
            redirect(APP_URL . '/admin/users.php');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'Please enter a valid email address.');
            redirect(APP_URL . '/admin/users.php');
        }
        if (strlen($phone) < 10) {
            flash('danger', 'Please enter a valid 10-digit phone number.');
            redirect(APP_URL . '/admin/users.php');
        }
        if (strlen($pass) < 6) {
            flash('danger', 'Password must be at least 6 characters.');
            redirect(APP_URL . '/admin/users.php');
        }

        if (db_select_one('SELECT id FROM users WHERE email = ? LIMIT 1', [$email])) {
            flash('danger', 'An account with this email already exists.');
            redirect(APP_URL . '/admin/users.php');
        }
        if (db_select_one('SELECT id FROM users WHERE phone = ? LIMIT 1', [$phone])) {
            flash('danger', 'An account with this phone already exists.');
            redirect(APP_URL . '/admin/users.php');
        }

        $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
        $id   = gen_id('u_');
        $referralCode = strtoupper(substr(preg_replace('/[^A-Z]/', '', strtoupper($name)), 0, 4)) . rand(10, 99);

        db_insert('users', [
            'id'              => $id,
            'email'           => $email,
            'phone'           => $phone,
            'name'            => $name,
            'password_hash'   => $hash,
            'role'            => $role,
            'address'         => $address ?: null,
            'reward_points'   => 0,
            'referral_code'   => $referralCode,
            'membership_tier' => 'bronze',
            'active'          => 1,
        ]);
        flash('success', 'User "' . $name . '" (' . role_label($role) . ') created successfully.');
        redirect(APP_URL . '/admin/users.php');
    }

    if ($action === 'update') {
        $id      = clean($_POST['id'] ?? '');
        $name    = clean($_POST['name'] ?? '');
        $email   = strtolower(trim($_POST['email'] ?? ''));
        $phone   = preg_replace('/\D/', '', $_POST['phone'] ?? '');
        $role    = clean($_POST['role'] ?? ROLE_CUSTOMER);
        $address = clean($_POST['address'] ?? '');
        $pass    = $_POST['password'] ?? '';

        if (!in_array($role, [ROLE_ADMIN, ROLE_CUSTOMER, ROLE_GROOMER, ROLE_DELIVERY], true)) {
            $role = ROLE_CUSTOMER;
        }

        $exists = db_select_one('SELECT id FROM users WHERE id = ? LIMIT 1', [$id]);
        if (!$exists) {
            flash('danger', 'User not found.');
            redirect(APP_URL . '/admin/users.php');
        }

        if (strlen($name) < 2) {
            flash('danger', 'Please enter a valid name.');
            redirect(APP_URL . '/admin/users.php');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'Please enter a valid email address.');
            redirect(APP_URL . '/admin/users.php');
        }
        if (strlen($phone) < 10) {
            flash('danger', 'Please enter a valid 10-digit phone number.');
            redirect(APP_URL . '/admin/users.php');
        }

        // Uniqueness (excluding self)
        if (db_select_one('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1', [$email, $id])) {
            flash('danger', 'Email is already used by another account.');
            redirect(APP_URL . '/admin/users.php');
        }
        if (db_select_one('SELECT id FROM users WHERE phone = ? AND id <> ? LIMIT 1', [$phone, $id])) {
            flash('danger', 'Phone is already used by another account.');
            redirect(APP_URL . '/admin/users.php');
        }

        // Prevent admin from locking themselves out
        if ($id === $u['id'] && $role !== ROLE_ADMIN) {
            flash('danger', 'You cannot change your own admin role.');
            redirect(APP_URL . '/admin/users.php');
        }

        if ($pass !== '') {
            if (strlen($pass) < 6) {
                flash('danger', 'Password must be at least 6 characters.');
                redirect(APP_URL . '/admin/users.php');
            }
            $newHash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
            db_execute(
                'UPDATE users SET name = ?, email = ?, phone = ?, role = ?, address = ?, password_hash = ? WHERE id = ?',
                [$name, $email, $phone, $role, $address ?: null, $newHash, $id]
            );
        } else {
            db_execute(
                'UPDATE users SET name = ?, email = ?, phone = ?, role = ?, address = ? WHERE id = ?',
                [$name, $email, $phone, $role, $address ?: null, $id]
            );
        }

        flash('success', 'User "' . $name . '" updated successfully.');
        redirect(APP_URL . '/admin/users.php');
    }

    if ($action === 'toggle_active') {
        $id = clean($_POST['id'] ?? '');
        $user = db_select_one('SELECT id, name, active FROM users WHERE id = ? LIMIT 1', [$id]);
        if (!$user) {
            flash('danger', 'User not found.');
            redirect(APP_URL . '/admin/users.php');
        }
        if ($id === $u['id']) {
            flash('danger', 'You cannot deactivate your own account.');
            redirect(APP_URL . '/admin/users.php');
        }
        $newActive = (int)$user['active'] === 1 ? 0 : 1;
        db_execute('UPDATE users SET active = ? WHERE id = ?', [$newActive, $id]);
        flash($newActive === 1 ? 'success' : 'info',
            'User "' . $user['name'] . '" ' . ($newActive === 1 ? 'activated.' : 'deactivated.'));
        redirect(APP_URL . '/admin/users.php');
    }

    if ($action === 'reset_password') {
        $id   = clean($_POST['id'] ?? '');
        $pass = $_POST['password'] ?? '';
        if (strlen($pass) < 6) {
            flash('danger', 'Password must be at least 6 characters.');
            redirect(APP_URL . '/admin/users.php');
        }
        $user = db_select_one('SELECT id, name FROM users WHERE id = ? LIMIT 1', [$id]);
        if (!$user) {
            flash('danger', 'User not found.');
            redirect(APP_URL . '/admin/users.php');
        }
        $newHash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
        db_execute('UPDATE users SET password_hash = ? WHERE id = ?', [$newHash, $id]);
        flash('success', 'Password reset for "' . $user['name'] . '".');
        redirect(APP_URL . '/admin/users.php');
    }

    flash('danger', 'Unknown action.');
    redirect(APP_URL . '/admin/users.php');
}

// ─── Data (with optional role filter) ───────────────────────────────
$roleFilter = trim($_GET['role'] ?? '');
$allowedRoles = [ROLE_ADMIN, ROLE_CUSTOMER, ROLE_GROOMER, ROLE_DELIVERY];
if ($roleFilter !== '' && !in_array($roleFilter, $allowedRoles, true)) {
    $roleFilter = '';
}

$where = '';
$params = [];
if ($roleFilter !== '') {
    $where = ' WHERE role = ?';
    $params[] = $roleFilter;
}

$users = db_select(
    'SELECT id, email, phone, name, role, address, avatar,
            reward_points, referral_code, membership_tier, active, created_at
       FROM users'
    . $where . '
      ORDER BY created_at DESC'
    ,
    $params
);

$roleBadgeMap = [
    ROLE_ADMIN    => 'bg-danger',
    ROLE_CUSTOMER => 'bg-primary',
    ROLE_GROOMER  => 'bg-info text-dark',
    ROLE_DELIVERY => 'bg-success',
];

$tierBadgeMap = [
    'bronze' => 'bg-secondary',
    'silver' => 'bg-light text-dark border',
    'gold'   => 'bg-warning text-dark',
    'platinum' => 'bg-purple-soft text-purple',
];

$pageTitle = 'Users';
include __DIR__ . '/../includes/header.php';
?>
<!-- Page header -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 fade-in">
    <div>
        <h1 class="h3 mb-1 fw-bold text-purple">
            <i class="fas fa-users me-2"></i>Users
        </h1>
        <p class="text-muted mb-0"><?= count($users) ?> users<?= $roleFilter !== '' ? ' · filtered by ' . e(role_label($roleFilter)) : '' ?>.</p>
    </div>
    <div class="d-flex align-items-center gap-2 mt-2 mt-md-0">
        <form method="get" class="d-flex align-items-center gap-2">
            <label class="small text-muted mb-0">Role:</label>
            <select name="role" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                <option value="">All</option>
                <?php foreach ($allowedRoles as $r): ?>
                    <option value="<?= e($r) ?>" <?= $r === $roleFilter ? 'selected' : '' ?>>
                        <?= e(role_label($r)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($roleFilter !== ''): ?>
                <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-link btn-sm text-muted">Clear</a>
            <?php endif; ?>
        </form>
        <button type="button" class="btn btn-grad btn-sm" data-bs-toggle="modal" data-bs-target="#usrCreateModal">
            <i class="fas fa-plus me-1"></i>Add User
        </button>
    </div>
</div>

<!-- Users table -->
<div class="card">
    <div class="card-body p-0">
        <?php if (!$users): ?>
            <div class="empty-state">
                <i class="fas fa-users-slash"></i>
                <p class="mb-0">No users found<?= $roleFilter !== '' ? ' for this role' : '' ?>.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0 table-hover">
                <thead><tr>
                    <th>User</th><th>Role</th><th>Phone</th>
                    <th class="text-center">Points</th><th>Tier</th>
                    <th class="text-center">Status</th><th class="text-end">Actions</th>
                </tr></thead>
                <tbody>
                <?php foreach ($users as $usr): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <?php if (!empty($usr['avatar'])): ?>
                                    <img src="<?= APP_URL . '/' . e($usr['avatar']) ?>" alt=""
                                         style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
                                <?php else: ?>
                                    <span class="ph-avatar" style="background:<?= $roleBadgeMap[$usr['role']] ?? 'bg-secondary' ?>;">
                                        <?= e(initials($usr['name'])) ?>
                                    </span>
                                <?php endif; ?>
                                <div>
                                    <div class="fw-600"><?= e($usr['name']) ?></div>
                                    <small class="text-muted"><?= e($usr['email']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge <?= $roleBadgeMap[$usr['role']] ?? 'bg-secondary' ?>"><?= e(role_label($usr['role'])) ?></span></td>
                        <td class="small"><?= e($usr['phone']) ?></td>
                        <td class="text-center">
                            <span class="badge bg-purple-soft text-purple"><?= (int)$usr['reward_points'] ?></span>
                        </td>
                        <td>
                            <span class="badge <?= $tierBadgeMap[$usr['membership_tier']] ?? 'bg-secondary' ?> text-capitalize">
                                <?= e($usr['membership_tier']) ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <?php if ((int)$usr['active'] === 1): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal" data-bs-target="#usrEditModal_<?= e($usr['id']) ?>"
                                    title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-warning"
                                    data-bs-toggle="modal" data-bs-target="#usrResetModal_<?= e($usr['id']) ?>"
                                    title="Reset password">
                                <i class="fas fa-key"></i>
                            </button>
                            <form method="post" action="<?= APP_URL ?>/admin/users.php" class="d-inline"
                                  onsubmit="return confirm('Toggle active status for <?= e($usr['name']) ?>?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="id" value="<?= e($usr['id']) ?>">
                                <button type="submit" class="btn btn-sm <?= (int)$usr['active'] === 1 ? 'btn-outline-secondary' : 'btn-outline-success' ?>"
                                        title="<?= (int)$usr['active'] === 1 ? 'Deactivate' : 'Activate' ?>"
                                        <?= $usr['id'] === $u['id'] ? 'disabled' : '' ?>>
                                    <i class="fas <?= (int)$usr['active'] === 1 ? 'fa-pause-circle' : 'fa-play-circle' ?>"></i>
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

<!-- ─── Create Modal ──────────────────────────────────────────────── -->
<div class="modal fade" id="usrCreateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="post" action="<?= APP_URL ?>/admin/users.php" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-header bg-purple-soft">
                <h5 class="modal-title text-purple"><i class="fas fa-user-plus me-2"></i>Add User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required maxlength="150">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select name="role" class="form-select">
                            <option value="<?= e(ROLE_CUSTOMER) ?>"><?= e(role_label(ROLE_CUSTOMER)) ?></option>
                            <option value="<?= e(ROLE_GROOMER) ?>"><?= e(role_label(ROLE_GROOMER)) ?></option>
                            <option value="<?= e(ROLE_DELIVERY) ?>"><?= e(role_label(ROLE_DELIVERY)) ?></option>
                            <option value="<?= e(ROLE_ADMIN) ?>"><?= e(role_label(ROLE_ADMIN)) ?></option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required maxlength="191">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control" required maxlength="20" placeholder="10-digit number">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                        <small class="text-muted">Minimum 6 characters.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control" maxlength="500">
                    </div>
                    <div class="col-12">
                        <div class="alert alert-info small mb-0">
                            <i class="fas fa-info-circle me-1"></i>
                            New users start with <strong>0 reward points</strong>, <strong>bronze</strong> tier and a unique referral code.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-grad"><i class="fas fa-save me-1"></i>Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- ─── Edit + Reset Password Modals per row ──────────────────────── -->
<?php foreach ($users as $usr): ?>
<!-- Edit Modal -->
<div class="modal fade" id="usrEditModal_<?= e($usr['id']) ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="post" action="<?= APP_URL ?>/admin/users.php" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= e($usr['id']) ?>">
            <div class="modal-header bg-purple-soft">
                <h5 class="modal-title text-purple"><i class="fas fa-user-edit me-2"></i>Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required maxlength="150"
                               value="<?= e($usr['name']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select name="role" class="form-select" <?= $usr['id'] === $u['id'] ? 'disabled' : '' ?>>
                            <option value="<?= e(ROLE_CUSTOMER) ?>" <?= $usr['role'] === ROLE_CUSTOMER ? 'selected' : '' ?>><?= e(role_label(ROLE_CUSTOMER)) ?></option>
                            <option value="<?= e(ROLE_GROOMER) ?>"  <?= $usr['role'] === ROLE_GROOMER  ? 'selected' : '' ?>><?= e(role_label(ROLE_GROOMER)) ?></option>
                            <option value="<?= e(ROLE_DELIVERY) ?>" <?= $usr['role'] === ROLE_DELIVERY ? 'selected' : '' ?>><?= e(role_label(ROLE_DELIVERY)) ?></option>
                            <option value="<?= e(ROLE_ADMIN) ?>"    <?= $usr['role'] === ROLE_ADMIN    ? 'selected' : '' ?>><?= e(role_label(ROLE_ADMIN)) ?></option>
                        </select>
                        <?php if ($usr['id'] === $u['id']): ?>
                            <input type="hidden" name="role" value="<?= e($usr['role']) ?>">
                            <small class="text-muted">You cannot change your own role.</small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required maxlength="191"
                               value="<?= e($usr['email']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control" required maxlength="20"
                               value="<?= e($usr['phone']) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea name="address" rows="2" class="form-control" maxlength="500"><?= e($usr['address'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">New Password <small class="text-muted">(leave blank to keep current)</small></label>
                        <input type="password" name="password" class="form-control" minlength="6" autocomplete="new-password">
                        <small class="text-muted">Fill in only if you want to reset the password as part of this edit.</small>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">Reward Points</small>
                        <span class="badge bg-purple-soft text-purple"><?= (int)$usr['reward_points'] ?> pts</span>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">Referral Code</small>
                        <code class="bg-light px-2 py-1 rounded"><?= e($usr['referral_code'] ?? '—') ?></code>
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

<!-- Reset Password Modal -->
<div class="modal fade" id="usrResetModal_<?= e($usr['id']) ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="<?= APP_URL ?>/admin/users.php" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="id" value="<?= e($usr['id']) ?>">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="fas fa-key me-2"></i>Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Set a new password for:</p>
                <p class="fw-600 text-purple mb-3"><?= e($usr['name']) ?> (<?= e($usr['email']) ?>)</p>
                <label class="form-label">New Password <span class="text-danger">*</span></label>
                <input type="password" name="password" class="form-control" required minlength="6" autocomplete="new-password">
                <small class="text-muted">Minimum 6 characters. The user will need to log in with this password.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning"><i class="fas fa-key me-1"></i>Reset Password</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
