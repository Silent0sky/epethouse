<?php
/**
 * admin/bookings.php — All bookings management (Grooming | Boarding | Walking).
 *
 * Each tab lists bookings with customer/pet joins and a per-row status update
 * form (POST). Status changes notify() the customer.
 */
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(ROLE_ADMIN);
$pageTitle = 'All Bookings';

// ─── POST: status update ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action     = clean($_POST['action'] ?? '');
    $bookingId  = clean($_POST['booking_id'] ?? '');
    $newStatus  = clean($_POST['new_status'] ?? '');
    $type       = clean($_POST['type'] ?? 'grooming');

    $tableMap = [
        'grooming' => ['grooming_bookings',    'Grooming booking'],
        'boarding' => ['boarding_reservations', 'Boarding reservation'],
        'walking'  => ['walking_bookings',      'Walking booking'],
    ];
    $allowedStatus = [
        'grooming' => ['pending','confirmed','in_progress','completed','cancelled'],
        'boarding' => ['pending','confirmed','active','completed','cancelled'],
        'walking'  => ['pending','confirmed','completed','cancelled'],
    ];

    if ($action === 'update_status'
        && isset($tableMap[$type])
        && in_array($newStatus, $allowedStatus[$type], true)) {

        [$tbl, $label] = $tableMap[$type];
        $row = db_select_one("SELECT id, user_id FROM `{$tbl}` WHERE id = ? LIMIT 1", [$bookingId]);
        if ($row) {
            db_execute("UPDATE `{$tbl}` SET status = ? WHERE id = ?", [$newStatus, $bookingId]);
            notify(
                $row['user_id'],
                $label . ' updated',
                'Your ' . strtolower($label) . ' (ref #' . substr($bookingId, -6) . ') is now ' . $newStatus . '.',
                'booking'
            );
            flash('success', $label . ' status updated to ' . ucfirst(str_replace('_', ' ', $newStatus)) . '.');
        } else {
            flash('danger', 'Booking not found.');
        }
        redirect(APP_URL . '/admin/bookings.php?tab=' . $type);
    }
    flash('danger', 'Invalid status update request.');
    redirect(APP_URL . '/admin/bookings.php');
}

$tab   = $_GET['tab'] ?? 'grooming';
if (!in_array($tab, ['grooming','boarding','walking'], true)) $tab = 'grooming';
$qDate = trim($_GET['date'] ?? '');

// ─── Data per tab ────────────────────────────────────────────────────
include __DIR__ . '/../includes/header.php';
$groomings = [];
$boardings = [];
$walkings  = [];

if ($tab === 'grooming') {
    $sql = 'SELECT gb.id, gb.date, gb.time, gb.status, gb.notes, gb.created_at,
                   u.name AS customer, u.phone, p.name AS pet, p.species, gs.name AS service
              FROM grooming_bookings gb
              JOIN users u ON u.id = gb.user_id
              JOIN pets p ON p.id = gb.pet_id
              JOIN grooming_services gs ON gs.id = gb.service_id';
    $params = [];
    if ($qDate !== '') {
        $sql .= ' WHERE gb.date = ?';
        $params[] = $qDate;
    }
    $sql .= ' ORDER BY gb.created_at DESC';
    $groomings = db_select($sql, $params);
} elseif ($tab === 'boarding') {
    $sql = 'SELECT br.id, br.check_in, br.check_out, br.status, br.notes, br.created_at,
                   u.name AS customer, u.phone, p.name AS pet, p.species, r.name AS room, r.type
              FROM boarding_reservations br
              JOIN users u ON u.id = br.user_id
              JOIN pets p ON p.id = br.pet_id
              JOIN boarding_rooms r ON r.id = br.room_id';
    $params = [];
    if ($qDate !== '') {
        $sql .= ' WHERE br.check_in = ? OR br.check_out = ?';
        $params[] = $qDate; $params[] = $qDate;
    }
    $sql .= ' ORDER BY br.created_at DESC';
    $boardings = db_select($sql, $params);
} else {
    $sql = 'SELECT wb.id, wb.date, wb.time, wb.duration, wb.status, wb.notes, wb.created_at,
                   u.name AS customer, u.phone, p.name AS pet, p.species
              FROM walking_bookings wb
              JOIN users u ON u.id = wb.user_id
              JOIN pets p ON p.id = wb.pet_id';
    $params = [];
    if ($qDate !== '') {
        $sql .= ' WHERE wb.date = ?';
        $params[] = $qDate;
    }
    $sql .= ' ORDER BY wb.created_at DESC';
    $walkings = db_select($sql, $params);
}

