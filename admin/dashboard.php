<?php
/**
 * admin/dashboard.php — Admin overview.
 *
 * Stat cards, 7-day revenue trend (Chart.js), recent orders, recent grooming
 * bookings and a low-stock alert list.
 */
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(ROLE_ADMIN);
$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';

// ─── KPIs ─────────────────────────────────────────────────────────────
$totalUsers = (int) db_scalar('SELECT COUNT(*) FROM users');
$ordersRow  = db_select_one(
    "SELECT COUNT(*) AS cnt, COALESCE(SUM(total),0) AS sum
       FROM orders"
);
$totalOrders     = (int) ($ordersRow['cnt'] ?? 0);
$totalOrdersSum  = (float) ($ordersRow['sum'] ?? 0);

$totalRevenue = (float) db_scalar(
    "SELECT COALESCE(SUM(total),0) FROM orders WHERE status IN ('delivered','completed')"
);

$activeBookings = (int) db_scalar(
    "SELECT COUNT(*) FROM (
        SELECT id FROM grooming_bookings    WHERE status IN ('pending','confirmed')
        UNION ALL
        SELECT id FROM boarding_reservations WHERE status IN ('pending','confirmed','active')
        UNION ALL
        SELECT id FROM walking_bookings     WHERE status IN ('pending','confirmed')
     ) t"
);

// ─── Revenue trend (last 7 days) ─────────────────────────────────────
$trendRows = db_select(
    "SELECT DATE(created_at) AS d, COALESCE(SUM(total),0) AS rev
       FROM orders
       WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
       GROUP BY DATE(created_at)
       ORDER BY d ASC"
);
// Fill any missing day with 0
$dayMap = [];
foreach ($trendRows as $r) {
    $dayMap[$r['d']] = (float) $r['rev'];
}
$trendLabels = [];
$trendValues = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} days"));
    $trendLabels[] = date('D, j M', strtotime($day));
    $trendValues[] = isset($dayMap[$day]) ? $dayMap[$day] : 0;
}

// ─── Recent orders (8) ───────────────────────────────────────────────
$recentOrders = db_select(
    'SELECT o.id, o.total, o.status, o.created_at, u.name AS customer
       FROM orders o
       JOIN users u ON u.id = o.user_id
      ORDER BY o.created_at DESC
      LIMIT 8'
);

// ─── Recent grooming bookings (8) ────────────────────────────────────
$recentBookings = db_select(
    'SELECT gb.id, gb.date, gb.time, gb.status, gb.created_at,
            u.name AS customer, p.name AS pet, p.species, gs.name AS service
       FROM grooming_bookings gb
       JOIN users u ON u.id = gb.user_id
       JOIN pets p ON p.id = gb.pet_id
       JOIN grooming_services gs ON gs.id = gb.service_id
      ORDER BY gb.created_at DESC
      LIMIT 8'
);

// ─── Low-stock products ──────────────────────────────────────────────
$lowStock = db_select(
    'SELECT id, name, category, stock_qty, price
       FROM products
      WHERE stock_qty < 10
      ORDER BY stock_qty ASC
      LIMIT 10'
);
?>
<!-- Page header -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 fade-in">
    <div>
        <h1 class="h3 mb-1 fw-bold text-purple">
            <i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard
        </h1>
        <p class="text-muted mb-0">Welcome back, <?= e($u['name']) ?>. Here's an overview of <?= e(APP_NAME) ?>.</p>
    </div>
    <div class="d-flex gap-2 mt-2 mt-md-0">
        <a href="<?= APP_URL ?>/admin/orders.php" class="btn btn-grad btn-sm">
            <i class="fas fa-shopping-bag me-1"></i> Manage Orders
        </a>
        <a href="<?= APP_URL ?>/admin/services.php" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-scissors me-1"></i> Services
        </a>
        <a href="<?= APP_URL ?>/admin/products.php" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-box me-1"></i> Products
        </a>
    </div>
</div>

