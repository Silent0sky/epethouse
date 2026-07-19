<?php
/**
 * delivery/dashboard.php — Delivery partner home page.
 *
 * - 4 stat cards: Assigned / In Transit / Delivered Today / Total Delivered
 * - Active deliveries table (assigned / picked_up / in_transit)
 * - Status transitions: Picked Up → Out for Delivery → Delivered (with proof)
 * - Pending assignments info banner (orders with status=confirmed but no delivery row)
 */
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(ROLE_DELIVERY);
$uid = $u['id'];

$today = date('Y-m-d');

// ─── POST: status transitions ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action      = $_POST['action'] ?? '';
    $deliveryId  = trim($_POST['delivery_id'] ?? '');

    $delivery = db_select_one(
        'SELECT d.id, d.order_id, d.status, d.partner_id, d.notes, d.proof_image,
                o.id AS order_id, o.user_id, o.address, o.total, u.name AS customer_name, u.phone
           FROM deliveries d
           JOIN orders o ON o.id = d.order_id
           JOIN users  u ON u.id = o.user_id
          WHERE d.id = ? AND d.partner_id = ? LIMIT 1',
        [$deliveryId, $uid]
    );

    if (!$delivery) {
        flash('danger', 'Delivery not found or not assigned to you.');
    } else {
        $newStatus = match ($action) {
            'pick_up'     => $delivery['status'] === 'assigned'    ? 'picked_up'   : $delivery['status'],
            'out_for_del' => $delivery['status'] === 'picked_up'   ? 'in_transit'  : $delivery['status'],
            'deliver'     => in_array($delivery['status'], ['picked_up', 'in_transit'], true)
                                ? 'delivered' : $delivery['status'],
            default       => null,
        };

        if ($newStatus === null || $newStatus === $delivery['status']) {
            flash('warning', 'That action is not allowed for this delivery right now.');
        } elseif ($newStatus === 'delivered') {
            // Delivered requires proof photo + notes
            $proofUrl = $delivery['proof_image'];
            $notes    = clean($_POST['notes'] ?? '');

            if (!empty($_FILES['proof_image']['name'])) {
                $up = upload_file('proof_image', 'delivery_proof');
                if ($up['ok']) $proofUrl = $up['url'];
                else { flash('danger', 'Proof photo upload failed: ' . $up['error']); redirect(APP_URL . '/delivery/dashboard.php'); }
            } elseif (empty($proofUrl)) {
                flash('danger', 'A proof photo is required to mark as delivered.');
                redirect(APP_URL . '/delivery/dashboard.php');
            }

            db_execute(
                'UPDATE deliveries SET status = ?, proof_image = ?, notes = ?, completed_at = ? WHERE id = ?',
                [$newStatus, $proofUrl, $notes ?: null, date('Y-m-d H:i:s'), $deliveryId]
            );
            // Also mark the order as delivered
            db_execute("UPDATE orders SET status = 'delivered' WHERE id = ?", [$delivery['order_id']]);

            notify(
                $delivery['user_id'],
                'Order Delivered',
                'Your order #' . substr($delivery['order_id'], -6) . ' has been delivered. Thank you for shopping with ' . APP_NAME . '!',
                'order'
            );
            flash('success', 'Order #' . substr($delivery['order_id'], -6) . ' marked as delivered.');
        } else {
            db_execute('UPDATE deliveries SET status = ? WHERE id = ?', [$newStatus, $deliveryId]);

            $msgs = [
                'picked_up'  => 'Your order #%s is picked up and on the way.',
                'in_transit' => 'Your order #%s is out for delivery and will reach you shortly.',
            ];
            $custMsg = sprintf(
                $msgs[$newStatus] ?? 'Your order #%s delivery status was updated.',
                substr($delivery['order_id'], -6)
            );
            notify($delivery['user_id'], 'Delivery Update: ' . ucfirst(str_replace('_', ' ', $newStatus)), $custMsg, 'order');
            flash('success', 'Delivery status updated to ' . ucfirst(str_replace('_', ' ', $newStatus)) . '.');
        }
    }
    redirect(APP_URL . '/delivery/dashboard.php');
}

