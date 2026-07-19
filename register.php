<?php
/**
 * register.php — New customer/groomer/delivery signup.
 */
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(dashboard_url(current_user()['role']));
}

$error = '';
$form = ['name'=>'','email'=>'','phone'=>'','role'=>ROLE_CUSTOMER,'address'=>''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $form = [
        'name'     => $_POST['name'] ?? '',
        'email'    => $_POST['email'] ?? '',
        'phone'    => $_POST['phone'] ?? '',
        'address'  => $_POST['address'] ?? '',
        'role'     => $_POST['role'] ?? ROLE_CUSTOMER,
        'password' => $_POST['password'] ?? '',
    ];
    [$ok, $err] = register_user($form);
    if ($ok) {
        // Auto-login
        attempt_login($form['email'], $form['password']);
        flash('success', 'Account created! Welcome to ' . APP_NAME . '.');
        redirect(dashboard_url($form['role']));
    }
    $error = is_string($err) ? $err : 'Registration failed. Please try again.';
}
$pageTitle = 'Register';
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register · <?= e(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head><body>
<div class="auth-wrap">
  <div class="auth-card fade-in">
    <div class="auth-side">
      <div class="auth-logo"><i class="fas fa-paw"></i></div>
      <h2 class="fw-bold mb-3">Join <?= e(APP_NAME) ?></h2>
      <p style="opacity:0.9;">Create your account and get instant access to grooming, boarding, shopping and pet care services.</p>
      <div class="mt-4 p-3 rounded" style="background:rgba(255,255,255,0.12);">
        <p class="mb-1 fw-semibold"><i class="fas fa-gift me-2"></i>Welcome bonus</p>
        <p class="mb-0 small" style="opacity:0.9;">Get <?= REFERRAL_BONUS ?> reward points when you sign up!</p>
      </div>
    </div>
    <div class="auth-form">
      <h3 class="fw-bold mb-1 text-purple">Create account</h3>
      <p class="text-muted mb-4">Fill in your details to register.</p>

      <?php if ($error): ?>
      <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-2"></i><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <div class="mb-2">
          <label class="form-label">Full Name</label>
          <input type="text" name="name" class="form-control" required value="<?= e($form['name']) ?>" placeholder="Rahul Sharma">
        </div>
        <div class="mb-2">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" required value="<?= e($form['email']) ?>" placeholder="you@email.com">
        </div>
        <div class="mb-2">
          <label class="form-label">Phone</label>
          <input type="tel" name="phone" class="form-control" required pattern="[0-9]{10}" value="<?= e($form['phone']) ?>" placeholder="9876543210">
        </div>
        <div class="mb-2">
          <label class="form-label">Address (optional)</label>
          <input type="text" name="address" class="form-control" value="<?= e($form['address']) ?>" placeholder="City, area">
        </div>
        <div class="mb-2">
          <label class="form-label">Account Type</label>
          <select name="role" class="form-select">
            <option value="<?= ROLE_CUSTOMER ?>" <?= $form['role']===ROLE_CUSTOMER?'selected':'' ?>>Customer — book services & shop</option>
            <option value="<?= ROLE_GROOMER ?>"  <?= $form['role']===ROLE_GROOMER?'selected':'' ?>>Groomer (staff)</option>
            <option value="<?= ROLE_DELIVERY ?>" <?= $form['role']===ROLE_DELIVERY?'selected':'' ?>>Delivery Partner</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input type="password" name="password" id="password" class="form-control" required minlength="6" placeholder="Min 6 characters">
        </div>
        <button type="submit" class="btn btn-grad w-100 py-2"><i class="fas fa-user-plus me-1"></i> Create Account</button>
      </form>

      <div class="text-center mt-3">
        <span class="text-muted small">Already have an account?</span>
        <a href="<?= APP_URL ?>/login.php" class="small fw-semibold">Login</a>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
