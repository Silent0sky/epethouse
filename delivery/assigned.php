<?php
/**
 * delivery/assigned.php — All deliveries assigned to this partner.
 *
 * - Filter by status
 * - All states listed (assigned / picked_up / in_transit / delivered)
 * - Status transitions (Picked Up / Out for Delivery) inline
 * - "Mark Delivered" opens a modal to upload proof photo + notes
 */
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(ROLE_DELIVERY);
$uid = $u['id'];

// ─── POST: status transitions (mirror of dashboard.php) ───────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action     = $_POST['action'] ?? '';
    $deliveryId = trim($_POST['delivery_id'] ?? '');

    $delivery = db_select_one(
        'SELECT d.id, d.order_id, d.status, d.partner_id, d.notes, d.proof_image,
                o.user_id
           FROM deliveries d
           JOIN orders o ON o.id = d.order_id
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
            $proofUrl = $delivery['proof_image'];
            $notes    = clean($_POST['notes'] ?? '');

            if (!empty($_FILES['proof_image']['name'])) {
                $up = upload_file('proof_image', 'delivery_proof');
                if ($up['ok']) $proofUrl = $up['url'];
                else { flash('danger', 'Proof photo upload failed: ' . $up['error']); redirect(APP_URL . '/delivery/assigned.php'); }
            } elseif (empty($proofUrl)) {
                flash('danger', 'A proof photo is required to mark as delivered.');
                redirect(APP_URL . '/delivery/assigned.php');
            }

            db_execute(
                'UPDATE deliveries SET status = ?, proof_image = ?, notes = ?, completed_at = ? WHERE id = ?',
                [$newStatus, $proofUrl, $notes ?: null, date('Y-m-d H:i:s'), $deliveryId]
            );
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
            $custMsg = sprintf($msgs[$newStatus] ?? 'Your order #%s delivery status was updated.', substr($delivery['order_id'], -6));
            notify($delivery['user_id'], 'Delivery Update: ' . ucfirst(str_replace('_', ' ', $newStatus)), $custMsg, 'order');
            flash('success', 'Delivery status updated to ' . ucfirst(str_replace('_', ' ', $newStatus)) . '.');
        }
    }
    redirect(APP_URL . '/delivery/assigned.php' . (!empty($_POST['qs']) ? '?' . $_POST['qs'] : ''));
}

// ─── Filter ───────────────────────────────────────────────────────────
$statusFilter = $_GET['status'] ?? '';
$validStatuses = ['assigned', 'picked_up', 'in_transit', 'delivered'];
if (!in_array($statusFilter, $validStatuses, true)) $statusFilter = '';

$where  = ['d.partner_id = ?'];
$params = [$uid];
if ($statusFilter !== '') { $where[] = 'd.status = ?'; $params[] = $statusFilter; }
$whereSql = implode(' AND ', $where);

$countSql = "SELECT COUNT(*) FROM deliveries d WHERE $whereSql";
$dataSql  = "SELECT d.id, d.status, d.estimated_at, d.completed_at, d.notes, d.proof_image, d.created_at,
                    o.id AS order_id, o.total, o.address,
                    u.name AS customer_name, u.phone
               FROM deliveries d
               JOIN orders o ON o.id = d.order_id
               JOIN users  u ON u.id = o.user_id
              WHERE $whereSql
              ORDER BY FIELD(d.status,'assigned','picked_up','in_transit','delivered'), d.created_at DESC";

$pg = paginate($countSql, $dataSql, $params, 15);
$deliveries = $pg['rows'];

$qs = http_build_query(array_filter(['status' => $statusFilter]));

$pageTitle = 'Assigned Orders';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="h3 fw-bold text-purple mb-0"><i class="fas fa-clipboard-list me-2"></i>Assigned Orders</h1>
    <a href="<?= APP_URL ?>/delivery/dashboard.php" class="btn btn-outline-primary btn-sm">
        <i class="fas fa-arrow-left me-1"></i>Dashboard
    </a>
</div>

