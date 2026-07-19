<?php
/**
 * customer/rewards.php — Rewards & referrals page.
 */
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(ROLE_CUSTOMER);
$uid = $u['id'];

// Refresh reward_points + tier from DB (in case it changed)
$user = db_select_one('SELECT reward_points, membership_tier, referral_code FROM users WHERE id = ?', [$uid]);
$points = (int) ($user['reward_points'] ?? 0);
$tier   = ucfirst($user['membership_tier'] ?? 'bronze');
$referralCode = $user['referral_code'] ?? '';

// Tier thresholds (illustrative)
$tierInfo = [
    'bronze' => ['min' => 0,    'next' => 'silver', 'gap' => 500 - 0,   'color' => 'bg-grad-amber'],
    'silver' => ['min' => 500,  'next' => 'gold',   'gap' => 1500 - 500,'color' => 'bg-secondary'],
    'gold'   => ['min' => 1500, 'next' => 'platinum','gap'=> 3000 - 1500,'color' => 'bg-grad-amber'],
    'platinum'=>['min'=> 3000,  'next' => null,      'gap' => 0,         'color' => 'bg-grad-purple'],
];
$currentTier = strtolower($user['membership_tier'] ?? 'bronze');
$tierData = $tierInfo[$currentTier] ?? $tierInfo['bronze'];
$nextTier = $tierData['next'];
$tierProgress = $nextTier
    ? min(100, round(($points - $tierData['min']) / $tierData['gap'] * 100))
    : 100;

// Recent reward transactions
$transactions = db_select(
    'SELECT id, points, type, source, description, created_at
       FROM reward_transactions
      WHERE user_id = ?
      ORDER BY created_at DESC
      LIMIT 30',
    [$uid]
);

$pageTitle = 'Rewards & Referrals';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="h3 fw-bold text-purple mb-0"><i class="fas fa-gift me-2"></i>Rewards & Referrals</h1>
</div>

<div class="row g-3 mb-4">
    <!-- Balance card -->
    <div class="col-md-4">
        <div class="stat-card bg-grad-purple h-100">
            <div class="stat-label">Current Balance</div>
            <div class="stat-value"><?= $points ?></div>
            <div class="stat-label mt-2">reward points</div>
            <i class="fas fa-gift stat-icon"></i>
        </div>
    </div>
    <!-- Tier card -->
    <div class="col-md-4">
        <div class="stat-card <?= e($tierData['color']) ?> h-100">
            <div class="stat-label">Membership Tier</div>
            <div class="stat-value text-capitalize"><?= e($tier) ?></div>
            <div class="stat-label mt-2">
                <?php if ($nextTier): ?>
                    <?= $tierData['gap'] - ($points - $tierData['min']) ?> pts to <?= e(ucfirst($nextTier)) ?>
                <?php else: ?>
                    Top tier reached!
                <?php endif; ?>
            </div>
            <i class="fas fa-crown stat-icon"></i>
        </div>
    </div>
    <!-- Earned this month -->
    <div class="col-md-4">
        <div class="stat-card bg-grad-green h-100">
            <div class="stat-label">Earned This Month</div>
            <div class="stat-value">
                +<?= (int) db_scalar(
                    "SELECT COALESCE(SUM(points),0) FROM reward_transactions
                      WHERE user_id = ? AND type IN ('earn','bonus') AND created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')",
                    [$uid]
                ) ?>
            </div>
            <div class="stat-label mt-2">points this month</div>
            <i class="fas fa-arrow-trend-up stat-icon"></i>
        </div>
    </div>
</div>