// ─── Stat cards ───────────────────────────────────────────────────────
$assignedCount = (int) db_scalar(
    "SELECT COUNT(*) FROM deliveries WHERE partner_id = ? AND status = 'assigned'",
    [$uid]
);
$transitCount = (int) db_scalar(
    "SELECT COUNT(*) FROM deliveries WHERE partner_id = ? AND status = 'in_transit'",
    [$uid]
);
$deliveredToday = (int) db_scalar(
    "SELECT COUNT(*) FROM deliveries WHERE partner_id = ? AND status = 'delivered' AND DATE(completed_at) = ?",
    [$uid, $today]
);
$totalDelivered = (int) db_scalar(
    "SELECT COUNT(*) FROM deliveries WHERE partner_id = ? AND status = 'delivered'",
    [$uid]
);

// ─── Active deliveries ────────────────────────────────────────────────
$active = db_select(
    "SELECT d.id, d.status, d.estimated_at, d.notes, d.proof_image,
            o.id AS order_id, o.total, o.address,
            u.name AS customer_name, u.phone
       FROM deliveries d
       JOIN orders o ON o.id = d.order_id
       JOIN users  u ON u.id = o.user_id
      WHERE d.partner_id = ? AND d.status IN ('assigned','picked_up','in_transit')
      ORDER BY FIELD(d.status,'assigned','picked_up','in_transit'), d.created_at ASC",
    [$uid]
);

// ─── Pending assignments (info only) ──────────────────────────────────
$pendingAssignments = (int) db_scalar(
    "SELECT COUNT(*) FROM orders o
      WHERE o.status = 'confirmed'
        AND NOT EXISTS (SELECT 1 FROM deliveries d WHERE d.order_id = o.id)"
);

$pageTitle = 'Delivery Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<!-- Welcome header -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 fade-in">
    <div>
        <h1 class="h3 mb-1 fw-bold text-purple">
            <i class="fas fa-truck me-2"></i>Welcome, <?= e($u['name']) ?>!
        </h1>
        <p class="text-muted mb-0">
            <?= e(fmt_date($today)) ?> · You have
            <strong class="text-purple"><?= $assignedCount ?></strong> active assignment<?= $assignedCount === 1 ? '' : 's' ?>
            and <strong class="text-primary"><?= $transitCount ?></strong> in transit.
        </p>
    </div>
    <div class="mt-2 mt-md-0">
        <a href="<?= APP_URL ?>/delivery/assigned.php" class="btn btn-grad btn-sm">
            <i class="fas fa-clipboard-list me-1"></i>All Assignments
        </a>
    </div>
</div>

<!-- Stat cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card bg-grad-purple h-100">
            <div class="stat-value"><?= $assignedCount ?></div>
            <div class="stat-label">Assigned</div>
            <i class="fas fa-clipboard-list stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card bg-grad-blue h-100">
            <div class="stat-value"><?= $transitCount ?></div>
            <div class="stat-label">In Transit</div>
            <i class="fas fa-truck-loading stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card bg-grad-teal h-100">
            <div class="stat-value"><?= $deliveredToday ?></div>
            <div class="stat-label">Delivered Today</div>
            <i class="fas fa-check-double stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card bg-grad-amber h-100">
            <div class="stat-value"><?= $totalDelivered ?></div>
            <div class="stat-label">Total Delivered</div>
            <i class="fas fa-trophy stat-icon"></i>
        </div>
    </div>
</div>

<!-- Pending assignments info -->
<?php if ($pendingAssignments > 0): ?>
<div class="alert alert-info d-flex align-items-center" role="alert">
    <i class="fas fa-info-circle me-2 fs-5"></i>
    <div>
        <strong><?= $pendingAssignments ?></strong> order<?= $pendingAssignments === 1 ? '' : 's' ?> awaiting delivery assignment.
        <span class="text-muted">The admin team will assign these shortly — they will appear here automatically once assigned.</span>
    </div>
</div>
<?php endif; ?>

