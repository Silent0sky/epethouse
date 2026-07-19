<?php
/**
 * customer/profile.php — Profile + settings.
 *
 * 1. Profile form (name, email, phone, address, avatar upload)
 * 2. Change password form
 * 3. Addresses CRUD
 * 4. Support tickets: list + new ticket
 */
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(ROLE_CUSTOMER);
$uid = $u['id'];

// ─── POST handler ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $formType = $_POST['form_type'] ?? '';

    // ─── Profile update ───
    if ($formType === 'profile') {
        $name    = clean($_POST['name'] ?? '');
        $email   = strtolower(trim($_POST['email'] ?? ''));
        $phone   = preg_replace('/\D/', '', $_POST['phone'] ?? '');
        $address = clean($_POST['address'] ?? '');

        if (strlen($name) < 2) flash('danger', 'Please enter a valid name.');
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) flash('danger', 'Invalid email address.');
        elseif (strlen($phone) < 10) flash('danger', 'Phone must be at least 10 digits.');
        else {
            // uniqueness (excluding current user)
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
        redirect(APP_URL . '/customer/profile.php');
    }

    // ─── Change password ───
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
        redirect(APP_URL . '/customer/profile.php');
    }

    // ─── Address CRUD ───
    if ($formType === 'save_address') {
        $addrId  = trim($_POST['address_id'] ?? '');
        $label   = clean($_POST['label'] ?? '');
        $address = clean($_POST['address'] ?? '');
        $city    = clean($_POST['city'] ?? '');
        $pincode = preg_replace('/\D/', '', $_POST['pincode'] ?? '');
        $isDef   = isset($_POST['is_default']) ? 1 : 0;

        if ($label === '' || $address === '' || $city === '' || strlen($pincode) < 6) {
            flash('danger', 'Please fill all address fields (pincode must be 6 digits).');
        } else {
            if ($isDef) {
                db_execute('UPDATE addresses SET is_default = 0 WHERE user_id = ?', [$uid]);
            }
            if ($addrId !== '') {
                $owned = db_scalar('SELECT id FROM addresses WHERE id = ? AND user_id = ?', [$addrId, $uid]);
                if (!$owned) {
                    flash('danger', 'Address not found.');
                } else {
                    db_execute(
                        'UPDATE addresses SET label = ?, address = ?, city = ?, pincode = ?, is_default = ? WHERE id = ? AND user_id = ?',
                        [$label, $address, $city, $pincode, $isDef, $addrId, $uid]
                    );
                    flash('success', 'Address updated.');
                }
            } else {
                db_insert('addresses', [
                    'id'         => gen_id('ad_'),
                    'user_id'    => $uid,
                    'label'      => $label,
                    'address'    => $address,
                    'city'       => $city,
                    'pincode'    => $pincode,
                    'is_default' => $isDef,
                ]);
                flash('success', 'Address added.');
            }
        }
        redirect(APP_URL . '/customer/profile.php#addresses');
    }

    if ($formType === 'delete_address') {
        db_execute('DELETE FROM addresses WHERE id = ? AND user_id = ?', [$_POST['address_id'] ?? '', $uid]);
        flash('info', 'Address removed.');
        redirect(APP_URL . '/customer/profile.php#addresses');
    }

    // ─── Support ticket ───
    if ($formType === 'new_ticket') {
        $subject = clean($_POST['subject'] ?? '');
        $message = clean($_POST['message'] ?? '');
        $priority = $_POST['priority'] ?? 'medium';
        if (!in_array($priority, ['low','medium','high'], true)) $priority = 'medium';

        if (strlen($subject) < 5) flash('danger', 'Subject must be at least 5 characters.');
        elseif (strlen($message) < 10) flash('danger', 'Message must be at least 10 characters.');
        else {
            db_insert('support_tickets', [
                'id'       => gen_id('st_'),
                'user_id'  => $uid,
                'subject'  => $subject,
                'message'  => $message,
                'status'   => 'open',
                'priority' => $priority,
            ]);
            notify($uid, 'Support Ticket Created', 'We received your ticket: "' . $subject . '". Our team will get back to you soon.', 'general');
            flash('success', 'Support ticket submitted. We will reply soon.');
        }
        redirect(APP_URL . '/customer/profile.php#tickets');
    }
}

$pageTitle = 'My Profile';
include __DIR__ . '/../includes/header.php';

// Reload fresh user data
$u = current_user();

