<?php
/**
 * login.php — User login (phone/email + password).
 */
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(dashboard_url(current_user()['role']));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $identifier = clean($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';
    [$ok, $err] = attempt_login($identifier, $password);
    if ($ok) {
        $u = current_user();
        flash('success', 'Welcome back, ' . $u['name'] . '!');
        redirect(dashboard_url($u['role']));
    }
    $error = $err;
}
$pageTitle = 'Login';
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login · <?= e(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head><body>
<div class="auth-wrap">
  <div class="auth-card fade-in">
    <div class="auth-side">
      <div class="auth-logo"><i class="fas fa-paw"></i></div>
      <h2 class="fw-bold mb-3">Welcome to <?= e(APP_NAME) ?></h2>
      <p class="mb-4" style="opacity:0.9;">Your one-stop destination for pet grooming, boarding, shopping and more — all in <?= e(APP_CITY) ?>.</p>
      <ul class="list-unstyled mb-0" style="opacity:0.95;">
        <li class="mb-2"><i class="fas fa-check-circle me-2"></i>Book grooming & boarding online</li>
        <li class="mb-2"><i class="fas fa-check-circle me-2"></i>Shop premium pet products</li>
        <li class="mb-2"><i class="fas fa-check-circle me-2"></i>Earn rewards on every order</li>
        <li class="mb-2"><i class="fas fa-check-circle me-2"></i>Track orders & deliveries</li>
      </ul>
    </div>
    <div class="auth-form">
      <h3 class="fw-bold mb-1 text-purple">Sign in</h3>
      <p class="text-muted mb-4">Enter your phone/email and password to continue.</p>

      <?php if ($error): ?>
      <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-2"></i><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label">Phone or Email</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fas fa-user"></i></span>
            <input type="text" name="identifier" class="form-control" required autofocus
                   value="<?= e($_POST['identifier'] ?? '') ?>" placeholder="9876543210 or you@email.com">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fas fa-lock"></i></span>
            <input type="password" name="password" id="password" class="form-control" required placeholder="••••••••">
            <button class="btn btn-outline-secondary" type="button" id="togglePass"><i class="far fa-eye"></i></button>
          </div>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="remember" id="remember">
            <label class="form-check-label small" for="remember">Remember me</label>
          </div>
          <a href="<?= APP_URL ?>/forgot_password.php" class="small">Forgot password?</a>
        </div>
        <button type="submit" class="btn btn-grad w-100 py-2"><i class="fas fa-sign-in-alt me-1"></i> Login</button>
      </form>

      <div class="text-center mt-4">
        <span class="text-muted small">Don't have an account?</span>
        <a href="<?= APP_URL ?>/register.php" class="small fw-semibold">Create one</a>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.getElementById('togglePass').addEventListener('click', function(){
    const i = document.getElementById('password');
    i.type = i.type === 'password' ? 'text' : 'password';
    this.querySelector('i').classList.toggle('fa-eye');
    this.querySelector('i').classList.toggle('fa-eye-slash');
  });
</script>
</body></html>