<!-- Tier progress -->
<?php if ($nextTier): ?>
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between mb-2">
            <span class="fw-600 text-capitalize"><?= e($tier) ?></span>
            <span class="text-muted text-capitalize"><?= e(ucfirst($nextTier)) ?></span>
        </div>
        <div class="progress" style="height:10px;">
            <div class="progress-bar bg-grad-purple" style="width:<?= $tierProgress ?>%"></div>
        </div>
        <small class="text-muted">Earn <?= ($tierData['min'] + $tierData['gap'] - $points) ?> more points to reach <?= e(ucfirst($nextTier)) ?> tier.</small>
    </div>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <!-- How to earn -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-lightbulb me-2 text-purple"></i>How to Earn Points</div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-3 d-flex gap-3">
                        <i class="fas fa-shopping-bag text-purple fa-lg mt-1"></i>
                        <div>
                            <strong>Place Orders</strong>
                            <p class="small text-muted mb-0">Earn 1 point per Rs.10 spent on every order (rounded down).</p>
                        </div>
                    </li>
                    <li class="mb-3 d-flex gap-3">
                        <i class="fas fa-user-plus text-purple fa-lg mt-1"></i>
                        <div>
                            <strong>Refer Friends</strong>
                            <p class="small text-muted mb-0">Get <?= REFERRAL_BONUS ?> bonus points when a friend signs up with your code.</p>
                        </div>
                    </li>
                    <li class="mb-0 d-flex gap-3">
                        <i class="fas fa-birthday-cake text-purple fa-lg mt-1"></i>
                        <div>
                            <strong>Welcome Bonus</strong>
                            <p class="small text-muted mb-0">New members get <?= REFERRAL_BONUS ?> points on signup.</p>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Referral card -->
    <div class="col-md-6">
        <div class="card h-100 bg-grad-purple text-white">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-share-alt me-2"></i>Refer & Earn</h5>
                <p class="small mb-3 opacity-90">Share your referral code with friends. When they sign up, you both get <?= REFERRAL_BONUS ?> bonus points!</p>
                <label class="form-label small opacity-75 mb-1">Your referral code</label>
                <div class="input-group mb-3">
                    <input type="text" class="form-control form-control-lg fw-bold text-center"
                           id="referralCode" value="<?= e($referralCode) ?>" readonly
                           style="letter-spacing:3px;font-family:monospace;">
                    <button class="btn btn-light" type="button" onclick="copyReferral()">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
                <label class="form-label small opacity-75 mb-1">Share text</label>
                <textarea class="form-control form-control-sm bg-white bg-opacity-25 text-white border-0"
                          id="shareText" rows="3" readonly>Join me on <?= e(APP_NAME) ?>! Use my referral code <?= e($referralCode) ?> to get <?= REFERRAL_BONUS ?> welcome bonus points. 🐾</textarea>
                <button class="btn btn-light btn-sm w-100 mt-2" onclick="copyShareText()">
                    <i class="fas fa-share me-1"></i>Copy Share Text
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Transactions table -->
<h2 class="section-title">Reward History</h2>
<div class="card">
    <div class="card-body p-0">
        <?php if (!$transactions): ?>
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <p class="mb-0">No reward activity yet. Place an order to start earning!</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr>
                    <th>Date</th><th>Type</th><th>Source</th><th>Description</th><th class="text-end">Points</th>
                </tr></thead>
                <tbody>
                <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td class="small"><?= e(fmt_datetime($t['created_at'])) ?></td>
                        <td>
                            <?php
                            $typeClass = match($t['type']) {
                                'earn'  => 'bg-success',
                                'bonus' => 'bg-primary',
                                'redeem'=> 'bg-warning text-dark',
                                default => 'bg-secondary',
                            };
                            ?>
                            <span class="badge <?= $typeClass ?> text-capitalize"><?= e($t['type']) ?></span>
                        </td>
                        <td class="text-capitalize"><?= e(str_replace('_', ' ', $t['source'])) ?></td>
                        <td class="small"><?= e($t['description']) ?></td>
                        <td class="text-end fw-bold <?= in_array($t['type'], ['earn','bonus']) ? 'text-success' : 'text-danger' ?>">
                            <?= in_array($t['type'], ['earn','bonus']) ? '+' : '−' ?><?= (int)$t['points'] ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
async function copyReferral() {
    const code = document.getElementById('referralCode').value;
    try {
        await navigator.clipboard.writeText(code);
        window.showToast('Referral code copied!', 'success');
    } catch {
        // fallback
        const inp = document.getElementById('referralCode');
        inp.select(); document.execCommand('copy');
        window.showToast('Referral code copied!', 'success');
    }
}
async function copyShareText() {
    const text = document.getElementById('shareText').value;
    try {
        await navigator.clipboard.writeText(text);
        window.showToast('Share text copied!', 'success');
    } catch {
        const ta = document.getElementById('shareText');
        ta.select(); document.execCommand('copy');
        window.showToast('Share text copied!', 'success');
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
