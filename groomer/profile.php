<?php
/**
 * groomer/profile.php — Groomer profile + password management.
 *
 * 1. Profile form (name, email, phone, address, avatar upload)
 * 2. Change password form
 * 3. Stats summary (total completed, average rating)
 */
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(ROLE_GROOMER);
$uid = $u['id'];

// ─── POST handler ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $formType = $_POST['form_type'] ?? '';

    // Profile update
    if ($formType === 'profile') {
        $name    = clean($_POST['name'] ?? '');
        $email   = strtolower(trim($_POST['email'] ?? ''));
        $phone   = preg_replace('/\D/', '', $_POST['phone'] ?? '');
        $address = clean($_POST['address'] ?? '');

        if (strlen($name) < 2) flash('danger', 'Please enter a valid name.');
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) flash('danger', 'Invalid email address.');
        elseif (strlen($phone) < 10) flash('danger', 'Phone must be at least 10 digits.');
        else {
            $dup = db_select_one(
                'SELECT id FROM users WHERE (email = ? OR phone = ?) AND id <> ? LIMIT 1',
                [$email, $phone, $uid]
            );
            if ($dup) {
                flash('danger', 'Email or phone already in use by another account.');
            } else {
                $avatarUrl = $u['avatar'] ?? null;
                if (!empty($_FILES['avatar']['name'])) {
                    $up = upload_file('avatar', 'profile_photo');
                    if ($up['ok']) $avatarUrl = $up['url'];
                    else flash('warning', 'Avatar upload skipped: ' . $up['error']);
                }
                db_execute(
                    'UPDATE users SET name = ?, email = ?, phone = ?, address = ?, avatar = ? WHERE id = ?',
                    [$name, $email, $phone, $address ?: null, $avatarUrl, $uid]
                );
                flash('success', 'Profile updated successfully.');
            }
        }
        redirect(APP_URL . '/groomer/profile.php');
    }

    // Change password
    if ($formType === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $row = db_select_one('SELECT password_hash FROM users WHERE id = ?', [$uid]);
        if (!$row || !password_verify($current, $row['password_hash'])) {
            flash('danger', 'Current password is incorrect.');
        } elseif (strlen($new) < 6) {
            flash('danger', 'New password must be at least 6 characters.');
        } elseif ($new !== $confirm) {
            flash('danger', 'New passwords do not match.');
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
            db_execute('UPDATE users SET password_hash = ? WHERE id = ?', [$hash, $uid]);
            notify($uid, 'Password Changed', 'Your account password was just updated. If this wasn\'t you, please contact support immediately.', 'security');
            flash('success', 'Password changed successfully.');
        }
        redirect(APP_URL . '/groomer/profile.php');
    }
}

$pageTitle = 'My Profile';
include __DIR__ . '/../includes/header.php';

// Reload fresh user data
$u = current_user();

// ─── Stats ────────────────────────────────────────────────────────────
$totalCompleted = (int) db_scalar("SELECT COUNT(*) FROM grooming_bookings WHERE status = 'completed'");
$totalAppointments = (int) db_scalar("SELECT COUNT(*) FROM grooming_bookings");
$totalCancelled = (int) db_scalar("SELECT COUNT(*) FROM grooming_bookings WHERE status = 'cancelled'");
$todayCount = (int) db_scalar(
    "SELECT COUNT(*) FROM grooming_bookings WHERE date = ? AND status IN ('confirmed','in_progress')",
    [date('Y-m-d')]
);

// Rating: reviews don't have a groomer_id column in this schema, so show N/A
// (the schema tracks reviews for products, not groomers — kept honest here).
$hasRating = false;
$avgRating = null;
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="h3 fw-bold text-purple mb-0"><i class="fas fa-user me-2"></i>My Profile</h1>
    <span class="badge bg-purple-soft text-purple px-3 py-2"><?= e(role_label($u['role'])) ?></span>
</div>

<!-- Profile summary banner -->
<div class="card mb-4 bg-grad-purple text-white">
    <div class="card-body d-flex align-items-center gap-3">
        <?php if (!empty($u['avatar'])): ?>
            <img src="<?= APP_URL ?>/<?= e($u['avatar']) ?>" alt="avatar"
                 style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid rgba(255,255,255,0.4);">
        <?php else: ?>
            <div class="d-flex align-items-center justify-content-center"
                 style="width:72px;height:72px;border-radius:50%;background:rgba(255,255,255,0.2);font-size:1.6rem;font-weight:700;">
                <?= e(initials($u['name'])) ?>
            </div>
        <?php endif; ?>
        <div>
            <h4 class="mb-0"><?= e($u['name']) ?></h4>
            <p class="mb-0 opacity-90">
                <i class="fas fa-envelope me-1"></i><?= e($u['email']) ?>
                · <i class="fas fa-phone me-1"></i><?= e($u['phone']) ?>
            </p>
        </div>
        <div class="ms-auto text-end d-none d-md-block">
            <div class="opacity-75 small">Member since</div>
            <div class="fw-600"><?= e(fmt_date(substr($u['created_at'], 0, 10))) ?></div>
        </div>
    </div>
</div>

<!-- Stat cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card bg-grad-purple h-100">
            <div class="stat-value"><?= $totalAppointments ?></div>
            <div class="stat-label">Total Appointments</div>
            <i class="fas fa-calendar-check stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card bg-grad-teal h-100">
            <div class="stat-value"><?= $totalCompleted ?></div>
            <div class="stat-label">Completed</div>
            <i class="fas fa-check-double stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card bg-grad-amber h-100">
            <div class="stat-value"><?= $todayCount ?></div>
            <div class="stat-label">Today's Schedule</div>
            <i class="fas fa-calendar-day stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card bg-grad-pink h-100">
            <div class="stat-value">
                <?= $hasRating ? number_format((float)$avgRating, 1) : 'N/A' ?>
            </div>
            <div class="stat-label">Average Rating</div>
            <i class="fas fa-star stat-icon"></i>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Profile form -->
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-id-card me-2 text-purple"></i>Personal Information</div>
            <div class="card-body">
                <form method="post" action="<?= APP_URL ?>/groomer/profile.php" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_type" value="profile">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control" required value="<?= e($u['name']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control" required value="<?= e($u['phone']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required value="<?= e($u['email']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2"><?= e($u['address'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Profile Photo</label>
                            <input type="file" name="avatar" class="form-control" accept="image/*">
                            <?php if (!empty($u['avatar'])): ?>
                                <small class="text-muted">Current photo will be kept unless you upload a new one.</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button class="btn btn-grad mt-3"><i class="fas fa-save me-1"></i>Save Changes</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Password -->
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-lock me-2 text-purple"></i>Change Password</div>
            <div class="card-body">
                <form method="post" action="<?= APP_URL ?>/groomer/profile.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_type" value="password">
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" required minlength="6">
                            <small class="text-muted">Minimum 6 characters.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="6">
                        </div>
                    </div>
                    <button class="btn btn-grad mt-3"><i class="fas fa-key me-1"></i>Update Password</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="fas fa-shield-alt me-2 text-purple"></i>Security Tips</div>
            <div class="card-body small text-muted">
                <ul class="mb-0 ps-3">
                    <li>Use a unique password you don't reuse elsewhere.</li>
                    <li>Log out from shared computers after your shift.</li>
                    <li>Contact support immediately if you notice unusual activity.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
