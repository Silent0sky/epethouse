<?php
/**
 * customer/cart.php — Shopping cart with quantity steppers, coupon
 * apply and order summary.
 */
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(ROLE_CUSTOMER);
$pageTitle = 'My Cart';

// ─── POST handlers (coupon apply / remove) ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'apply_coupon') {
        $code = strtoupper(trim($_POST['coupon_code'] ?? ''));
        $subtotal = (float) db_scalar(
            'SELECT COALESCE(SUM(c.quantity * p.price), 0)
               FROM cart_items c JOIN products p ON p.id = c.product_id
              WHERE c.user_id = ?',
            [$u['id']]
        );
        if ($code === '') {
            flash('warning', 'Please enter a coupon code.');
        } else {
            [$ok, $discount, $err] = validate_coupon($code, $subtotal);
            if ($ok) {
                $_SESSION['coupon'] = [
                    'code'     => $code,
                    'discount' => (float) $discount,
                ];
                flash('success', 'Coupon "' . e($code) . '" applied — you saved ' . money($discount) . '!');
            } else {
                unset($_SESSION['coupon']);
                flash('danger', $err);
            }
        }
        redirect(APP_URL . '/customer/cart.php');
    }

    if ($action === 'remove_coupon') {
        unset($_SESSION['coupon']);
        flash('info', 'Coupon removed.');
        redirect(APP_URL . '/customer/cart.php');
    }

    // Remove cart item (non-AJAX fallback)
    if ($action === 'remove_item') {
        db_execute(
            'DELETE FROM cart_items WHERE id = ? AND user_id = ?',
            [$_POST['cart_id'] ?? '', $u['id']]
        );
        flash('success', 'Item removed from cart.');
        redirect(APP_URL . '/customer/cart.php');
    }

    // Update quantity (non-AJAX fallback)
    if ($action === 'update_qty') {
        $cid = $_POST['cart_id'] ?? '';
        $qty = max(1, (int) ($_POST['quantity'] ?? 1));
        db_execute(
            'UPDATE cart_items SET quantity = ? WHERE id = ? AND user_id = ?',
            [$qty, $cid, $u['id']]
        );
        flash('success', 'Cart updated.');
        redirect(APP_URL . '/customer/cart.php');
    }
}

include __DIR__ . '/../includes/header.php';

// ─── Load cart rows ─────────────────────────────────────────────────
$cartRows = db_select(
    'SELECT c.id AS cart_id, c.quantity,
            p.id AS product_id, p.name, p.price, p.original_price,
            p.image, p.stock_qty, p.in_stock, p.category
       FROM cart_items c
       JOIN products p ON p.id = c.product_id
      WHERE c.user_id = ?
      ORDER BY c.created_at ASC',
    [$u['id']]
);

$subtotal = 0.0;
foreach ($cartRows as $r) {
    $subtotal += (float) $r['price'] * (int) $r['quantity'];
}

// coupon
$coupon   = $_SESSION['coupon'] ?? null;
$discount = $coupon['discount'] ?? 0;
// re-validate (coupon might have expired since applied)
if ($coupon) {
    [$ok, $newDisc, $err] = validate_coupon($coupon['code'], $subtotal);
    if (!$ok) {
        unset($_SESSION['coupon']);
        $coupon = null; $discount = 0;
        flash('warning', 'Coupon no longer valid: ' . $err);
    } else {
        $discount = $newDisc;
        $_SESSION['coupon']['discount'] = $newDisc;
    }
}

$tax      = round(max(0, $subtotal - $discount) * TAX_RATE, 2);
$total    = max(0, $subtotal - $discount) + $tax;
$freeDelivery = $subtotal >= FREE_DELIVERY_MIN;
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="h3 fw-bold text-purple mb-0"><i class="fas fa-shopping-cart me-2"></i>My Cart</h1>
    <a href="<?= APP_URL ?>/customer/shop.php" class="btn btn-outline-primary btn-sm">
        <i class="fas fa-arrow-left me-1"></i>Continue Shopping
    </a>
</div>

<?php if (!$cartRows): ?>
    <div class="card"><div class="card-body empty-state">
        <i class="fas fa-cart-arrow-down"></i>
        <h4 class="mt-2">Your cart is empty</h4>
        <p class="mb-3">Looks like you haven't added anything yet.</p>
        <a href="<?= APP_URL ?>/customer/shop.php" class="btn btn-grad">
            <i class="fas fa-shopping-bag me-1"></i>Start Shopping
        </a>
    </div></div>
<?php else: ?>

