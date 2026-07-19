<?php
/**
 * admin/orders.php — Orders management.
 *
 * Default: paginated list of all orders (with status filter), JOIN users.
 * ?id=<id>: detail view with customer info, items, totals, status update form
 *           and delivery-partner assignment (when status = confirmed and no
 *           delivery row exists yet).
 */
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(ROLE_ADMIN);

// ─── POST handler ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = clean($_POST['action'] ?? '');

    if ($action === 'update_status') {
        $orderId   = clean($_POST['order_id'] ?? '');
        $newStatus = clean($_POST['new_status'] ?? '');

        if (!in_array($newStatus, ['pending','confirmed','shipped','delivered','cancelled'], true)) {
            flash('danger', 'Invalid order status.');
            redirect(APP_URL . '/admin/orders.php');
        }

        $order = db_select_one('SELECT id, user_id FROM orders WHERE id = ? LIMIT 1', [$orderId]);
        if (!$order) {
            flash('danger', 'Order not found.');
            redirect(APP_URL . '/admin/orders.php');
        }

        db_execute('UPDATE orders SET status = ? WHERE id = ?', [$newStatus, $orderId]);
        notify(
            $order['user_id'],
            'Order #' . substr($orderId, -6) . ' updated',
            'Your order #' . substr($orderId, -6) . ' status: ' . ucfirst($newStatus) . '.',
            'order'
        );
        flash('success', 'Order #' . substr($orderId, -6) . ' status updated to ' . ucfirst($newStatus) . '.');
        redirect(APP_URL . '/admin/orders.php?id=' . urlencode($orderId));
    }

    if ($action === 'assign_delivery') {
        $orderId   = clean($_POST['order_id'] ?? '');
        $partnerId = clean($_POST['partner_id'] ?? '');

        if ($orderId === '' || $partnerId === '') {
            flash('danger', 'Order and delivery partner are required.');
            redirect(APP_URL . '/admin/orders.php');
        }

        $order = db_select_one('SELECT id, user_id, status FROM orders WHERE id = ? LIMIT 1', [$orderId]);
        if (!$order) {
            flash('danger', 'Order not found.');
            redirect(APP_URL . '/admin/orders.php');
        }

        $partner = db_select_one(
            'SELECT id, name FROM users WHERE id = ? AND role = ? AND active = 1 LIMIT 1',
            [$partnerId, ROLE_DELIVERY]
        );
        if (!$partner) {
            flash('danger', 'Delivery partner not found or inactive.');
            redirect(APP_URL . '/admin/orders.php?id=' . urlencode($orderId));
        }

        $existing = db_select_one('SELECT id FROM deliveries WHERE order_id = ? LIMIT 1', [$orderId]);
        if ($existing) {
            flash('warning', 'This order already has a delivery partner assigned.');
            redirect(APP_URL . '/admin/orders.php?id=' . urlencode($orderId));
        }

        db_insert('deliveries', [
            'id'     => gen_id('dl_'),
            'order_id' => $orderId,
            'partner_id' => $partnerId,
            'status' => 'assigned',
        ]);

        // Notify both partner and customer
        notify(
            $partnerId,
            'New delivery assigned',
            'You have been assigned order #' . substr($orderId, -6) . '. Please pick it up for delivery.',
            'delivery'
        );
        notify(
            $order['user_id'],
            'Delivery partner assigned',
            'A delivery partner has been assigned to your order #' . substr($orderId, -6) . '.',
            'order'
        );

        flash('success', 'Order #' . substr($orderId, -6) . ' assigned to ' . e($partner['name']) . '.');
        redirect(APP_URL . '/admin/orders.php?id=' . urlencode($orderId));
    }

    flash('danger', 'Unknown action.');
    redirect(APP_URL . '/admin/orders.php');
}

