<?php
/**
 * index.php — Public landing page. Redirects to dashboard if logged in,
 * otherwise shows the marketing landing.
 */
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(dashboard_url(current_user()['role']));
}

$services = db_select('SELECT * FROM grooming_services WHERE active = 1 ORDER BY price ASC LIMIT 3');
$products = db_select('SELECT * FROM products WHERE in_stock = 1 ORDER BY featured DESC, rating DESC LIMIT 4');
$pageTitle = 'Pet Shop & Pet Salon in ' . APP_CITY;
include __DIR__ . '/includes/header.php';
?>
<!-- Hero -->
<section class="hero text-center">
    <div class="container">
        <div class="ph-logo mx-auto mb-3" style="width:72px;height:72px;font-size:2rem;background:rgba(255,255,255,0.2);border-radius:20px;display:flex;align-items:center;justify-content:center;">
            <i class="fas fa-paw"></i>
        </div>
        <h1 class="display-4 fw-bold"><?= e(APP_NAME) ?></h1>
        <p class="lead mb-4"><?= e(APP_TAGLINE) ?> · <?= e(APP_CITY) ?></p>
        <div class="d-flex gap-2 justify-content-center flex-wrap">
            <a href="<?= APP_URL ?>/login.php" class="btn btn-light btn-lg px-4">
                <i class="fas fa-sign-in-alt me-1"></i> Login
            </a>
            <a href="<?= APP_URL ?>/register.php" class="btn btn-outline-light btn-lg px-4">
                <i class="fas fa-user-plus me-1"></i> Register
            </a>
        </div>
    </div>
</section>

<div class="container py-5">
    <!-- Quick features -->
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card h-100 text-center p-4 fade-in">
                <div class="mx-auto mb-3" style="width:64px;height:64px;background:var(--ph-purple-50);color:var(--ph-purple);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.8rem;">
                    <i class="fas fa-scissors"></i>
                </div>
                <h5>Grooming & Spa</h5>
                <p class="text-muted small mb-0">Professional grooming, baths, haircuts and relaxing spa treatments for dogs and cats.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 text-center p-4 fade-in">
                <div class="mx-auto mb-3" style="width:64px;height:64px;background:#fef3c7;color:#d97706;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.8rem;">
                    <i class="fas fa-hotel"></i>
                </div>
                <h5>Pet Boarding</h5>
                <p class="text-muted small mb-0">Safe, comfortable boarding rooms with AC, CCTV and daily meals while you're away.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 text-center p-4 fade-in">
                <div class="mx-auto mb-3" style="width:64px;height:64px;background:#dcfce7;color:#15803d;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:1.8rem;">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <h5>Pet Shop</h5>
                <p class="text-muted small mb-0">Food, toys, accessories and more — delivered to your doorstep across <?= e(APP_CITY) ?>.</p>
            </div>
        </div>
    </div>

    <!-- Featured services -->
    <h2 class="section-title">Popular Grooming Services</h2>
    <div class="row g-4 mb-5">
        <?php foreach ($services as $s): ?>
        <div class="col-md-4">
            <div class="card service-card h-100">
                <div class="card-body">
                    <span class="badge bg-purple-soft text-purple mb-2 text-uppercase"><?= e($s['category']) ?></span>
                    <h5 class="card-title"><?= e($s['name']) ?></h5>
                    <p class="text-muted small"><?= e($s['description']) ?></p>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <span class="h5 text-purple mb-0"><?= money($s['price']) ?></span>
                        <small class="text-muted"><i class="far fa-clock me-1"></i><?= (int)$s['duration'] ?> min</small>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Featured products -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="section-title mb-0">Featured Products</h2>
        <a href="<?= APP_URL ?>/login.php" class="btn btn-sm btn-outline-primary">Shop All <i class="fas fa-arrow-right ms-1"></i></a>
    </div>
    <div class="row g-4">
        <?php foreach ($products as $p): ?>
        <div class="col-md-3 col-sm-6">
            <div class="card product-card h-100">
                <div class="product-thumb position-relative overflow-hidden">
                    <?php if (!empty($p['image']) && file_exists(UPLOAD_DIR . 'products/' . $p['image'])): ?>
                        <img src="<?= UPLOAD_URL ?>products/<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" class="w-100 h-100 object-fit-cover">
                    <?php else: ?>
                        <i class="fas fa-paw"></i>
                    <?php endif; ?>
                </div>
                <div class="card-body d-flex flex-column">
                    <span class="badge bg-light text-dark align-self-start mb-2 text-uppercase"><?= e($p['category']) ?></span>
                    <h6 class="card-title"><?= e($p['name']) ?></h6>
                    <div class="mb-2 small"><?= star_html($p['rating']) ?> <span class="text-muted">(<?= (int)$p['review_count'] ?>)</span></div>
                    <div class="mt-auto d-flex align-items-center justify-content-between">
                        <span class="h6 text-purple mb-0"><?= money($p['price']) ?></span>
                        <?php if ($p['original_price']): ?>
                        <small class="text-muted text-decoration-line-through"><?= money($p['original_price']) ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="text-center mt-5">
        <a href="<?= APP_URL ?>/register.php" class="btn btn-grad btn-lg px-5">Get Started — It's Free</a>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
