<?php

/**
 * config.php — Central configuration for Pet House (Core PHP version)
 *
 * Edit ONLY the DB_* constants below to match your XAMPP MySQL setup,
 * then visit http://localhost/php-pethouse/setup.php once to seed users.
 */

// ─── Environment Detection ───────────────────────────────────────
$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') == 443)
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
);
$protocol = $isHttps ? 'https://' : 'http://';
$httpHost = $_SERVER['HTTP_HOST'] ?? 'localhost';

$hostName = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0]);

// Detect if request is running on local PC or local network IP (XAMPP)
$isLocalEnvironment = (
    $hostName === 'localhost'
    || $hostName === '127.0.0.1'
    || $hostName === '::1'
    || strpos($hostName, '192.168.') === 0
    || strpos($hostName, '10.') === 0
    || strpos($hostName, '172.') === 0
    || strpos($hostName, '.local') !== false
);

// Optional custom config override
if (is_file(__DIR__ . '/config.custom.php')) {
    require_once __DIR__ . '/config.custom.php';
}

// ─── Database Credentials & APP_URL (Auto-Detected) ────────────────
if ($isLocalEnvironment) {
    // ── Localhost / Local LAN IP Access (XAMPP) ──
    if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1');
    if (!defined('DB_PORT')) define('DB_PORT', 3306);
    if (!defined('DB_NAME')) define('DB_NAME', 'pethouse');
    if (!defined('DB_USER')) define('DB_USER', 'root');
    if (!defined('DB_PASS')) define('DB_PASS', '');                 // XAMPP default
    if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

    // Auto-detect base path for LAN IP or localhost
    if (!defined('APP_URL')) {
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $basePath = preg_replace('#/(admin|customer|groomer|delivery|ajax|includes|config).*$#', '', $scriptDir);
        if ($basePath === '/' || $basePath === '\\') $basePath = '';
        define('APP_URL', $protocol . $httpHost . ($basePath ?: '/pet-house'));
    }
} else {
    // ── InfinityFree / Live Hosting Defaults ────────────────────────
    if (!defined('DB_HOST')) define('DB_HOST', 'sql100.infinityfree.com'); // InfinityFree MySQL Hostname
    if (!defined('DB_PORT')) define('DB_PORT', 3306);
    if (!defined('DB_NAME')) define('DB_NAME', 'if0_41194357_epethouse');  // InfinityFree DB Name
    if (!defined('DB_USER')) define('DB_USER', 'if0_41194357');            // InfinityFree DB Username
    if (!defined('DB_PASS')) define('DB_PASS', 'Mundheakash0512');         // InfinityFree VPanel Password
    if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');
    if (!defined('APP_URL')) define('APP_URL', $protocol . $httpHost);
}

// ─── Application Settings ────────────────────────────────────────
define('APP_NAME', 'Pet House');
define('APP_TAGLINE', 'Pet Shop & Pet Salon');
define('APP_CITY', 'Chhatrapati Sambhajinagar');
define('APP_TIMEZONE', 'Asia/Kolkata');

// ─── Security ────────────────────────────────────────────────────
define('HASH_COST', 10);               // bcrypt cost
define('SESSION_LIFETIME', 60 * 60 * 24 * 7);  // 7 days
define('CSRF_TOKEN_NAME', '_csrf_token');

// ─── Business Rules ──────────────────────────────────────────────
define('FREE_DELIVERY_MIN', 499);
define('TAX_RATE', 0.10);              // 10% tax
define('REWARD_RATE', 0.1);            // 1 point per Rs.10 = 0.1
define('REFERRAL_BONUS', 100);

// ─── Uploads ─────────────────────────────────────────────────────
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/');
define('UPLOAD_URL',  APP_URL . '/assets/uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_FILE_TYPES', ['pdf', 'jpg', 'jpeg', 'png']);

// ─── Roles ───────────────────────────────────────────────────────
define('ROLE_ADMIN',     'ADMIN');
define('ROLE_CUSTOMER',  'CUSTOMER');
define('ROLE_GROOMER',   'GROOMER');
define('ROLE_DELIVERY',  'DELIVERY_PARTNER');

// ─── Bootstrap environment ───────────────────────────────────────
date_default_timezone_set(APP_TIMEZONE);

// Detect production: APP_URL is not localhost OR request is HTTPS
$isProduction = (
    strpos(APP_URL, 'localhost') === false
    && strpos(APP_URL, '127.0.0.1') === false
);
$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') == 443)
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
);

// Error reporting: hide from users in production, show in dev
if ($isProduction) {
    error_reporting(E_ALL);
    @ini_set('display_errors', '0');
    @ini_set('log_errors', '1');
} else {
    error_reporting(E_ALL);
    @ini_set('display_errors', '1');
    @ini_set('log_errors', '1');
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $isHttps,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Helper: is the current deployment production (not localhost)?
 * Used to gate demo-credential display on the login page.
 */
function is_production(): bool
{
    return strpos(APP_URL, 'localhost') === false
        && strpos(APP_URL, '127.0.0.1') === false;
}