<!-- Filter bar -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" action="<?= APP_URL ?>/delivery/assigned.php" class="d-flex gap-2 align-items-end flex-wrap">
            <div>
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    <?php foreach ($validStatuses as $s): ?>
                        <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($statusFilter !== ''): ?>
                <a href="<?= APP_URL ?>/delivery/assigned.php" class="btn btn-sm btn-outline-secondary">Reset</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (!$deliveries): ?>
            <div class="empty-state">
                <i class="fas fa-clipboard"></i>
                <h5 class="mt-2">No assignments yet</h5>
                <p class="mb-0">When the admin assigns orders to you, they will appear here.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead><tr>
                    <th>Order</th><th>Customer</th><th>Address</th>
                    <th>Est. Time</th><th>Status</th><th class="text-end">Action</th>
                </tr></thead>
                <tbody>
                <?php foreach ($deliveries as $d): ?>
                <tr>
                    <td>
                        <span class="fw-600 text-purple">#<?= e(substr($d['order_id'], -6)) ?></span>
                        <small class="text-muted d-block"><?= money($d['total']) ?></small>
                    </td>
                    <td>
                        <div class="fw-600"><?= e($d['customer_name']) ?></div>
                        <small class="text-muted"><i class="fas fa-phone me-1"></i>
                            <a href="tel:<?= e($d['phone']) ?>"><?= e($d['phone']) ?></a>
                        </small>
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
                            <form method="post" action="<?= APP_URL ?>/delivery/assigned.php" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="qs" value="<?= e($qs) ?>">
                                <input type="hidden" name="action" value="pick_up">
                                <input type="hidden" name="delivery_id" value="<?= e($d['id']) ?>">
                                <button class="btn btn-sm btn-primary"><i class="fas fa-hand-paper me-1"></i>Picked Up</button>
                            </form>
                        <?php elseif ($d['status'] === 'picked_up'): ?>
                            <form method="post" action="<?= APP_URL ?>/delivery/assigned.php" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="qs" value="<?= e($qs) ?>">
                                <input type="hidden" name="action" value="out_for_del">
                                <input type="hidden" name="delivery_id" value="<?= e($d['id']) ?>">
                                <button class="btn btn-sm btn-primary"><i class="fas fa-truck me-1"></i>Out for Delivery</button>
                            </form>
                        <?php endif; ?>

                        <?php if (in_array($d['status'], ['picked_up', 'in_transit'], true)): ?>
                            <button class="btn btn-sm btn-success"
                                    data-bs-toggle="modal" data-bs-target="#deliverModal"
                                    data-delivery-id="<?= e($d['id']) ?>"
                                    data-order-id="<?= e(substr($d['order_id'], -6)) ?>">
                                <i class="fas fa-check me-1"></i>Mark Delivered
                            </button>
                        <?php elseif ($d['status'] === 'delivered'): ?>
                            <a href="<?= APP_URL ?>/delivery/history.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-eye me-1"></i>View
                            </a>
                        <?php else: ?>
                            <span class="text-muted small">—</span>
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

<div class="mt-3">
    <?= pagination_links($pg, APP_URL . '/delivery/assigned.php') ?>
</div>

<!-- Mark Delivered modal -->
<div class="modal fade" id="deliverModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
        <form method="post" action="<?= APP_URL ?>/delivery/assigned.php" enctype="multipart/form-data" id="deliverForm">
            <?= csrf_field() ?>
            <input type="hidden" name="qs" value="<?= e($qs) ?>">
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
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('deliverModal');
    if (!modalEl) return;
    modalEl.addEventListener('show.bs.modal', function (e) {
        const trigger = e.relatedTarget;
        if (!trigger) return;
        const dId    = trigger.getAttribute('data-delivery-id') || '';
        const oShort = trigger.getAttribute('data-order-id') || '—';
        const fId    = document.getElementById('deliverModalDeliveryId');
        const fO     = document.getElementById('deliverModalOrderShort');
        if (fId) fId.value = dId;
        if (fO)  fO.textContent = oShort;
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