<!-- Stat cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card bg-grad-purple h-100">
            <div class="stat-value"><?= $totalUsers ?></div>
            <div class="stat-label">Total Users</div>
            <i class="fas fa-users stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card bg-grad-pink h-100">
            <div class="stat-value"><?= $totalOrders ?></div>
            <div class="stat-label"><?= money($totalOrdersSum) ?> · Orders</div>
            <i class="fas fa-shopping-bag stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card bg-grad-teal h-100">
            <div class="stat-value"><?= money($totalRevenue) ?></div>
            <div class="stat-label">Total Revenue</div>
            <i class="fas fa-indian-rupee-sign stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card bg-grad-amber h-100">
            <div class="stat-value"><?= $activeBookings ?></div>
            <div class="stat-label">Active Bookings</div>
            <i class="fas fa-calendar-check stat-icon"></i>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Revenue chart -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-chart-line me-2 text-purple"></i>Revenue Trend (last 7 days)</span>
                <span class="badge bg-purple-soft text-purple"><?= money(array_sum($trendValues)) ?></span>
            </div>
            <div class="card-body">
                <canvas id="revChart" height="140"></canvas>
            </div>
        </div>
    </div>

    <!-- Low stock -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-exclamation-triangle me-2 text-warning"></i>Low Stock Alerts</span>
                <span class="badge bg-warning text-dark"><?= count($lowStock) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (!$lowStock): ?>
                    <div class="empty-state py-4">
                        <i class="fas fa-check-circle text-success"></i>
                        <p class="mb-0">All products are well stocked.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($lowStock as $p): ?>
                            <a href="<?= APP_URL ?>/admin/products.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-600"><?= e($p['name']) ?></div>
                                    <small class="text-muted text-uppercase"><?= e($p['category']) ?> · <?= money($p['price']) ?></small>
                                </div>
                                <span class="badge <?= $p['stock_qty'] <= 0 ? 'bg-danger' : 'bg-warning text-dark' ?>">
                                    <?= (int)$p['stock_qty'] ?> left
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Recent orders -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-shopping-bag me-2 text-purple"></i>Recent Orders</span>
                <a href="<?= APP_URL ?>/admin/orders.php" class="btn btn-sm btn-link text-purple text-decoration-none">View all →</a>
            </div>
            <div class="card-body p-0">
                <?php if (!$recentOrders): ?>
                    <div class="empty-state py-4"><i class="fas fa-box-open"></i><p class="mb-0">No orders yet.</p></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Order</th><th>Customer</th><th class="text-end">Total</th><th>Status</th><th>Date</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentOrders as $o): ?>
                            <tr>
                                <td><a href="<?= APP_URL ?>/admin/orders.php?id=<?= e($o['id']) ?>" class="fw-600 text-purple text-decoration-none">#<?= e(substr($o['id'], -6)) ?></a></td>
                                <td><?= e($o['customer']) ?></td>
                                <td class="text-end fw-600"><?= money($o['total']) ?></td>
                                <td><?= status_badge($o['status']) ?></td>
                                <td class="small text-muted"><?= e(time_ago($o['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent grooming bookings -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-scissors me-2 text-purple"></i>Recent Grooming Bookings</span>
                <a href="<?= APP_URL ?>/admin/bookings.php" class="btn btn-sm btn-link text-purple text-decoration-none">View all →</a>
            </div>
            <div class="card-body p-0">
                <?php if (!$recentBookings): ?>
                    <div class="empty-state py-4"><i class="fas fa-calendar-times"></i><p class="mb-0">No bookings yet.</p></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Service</th><th>Customer</th><th>Pet</th><th>Schedule</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentBookings as $b): ?>
                            <tr>
                                <td class="fw-600"><?= e($b['service']) ?></td>
                                <td><?= e($b['customer']) ?></td>
                                <td>
                                    <span class="text-muted"><i class="fas fa-paw me-1"></i></span>
                                    <?= e($b['pet']) ?>
                                    <small class="text-muted">(<?= e($b['species']) ?>)</small>
                                </td>
                                <td class="small"><?= e(fmt_date($b['date'])) ?><br><span class="text-muted"><?= e($b['time']) ?></span></td>
                                <td><?= status_badge($b['status']) ?></td>
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

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    const ctx = document.getElementById('revChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($trendLabels) ?>,
                datasets: [{
                    label: 'Revenue (₹)',
                    data: <?= json_encode($trendValues) ?>,
                    backgroundColor: 'rgba(124, 58, 237, 0.75)',
                    borderColor: 'rgba(91, 33, 182, 1)',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: v => '₹' + v } }
                }
            }
        });
    }
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