// Status option lists per tab
$groomingStatuses = ['pending','confirmed','in_progress','completed','cancelled'];
$boardingStatuses = ['pending','confirmed','active','completed','cancelled'];
$walkingStatuses  = ['pending','confirmed','completed','cancelled'];

/** Render a small inline status-update form. */
function status_form(string $type, string $bookingId, string $current, array $options): string {
    ob_start();
    ?>
    <form method="post" action="<?= APP_URL ?>/admin/bookings.php" class="d-flex gap-1">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="type" value="<?= e($type) ?>">
        <input type="hidden" name="booking_id" value="<?= e($bookingId) ?>">
        <select name="new_status" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
            <?php foreach ($options as $opt): ?>
                <option value="<?= e($opt) ?>" <?= $opt === $current ? 'selected' : '' ?>>
                    <?= e(ucfirst(str_replace('_', ' ', $opt))) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php
    return ob_get_clean();
}
?>
<!-- Page header -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 fade-in">
    <div>
        <h1 class="h3 mb-1 fw-bold text-purple">
            <i class="fas fa-calendar-check me-2"></i>All Bookings
        </h1>
        <p class="text-muted mb-0">Manage grooming, boarding and walking reservations.</p>
    </div>
    <form method="get" class="d-flex align-items-center gap-2 mt-2 mt-md-0">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <input type="date" name="date" value="<?= e($qDate) ?>" class="form-control form-control-sm" style="width:auto;">
        <button type="submit" class="btn btn-outline-primary btn-sm"><i class="fas fa-filter me-1"></i>Filter</button>
        <?php if ($qDate !== ''): ?>
            <a href="<?= APP_URL ?>/admin/bookings.php?tab=<?= e($tab) ?>" class="btn btn-link btn-sm text-muted">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Tabs -->
<ul class="nav nav-pills mb-3" id="bookingTabs">
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'grooming' ? 'active' : '' ?>" href="?tab=grooming">
            <i class="fas fa-scissors me-1"></i>Grooming
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'boarding' ? 'active' : '' ?>" href="?tab=boarding">
            <i class="fas fa-home me-1"></i>Boarding
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'walking' ? 'active' : '' ?>" href="?tab=walking">
            <i class="fas fa-walking me-1"></i>Walking
        </a>
    </li>
</ul>