<div class="row g-3">
    <!-- Cart items -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-box me-2 text-purple"></i><?= count($cartRows) ?> item(s) in your cart
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="text-center">Price</th>
                                <th class="text-center">Quantity</th>
                                <th class="text-end">Subtotal</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cartRows as $r):
                                $lineTotal = (float)$r['price'] * (int)$r['quantity'];
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="product-thumb" style="width:56px;height:56px;font-size:1.4rem;border-radius:10px;flex-shrink:0;">
                                            <i class="fas fa-paw"></i>
                                        </div>
                                        <div>
                                            <div class="fw-600"><?= e($r['name']) ?></div>
                                            <small class="text-muted text-uppercase"><?= e($r['category']) ?></small>
                                            <?php if ((int)$r['stock_qty'] < (int)$r['quantity']): ?>
                                                <div class="badge bg-warning text-dark">Only <?= (int)$r['stock_qty'] ?> in stock</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="fw-600 text-purple"><?= money($r['price']) ?></div>
                                    <?php if (!empty($r['original_price']) && (float)$r['original_price'] > (float)$r['price']): ?>
                                        <div class="text-muted text-decoration-line-through small"><?= money($r['original_price']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="input-group input-group-sm" style="max-width:140px;margin:0 auto;">
                                        <button class="btn btn-outline-secondary" type="button"
                                                onclick="cartQtyStep('<?= e($r['cart_id']) ?>', -1)">−</button>
                                        <input type="text" class="form-control text-center" value="<?= (int)$r['quantity'] ?>"
                                               id="qty_<?= e($r['cart_id']) ?>" readonly>
                                        <button class="btn btn-outline-secondary" type="button"
                                                onclick="cartQtyStep('<?= e($r['cart_id']) ?>', 1)">+</button>
                                    </div>
                                </td>
                                <td class="text-end fw-600"><?= money($lineTotal) ?></td>
                                <td class="text-end">
                                    <form method="post" action="<?= APP_URL ?>/customer/cart.php" class="d-inline"
                                          data-confirm-submit="Remove this item from your cart?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="remove_item">
                                        <input type="hidden" name="cart_id" value="<?= e($r['cart_id']) ?>">
                                        <button class="btn btn-link text-danger p-0" title="Remove"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Order summary -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="fas fa-receipt me-2 text-purple"></i>Order Summary</div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Subtotal</span><span class="fw-600"><?= money($subtotal) ?></span>
                </div>
                <?php if ($coupon): ?>
                <div class="d-flex justify-content-between mb-2 text-success">
                    <span>Discount (<?= e($coupon['code']) ?>)</span><span class="fw-600">−<?= money($discount) ?></span>
                </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between mb-2">
                    <span>Tax (<?= (int)(TAX_RATE * 100) ?>%)</span><span class="fw-600"><?= money($tax) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Delivery</span>
                    <?php if ($freeDelivery): ?>
                        <span class="badge bg-success">FREE</span>
                    <?php else: ?>
                                        <span class="text-muted small">Free over <?= money(FREE_DELIVERY_MIN) ?></span>
                    <?php endif; ?>
                </div>
                <hr>
                <div class="d-flex justify-content-between fs-5 fw-bold">
                    <span>Total</span><span class="text-purple"><?= money($total) ?></span>
                </div>

                <!-- Coupon -->
                <?php if ($coupon): ?>
                    <form method="post" action="<?= APP_URL ?>/customer/cart.php" class="mt-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove_coupon">
                        <button class="btn btn-outline-danger btn-sm w-100">
                            <i class="fas fa-times me-1"></i>Remove coupon "<?= e($coupon['code']) ?>"
                        </button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?= APP_URL ?>/customer/cart.php" class="mt-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="apply_coupon">
                        <label class="form-label small mb-1">Have a coupon?</label>
                        <div class="input-group input-group-sm">
                            <input type="text" name="coupon_code" class="form-control" placeholder="WELCOME10" required>
                            <button class="btn btn-grad" type="submit">Apply</button>
                        </div>
                    </form>
                <?php endif; ?>

                <a href="<?= APP_URL ?>/customer/checkout.php" class="btn btn-grad w-100 mt-3">
                    <i class="fas fa-credit-card me-1"></i>Proceed to Checkout
                </a>
                <a href="<?= APP_URL ?>/customer/shop.php" class="btn btn-link w-100 mt-1 text-muted small">
                    Continue Shopping
                </a>
            </div>
        </div>
    </div>
</div>

<script>
/* Quantity stepper — uses AJAX endpoint then reloads the page. */
async function cartQtyStep(cartId, delta) {
    const input = document.getElementById('qty_' + cartId);
    if (!input) return;
    const newQty = Math.max(0, parseInt(input.value, 10) + delta);
    const r = await window.api(window.APP_URL + '/ajax/update_cart.php', {
        method: 'POST',
        body: { cart_id: cartId, quantity: newQty }
    });
    if (r.ok && r.data.success) {
        if (newQty <= 0) {
            // item was removed — reload
            location.reload();
        } else {
            // refresh totals (cheap reload)
            location.reload();
        }
    } else {
        window.showToast(r.data.message || 'Could not update cart', 'danger');
    }
}
</script>

<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
