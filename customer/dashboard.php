<?php
/**
 * customer/dashboard.php — Customer home page.
 *
 * Stats, quick actions, featured grooming services & products,
 * daily pet tip, and recent orders.
 */
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(ROLE_CUSTOMER);
$pageTitle = 'Dashboard';
include __DIR__ . '/../includes/header.php';

$uid = $u['id'];

// ─── Counts for the 4 stat cards ───────────────────────────────────
$petCount      = (int) db_scalar('SELECT COUNT(*) FROM pets WHERE user_id = ?', [$uid]);
$activeBook    = (int) db_scalar(
    "SELECT COUNT(*) FROM (
        SELECT id FROM grooming_bookings   WHERE user_id = ? AND status IN ('pending','confirmed')
        UNION ALL
        SELECT id FROM boarding_reservations WHERE user_id = ? AND status IN ('pending','confirmed')
        UNION ALL
        SELECT id FROM walking_bookings     WHERE user_id = ? AND status IN ('pending','confirmed')
     ) t",
    [$uid, $uid, $uid]
);
$cartN         = cart_count($uid);
$rewardPoints  = (int) $u['reward_points'];
$tier          = ucfirst($u['membership_tier'] ?? 'bronze');

// ─── Active grooming services (top 3) ──────────────────────────────
$services = db_select(
    'SELECT id, name, description, price, duration, category
       FROM grooming_services
      WHERE active = 1
      ORDER BY created_at ASC
      LIMIT 3'
);

// ─── Featured products (top 4) ─────────────────────────────────────
$products = db_select(
    'SELECT id, name, price, original_price, rating, review_count, category, in_stock, stock_qty, image
       FROM products
      WHERE featured = 1 AND in_stock = 1
      ORDER BY rating DESC
      LIMIT 4'
);

// ─── Recent orders (last 3) ────────────────────────────────────────
$recentOrders = db_select(
    'SELECT id, total, status, created_at
       FROM orders
      WHERE user_id = ?
      ORDER BY created_at DESC
      LIMIT 3',
    [$uid]
);

// wishlist ids (for the heart UI state)
$wishIds = [];
$wishRows = db_select('SELECT product_id FROM wishlist_items WHERE user_id = ?', [$uid]);
foreach ($wishRows as $r) $wishIds[$r['product_id']] = true;

// ─── Daily pet tips (rotates by day-of-year) ───────────────────────
$tips = [
    'Always provide fresh, clean water for your pet — change it at least twice a day.',
    'A short 15-minute walk every morning keeps your dog physically and mentally fit.',
    'Cats love routine — feed them at the same times each day to reduce stress.',
    'Brush your pet\'s coat weekly to reduce shedding and spot skin issues early.',
    'Keep toxic foods like chocolate, grapes and onions out of paws\' reach.',
    'Schedule an annual vet check-up even if your pet looks perfectly healthy.',
    'Mental stimulation (puzzle toys, training) tires a dog out as much as a walk.',
    'Trim nails regularly — overgrown nails cause pain and posture issues.',
    'In summer, walk dogs early morning or late evening to avoid burnt paws.',
];
$tipIndex = ((int) date('z')) % count($tips);
$dailyTip = $tips[$tipIndex];
?>

<!-- Welcome header -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 fade-in">
    <div>
        <h1 class="h3 mb-1 fw-bold text-purple">Hello, <?= e($u['name']) ?>! <i class="fas fa-paw"></i></h1>
        <p class="text-muted mb-0">Welcome back to <?= e(APP_NAME) ?>. Here's what's happening with your pets today.</p>
    </div>
    <div class="d-flex gap-2 mt-2 mt-md-0">
        <span class="badge bg-purple-soft text-purple px-3 py-2 fs-6">
            <i class="fas fa-crown me-1"></i><?= e($tier) ?> Member
        </span>
        <span class="badge bg-grad-amber px-3 py-2 fs-6">
            <i class="fas fa-gift me-1"></i><?= $rewardPoints ?> pts
        </span>
    </div>
</div>

<!-- Stat cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card bg-grad-purple h-100">
            <div class="stat-value"><?= $petCount ?></div>
            <div class="stat-label">My Pets</div>
            <i class="fas fa-paw stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card bg-grad-pink h-100">
            <div class="stat-value"><?= $activeBook ?></div>
            <div class="stat-label">Active Bookings</div>
            <i class="fas fa-calendar-check stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card bg-grad-teal h-100">
            <div class="stat-value"><?= $cartN ?></div>
            <div class="stat-label">Cart Items</div>
            <i class="fas fa-shopping-cart stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card bg-grad-amber h-100">
            <div class="stat-value"><?= $rewardPoints ?></div>
            <div class="stat-label">Reward Points</div>
            <i class="fas fa-gift stat-icon"></i>
        </div>
    </div>
</div>

