<?php
/**
 * groomer/appointments.php — All upcoming grooming appointments.
 *
 * - List bookings with date >= today
 * - Filter by status (?status=)
 * - Search by date range (from / to)
 * - Action buttons drive the same status flow as the dashboard
 */
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(ROLE_GROOMER);
$uid = $u['id'];

$today = date('Y-m-d');

// ─── POST: status transitions (same as dashboard) ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action    = $_POST['action'] ?? '';
    $bookingId = trim($_POST['booking_id'] ?? '');

    $booking = db_select_one(
        'SELECT gb.id, gb.user_id, gb.status, gb.date, gb.time,
                u.name AS customer_name,
                p.name AS pet_name, s.name AS service_name
           FROM grooming_bookings gb
           JOIN users  u ON u.id  = gb.user_id
           JOIN pets   p ON p.id  = gb.pet_id
           JOIN grooming_services s ON s.id = gb.service_id
          WHERE gb.id = ? LIMIT 1',
        [$bookingId]
    );

    if (!$booking) {
        flash('danger', 'Booking not found.');
    } else {
        $newStatus = match ($action) {
            'accept'   => $booking['status'] === 'pending' ? 'confirmed' : $booking['status'],
            'decline'  => $booking['status'] === 'pending' ? 'cancelled' : $booking['status'],
            'start'    => $booking['status'] === 'confirmed' ? 'in_progress' : $booking['status'],
            'complete' => $booking['status'] === 'in_progress' ? 'completed' : $booking['status'],
            default    => null,
        };
        if ($newStatus === null || $newStatus === $booking['status']) {
            flash('warning', 'That action is not allowed for this booking right now.');
        } else {
            db_execute('UPDATE grooming_bookings SET status = ? WHERE id = ?', [$newStatus, $bookingId]);
            $msgs = [
                'confirmed'   => 'Your grooming appointment for %s (%s) on %s at %s has been CONFIRMED.',
                'cancelled'   => 'Your grooming appointment for %s (%s) on %s at %s has been DECLINED. Please book another slot.',
                'in_progress' => 'Your grooming session for %s (%s) has STARTED. Sit back and relax!',
                'completed'   => 'Your grooming session for %s (%s) is now COMPLETE. Thank you for choosing ' . APP_NAME . '!',
            ];
            $custMsg = sprintf(
                $msgs[$newStatus] ?? 'Your grooming booking status was updated.',
                $booking['pet_name'], $booking['service_name'],
                fmt_date($booking['date']), $booking['time']
            );
            notify($booking['user_id'], 'Grooming Update: ' . ucfirst(str_replace('_', ' ', $newStatus)), $custMsg, 'booking');
            flash('success', 'Booking status updated.');
        }
    }
    redirect(APP_URL . '/groomer/appointments.php' . (!empty($_POST['qs']) ? '?' . $_POST['qs'] : ''));
}

// ─── Filters ──────────────────────────────────────────────────────────
$statusFilter = $_GET['status'] ?? '';
$validStatuses = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];
if (!in_array($statusFilter, $validStatuses, true)) $statusFilter = '';

