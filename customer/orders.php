<?php
/**
 * customer/orders.php — Order history + order detail view.
 *
 * Default: paginated table of user's orders.
 * ?id=<id> : single order detail with items + status timeline.
 */
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(ROLE_CUSTOMER);
$uid = $u['id'];

$orderId = $_GET['id'] ?? '';

// ─── Detail view ────────────────────────────────────────────────────
if ($orderId !== '') {
    $order = db_select_one(
        'SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1',
        [$orderId, $uid]
    );
    if (!$order) {
        flash('warning', 'Order not found.');
        redirect(APP_URL . '/customer/orders.php');
    }
    $items = db_select(
        'SELECT oi.id, oi.quantity, oi.price, p.id AS product_id, p.name, p.image, p.category
           FROM order_items oi
           JOIN products p ON p.id = oi.product_id
          WHERE oi.order_id = ?',
        [$order['id']]
    );

    // Status timeline (visual only)
    $statusFlow = ['pending', 'confirmed', 'shipped', 'delivered'];
    $cancelled = $order['status'] === 'cancelled';
    $currentIdx = array_search($order['status'], $statusFlow, true);

    $pageTitle = 'Order #' . substr($order['id'], -6);
    include __DIR__ . '/../includes/header.php';
    ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <h1 class="h3 fw-bold text-purple mb-0">
            <a href="<?= APP_URL ?>/customer/orders.php" class="text-purple text-decoration-none">
                <i class="fas fa-arrow-left me-2"></i>
            </a>
            Order #<?= e(substr($order['id'], -6)) ?>
        </h1>
        <div>
            <?= status_badge($order['status']) ?>
            <span class="text-muted ms-2 small"><?= e(fmt_datetime($order['created_at'])) ?></span>
        </div>
    </div>

    <div class="row g-3">
        <!-- Left: items + status -->
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header"><i class="fas fa-box me-2 text-purple"></i>Items (<?= count($items) ?>)</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>Product</th><th class="text-center">Qty</th><th class="text-end">Price</th><th class="text-end">Total</th></tr></thead>
                            <tbody>
                            <?php foreach ($items as $it): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="product-thumb" style="width:48px;height:48px;font-size:1.2rem;border-radius:8px;flex-shrink:0;">
                                                <i class="fas fa-paw"></i>
                                            </div>
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
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Status timeline -->
            <div class="card">
                <div class="card-header"><i class="fas fa-route me-2 text-purple"></i>Order Status</div>
                <div class="card-body">
                    <?php if ($cancelled): ?>
                        <div class="alert alert-danger mb-0">
                            <i class="fas fa-times-circle me-1"></i>This order was cancelled.
                        </div>
                    <?php else: ?>
                    <div class="d-flex justify-content-between position-relative" id="timeline">
                        <?php
                        $steps = [
                            ['pending',   'fa-receipt',     'Placed'],
                            ['confirmed', 'fa-check-circle', 'Confirmed'],
                            ['shipped',   'fa-truck',        'Shipped'],
                            ['delivered', 'fa-house-circle', 'Delivered'],
                        ];
                        foreach ($steps as $i => [$key, $icon, $label]):
                            $done = $currentIdx !== false && $i <= $currentIdx;
                        ?>
                            <div class="text-center flex-fill">
                                <div class="mx-auto mb-2 d-flex align-items-center justify-content-center"
                                     style="width:48px;height:48px;border-radius:50%;
                                     background:<?= $done ? 'var(--ph-purple)' : 'var(--ph-purple-50)' ?>;
                                     color:<?= $done ? '#fff' : 'var(--ph-purple)' ?>;">
                                    <i class="fas <?= $icon ?>"></i>
                                </div>
                                <div class="small fw-600 <?= $done ? 'text-purple' : 'text-muted' ?>"><?= e($label) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: summary -->
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><i class="fas fa-receipt me-2 text-purple"></i>Payment Summary</div>
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
                        <span class="badge bg-purple-soft text-purple text-capitalize"><?= e($order['payment_method']) ?></span>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><i class="fas fa-map-marker-alt me-2 text-purple"></i>Delivery Address</div>
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

// ─── List view ──────────────────────────────────────────────────────
$pg = paginate(
    'SELECT COUNT(*) FROM orders WHERE user_id = ?',
    'SELECT id, total, subtotal, tax, discount, status, payment_method, created_at
       FROM orders WHERE user_id = ? ORDER BY created_at DESC',
    [$uid],
    10
);
$orders = $pg['rows'];

$pageTitle = 'My Orders';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="h3 fw-bold text-purple mb-0"><i class="fas fa-box me-2"></i>My Orders</h1>
    <a href="<?= APP_URL ?>/customer/shop.php" class="btn btn-outline-primary btn-sm">
        <i class="fas fa-shopping-bag me-1"></i>Continue Shopping
    </a>
</div>

<?php if (!$orders): ?>
    <div class="card"><div class="card-body empty-state">
        <i class="fas fa-box-open"></i>
        <h4>No orders yet</h4>
        <p class="mb-3">When you place your first order, it will appear here.</p>
        <a href="<?= APP_URL ?>/customer/shop.php" class="btn btn-grad">
            <i class="fas fa-shopping-bag me-1"></i>Start Shopping
        </a>
    </div></div>
<?php else: ?>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr>
                        <th>Order #</th><th>Date</th><th class="text-center">Items</th>
                        <th class="text-end">Total</th><th>Payment</th><th>Status</th><th></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($orders as $o):
                        $itemCount = (int) db_scalar('SELECT COUNT(*) FROM order_items WHERE order_id = ?', [$o['id']]);
                    ?>
                    <tr>
                        <td class="fw-600 text-purple">#<?= e(substr($o['id'], -6)) ?></td>
                        <td class="small"><?= e(fmt_datetime($o['created_at'])) ?></td>
                        <td class="text-center"><?= $itemCount ?></td>
                        <td class="text-end fw-600"><?= money($o['total']) ?></td>
                        <td><span class="badge bg-purple-soft text-purple text-capitalize"><?= e($o['payment_method']) ?></span></td>
                        <td><?= status_badge($o['status']) ?></td>
                        <td class="text-end">
                            <a href="<?= APP_URL ?>/customer/orders.php?id=<?= e($o['id']) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye me-1"></i>View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="mt-3">
        <?= pagination_links($pg, APP_URL . '/customer/orders.php') ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
