<?php
/**
 * test_db.php — Standalone Database & Environment Diagnostic Tool for Pet House.
 * 
 * Tests database connectivity, verifies table schemas, and detects common 
 * configuration issues on both Localhost (XAMPP) and InfinityFree Hosting.
 */

require_once __DIR__ . '/config/config.php';

header('Content-Type: text/html; charset=utf-8');

$hostName = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0]);
$isLocal = (
    $hostName === 'localhost'
    || $hostName === '127.0.0.1'
    || $hostName === '::1'
    || strpos($hostName, '192.168.') === 0
    || strpos($hostName, '10.') === 0
    || strpos($hostName, '172.') === 0
    || strpos($hostName, '.local') !== false
);

$pdo = null;
$dbError = null;
$tables = [];
$userCount = 0;

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 5,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('users', $tables)) {
        $userCount = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    }
} catch (PDOException $e) {
    $dbError = $e;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pet House — Database Connection Diagnostic</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
  body { background-color: #f8f7fc; color: #3b3563; font-family: system-ui, -apple-system, sans-serif; padding-top: 40px; padding-bottom: 60px; }
  .card-diag { background: #ffffff; border: 1px solid #e9e5f5; border-radius: 16px; box-shadow: 0 10px 30px rgba(124, 58, 237, 0.08); overflow: hidden; margin-bottom: 24px; }
  .card-diag-header { background: linear-gradient(135deg, #7c3aed, #5b21b6); color: #fff; padding: 20px 24px; }
  .status-badge { font-size: 0.85rem; font-weight: 600; padding: 6px 14px; border-radius: 50px; text-transform: uppercase; letter-spacing: 0.5px; }
  .bg-pass { background-color: #dcfce7; color: #15803d; }
  .bg-fail { background-color: #fee2e2; color: #b91c1c; }
  code { background: #f3f0ff; color: #6b21a8; padding: 2px 6px; border-radius: 4px; font-size: 0.9em; }
</style>
</head>
<body>

<div class="container" style="max-width: 820px;">
  <div class="card-diag">
    <div class="card-diag-header d-flex justify-content-between align-items-center">
      <div>
        <h3 class="fw-bold mb-0"><i class="fas fa-database me-2"></i> Pet House Database Diagnostic</h3>
        <p class="mb-0 small text-white-50">Testing connectivity for Localhost (XAMPP) & InfinityFree Hosting</p>
      </div>
      <div>
        <?php if ($pdo): ?>
          <span class="status-badge bg-pass"><i class="fas fa-check-circle me-1"></i> Connected</span>
        <?php else: ?>
          <span class="status-badge bg-fail"><i class="fas fa-times-circle me-1"></i> Failed</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="card-body p-4">
      <!-- Environment Summary -->
      <h5 class="fw-bold text-purple mb-3"><i class="fas fa-server me-2"></i> Environment Detection</h5>
      <div class="table-responsive mb-4">
        <table class="table table-bordered align-middle">
          <tbody>
            <tr>
              <th style="width: 200px;" class="bg-light">Detected Environment</th>
              <td>
                <?php if ($isLocal): ?>
                  <span class="badge bg-primary fs-6"><i class="fas fa-desktop me-1"></i> Localhost / LAN IP</span>
                <?php else: ?>
                  <span class="badge bg-success fs-6"><i class="fas fa-cloud me-1"></i> Live Hosting (InfinityFree)</span>
                <?php endif; ?>
              </td>
            </tr>
            <tr>
              <th class="bg-light">HTTP Host</th>
              <td><code><?= htmlspecialchars($hostName) ?></code></td>
            </tr>
            <tr>
              <th class="bg-light">Configured APP_URL</th>
              <td><a href="<?= htmlspecialchars(APP_URL) ?>" target="_blank"><code><?= htmlspecialchars(APP_URL) ?></code></a></td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Configured DB Details -->
      <h5 class="fw-bold text-purple mb-3"><i class="fas fa-cogs me-2"></i> Active Database Settings</h5>
      <div class="table-responsive mb-4">
        <table class="table table-bordered align-middle">
          <tbody>
            <tr>
              <th style="width: 200px;" class="bg-light">DB_HOST</th>
              <td><code><?= htmlspecialchars(DB_HOST) ?></code> (Port: <?= (int)DB_PORT ?>)</td>
            </tr>
            <tr>
              <th class="bg-light">DB_NAME</th>
              <td><code><?= htmlspecialchars(DB_NAME) ?></code></td>
            </tr>
            <tr>
              <th class="bg-light">DB_USER</th>
              <td><code><?= htmlspecialchars(DB_USER) ?></code></td>
            </tr>
            <tr>
              <th class="bg-light">DB_PASS</th>
              <td>
                <code><?= DB_PASS === '' ? '[EMPTY]' : substr(DB_PASS, 0, 4) . str_repeat('*', max(4, strlen(DB_PASS) - 4)) ?></code>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Result Section -->
      <?php if ($pdo): ?>
        <div class="alert alert-success p-3 rounded-3 mb-4">
          <h5 class="alert-heading fw-bold mb-1"><i class="fas fa-check-circle me-2"></i> Connection Successful!</h5>
          <p class="mb-0 small">PDO connected cleanly using <code>mysql:host=<?= htmlspecialchars(DB_HOST) ?>;dbname=<?= htmlspecialchars(DB_NAME) ?>;charset=utf8mb4</code>.</p>
        </div>

        <h5 class="fw-bold text-purple mb-3"><i class="fas fa-table me-2"></i> Schema Verification</h5>
        <p class="text-muted small">Found <strong><?= count($tables) ?></strong> tables in database <code><?= htmlspecialchars(DB_NAME) ?></code>.</p>
        <div class="d-flex flex-wrap gap-2 mb-4">
          <?php foreach ($tables as $t): ?>
            <span class="badge bg-light text-dark border"><i class="fas fa-table me-1 text-purple"></i><?= htmlspecialchars($t) ?></span>
          <?php endforeach; ?>
        </div>

        <div class="p-3 bg-light rounded-3 d-flex justify-content-between align-items-center">
          <div>
            <strong>Registered Users:</strong> <?= $userCount ?> user(s) seeded.
          </div>
          <a href="<?= htmlspecialchars(APP_URL) ?>/login.php" class="btn btn-primary btn-sm"><i class="fas fa-sign-in-alt me-1"></i> Go to Login</a>
        </div>

      <?php else: ?>
        <!-- Error Diagnosis -->
        <div class="alert alert-danger p-3 rounded-3 mb-4">
          <h5 class="alert-heading fw-bold mb-2"><i class="fas fa-exclamation-triangle me-2"></i> Connection Failed</h5>
          <p class="mb-0 font-monospace small">Driver Message: <?= htmlspecialchars($dbError->getMessage()) ?></p>
        </div>

        <h5 class="fw-bold text-danger mb-3"><i class="fas fa-tools me-2"></i> Diagnostic Analysis & Solutions</h5>
        
        <?php
        $errCode = (int)$dbError->getCode();
        $errMsg  = strtolower($dbError->getMessage());
        ?>

        <?php if (strpos($errMsg, 'access denied') !== false && strpos($errMsg, '192.168.') !== false): ?>
          <div class="card border-danger mb-3">
            <div class="card-body">
              <h6 class="fw-bold text-danger"><i class="fas fa-ban me-2"></i> Remote InfinityFree Access Blocked</h6>
              <p class="small mb-2">You are running <code>test_db.php</code> from a local computer (IP <code>192.168.x.x</code>) while configured to connect to InfinityFree MySQL (<code>sql100.infinityfree.com</code>).</p>
              <p class="small mb-0"><strong>Solution:</strong> InfinityFree blocks remote MySQL connections from home PCs. To test your InfinityFree database, upload <code>test_db.php</code> to <code>epethouse.infinityfree.io/htdocs/</code> and open it via <code>https://epethouse.infinityfree.io/test_db.php</code>.</p>
            </div>
          </div>
        <?php elseif (strpos($errMsg, 'access denied') !== false): ?>
          <div class="card border-danger mb-3">
            <div class="card-body">
              <h6 class="fw-bold text-danger"><i class="fas fa-key me-2"></i> Invalid Database Credentials</h6>
              <p class="small mb-2">MySQL rejected the username (<code><?= htmlspecialchars(DB_USER) ?></code>) or password.</p>
              <ul class="small mb-0">
                <li>If on InfinityFree, verify your <strong>vPanel Password</strong> in <code>config/config.php</code>.</li>
                <li>Ensure <strong>MYSQL USERNAME</strong> matches your InfinityFree account (e.g. <code>if0_41194357</code>).</li>
              </ul>
            </div>
          </div>
        <?php elseif (strpos($errMsg, 'unknown database') !== false): ?>
          <div class="card border-warning mb-3">
            <div class="card-body">
              <h6 class="fw-bold text-warning-emphasis"><i class="fas fa-folder-minus me-2"></i> Database Does Not Exist</h6>
              <p class="small mb-0">The database <code><?= htmlspecialchars(DB_NAME) ?></code> was not found. Please create it in your hosting Control Panel (MySQL Databases tab) or XAMPP phpMyAdmin.</p>
            </div>
          </div>
        <?php else: ?>
          <div class="card border-secondary mb-3">
            <div class="card-body">
              <h6 class="fw-bold"><i class="fas fa-info-circle me-2"></i> Connection Setup Checklist</h6>
              <ol class="small mb-0">
                <li>If running locally on XAMPP, ensure MySQL module is started in XAMPP Control Panel.</li>
                <li>Import <code>database/database.sql</code> via phpMyAdmin.</li>
              </ol>
            </div>
          </div>
        <?php endif; ?>

      <?php endif; ?>
    </div>
  </div>
</div>

</body>
</html>
