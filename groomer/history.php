<?php
/**
 * groomer/history.php — Past grooming bookings.
 *
 * Past = date < today OR status IN (completed, cancelled).
 * Paginated at 15 per page.
 */
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(ROLE_GROOMER);

$today = date('Y-m-d');

$whereSql  = "(gb.date < ? OR gb.status IN ('completed','cancelled'))";
$params    = [$today];

$countSql  = "SELECT COUNT(*) FROM grooming_bookings gb WHERE $whereSql";
$dataSql   = "SELECT gb.id, gb.date, gb.time, gb.status, gb.notes, gb.updated_at, gb.created_at,
                     u.name AS customer_name, u.phone,
                     p.name AS pet_name, p.species,
                     s.name AS service_name, s.price
                FROM grooming_bookings gb
                JOIN users  u ON u.id  = gb.user_id
                JOIN pets   p ON p.id  = gb.pet_id
                JOIN grooming_services s ON s.id = gb.service_id
               WHERE $whereSql
               ORDER BY gb.date DESC, gb.time DESC";

$pg = paginate($countSql, $dataSql, $params, 15);
$history = $pg['rows'];

$pageTitle = 'Grooming History';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="h3 fw-bold text-purple mb-0"><i class="fas fa-history me-2"></i>Grooming History</h1>
    <a href="<?= APP_URL ?>/groomer/appointments.php" class="btn btn-outline-primary btn-sm">
        <i class="fas fa-calendar-check me-1"></i>Upcoming
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (!$history): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h5 class="mt-2">No history yet</h5>
                <p class="mb-0">Completed and cancelled appointments will appear here.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead><tr>
                    <th>Date</th><th>Time</th><th>Customer</th>
                    <th>Pet</th><th>Service</th><th>Status</th>
                    <th>Last Updated</th>
                </tr></thead>
                <tbody>
                <?php foreach ($history as $b): ?>
                <tr>
                    <td class="fw-600 text-purple"><?= e(fmt_date($b['date'])) ?></td>
                    <td><?= e(date('g:i A', strtotime($b['time']))) ?></td>
                    <td>
                        <div class="fw-600"><?= e($b['customer_name']) ?></div>
                        <small class="text-muted"><i class="fas fa-phone me-1"></i><?= e($b['phone']) ?></small>
                    </td>
                    <td>
                        <div class="fw-600"><?= e($b['pet_name']) ?></div>
                        <small class="text-muted text-capitalize"><?= e($b['species']) ?></small>
                    </td>
                    <td>
                        <div><?= e($b['service_name']) ?></div>
                        <small class="text-muted"><?= money($b['price']) ?></small>
                    </td>
                    <td><?= status_badge($b['status']) ?></td>
                    <td class="small text-muted"><?= e(time_ago($b['updated_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="mt-3">
    <?= pagination_links($pg, APP_URL . '/groomer/history.php') ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