<!-- ─── Grooming tab ─────────────────────────────────────────────────── -->
<?php if ($tab === 'grooming'): ?>
<div class="card">
    <div class="card-header"><i class="fas fa-scissors me-2 text-purple"></i>Grooming Bookings (<?= count($groomings) ?>)</div>
    <div class="card-body p-0">
        <?php if (!$groomings): ?>
            <div class="empty-state"><i class="fas fa-calendar-times"></i><p class="mb-0">No grooming bookings found.</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0 table-hover">
                <thead><tr>
                    <th>Customer</th><th>Pet</th><th>Service</th>
                    <th>Schedule</th><th>Status</th><th>Update Status</th><th>Notes</th>
                </tr></thead>
                <tbody>
                <?php foreach ($groomings as $b): ?>
                    <tr>
                        <td>
                            <div class="fw-600"><?= e($b['customer']) ?></div>
                            <small class="text-muted"><i class="fas fa-phone me-1"></i><?= e($b['phone']) ?></small>
                        </td>
                        <td>
                            <i class="fas fa-paw me-1 text-purple"></i><?= e($b['pet']) ?>
                            <small class="text-muted">(<?= e($b['species']) ?>)</small>
                        </td>
                        <td><?= e($b['service']) ?></td>
                        <td><?= e(fmt_date($b['date'])) ?><br><small class="text-muted"><?= e($b['time']) ?></small></td>
                        <td><?= status_badge($b['status']) ?></td>
                        <td><?= status_form('grooming', $b['id'], $b['status'], $groomingStatuses) ?></td>
                        <td class="small text-muted"><?= e(mb_strimwidth($b['notes'] ?? '', 0, 60, '…') ?: '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ─── Boarding tab ───────────────────────────────────────────────── -->
<?php elseif ($tab === 'boarding'): ?>
<div class="card">
    <div class="card-header"><i class="fas fa-home me-2 text-purple"></i>Boarding Reservations (<?= count($boardings) ?>)</div>
    <div class="card-body p-0">
        <?php if (!$boardings): ?>
            <div class="empty-state"><i class="fas fa-calendar-times"></i><p class="mb-0">No boarding reservations found.</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0 table-hover">
                <thead><tr>
                    <th>Customer</th><th>Pet</th><th>Room</th>
                    <th>Check-in</th><th>Check-out</th><th>Status</th><th>Update Status</th>
                </tr></thead>
                <tbody>
                <?php foreach ($boardings as $b): ?>
                    <tr>
                        <td>
                            <div class="fw-600"><?= e($b['customer']) ?></div>
                            <small class="text-muted"><i class="fas fa-phone me-1"></i><?= e($b['phone']) ?></small>
                        </td>
                        <td><i class="fas fa-paw me-1 text-purple"></i><?= e($b['pet']) ?> <small class="text-muted">(<?= e($b['species']) ?>)</small></td>
                        <td><?= e($b['room']) ?> <small class="text-muted text-uppercase">(<?= e($b['type']) ?>)</small></td>
                        <td><?= e(fmt_date($b['check_in'])) ?></td>
                        <td><?= e(fmt_date($b['check_out'])) ?></td>
                        <td><?= status_badge($b['status']) ?></td>
                        <td><?= status_form('boarding', $b['id'], $b['status'], $boardingStatuses) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ─── Walking tab ───────────────────────────────────────────────── -->
<?php else: ?>
<div class="card">
    <div class="card-header"><i class="fas fa-walking me-2 text-purple"></i>Walking Bookings (<?= count($walkings) ?>)</div>
    <div class="card-body p-0">
        <?php if (!$walkings): ?>
            <div class="empty-state"><i class="fas fa-calendar-times"></i><p class="mb-0">No walking bookings found.</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0 table-hover">
                <thead><tr>
                    <th>Customer</th><th>Pet</th><th>Date</th><th>Time</th><th>Duration</th><th>Status</th><th>Update Status</th>
                </tr></thead>
                <tbody>
                <?php foreach ($walkings as $b): ?>
                    <tr>
                        <td>
                            <div class="fw-600"><?= e($b['customer']) ?></div>
                            <small class="text-muted"><i class="fas fa-phone me-1"></i><?= e($b['phone']) ?></small>
                        </td>
                        <td><i class="fas fa-paw me-1 text-purple"></i><?= e($b['pet']) ?> <small class="text-muted">(<?= e($b['species']) ?>)</small></td>
                        <td><?= e(fmt_date($b['date'])) ?></td>
                        <td><?= e($b['time']) ?></td>
                        <td><span class="badge bg-purple-soft text-purple"><?= (int)$b['duration'] ?> min</span></td>
                        <td><?= status_badge($b['status']) ?></td>
                        <td><?= status_form('walking', $b['id'], $b['status'], $walkingStatuses) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
