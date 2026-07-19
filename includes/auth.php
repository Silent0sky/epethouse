<?php
/**
 * auth.php — Authentication & role-based access control.
 *
 * Provides: current_user(), require_login(), require_role(),
 * login flow, register flow, logout, password hashing helpers.
 */

require_once __DIR__ . '/functions.php';

/* ===============================================================
 *  SESSION / CURRENT USER
 * =============================================================== */

/** Get the currently logged-in user array (or null). */
function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) return null;
    static $cached = null;
    if ($cached !== null && ($cached['id'] ?? null) === $_SESSION['user_id']) {
        return $cached;
    }
    $user = db_select_one(
        'SELECT id, email, phone, name, role, address, avatar, reward_points,
                referral_code, membership_tier, active, created_at
         FROM users WHERE id = ? LIMIT 1',
        [$_SESSION['user_id']]
    );
    if (!$user || !$user['active']) {
        session_destroy();
        return null;
    }
    $cached = $user;
    return $user;
}

/** Is anyone logged in? */
function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

/** Require a logged-in user; else redirect to login. */
function require_login(): array
{
    $u = current_user();
    if (!$u) {
        flash('warning', 'Please log in to continue.');
        redirect(APP_URL . '/login.php');
    }
    return $u;
}

/** Require a specific role; aborts with 403 if mismatch. */
function require_role(string ...$roles): array
{
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        include __DIR__ . '/../includes/header.php';
        echo '<div class="container py-5 text-center">
                <i class="fas fa-lock fa-3x text-danger mb-3"></i>
                <h3>Access Denied</h3>
                <p class="text-muted">You do not have permission to view this page.</p>
                <a href="' . APP_URL . '/dashboard.php" class="btn btn-primary">Go to Dashboard</a>
              </div>';
        include __DIR__ . '/../includes/footer.php';
        exit;
    }
    return $u;
}

/* ===============================================================
 *  LOGIN / REGISTER / LOGOUT
 * =============================================================== */

/**
 * Attempt to log in a user by phone/email + password.
 * Returns [success, error_message].
 */
function attempt_login(string $identifier, string $password): array
{
    $identifier = trim($identifier);

    // ─── Rate limiting: max 5 attempts per 5 minutes per identifier + IP ───
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rlKey = 'login_attempts_' . md5($identifier . '|' . $ip);
    $attempts = $_SESSION[$rlKey] ?? [];
    $now = time();
    // Keep only attempts from the last 5 minutes
    $attempts = array_values(array_filter($attempts, fn($t) => $t > $now - 300));
    if (count($attempts) >= 5) {
        $wait = 300 - ($now - min($attempts));
        return [false, 'Too many login attempts. Please try again in ' . max(1, ceil($wait / 60)) . ' minute(s).'];
    }

    $user = db_select_one(
        'SELECT * FROM users WHERE phone = ? OR email = ? LIMIT 1',
        [$identifier, $identifier]
    );
    if (!$user) {
        $attempts[] = $now;
        $_SESSION[$rlKey] = $attempts;
        return [false, 'No account found with that phone/email.'];
    }
    if (!$user['active']) {
        return [false, 'Your account has been deactivated. Contact support.'];
    }
    if (!password_verify($password, $user['password_hash'])) {
        $attempts[] = $now;
        $_SESSION[$rlKey] = $attempts;
        return [false, 'Incorrect password.'];
    }
    // Re-hash if needed (cost upgrade)
    if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => HASH_COST])) {
        $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
        db_execute('UPDATE users SET password_hash = ? WHERE id = ?', [$newHash, $user['id']]);
    }
    // Set session
    session_regenerate_id(true);
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['login_time'] = time();
    // Clear rate-limit counter on successful login
    unset($_SESSION[$rlKey]);
    return [true, null];
}

/**
 * Register a new user.
 * Returns [success, user_id_or_error].
 */
function register_user(array $data): array
{
    $name  = clean($data['name'] ?? '');
    $email = strtolower(trim($data['email'] ?? ''));
    $phone = preg_replace('/\D/', '', $data['phone'] ?? '');
    $pass  = $data['password'] ?? '';
    $role  = $data['role'] ?? ROLE_CUSTOMER;
    $addr  = clean($data['address'] ?? '');

    // Validation
    if (strlen($name) < 2)  return [false, 'Please enter your full name.'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        return [false, 'Please enter a valid email address.'];
    if (strlen($phone) < 10)
        return [false, 'Please enter a valid 10-digit phone number.'];
    if (strlen($pass) < 6)
        return [false, 'Password must be at least 6 characters.'];
    if (!in_array($role, [ROLE_CUSTOMER, ROLE_GROOMER, ROLE_DELIVERY], true)) {
        $role = ROLE_CUSTOMER; // public signup cannot create ADMIN
    }

    // Uniqueness
    if (db_select_one('SELECT id FROM users WHERE email = ? LIMIT 1', [$email])) {
        return [false, 'An account with this email already exists.'];
    }
    if (db_select_one('SELECT id FROM users WHERE phone = ? LIMIT 1', [$phone])) {
        return [false, 'An account with this phone already exists.'];
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
        'address'         => $addr ?: null,
        'reward_points'   => REFERRAL_BONUS,
        'referral_code'   => $referralCode,
        'membership_tier' => 'bronze',
        'active'          => 1,
    ]);

    // Signup bonus
    db_insert('reward_transactions', [
        'id'          => gen_id('rt_'),
        'user_id'     => $id,
        'points'      => REFERRAL_BONUS,
        'type'        => 'bonus',
        'source'      => 'signup',
        'description' => 'Welcome bonus points',
    ]);
    notify($id, 'Welcome to ' . APP_NAME . '!', 'You received ' . REFERRAL_BONUS . ' welcome bonus points.', 'reward');

    return [true, $id];
}

/** Log out the current user. */
function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ===============================================================
 *  ROLE HELPERS
 * =============================================================== */

function is_admin(array $u): bool    { return $u['role'] === ROLE_ADMIN; }
function is_customer(array $u): bool { return $u['role'] === ROLE_CUSTOMER; }
function is_groomer(array $u): bool  { return $u['role'] === ROLE_GROOMER; }
function is_delivery(array $u): bool { return $u['role'] === ROLE_DELIVERY; }

/** Role display label. */
function role_label(string $role): string
{
    return match ($role) {
        ROLE_ADMIN     => 'Administrator',
        ROLE_CUSTOMER  => 'Customer',
        ROLE_GROOMER   => 'Groomer',
        ROLE_DELIVERY  => 'Delivery Partner',
        default        => ucfirst($role),
    };
}

/** Dashboard URL for a role. */
function dashboard_url(string $role): string
{
    return match ($role) {
        ROLE_ADMIN     => APP_URL . '/admin/dashboard.php',
        ROLE_GROOMER   => APP_URL . '/groomer/dashboard.php',
        ROLE_DELIVERY  => APP_URL . '/delivery/dashboard.php',
        default        => APP_URL . '/customer/dashboard.php',
    };
}
