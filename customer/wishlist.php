<?php
/**
 * customer/wishlist.php — Wishlist grid with Move-to-Cart and Remove.
 */
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(ROLE_CUSTOMER);
$uid = $u['id'];

// ─── POST handler (remove / move to cart fallback) ──────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'remove') {
        $pid = $_POST['product_id'] ?? '';
        db_execute(
            'DELETE FROM wishlist_items WHERE user_id = ? AND product_id = ?',
            [$uid, $pid]
        );
        flash('info', 'Removed from wishlist.');
        redirect(APP_URL . '/customer/wishlist.php');
    }

    if ($action === 'move_to_cart') {
        $pid = $_POST['product_id'] ?? '';
        $product = db_select_one(
            'SELECT id, in_stock, stock_qty FROM products WHERE id = ? LIMIT 1',
            [$pid]
        );
        if (!$product || !$product['in_stock'] || (int)$product['stock_qty'] <= 0) {
            flash('danger', 'Product is no longer available.');
            redirect(APP_URL . '/customer/wishlist.php');
        }
        // Upsert into cart
        $existing = db_select_one(
            'SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ?',
            [$uid, $pid]
        );
        if ($existing) {
            db_execute('UPDATE cart_items SET quantity = quantity + 1 WHERE id = ?', [$existing['id']]);
        } else {
            db_insert('cart_items', [
                'id'         => gen_id('ci_'),
                'user_id'    => $uid,
                'product_id' => $pid,
                'quantity'   => 1,
            ]);
        }
        // Remove from wishlist
        db_execute('DELETE FROM wishlist_items WHERE user_id = ? AND product_id = ?', [$uid, $pid]);
        flash('success', 'Moved to cart!');
        redirect(APP_URL . '/customer/wishlist.php');
    }
}

$pageTitle = 'Wishlist';
include __DIR__ . '/../includes/header.php';

$items = db_select(
    'SELECT w.id AS wish_id, w.created_at,
            p.id, p.name, p.description, p.price, p.original_price,
            p.category, p.rating, p.review_count, p.in_stock, p.stock_qty, p.featured
       FROM wishlist_items w
       JOIN products p ON p.id = w.product_id
      WHERE w.user_id = ?
      ORDER BY w.created_at DESC',
    [$uid]
);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="h3 fw-bold text-purple mb-0">
        <i class="fas fa-heart me-2"></i>My Wishlist
        <?php if ($items): ?>
            <span class="badge bg-purple-soft text-purple ms-1"><?= count($items) ?></span>
        <?php endif; ?>
    </h1>
    <a href="<?= APP_URL ?>/customer/shop.php" class="btn btn-outline-primary btn-sm">
        <i class="fas fa-shopping-bag me-1"></i>Browse More
    </a>
</div>

<?php if (!$items): ?>
    <div class="card"><div class="card-body empty-state">
        <i class="far fa-heart"></i>
        <h4>Your wishlist is empty</h4>
        <p class="mb-3">Tap the heart icon on any product to save it for later.</p>
        <a href="<?= APP_URL ?>/customer/shop.php" class="btn btn-grad">
            <i class="fas fa-shopping-bag me-1"></i>Discover Products
        </a>
    </div></div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($items as $p):
            $hasDiscount = !empty($p['original_price']) && (float)$p['original_price'] > (float)$p['price'];
        ?>
        <div class="col-sm-6 col-lg-3">
            <div class="card product-card h-100">
                <div class="product-thumb position-relative">
                    <i class="fas fa-paw"></i>
                    <?php if ((int)$p['featured'] === 1): ?>
                        <span class="badge bg-grad-amber position-absolute top-0 start-0 m-2">Featured</span>
                    <?php endif; ?>
                    <?php if (!$p['in_stock']): ?>
                        <span class="badge bg-danger position-absolute top-0 end-0 m-2">Out of stock</span>
                    <?php endif; ?>
                </div>
                <div class="card-body d-flex flex-column">
                    <div class="small text-muted text-uppercase mb-1"><?= e($p['category']) ?></div>
                    <h6 class="card-title mb-1"><?= e($p['name']) ?></h6>
                    <div class="small mb-2"><?= star_html($p['rating']) ?>
                        <span class="text-muted">(<?= (int)$p['review_count'] ?>)</span>
                    </div>
                    <div class="mb-3">
                        <span class="fw-bold text-purple fs-5"><?= money($p['price']) ?></span>
                        <?php if ($hasDiscount): ?>
                            <span class="text-muted text-decoration-line-through ms-1 small"><?= money($p['original_price']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-1 mt-auto">
                        <?php if ($p['in_stock']): ?>
                        <form method="post" action="<?= APP_URL ?>/customer/wishlist.php" class="flex-fill">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="move_to_cart">
                            <input type="hidden" name="product_id" value="<?= e($p['id']) ?>">
                            <button class="btn btn-grad btn-sm w-100">
                                <i class="fas fa-cart-plus me-1"></i>Move to Cart
                            </button>
                        </form>
                        <?php else: ?>
                            <button class="btn btn-secondary btn-sm flex-fill" disabled>
                                <i class="fas fa-ban me-1"></i>Unavailable
                            </button>
                        <?php endif; ?>
                        <form method="post" action="<?= APP_URL ?>/customer/wishlist.php"
                              data-confirm-submit="Remove from wishlist?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="product_id" value="<?= e($p['id']) ?>">
                            <button class="btn btn-outline-danger btn-sm" title="Remove">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
