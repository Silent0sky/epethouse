<?php
/**
 * admin/settings.php — Store settings (key/value upsert).
 *
 * Loads all settings, displays an editable form for the core store fields,
 * and on POST upserts each key into the store_settings table. Uses gen_id()
 * for any newly inserted row.
 */
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(ROLE_ADMIN);
$pageTitle = 'Store Settings';

// ─── POST handler: upsert settings ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $keys = ['store_name', 'store_city', 'store_phone', 'free_delivery_min', 'tax_rate'];

    foreach ($keys as $key) {
        $value = $_POST[$key] ?? '';

        // Clean / cast
        if ($key === 'free_delivery_min') {
            $value = max(0, (float) $value);
        } elseif ($key === 'tax_rate') {
            $value = max(0, min(100, (float) $value));
        } else {
            $value = trim((string) $value);
            if ($value === '') continue; // skip empty
        }

        // Check if row exists
        $existing = db_select_one('SELECT id FROM store_settings WHERE `key` = ? LIMIT 1', [$key]);
        if ($existing) {
            db_execute(
                'UPDATE store_settings SET `value` = ? WHERE id = ?',
                [(string) $value, $existing['id']]
            );
        } else {
            db_insert('store_settings', [
                'id'    => gen_id('s_'),
                'key'   => $key,
                'value' => (string) $value,
            ]);
        }
    }

    flash('success', 'Settings updated successfully.');
    redirect(APP_URL . '/admin/settings.php');
}

// ─── Load all settings ───────────────────────────────────────────────
include __DIR__ . '/../includes/header.php';
$allRows = db_select('SELECT `key`, `value` FROM store_settings');
$all = [];
foreach ($allRows as $r) {
    $all[$r['key']] = $r['value'];
}

// Current values (with defaults from config.php)
$storeName       = setting('store_name',        APP_NAME);
$storeCity       = setting('store_city',        APP_CITY);
$storePhone      = setting('store_phone',       '');
$freeDeliveryMin = setting('free_delivery_min', FREE_DELIVERY_MIN);
$taxRate         = setting('tax_rate',          (string) ((float) TAX_RATE * 100));
?>
<!-- Page header -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 fade-in">
    <div>
        <h1 class="h3 mb-1 fw-bold text-purple">
            <i class="fas fa-cog me-2"></i>Store Settings
        </h1>
        <p class="text-muted mb-0">Configure core store information and business rules.</p>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <form method="post" action="<?= APP_URL ?>/admin/settings.php">
            <?= csrf_field() ?>

            <!-- Store info -->
            <div class="card mb-3">
                <div class="card-header"><i class="fas fa-store me-2 text-purple"></i>Store Information</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Store Name</label>
                            <input type="text" name="store_name" class="form-control" value="<?= e($storeName) ?>" maxlength="120" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">City</label>
                            <input type="text" name="store_city" class="form-control" value="<?= e($storeCity) ?>" maxlength="80">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Phone</label>
                            <input type="text" name="store_phone" class="form-control" value="<?= e($storePhone) ?>" maxlength="20" placeholder="e.g. +91 98765 43210">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Business rules -->
            <div class="card mb-3">
                <div class="card-header"><i class="fas fa-sliders-h me-2 text-purple"></i>Business Rules</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">
                                Free Delivery Threshold
                                <small class="text-muted d-block">Minimum order value (₹) for free delivery</small>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" name="free_delivery_min" class="form-control"
                                       value="<?= e((float) $freeDeliveryMin) ?>" min="0" step="1" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">
                                Tax Rate
                                <small class="text-muted d-block">Percentage applied at checkout (0–100)</small>
                            </label>
                            <div class="input-group">
                                <input type="number" name="tax_rate" class="form-control"
                                       value="<?= e((float) $taxRate) ?>" min="0" max="100" step="0.1" required>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-grad">
                    <i class="fas fa-save me-1"></i>Save Settings
                </button>
                <a href="<?= APP_URL ?>/admin/settings.php" class="btn btn-link text-muted">Reset</a>
            </div>
        </form>
    </div>

    <!-- Side: current settings summary -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="fas fa-eye me-2 text-purple"></i>Current Saved Values</div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">store_name</span>
                        <span class="fw-600"><?= e($storeName) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">store_city</span>
                        <span class="fw-600"><?= e($storeCity) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">store_phone</span>
                        <span class="fw-600"><?= e($storePhone ?: '—') ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">free_delivery_min</span>
                        <span class="fw-600"><?= money($freeDeliveryMin) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">tax_rate</span>
                        <span class="fw-600"><?= e($taxRate) ?>%</span>
                    </li>
                </ul>
                <hr>
                <p class="small text-muted mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    These values are read at runtime via the <code>setting()</code> helper. Changes apply immediately across the app.
                </p>
            </div>
        </div>

        <?php if (!empty($all)): ?>
        <div class="card mt-3">
            <div class="card-header"><i class="fas fa-database me-2 text-purple"></i>All Stored Keys (<?= count($all) ?>)</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Key</th><th>Value</th></tr></thead>
                        <tbody>
                        <?php foreach ($all as $k => $v): ?>
                            <tr>
                                <td><code class="small"><?= e($k) ?></code></td>
                                <td class="small text-truncate" style="max-width:160px;" title="<?= e($v) ?>"><?= e(mb_strimwidth($v, 0, 50, '…')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php';