$from = $_GET['from'] ?? '';
$to   = $_GET['to']   ?? '';
if ($from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = '';
if ($to   !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = '';

// Build WHERE + params
$where  = ['gb.date >= ?'];
$params = [$today];
if ($statusFilter !== '') { $where[] = 'gb.status = ?'; $params[] = $statusFilter; }
if ($from !== '')         { $where[] = 'gb.date >= ?'; $params[] = $from; }
if ($to   !== '')         { $where[] = 'gb.date <= ?'; $params[] = $to; }
$whereSql = implode(' AND ', $where);

$dataSql = "SELECT gb.id, gb.date, gb.time, gb.status, gb.notes, gb.created_at,
                   u.name AS customer_name, u.phone,
                   p.name AS pet_name, p.species, p.breed,
                   s.name AS service_name, s.price, s.duration
              FROM grooming_bookings gb
              JOIN users  u ON u.id  = gb.user_id
              JOIN pets   p ON p.id  = gb.pet_id
              JOIN grooming_services s ON s.id = gb.service_id
             WHERE $whereSql
             ORDER BY gb.date ASC, gb.time ASC";

$countSql = "SELECT COUNT(*) FROM grooming_bookings gb WHERE $whereSql";

$pg = paginate($countSql, $dataSql, $params, 15);
$appointments = $pg['rows'];

// Persist filter query string for POST redirects
$qs = http_build_query(array_filter([
    'status' => $statusFilter, 'from' => $from, 'to' => $to,
]));

$pageTitle = 'Appointments';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="h3 fw-bold text-purple mb-0"><i class="fas fa-calendar-check me-2"></i>Appointments</h1>
    <a href="<?= APP_URL ?>/groomer/dashboard.php" class="btn btn-outline-primary btn-sm">
        <i class="fas fa-arrow-left me-1"></i>Dashboard
    </a>
</div>

<!-- Filter bar -->
<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= APP_URL ?>/groomer/appointments.php" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All statuses</option>
                    <?php foreach ($validStatuses as $s): ?>
                        <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-grad flex-fill"><i class="fas fa-filter me-1"></i>Filter</button>
                <a href="<?= APP_URL ?>/groomer/appointments.php" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (!$appointments): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h5 class="mt-2">No appointments match your filters</h5>
                <p class="mb-0">Try clearing the filters or check back later.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead><tr>
                    <th>Date / Time</th><th>Customer</th><th>Pet</th>
                    <th>Service</th><th>Notes</th><th>Status</th><th class="text-end">Action</th>
                </tr></thead>
                <tbody>
                <?php foreach ($appointments as $b): ?>
                <tr>
                    <td>
                        <div class="fw-600 text-purple"><?= e(fmt_date($b['date'])) ?></div>
                        <small class="text-muted"><i class="far fa-clock me-1"></i><?= e(date('g:i A', strtotime($b['time']))) ?></small>
                    </td>
                    <td>
                        <div class="fw-600"><?= e($b['customer_name']) ?></div>
                        <small class="text-muted"><i class="fas fa-phone me-1"></i><?= e($b['phone']) ?></small>
                    </td>
                    <td>
                        <div class="fw-600"><?= e($b['pet_name']) ?></div>
                        <small class="text-muted text-capitalize"><?= e($b['species']) ?> · <?= e($b['breed']) ?></small>
                    </td>
                    <td>
                        <div><?= e($b['service_name']) ?></div>
                        <small class="text-muted"><?= money($b['price']) ?> · <?= (int)$b['duration'] ?> min</small>
                    </td>
                    <td class="small text-muted">
                        <?= $b['notes'] ? e(mb_strimwidth($b['notes'], 0, 60, '…')) : '—' ?>
                    </td>
                    <td><?= status_badge($b['status']) ?></td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                        <?php if ($b['status'] === 'pending'): ?>
                            <form method="post" action="<?= APP_URL ?>/groomer/appointments.php" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="qs" value="<?= e($qs) ?>">
                                <input type="hidden" name="action" value="accept">
                                <input type="hidden" name="booking_id" value="<?= e($b['id']) ?>">
                                <button class="btn btn-sm btn-success" title="Accept"><i class="fas fa-check"></i></button>
                            </form>
                            <form method="post" action="<?= APP_URL ?>/groomer/appointments.php" class="d-inline"
                                  data-confirm-submit="Decline this booking?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="qs" value="<?= e($qs) ?>">
                                <input type="hidden" name="action" value="decline">
                                <input type="hidden" name="booking_id" value="<?= e($b['id']) ?>">
                                <button class="btn btn-sm btn-outline-danger" title="Decline"><i class="fas fa-times"></i></button>
                            </form>
                        <?php elseif ($b['status'] === 'confirmed'): ?>
                            <form method="post" action="<?= APP_URL ?>/groomer/appointments.php" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="qs" value="<?= e($qs) ?>">
                                <input type="hidden" name="action" value="start">
                                <input type="hidden" name="booking_id" value="<?= e($b['id']) ?>">
                                <button class="btn btn-sm btn-primary" title="Start session"><i class="fas fa-play"></i></button>
                            </form>
                        <?php elseif ($b['status'] === 'in_progress'): ?>
                            <form method="post" action="<?= APP_URL ?>/groomer/appointments.php" class="d-inline"
                                  data-confirm-submit="Mark as completed?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="qs" value="<?= e($qs) ?>">
                                <input type="hidden" name="action" value="complete">
                                <input type="hidden" name="booking_id" value="<?= e($b['id']) ?>">
                                <button class="btn btn-sm btn-success" title="Complete"><i class="fas fa-check"></i></button>
                            </form>
                        <?php else: ?>
                            <span class="text-muted small">—</span>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="mt-3">
    <?= pagination_links($pg, APP_URL . '/groomer/appointments.php') ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