// ─── Detail view (?id=<id>) ─────────────────────────────────────────
$orderId = $_GET['id'] ?? '';
if ($orderId !== '') {
    $order = db_select_one(
        'SELECT o.*, u.name AS customer, u.email, u.phone
           FROM orders o
           JOIN users u ON u.id = o.user_id
          WHERE o.id = ? LIMIT 1',
        [$orderId]
    );
    if (!$order) {
        flash('warning', 'Order not found.');
        redirect(APP_URL . '/admin/orders.php');
    }

    $items = db_select(
        'SELECT oi.id, oi.quantity, oi.price, p.id AS product_id, p.name, p.image, p.category
           FROM order_items oi
           JOIN products p ON p.id = oi.product_id
          WHERE oi.order_id = ?
          ORDER BY oi.id ASC',
        [$order['id']]
    );

    $delivery = db_select_one(
        'SELECT d.*, u.name AS partner_name, u.phone AS partner_phone
           FROM deliveries d
           JOIN users u ON u.id = d.partner_id
          WHERE d.order_id = ? LIMIT 1',
        [$order['id']]
    );

    $deliveryPartners = [];
    if ($order['status'] === 'confirmed' && !$delivery) {
        $deliveryPartners = db_select(
            'SELECT id, name, phone FROM users WHERE role = ? AND active = 1 ORDER BY name ASC',
            [ROLE_DELIVERY]
        );
    }

    $pageTitle = 'Order #' . substr($order['id'], -8);
    include __DIR__ . '/../includes/header.php';
    ?>
    <!-- Detail header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 fade-in">
        <div>
            <h1 class="h3 mb-1 fw-bold text-purple">
                <a href="<?= APP_URL ?>/admin/orders.php" class="text-purple text-decoration-none">
                    <i class="fas fa-arrow-left me-2"></i>
                </a>
                Order #<?= e(substr($order['id'], -8)) ?>
            </h1>
            <p class="text-muted mb-0 small">
                Placed on <?= e(fmt_datetime($order['created_at'])) ?> · Payment: <?= e(strtoupper($order['payment_method'])) ?>
            </p>
        </div>
        <div>
            <?= status_badge($order['status']) ?>
        </div>
    </div>

    <div class="row g-3">
        <!-- Left column: items + status update -->
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header"><i class="fas fa-box me-2 text-purple"></i>Items (<?= count($items) ?>)</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>Product</th><th class="text-center">Qty</th><th class="text-end">Price</th><th class="text-end">Line Total</th></tr></thead>
                            <tbody>
                            <?php if (!$items): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">No items found.</td></tr>
                            <?php else: foreach ($items as $it): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <?php if (!empty($it['image'])): ?>
                                                <img src="<?= APP_URL . '/' . e($it['image']) ?>" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:8px;">
                                            <?php else: ?>
                                                <div style="width:44px;height:44px;border-radius:8px;background:var(--ph-purple-50);"
                                                     class="d-flex align-items-center justify-content-center">
                                                    <i class="fas fa-paw text-purple"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="fw-600"><?= e($it['name']) ?></div>
                                                <small class="text-muted text-uppercase"><?= e($it['category']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center"><?= (int)$it['quantity'] ?></td>
                                    <td class="text-end"><?= money($it['price']) ?></td>
                                    <td class="text-end fw-600"><?= money($it['price'] * $it['quantity']) ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Status update form -->
            <div class="card mb-3">
                <div class="card-header"><i class="fas fa-sync me-2 text-purple"></i>Update Status</div>
                <div class="card-body">
                    <form method="post" action="<?= APP_URL ?>/admin/orders.php" class="row g-2 align-items-end">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="order_id" value="<?= e($order['id']) ?>">
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="new_status" class="form-select">
                                <?php foreach (['pending','confirmed','shipped','delivered','cancelled'] as $st): ?>
                                    <option value="<?= e($st) ?>" <?= $st === $order['status'] ? 'selected' : '' ?>>
                                        <?= e(ucfirst($st)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-grad w-100">
                                <i class="fas fa-save me-1"></i>Update Status &amp; Notify Customer
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Delivery assignment -->
            <?php if ($delivery): ?>
                <div class="card">
                    <div class="card-header"><i class="fas fa-truck me-2 text-purple"></i>Delivery Partner</div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-600"><?= e($delivery['partner_name']) ?></div>
                                <small class="text-muted">
                                    <i class="fas fa-phone me-1"></i><?= e($delivery['partner_phone']) ?>
                                </small>
                            </div>
                            <?= status_badge($delivery['status']) ?>
                        </div>
                    </div>
                </div>
            <?php elseif ($order['status'] === 'confirmed'): ?>
                <div class="card border-purple">
                    <div class="card-header bg-purple-soft text-purple">
                        <i class="fas fa-user-plus me-2"></i>Assign Delivery Partner
                    </div>
                    <div class="card-body">
                        <?php if (!$deliveryPartners): ?>
                            <p class="mb-0 text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                No active delivery partners available. Add a user with role <strong>DELIVERY_PARTNER</strong> first.
                            </p>
                        <?php else: ?>
                            <form method="post" action="<?= APP_URL ?>/admin/orders.php" class="row g-2 align-items-end">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="assign_delivery">
                                <input type="hidden" name="order_id" value="<?= e($order['id']) ?>">
                                <div class="col-md-8">
                                    <label class="form-label">Choose delivery partner</label>
                                    <select name="partner_id" class="form-select" required>
                                        <option value="">— Select —</option>
                                        <?php foreach ($deliveryPartners as $dp): ?>
                                            <option value="<?= e($dp['id']) ?>">
                                                <?= e($dp['name']) ?> · <?= e($dp['phone']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-grad w-100">
                                        <i class="fas fa-check me-1"></i>Assign
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header"><i class="fas fa-truck me-2 text-purple"></i>Delivery Partner</div>
                    <div class="card-body text-muted small">
                        <i class="fas fa-info-circle me-1"></i>
                        Delivery assignment is available only when the order status is <strong>Confirmed</strong>.
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right column: customer + totals + shipping -->
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><i class="fas fa-user me-2 text-purple"></i>Customer</div>
                <div class="card-body">
                    <div class="fw-600"><?= e($order['customer']) ?></div>
                    <div class="small text-muted mt-1">
                        <i class="fas fa-envelope me-1"></i><?= e($order['email']) ?>
                    </div>
                    <div class="small text-muted">
                        <i class="fas fa-phone me-1"></i><?= e($order['phone']) ?>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="fas fa-receipt me-2 text-purple"></i>Order Summary</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><span class="fw-600"><?= money($order['subtotal']) ?></span></div>
                    <?php if ((float)$order['discount'] > 0): ?>
                    <div class="d-flex justify-content-between mb-2 text-success"><span>Discount</span><span class="fw-600">−<?= money($order['discount']) ?></span></div>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between mb-2"><span>Tax</span><span class="fw-600"><?= money($order['tax']) ?></span></div>
                    <hr>
                    <div class="d-flex justify-content-between fs-5 fw-bold">
                        <span>Total</span><span class="text-purple"><?= money($order['total']) ?></span>
                    </div>
                    <div class="mt-3">
                        <small class="text-muted d-block">Payment Method</small>
                        <span class="badge bg-purple-soft text-purple"><?= e(strtoupper($order['payment_method'])) ?></span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="fas fa-map-marker-alt me-2 text-purple"></i>Shipping Address</div>
                <div class="card-body">
                    <p class="mb-0 small"><?= nl2br(e($order['address'] ?? '—')) ?></p>
                </div>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/../includes/footer.php';
    exit;
}

// ─── List view (paginated, optional status filter) ──────────────────
$statusFilter = trim($_GET['status'] ?? '');
$allowedStatuses = ['pending','confirmed','shipped','delivered','cancelled'];
if ($statusFilter !== '' && !in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

$baseWhere = '';
$params = [];
if ($statusFilter !== '') {
    $baseWhere = ' WHERE o.status = ?';
    $params[] = $statusFilter;
}

$countSql = 'SELECT COUNT(*) FROM orders o' . $baseWhere;
$dataSql  = 'SELECT o.id, o.total, o.status, o.payment_method, o.created_at,
                    u.name AS customer,
                    (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
               FROM orders o
               JOIN users u ON u.id = o.user_id'
          . $baseWhere
          . ' ORDER BY o.created_at DESC';

$pg = paginate($countSql, $dataSql, $params, 15);
$orders = $pg['rows'];

$pageTitle = 'Orders';
include __DIR__ . '/../includes/header.php';
?>
<!-- Page header -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 fade-in">
    <div>
        <h1 class="h3 mb-1 fw-bold text-purple">
            <i class="fas fa-shopping-bag me-2"></i>Orders
        </h1>
        <p class="text-muted mb-0"><?= $pg['total'] ?> total orders<?= $statusFilter !== '' ? ' · filtered by ' . e(ucfirst($statusFilter)) : '' ?>.</p>
    </div>
    <form method="get" class="d-flex align-items-center gap-2 mt-2 mt-md-0">
        <label class="small text-muted mb-0">Status:</label>
        <select name="status" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
            <option value="">All</option>
            <?php foreach ($allowedStatuses as $st): ?>
                <option value="<?= e($st) ?>" <?= $st === $statusFilter ? 'selected' : '' ?>>
                    <?= e(ucfirst($st)) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ($statusFilter !== ''): ?>
            <a href="<?= APP_URL ?>/admin/orders.php" class="btn btn-link btn-sm text-muted">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Orders table -->
<div class="card">
    <div class="card-body p-0">
        <?php if (!$orders): ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <p class="mb-0">No orders found<?= $statusFilter !== '' ? ' for this status' : '' ?>.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0 table-hover">
                <thead><tr>
                    <th>Order #</th><th>Customer</th><th>Date</th>
                    <th class="text-center">Items</th><th class="text-end">Total</th>
                    <th>Payment</th><th>Status</th><th class="text-end">View</th>
                </tr></thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td class="fw-600 text-purple">#<?= e(substr($o['id'], 0, 8)) ?></td>
                        <td><?= e($o['customer']) ?></td>
                        <td class="small"><?= e(fmt_date($o['created_at'])) ?></td>
                        <td class="text-center"><?= (int)$o['item_count'] ?></td>
                        <td class="text-end fw-600"><?= money($o['total']) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= e(strtoupper($o['payment_method'])) ?></span></td>
                        <td><?= status_badge($o['status']) ?></td>
                        <td class="text-end">
                            <a href="<?= APP_URL ?>/admin/orders.php?id=<?= e($o['id']) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye me-1"></i>View
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($pg['pages'] > 1): ?>
<div class="mt-3">
    <?= pagination_links($pg, APP_URL . '/admin/orders.php') ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