$addresses = db_select('SELECT id, label, address, city, pincode, is_default, created_at FROM addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC', [$uid]);
$tickets   = db_select('SELECT id, subject, message, status, priority, response, created_at, updated_at FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC LIMIT 10', [$uid]);
$editAddr  = null;
if (isset($_GET['edit_addr'])) {
    foreach ($addresses as $a) if ($a['id'] === $_GET['edit_addr']) { $editAddr = $a; break; }
}
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
            <p class="mb-0 opacity-90"><i class="fas fa-envelope me-1"></i><?= e($u['email']) ?> · <i class="fas fa-phone me-1"></i><?= e($u['phone']) ?></p>
        </div>
        <div class="ms-auto text-end d-none d-md-block">
            <div class="opacity-75 small">Member since</div>
            <div class="fw-600"><?= e(fmt_date(substr($u['created_at'], 0, 10))) ?></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Left column: profile + password -->
    <div class="col-lg-6">

        <!-- Profile form -->
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-id-card me-2 text-purple"></i>Personal Information</div>
            <div class="card-body">
                <form method="post" action="<?= APP_URL ?>/customer/profile.php" enctype="multipart/form-data">
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

        <!-- Password form -->
        <div class="card">
            <div class="card-header"><i class="fas fa-lock me-2 text-purple"></i>Change Password</div>
            <div class="card-body">
                <form method="post" action="<?= APP_URL ?>/customer/profile.php">
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

    </div>

    <!-- Right column: addresses + tickets -->
    <div class="col-lg-6">

        <!-- Addresses -->
        <div class="card mb-3" id="addresses">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-map-marked-alt me-2 text-purple"></i>Saved Addresses</span>
                <?php if (!$editAddr): ?>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addrModal" onclick="resetAddrForm()">
                    <i class="fas fa-plus me-1"></i>Add
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!$addresses): ?>
                    <p class="text-muted text-center mb-3">No saved addresses yet.</p>
                <?php else: ?>
                <div class="list-group mb-3">
                    <?php foreach ($addresses as $a): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="badge bg-purple-soft text-purple me-1"><?= e($a['label']) ?></span>
                                <?php if ((int)$a['is_default'] === 1): ?>
                                    <span class="badge bg-success">Default</span>
                                <?php endif; ?>
                                <p class="mb-0 small mt-1"><?= nl2br(e($a['address'])) ?></p>
                                <small class="text-muted"><?= e($a['city']) ?> — <?= e($a['pincode']) ?></small>
                            </div>
                            <div class="text-nowrap">
                                <a href="<?= APP_URL ?>/customer/profile.php?edit_addr=<?= e($a['id']) ?>#addresses" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="post" action="<?= APP_URL ?>/customer/profile.php" class="d-inline"
                                      data-confirm-submit="Delete this address?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="form_type" value="delete_address">
                                    <input type="hidden" name="address_id" value="<?= e($a['id']) ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash-alt"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($editAddr): ?>
        <!-- Edit address modal (shown on page load) -->
        <div class="modal fade show" id="editAddrModal" tabindex="-1" aria-hidden="true"
             style="display:block;background:rgba(0,0,0,0.5);">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="<?= APP_URL ?>/customer/profile.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_type" value="save_address">
                    <input type="hidden" name="address_id" value="<?= e($editAddr['id']) ?>">
                    <div class="modal-header bg-grad-purple text-white">
                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Address</h5>
                        <a href="<?= APP_URL ?>/customer/profile.php#addresses" class="btn-close btn-close-white"></a>
                    </div>
                    <div class="modal-body">
                        <?php
                        $af = $editAddr;
                        include __DIR__ . '/../includes/_address_form_fields.php';
                        ?>
                    </div>
                    <div class="modal-footer">
                        <a href="<?= APP_URL ?>/customer/profile.php#addresses" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-grad"><i class="fas fa-save me-1"></i>Save Address</button>
                    </div>
                </form>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Add address modal -->
        <div class="modal fade" id="addrModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="<?= APP_URL ?>/customer/profile.php" id="addrForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_type" value="save_address">
                    <input type="hidden" name="address_id" value="">
                    <div class="modal-header bg-grad-purple text-white">
                        <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add Address</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?php
                        $af = null;
                        include __DIR__ . '/../includes/_address_form_fields.php';
                        ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-grad"><i class="fas fa-save me-1"></i>Save Address</button>
                    </div>
                </form>
            </div>
          </div>
        </div>

        <!-- Support tickets -->
        <div class="card" id="tickets">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-life-ring me-2 text-purple"></i>Support Tickets</span>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#ticketModal">
                    <i class="fas fa-plus me-1"></i>New
                </button>
            </div>
            <div class="card-body p-0">
                <?php if (!$tickets): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p class="mb-0">No tickets yet. Need help? Open a new ticket.</p>
                    </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($tickets as $t): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="d-flex align-items-center gap-2">
                                    <?= status_badge($t['status']) ?>
                                    <span class="badge bg-purple-soft text-purple text-capitalize"><?= e($t['priority']) ?></span>
                                    <span class="fw-600"><?= e($t['subject']) ?></span>
                                </div>
                                <p class="small text-muted mb-1 mt-1"><?= e(mb_strimwidth($t['message'], 0, 120, '…')) ?></p>
                                <?php if (!empty($t['response'])): ?>
                                <div class="alert alert-info small py-1 px-2 mb-1">
                                    <i class="fas fa-reply me-1"></i><strong>Support:</strong> <?= e($t['response']) ?>
                                </div>
                                <?php endif; ?>
                                <small class="text-muted"><?= e(time_ago($t['created_at'])) ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- New ticket modal -->
<div class="modal fade" id="ticketModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
        <form method="post" action="<?= APP_URL ?>/customer/profile.php">
            <?= csrf_field() ?>
            <input type="hidden" name="form_type" value="new_ticket">
            <div class="modal-header bg-grad-purple text-white">
                <h5 class="modal-title"><i class="fas fa-headset me-2"></i>Open Support Ticket</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Subject <span class="text-danger">*</span></label>
                    <input type="text" name="subject" class="form-control" required minlength="5" placeholder="Briefly describe your issue">
                </div>
                <div class="mb-3">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-select">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label">Message <span class="text-danger">*</span></label>
                    <textarea name="message" class="form-control" rows="4" required minlength="10" placeholder="Describe your issue in detail..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-grad"><i class="fas fa-paper-plane me-1"></i>Submit Ticket</button>
            </div>
        </form>
    </div>
  </div>
</div>

<script>
function resetAddrForm() {
    const f = document.getElementById('addrForm');
    if (!f) return;
    f.reset();
    f.querySelector('[name="address_id"]').value = '';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
