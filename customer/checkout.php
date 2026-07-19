<?php
/**
 * customer/checkout.php — Place an order from the cart.
 *
 * Validates cart not empty, lets user pick address + payment method,
 * then on POST creates the order, decrements product stock, clears the
 * cart, marks coupon usage, awards reward points and notifies the user.
 */
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(ROLE_CUSTOMER);

// ─── Load cart once (used by both GET and POST) ─────────────────────
$cartRows = db_select(
    'SELECT c.id AS cart_id, c.quantity,
            p.id AS product_id, p.name, p.price, p.stock_qty, p.in_stock
       FROM cart_items c
       JOIN products p ON p.id = c.product_id
      WHERE c.user_id = ?
      ORDER BY c.created_at ASC',
    [$u['id']]
);

if (!$cartRows) {
    flash('warning', 'Your cart is empty. Add some products first.');
    redirect(APP_URL . '/customer/cart.php');
}

$subtotal = 0.0;
foreach ($cartRows as $r) {
    $subtotal += (float)$r['price'] * (int)$r['quantity'];
}

// ─── Coupon (re-validate against current subtotal) ──────────────────
$coupon   = $_SESSION['coupon'] ?? null;
$discount = 0.0;
if ($coupon) {
    [$ok, $d, $err] = validate_coupon($coupon['code'], $subtotal);
    if (!$ok) {
        unset($_SESSION['coupon']);
        $coupon = null;
        flash('warning', 'Coupon no longer valid: ' . $err);
    } else {
        $discount = (float) $d;
    }
}
$taxable = max(0, $subtotal - $discount);
$tax     = round($taxable * TAX_RATE, 2);
$total   = $taxable + $tax;

// ─── POST: place order ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $address  = clean($_POST['address'] ?? '');
    $payment  = $_POST['payment_method'] ?? 'cod';
    if (!in_array($payment, ['cod', 'upi', 'card'], true)) $payment = 'cod';

    if ($address === '') {
        flash('danger', 'Please enter a delivery address.');
        redirect(APP_URL . '/customer/checkout.php');
    }

    // Re-check stock + cart snapshot (concurrency-safe inside a tx)
    $pdo = db();
    try {
        $pdo->beginTransaction();

        // Re-fetch current cart inside the transaction
        $stmt = $pdo->prepare(
            'SELECT c.id AS cart_id, c.quantity,
                    p.id AS product_id, p.name, p.price, p.stock_qty, p.in_stock
               FROM cart_items c
               JOIN products p ON p.id = c.product_id
              WHERE c.user_id = ?
              FOR UPDATE'
        );
        $stmt->execute([$u['id']]);
        $txCart = $stmt->fetchAll();

        if (!$txCart) {
            $pdo->rollBack();
            flash('warning', 'Your cart is empty.');
            redirect(APP_URL . '/customer/cart.php');
        }

        $orderId = gen_id('or_');
        $pdo->prepare(
            'INSERT INTO orders (id, user_id, total, subtotal, tax, discount, status, payment_method, address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $orderId, $u['id'], $total, $subtotal, $tax, $discount,
            'pending', $payment, $address,
        ]);

        // Insert order items and decrement stock
        $itemStmt = $pdo->prepare(
            'INSERT INTO order_items (id, order_id, product_id, quantity, price)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stockStmt = $pdo->prepare(
            'UPDATE products SET stock_qty = stock_qty - ?, in_stock = IF(stock_qty - ? <= 0, 0, in_stock) WHERE id = ?'
        );
        foreach ($txCart as $r) {
            if ((int)$r['stock_qty'] < (int)$r['quantity']) {
                $pdo->rollBack();
                flash('danger', 'Not enough stock for "' . e($r['name']) . '".');
                redirect(APP_URL . '/customer/cart.php');
            }
            $itemStmt->execute([
                gen_id('oi_'), $orderId, $r['product_id'],
                (int)$r['quantity'], (float)$r['price'],
            ]);
            $stockStmt->execute([
                (int)$r['quantity'], (int)$r['quantity'], $r['product_id'],
            ]);
        }

        // Clear cart for the user
        $pdo->prepare('DELETE FROM cart_items WHERE user_id = ?')->execute([$u['id']]);

        // Coupon usage_count++
        if ($coupon) {
            $pdo->prepare('UPDATE coupons SET usage_count = usage_count + 1 WHERE code = ?')
                ->execute([$coupon['code']]);
            unset($_SESSION['coupon']);
        }

        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('danger', 'Could not place order. Please try again.');
        redirect(APP_URL . '/customer/checkout.php');
    }

    // Reward points: 1 point per Rs.10 spent (rounded down)
    $earned = (int) floor($total / 10);
    if ($earned > 0) {
        reward_earn($u['id'], $earned, 'order', 'Points earned from order #' . substr($orderId, -6));
    }

    // Notify
    notify(
        $u['id'],
        'Order Placed Successfully',
        'Your order #' . substr($orderId, -6) . ' for ' . money($total) . ' has been placed. We will process it shortly.',
        'order'
    );

    flash('success', 'Order placed successfully! Order #' . substr($orderId, -6));
    redirect(APP_URL . '/customer/orders.php?success=1');
}

