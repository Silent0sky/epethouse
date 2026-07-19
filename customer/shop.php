<?php
/**
 * customer/shop.php — Product listing with search, filter, sort and pagination.
 */
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(ROLE_CUSTOMER);
$pageTitle = 'Pet Shop';
include __DIR__ . '/../includes/header.php';

// ─── Filters (GET) ──────────────────────────────────────────────────
$q        = trim($_GET['q']        ?? '');
$category = trim($_GET['category'] ?? '');
$sort     = trim($_GET['sort']     ?? 'newest');

$allowedCats = ['food', 'toys', 'accessories', 'grooming'];
if (!in_array($category, $allowedCats, true)) $category = '';

$allowedSort = ['newest', 'price_asc', 'price_desc', 'rating'];
if (!in_array($sort, $allowedSort, true)) $sort = 'newest';

// ─── Build WHERE clause safely ──────────────────────────────────────
$where  = 'WHERE in_stock = 1 ';
$params = [];
if ($q !== '') {
    $where .= ' AND (name LIKE ? OR description LIKE ?) ';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($category !== '') {
    $where .= ' AND category = ? ';
    $params[] = $category;
}

// Sort
$order = match ($sort) {
    'price_asc'  => 'price ASC',
    'price_desc' => 'price DESC',
    'rating'     => 'rating DESC, review_count DESC',
    default      => 'created_at DESC',
};

// ─── Paginate ───────────────────────────────────────────────────────
$countSql = 'SELECT COUNT(*) FROM products ' . $where;
$dataSql  = 'SELECT id, name, description, price, original_price, category, rating,
                    review_count, in_stock, stock_qty, featured, image
               FROM products ' . $where . ' ORDER BY ' . $order;

$pg = paginate($countSql, $dataSql, $params, 12);
$products = $pg['rows'];

// wishlist ids for current user (for heart state)
$wishIds = [];
foreach (db_select('SELECT product_id FROM wishlist_items WHERE user_id = ?', [$u['id']]) as $r) {
    $wishIds[$r['product_id']] = true;
}
?>

<!-- Page header + search bar -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 fw-bold text-purple mb-0"><i class="fas fa-shopping-bag me-2"></i>Pet Shop</h1>
        <p class="text-muted mb-0">Everything your pet needs, delivered to your door.</p>
    </div>
    <a href="<?= APP_URL ?>/customer/cart.php" class="btn btn-outline-primary btn-sm mt-2 mt-md-0">
        <i class="fas fa-shopping-cart me-1"></i>View Cart
    </a>
</div>

<!-- Filter bar -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="<?= APP_URL ?>/customer/shop.php" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small mb-1">Search</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Search products..."
                           value="<?= e($q) ?>">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Category</label>
                <select name="category" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach (['food'=>'Food','toys'=>'Toys','accessories'=>'Accessories','grooming'=>'Grooming'] as $k => $v): ?>
                        <option value="<?= e($k) ?>" <?= $category === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Sort</label>
                <select name="sort" class="form-select">
                    <option value="newest"     <?= $sort === 'newest'     ? 'selected' : '' ?>>Newest</option>
                    <option value="price_asc"  <?= $sort === 'price_asc'  ? 'selected' : '' ?>>Price: Low to High</option>
                    <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                    <option value="rating"     <?= $sort === 'rating'     ? 'selected' : '' ?>>Top Rated</option>
                </select>
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-grad"><i class="fas fa-filter me-1"></i>Apply</button>
            </div>
        </form>
        <?php if ($q || $category): ?>
            <div class="mt-2 small text-muted">
                Showing results for
                <?php if ($q): ?><strong>"<?= e($q) ?>"</strong><?php endif; ?>
                <?php if ($q && $category): ?> in <?php endif; ?>
                <?php if ($category): ?><strong><?= e(ucfirst($category)) ?></strong><?php endif; ?>
                — <a href="<?= APP_URL ?>/customer/shop.php" class="text-purple">Clear filters</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Product grid -->
<?php if (!$products): ?>
    <div class="card"><div class="card-body empty-state">
        <i class="fas fa-box-open"></i>
        <h5>No products found</h5>
        <p class="mb-0">Try adjusting your filters or search query.</p>
    </div></div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($products as $p):
            $wished = isset($wishIds[$p['id']]);
            $hasDiscount = !empty($p['original_price']) && (float)$p['original_price'] > (float)$p['price'];
            $discountPct = $hasDiscount
                ? round(100 - ((float)$p['price'] / (float)$p['original_price'] * 100))
                : 0;
        ?>
        <div class="col-sm-6 col-lg-3">
            <div class="card product-card h-100">
                <div class="product-thumb position-relative overflow-hidden">
                    <?php if (!empty($p['image']) && file_exists(UPLOAD_DIR . 'products/' . $p['image'])): ?>
                        <img src="<?= UPLOAD_URL ?>products/<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" class="w-100 h-100 object-fit-cover">
                    <?php else: ?>
                        <i class="fas fa-paw"></i>
                    <?php endif; ?>
                    <?php if ((int)$p['featured'] === 1): ?>
                        <span class="badge bg-grad-amber position-absolute top-0 start-0 m-2">Featured</span>
                    <?php endif; ?>
                    <?php if ($hasDiscount): ?>
                        <span class="badge bg-danger position-absolute bottom-0 start-0 m-2">-<?= $discountPct ?>%</span>
                    <?php endif; ?>
                    <button class="btn btn-light btn-sm position-absolute top-0 end-0 m-2 rounded-circle"
                            data-wishlist-toggle="<?= e($p['id']) ?>" title="Wishlist">
                        <i class="<?= $wished ? 'fas text-danger' : 'far' ?> fa-heart"></i>
                    </button>
                </div>
                <div class="card-body d-flex flex-column">
                    <div class="small text-muted text-uppercase mb-1"><?= e($p['category']) ?></div>
                    <h6 class="card-title mb-1"><?= e($p['name']) ?></h6>
                    <div class="small mb-2">
                        <?= star_html($p['rating']) ?>
                        <span class="text-muted">(<?= (int)$p['review_count'] ?>)</span>
                    </div>
                    <p class="text-muted small flex-grow-1 mb-3"><?= e(mb_strimwidth($p['description'], 0, 80, '…')) ?></p>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="fw-bold text-purple fs-5"><?= money($p['price']) ?></span>
                            <?php if ($hasDiscount): ?>
                                <span class="text-muted text-decoration-line-through ms-1 small"><?= money($p['original_price']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ((int)$p['stock_qty'] < 5): ?>
                            <span class="badge bg-warning text-dark">Only <?= (int)$p['stock_qty'] ?> left</span>
                        <?php endif; ?>
                    </div>
                    <button class="btn btn-grad btn-sm mt-auto" data-cart-add="<?= e($p['id']) ?>">
                        <i class="fas fa-cart-plus me-1"></i>Add to Cart
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <div class="mt-4">
        <?= pagination_links($pg, APP_URL . '/customer/shop.php') ?>
        <p class="text-center text-muted small mt-1">
            Showing <?= count($products) ?> of <?= $pg['total'] ?> products
        </p>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