<!-- Quick actions -->
<h2 class="section-title">Quick Actions</h2>
<div class="row g-3 mb-4">
    <?php
    $actions = [
        ['bookings.php?tab=grooming', 'bg-grad-purple', 'fa-scissors', 'Book Grooming'],
        ['bookings.php?tab=boarding',  'bg-grad-teal',   'fa-home',      'Board Your Pet'],
        ['bookings.php?tab=walking',   'bg-grad-blue',   'fa-walking',   'Pet Walking'],
        ['shop.php',                    'bg-grad-pink',   'fa-shopping-bag','Shop Now'],
        ['orders.php',                  'bg-grad-amber',  'fa-box',        'My Orders'],
        ['pets.php',                    'bg-grad-green',  'fa-paw',        'My Pets'],
    ];
    foreach ($actions as [$href, $grad, $icon, $label]):
    ?>
    <div class="col-6 col-md-4 col-lg-2">
        <a href="<?= APP_URL ?>/customer/<?= e($href) ?>" class="card text-decoration-none h-100 text-center hover-shadow">
            <div class="card-body d-flex flex-column align-items-center justify-content-center py-4">
                <div class="stat-card <?= $grad ?> mb-2" style="width:54px;height:54px;padding:0;display:flex;align-items:center;justify-content:center;border-radius:14px;box-shadow:none;">
                    <i class="fas <?= $icon ?> fa-lg"></i>
                </div>
                <div class="fw-600 text-dark small"><?= e($label) ?></div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <!-- Left column: services + products -->
    <div class="col-lg-8">

        <!-- Grooming services -->
        <h2 class="section-title">Grooming Services</h2>
        <div class="row g-3 mb-4">
            <?php if (!$services): ?>
                <div class="col-12">
                    <div class="card"><div class="card-body empty-state">
                        <i class="fas fa-scissors"></i>
                        <p class="mb-0">No services available right now.</p>
                    </div></div>
                </div>
            <?php else: foreach ($services as $s): ?>
                <div class="col-md-4">
                    <div class="card service-card h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title mb-0"><?= e($s['name']) ?></h5>
                                <span class="badge bg-purple-soft text-purple"><?= e($s['category']) ?></span>
                            </div>
                            <p class="text-muted small flex-grow-1"><?= e(mb_strimwidth($s['description'], 0, 90, '…')) ?></p>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <div>
                                    <span class="fw-bold text-purple fs-5"><?= money($s['price']) ?></span>
                                    <small class="text-muted d-block"><?= (int)$s['duration'] ?> min</small>
                                </div>
                                <a href="<?= APP_URL ?>/customer/bookings.php?tab=grooming&service=<?= e($s['id']) ?>" class="btn btn-grad btn-sm">
                                    Book Now <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- Featured products -->
        <h2 class="section-title">Featured Products</h2>
        <div class="row g-3 mb-4">
            <?php if (!$products): ?>
                <div class="col-12"><div class="card"><div class="card-body empty-state">
                    <i class="fas fa-box-open"></i>
                    <p class="mb-0">No featured products at the moment.</p>
                </div></div></div>
            <?php else: foreach ($products as $p):
                $wished = isset($wishIds[$p['id']]);
            ?>
                <div class="col-sm-6 col-lg-3">
                    <div class="card product-card h-100">
                        <div class="product-thumb position-relative overflow-hidden">
                            <?php if (!empty($p['image']) && file_exists(UPLOAD_DIR . 'products/' . $p['image'])): ?>
                                <img src="<?= UPLOAD_URL ?>products/<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" class="w-100 h-100 object-fit-cover">
                            <?php else: ?>
                                <i class="fas fa-paw"></i>
                            <?php endif; ?>
                            <span class="badge bg-grad-amber position-absolute top-0 start-0 m-2">Featured</span>
                            <button class="btn btn-light btn-sm position-absolute top-0 end-0 m-2 rounded-circle"
                                    data-wishlist-toggle="<?= e($p['id']) ?>"
                                    title="Wishlist">
                                <i class="<?= $wished ? 'fas text-danger' : 'far' ?> fa-heart"></i>
                            </button>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <h6 class="card-title mb-1"><?= e($p['name']) ?></h6>
                            <div class="small mb-2"><?= star_html($p['rating']) ?> <span class="text-muted">(<?= (int)$p['review_count'] ?>)</span></div>
                            <div class="mb-3">
                                <span class="fw-bold text-purple"><?= money($p['price']) ?></span>
                                <?php if (!empty($p['original_price']) && (float)$p['original_price'] > (float)$p['price']): ?>
                                    <span class="text-muted text-decoration-line-through ms-2 small"><?= money($p['original_price']) ?></span>
                                <?php endif; ?>
                            </div>
                            <button class="btn btn-grad btn-sm mt-auto" data-cart-add="<?= e($p['id']) ?>">
                                <i class="fas fa-cart-plus me-1"></i> Add to Cart
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- Right column: tip + recent orders -->
    <div class="col-lg-4">

        <!-- Daily pet tip -->
        <div class="card bg-grad-purple text-white mb-4">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-lightbulb me-2"></i>Daily Pet Tip</h5>
                <p class="mb-0"><?= e($dailyTip) ?></p>
            </div>
        </div>

        <!-- Recent orders -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-clock-rotate-left me-2 text-purple"></i>Recent Orders</span>
                <a href="<?= APP_URL ?>/customer/orders.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (!$recentOrders): ?>
                    <div class="empty-state">
                        <i class="fas fa-box"></i>
                        <p class="mb-0">No orders yet.</p>
                        <a href="<?= APP_URL ?>/customer/shop.php" class="btn btn-grad btn-sm mt-2">Start Shopping</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead><tr><th>Order</th><th>Date</th><th>Total</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($recentOrders as $o): ?>
                                <tr>
                                    <td><a href="<?= APP_URL ?>/customer/orders.php?id=<?= e($o['id']) ?>" class="text-purple fw-600">#<?= e(substr($o['id'], -6)) ?></a></td>
                                    <td class="small text-muted"><?= e(fmt_date(substr($o['created_at'], 0, 10))) ?></td>
                                    <td><?= money($o['total']) ?></td>
                                    <td><?= status_badge($o['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
