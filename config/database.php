<?php
/**
 * database.php — Singleton PDO connection helper.
 *
 * Usage:  $pdo = db();   // returns a shared PDO instance
 *
 * All queries throughout the app MUST use prepared statements via this PDO.
 */

require_once __DIR__ . '/config.php';

/** @var PDO|null */
$GLOBALS['__pdo'] = null;

/**
 * Get the shared PDO connection (lazy, singleton).
 *
 * @return PDO
 */
function db(): PDO
{
    if ($GLOBALS['__pdo'] !== null) {
        return $GLOBALS['__pdo'];
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false,
    ];

    try {
        $GLOBALS['__pdo'] = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        http_response_code(500);

        // Hide raw database credentials in production
        $driverMsg = is_production()
            ? 'Database connection failed. Please verify hosting configuration or contact support.'
            : htmlspecialchars($e->getMessage());

        die(
            '<div style="font-family:sans-serif;max-width:640px;margin:80px auto;padding:28px;'
            . 'border:1px solid #fee2e2;border-radius:12px;background:#fff5f5;color:#991b1b;box-shadow:0 10px 30px rgba(0,0,0,0.05);">'
            . '<h2 style="margin-top:0;color:#991b1b;">Database Connection Error</h2>'
            . '<p>Pet House could not connect to MySQL.</p>'
            . '<ol style="line-height:1.6;font-size:14px;">'
            . '<li>If running locally, verify Apache and MySQL are active in <b>XAMPP Control Panel</b>.</li>'
            . '<li>If hosted on <b>InfinityFree</b>, verify MySQL Hostname (e.g. <code>sql100.infinityfree.com</code>), Database Name, User, and Password in <code>config/config.php</code>.</li>'
            . '<li>Import <code>database/database.sql</code> via phpMyAdmin into database <code>' . htmlspecialchars(DB_NAME) . '</code>.</li>'
            . '</ol>'
            . '<div style="background:#fee2e2;padding:12px;border-radius:6px;font-family:monospace;font-size:12px;margin-top:16px;">'
            . '<b>Message:</b> ' . $driverMsg
            . '</div>'
            . '<p style="margin-top:16px;font-size:13px;"><a href="' . htmlspecialchars(APP_URL ?? '') . '/test_db.php" style="color:#b91c1c;font-weight:bold;text-decoration:underline;">Run test_db.php diagnostic script</a></p>'
            . '</div>'
        );
    }

    return $GLOBALS['__pdo'];
}
