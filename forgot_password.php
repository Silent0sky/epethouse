<?php
/**
 * forgot_password.php — Password recovery request page.
 *
 * Since this is a self-hosted XAMPP app without a mail server, a self-service
 * token-reset flow is not available. Instead, this page verifies the account
 * exists and directs the user to contact an administrator (who can reset
 * passwords via Admin → Users → Reset Password). This keeps the auth flow
 * and database schema unchanged while making the "Forgot password?" link
 * functional instead of a dead anchor.
 */
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(dashboard_url(current_user()['role']));
}

$done   = false;
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $identifier = clean($_POST['identifier'] ?? '');

    if ($identifier === '') {
        $error = 'Please enter your phone number or email address.';
    } else {
        // Look up the account (do not reveal to the user whether it exists).
        $user = db_select_one(
            'SELECT name, email, phone FROM users WHERE email = ? OR phone = ? LIMIT 1',
            [$identifier, $identifier]
        );

        // Always show the same confirmation message regardless of whether the
        // account was found — this prevents account enumeration.
        $done = true;
    }
}

$pageTitle = 'Forgot Password';
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password · <?= e(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head><body>
<div class="auth-wrap">
  <div class="auth-card fade-in">
    <div class="auth-side">
      <div class="auth-logo"><i class="fas fa-paw"></i></div>
      <h2 class="fw-bold mb-3"><?= e(APP_NAME) ?></h2>
      <p class="mb-4" style="opacity:0.9;">Need help accessing your account? We're here to help you get back in.</p>
      <ul class="list-unstyled mb-0" style="opacity:0.95;">
        <li class="mb-2"><i class="fas fa-shield-alt me-2"></i>Your security is our priority</li>
        <li class="mb-2"><i class="fas fa-headset me-2"></i>Reach our support team anytime</li>
        <li class="mb-2"><i class="fas fa-lock me-2"></i>Passwords are never stored in plain text</li>
      </ul>
    </div>
    <div class="auth-form">
      <h3 class="fw-bold mb-1 text-purple"><i class="fas fa-key me-2"></i>Forgot Password</h3>
      <p class="text-muted mb-4">Enter your registered phone or email and we'll help you reset your password.</p>

      <?php if ($error): ?>
      <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-2"></i><?= e($error) ?></div>
      <?php endif; ?>

      <?php if ($done): ?>
        <div class="alert alert-success py-2">
          <i class="fas fa-check-circle me-2"></i>
          <strong>Request received.</strong>
        </div>
        <div class="bg-purple-soft rounded p-3 mb-3">
          <p class="mb-2"><i class="fas fa-info-circle me-1 text-purple"></i>
            For your security, password resets are handled by our administrator.</p>
          <p class="mb-2 small">Please contact our support team with your account details and a valid ID proof.
          Once verified, an administrator will reset your password and share your temporary credentials.</p>
          <div class="small">
            <div class="mb-1"><i class="fas fa-envelope me-2 text-purple"></i>Email: <strong><?= e(APP_NAME) ?> Support</strong></div>
            <div class="mb-1"><i class="fas fa-phone me-2 text-purple"></i>Call us during business hours</div>
            <div><i class="fas fa-map-marker-alt me-2 text-purple"></i><?= e(APP_CITY) ?></div>
          </div>
        </div>
        <div class="d-flex gap-2">
          <a href="<?= APP_URL ?>/login.php" class="btn btn-grad flex-grow-1">
            <i class="fas fa-arrow-left me-1"></i> Back to Login
          </a>
        </div>
      <?php else: ?>
        <form method="post" autocomplete="off">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">Phone or Email</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-user"></i></span>
              <input type="text" name="identifier" class="form-control" required autofocus
                     value="<?= e($_POST['identifier'] ?? '') ?>" placeholder="9876543210 or you@email.com">
            </div>
            <div class="form-text">Enter the phone number or email used during registration.</div>
          </div>
          <button type="submit" class="btn btn-grad w-100 py-2">
            <i class="fas fa-paper-plane me-1"></i> Submit Request
          </button>
        </form>
      <?php endif; ?>

      <div class="text-center mt-4">
        <span class="text-muted small">Remembered your password?</span>
        <a href="<?= APP_URL ?>/login.php" class="small fw-semibold">Sign in</a>
      </div>

      <hr class="my-4">
      <div class="text-center small text-muted">
        <i class="fas fa-shield-alt me-1"></i>
        We will never ask for your password over phone or email.
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
