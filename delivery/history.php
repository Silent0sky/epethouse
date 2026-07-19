<?php
/**
 * delivery/history.php — Past deliveries for this partner.
 *
 * - Status = delivered
 * - Proof thumbnail, delivered date, notes
 * - Paginated at 15 per page
 */
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(ROLE_DELIVERY);
$uid = $u['id'];

$whereSql = 'd.partner_id = ? AND d.status = ?';
$params   = [$uid, 'delivered'];

$countSql = "SELECT COUNT(*) FROM deliveries d WHERE $whereSql";
$dataSql  = "SELECT d.id, d.completed_at, d.proof_image, d.notes,
                    o.id AS order_id, o.total, o.address,
                    u.name AS customer_name, u.phone
               FROM deliveries d
               JOIN orders o ON o.id = d.order_id
               JOIN users  u ON u.id = o.user_id
              WHERE $whereSql
              ORDER BY d.completed_at DESC";

$pg = paginate($countSql, $dataSql, $params, 15);
$delivered = $pg['rows'];

$pageTitle = 'Delivery History';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="h3 fw-bold text-purple mb-0"><i class="fas fa-history me-2"></i>Delivery History</h1>
    <a href="<?= APP_URL ?>/delivery/assigned.php" class="btn btn-outline-primary btn-sm">
        <i class="fas fa-clipboard-list me-1"></i>Active Assignments
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (!$delivered): ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <h5 class="mt-2">No completed deliveries yet</h5>
                <p class="mb-0">Once you mark orders as delivered, they will show up here with proof photos.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead><tr>
                    <th>Order</th><th>Customer</th><th>Address</th>
                    <th>Delivered</th><th>Proof</th><th>Notes</th><th class="text-end">Total</th>
                </tr></thead>
                <tbody>
                <?php foreach ($delivered as $d): ?>
                <tr>
                    <td><span class="fw-600 text-purple">#<?= e(substr($d['order_id'], -6)) ?></span></td>
                    <td>
                        <div class="fw-600"><?= e($d['customer_name']) ?></div>
                        <small class="text-muted"><i class="fas fa-phone me-1"></i><?= e($d['phone']) ?></small>
                    </td>
                    <td class="small" style="max-width:260px;">
                        <?= nl2br(e(mb_strimwidth($d['address'] ?? '—', 0, 80, '…'))) ?>
                    </td>
                    <td class="small">
                        <?= $d['completed_at'] ? e(fmt_datetime($d['completed_at'])) : '<span class="text-muted">—</span>' ?>
                    </td>
                    <td>
                        <?php if (!empty($d['proof_image'])): ?>
                            <a href="<?= APP_URL ?>/<?= e($d['proof_image']) ?>" target="_blank" title="View proof photo">
                                <img src="<?= APP_URL ?>/<?= e($d['proof_image']) ?>" alt="proof"
                                     style="width:48px;height:48px;object-fit:cover;border-radius:8px;border:1px solid #e6e0f5;">
                            </a>
                        <?php else: ?>
                            <span class="text-muted small">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted">
                        <?= $d['notes'] ? e(mb_strimwidth($d['notes'], 0, 60, '…')) : '—' ?>
                    </td>
                    <td class="text-end fw-600"><?= money($d['total']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="mt-3">
    <?= pagination_links($pg, APP_URL . '/delivery/history.php') ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