<!-- Active deliveries -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-truck-fast me-2 text-purple"></i>Active Deliveries</span>
        <span class="badge bg-purple-soft text-purple"><?= count($active) ?> active</span>
    </div>
    <div class="card-body p-0">
        <?php if (!$active): ?>
            <div class="empty-state">
                <i class="fas fa-truck-ramp-box"></i>
                <h5 class="mt-2">No active deliveries</h5>
                <p class="mb-0">New assignments will show up here automatically.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead><tr>
                    <th>Order</th><th>Customer</th><th>Address</th>
                    <th>Est. Time</th><th>Status</th><th class="text-end">Action</th>
                </tr></thead>
                <tbody>
                <?php foreach ($active as $d): ?>
                <tr>
                    <td>
                        <span class="fw-600 text-purple">#<?= e(substr($d['order_id'], -6)) ?></span>
                        <small class="text-muted d-block"><?= money($d['total']) ?></small>
                    </td>
                    <td>
                        <div class="fw-600"><?= e($d['customer_name']) ?></div>
                        <small class="text-muted"><i class="fas fa-phone me-1"></i><?= e($d['phone']) ?></small>
                    </td>
                    <td class="small" style="max-width:280px;">
                        <?= nl2br(e(mb_strimwidth($d['address'] ?? '—', 0, 80, '…'))) ?>
                    </td>
                    <td class="small">
                        <?= $d['estimated_at'] ? e(fmt_datetime($d['estimated_at'])) : '<span class="text-muted">—</span>' ?>
                    </td>
                    <td><?= status_badge($d['status']) ?></td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end flex-wrap">
                        <?php if ($d['status'] === 'assigned'): ?>
                            <form method="post" action="<?= APP_URL ?>/delivery/dashboard.php" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="pick_up">
                                <input type="hidden" name="delivery_id" value="<?= e($d['id']) ?>">
                                <button class="btn btn-sm btn-primary">
                                    <i class="fas fa-hand-paper me-1"></i>Picked Up
                                </button>
                            </form>
                        <?php elseif ($d['status'] === 'picked_up'): ?>
                            <form method="post" action="<?= APP_URL ?>/delivery/dashboard.php" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="out_for_del">
                                <input type="hidden" name="delivery_id" value="<?= e($d['id']) ?>">
                                <button class="btn btn-sm btn-primary">
                                    <i class="fas fa-truck me-1"></i>Out for Delivery
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if (in_array($d['status'], ['picked_up', 'in_transit'], true)): ?>
                            <button class="btn btn-sm btn-success"
                                    data-bs-toggle="modal" data-bs-target="#deliverModal"
                                    data-delivery-id="<?= e($d['id']) ?>"
                                    data-order-id="<?= e(substr($d['order_id'], -6)) ?>">
                                <i class="fas fa-check me-1"></i>Mark Delivered
                            </button>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Mark Delivered modal -->
<div class="modal fade" id="deliverModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
        <form method="post" action="<?= APP_URL ?>/delivery/dashboard.php" enctype="multipart/form-data" id="deliverForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="deliver">
            <input type="hidden" name="delivery_id" id="deliverModalDeliveryId">
            <div class="modal-header bg-grad-purple text-white">
                <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i>Mark Order Delivered</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Confirming delivery for order <strong class="text-purple">#<span id="deliverModalOrderShort">—</span></strong>.</p>
                <div class="mb-3">
                    <label class="form-label">Proof Photo <span class="text-danger">*</span></label>
                    <input type="file" name="proof_image" class="form-control" accept="image/*" required>
                    <small class="text-muted">Take a photo at the delivery location as proof (jpg/png, max 5MB).</small>
                </div>
                <div class="mb-2">
                    <label class="form-label">Notes (optional)</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Handed to neighbour, gate was locked..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-check me-1"></i>Confirm Delivery</button>
            </div>
        </form>
    </div>
  </div>
</div>

<script>
// Wire up the Mark Delivered modal — pass delivery id + order short id from trigger button
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('deliverModal');
    if (!modalEl) return;
    modalEl.addEventListener('show.bs.modal', function (e) {
        const trigger = e.relatedTarget;
        if (!trigger) return;
        const dId   = trigger.getAttribute('data-delivery-id') || '';
        const oShort = trigger.getAttribute('data-order-id') || '—';
        const fId  = document.getElementById('deliverModalDeliveryId');
        const fO   = document.getElementById('deliverModalOrderShort');
        if (fId)  fId.value  = dId;
        if (fO)   fO.textContent = oShort;
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