$pageTitle = 'Checkout';
include __DIR__ . '/../includes/header.php';

$defaultAddress = $u['address'] ?? '';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="h3 fw-bold text-purple mb-0"><i class="fas fa-credit-card me-2"></i>Checkout</h1>
    <a href="<?= APP_URL ?>/customer/cart.php" class="btn btn-outline-primary btn-sm">
        <i class="fas fa-arrow-left me-1"></i>Back to Cart
    </a>
</div>

<form method="post" action="<?= APP_URL ?>/customer/checkout.php">
    <?= csrf_field() ?>
    <div class="row g-3">
        <!-- Left: address + payment -->
        <div class="col-lg-8">

            <!-- Delivery address -->
            <div class="card mb-3">
                <div class="card-header"><i class="fas fa-map-marker-alt me-2 text-purple"></i>Delivery Address</div>
                <div class="card-body">
                    <label class="form-label">Full address (house, street, area, city, pincode)</label>
                    <textarea name="address" class="form-control" rows="4" required
                              placeholder="Flat 12, Green Residency, MG Road, C.Sambhajinagar - 431001"><?= e($defaultAddress) ?></textarea>
                    <small class="text-muted">We will deliver your order to this address.</small>
                </div>
            </div>

            <!-- Payment method -->
            <div class="card mb-3">
                <div class="card-header"><i class="fas fa-wallet me-2 text-purple"></i>Payment Method</div>
                <div class="card-body">
                    <div class="row g-2">
                        <?php
                        $methods = [
                            'cod' => ['fa-money-bill-wave', 'Cash on Delivery', 'Pay with cash when your order arrives.'],
                            'upi' => ['fa-mobile-screen',   'UPI',                'Pay via UPI (GPay / PhonePe / Paytm).'],
                            'card'=> ['fa-credit-card',     'Credit / Debit Card','Visa, Mastercard, RuPay accepted.'],
                        ];
                        foreach ($methods as $key => [$icon, $title, $desc]):
                        ?>
                        <div class="col-md-4">
                            <label class="card h-100 cursor-pointer payment-card" data-method="<?= e($key) ?>">
                                <div class="card-body text-center">
                                    <input type="radio" name="payment_method" value="<?= e($key) ?>"
                                           class="form-check-input d-none" <?= $key === 'cod' ? 'checked' : '' ?>>
                                    <i class="fas <?= $icon ?> fa-2x text-purple mb-2"></i>
                                    <div class="fw-600"><?= e($title) ?></div>
                                    <small class="text-muted d-block mt-1"><?= e($desc) ?></small>
                                </div>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Order items -->
            <div class="card">
                <div class="card-header"><i class="fas fa-box me-2 text-purple"></i>Order Items (<?= count($cartRows) ?>)</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead><tr><th>Product</th><th class="text-center">Qty</th><th class="text-end">Price</th></tr></thead>
                            <tbody>
                            <?php foreach ($cartRows as $r): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="product-thumb" style="width:44px;height:44px;font-size:1.2rem;border-radius:8px;flex-shrink:0;">
                                                <i class="fas fa-paw"></i>
                                            </div>
                                            <span class="fw-600"><?= e($r['name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="text-center"><?= (int)$r['quantity'] ?></td>
                                    <td class="text-end"><?= money($r['price']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: summary -->
        <div class="col-lg-4">
            <div class="card sticky-top" style="top:80px;">
                <div class="card-header"><i class="fas fa-receipt me-2 text-purple"></i>Order Summary</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal (<?= count($cartRows) ?> items)</span>
                        <span class="fw-600"><?= money($subtotal) ?></span>
                    </div>
                    <?php if ($coupon): ?>
                    <div class="d-flex justify-content-between mb-2 text-success">
                        <span>Discount (<?= e($coupon['code']) ?>)</span>
                        <span class="fw-600">−<?= money($discount) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Tax (<?= (int)(TAX_RATE * 100) ?>%)</span>
                        <span class="fw-600"><?= money($tax) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Delivery</span>
                        <?php if ($subtotal >= FREE_DELIVERY_MIN): ?>
                            <span class="badge bg-success">FREE</span>
                        <?php else: ?>
                            <span class="text-muted small">Calculated at next step</span>
                        <?php endif; ?>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between fs-5 fw-bold">
                        <span>Total</span><span class="text-purple"><?= money($total) ?></span>
                    </div>
                    <?php $pts = (int) floor($total / 10); if ($pts > 0): ?>
                    <div class="alert bg-purple-soft text-purple small mt-3 mb-0">
                        <i class="fas fa-gift me-1"></i>You'll earn <strong><?= $pts ?> reward points</strong> from this order.
                    </div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-grad w-100 mt-3">
                        <i class="fas fa-check-circle me-1"></i>Place Order
                    </button>
                    <p class="text-muted text-center small mt-2 mb-0">
                        <i class="fas fa-lock me-1"></i>Secure checkout
                    </p>
                </div>
            </div>
        </div>
    </div>
</form>

<style>
.payment-card { border: 2px solid #e6e0f5; transition: all 0.15s ease; }
.payment-card:hover { border-color: var(--ph-purple-light); }
.payment-card:has(input:checked) { border-color: var(--ph-purple); background: var(--ph-purple-50); }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
