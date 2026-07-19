<?php
/**
 * groomer/dashboard.php — Groomer home page.
 *
 * - 4 stat cards (today / pending / completed-this-week / total-this-month)
 * - Today's schedule table with status transition actions
 * - Pending approval requests (accept / decline)
 *
 * All status updates are POST + CSRF; customer is notified on every change.
 */
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(ROLE_GROOMER);
$uid = $u['id'];

$today    = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week'));
$monthStart = date('Y-m-01');

// ─── POST: status transitions ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action    = $_POST['action'] ?? '';
    $bookingId = trim($_POST['booking_id'] ?? '');

    $booking = db_select_one(
        'SELECT gb.id, gb.user_id, gb.status, gb.date, gb.time,
                u.name AS customer_name, u.email,
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
            db_execute(
                'UPDATE grooming_bookings SET status = ? WHERE id = ?',
                [$newStatus, $bookingId]
            );

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

            $verb = match ($action) { 'accept' => 'accepted', 'decline' => 'declined', 'start' => 'started', 'complete' => 'completed', default => 'updated' };
            flash('success', 'Booking for ' . $booking['customer_name'] . ' (' . $booking['pet_name'] . ') has been ' . $verb . '.');
        }
    }
    redirect(APP_URL . '/groomer/dashboard.php');
}

// ─── Stat card counts ─────────────────────────────────────────────────
$todayCount = (int) db_scalar(
    "SELECT COUNT(*) FROM grooming_bookings
      WHERE date = ? AND status IN ('confirmed','in_progress')",
    [$today]
);
$pendingCount = (int) db_scalar(
    "SELECT COUNT(*) FROM grooming_bookings WHERE status = 'pending'"
);
$weekCompleted = (int) db_scalar(
    "SELECT COUNT(*) FROM grooming_bookings
      WHERE status = 'completed' AND date >= ? AND date <= ?",
    [$weekStart, $today]
);
$monthTotal = (int) db_scalar(
    "SELECT COUNT(*) FROM grooming_bookings WHERE date >= ? AND date <= ?",
    [$monthStart, $today]
);

// ─── Today's schedule ─────────────────────────────────────────────────
$todaySchedule = db_select(
    "SELECT gb.id, gb.time, gb.status, gb.notes,
            u.name AS customer_name, u.phone,
            p.name AS pet_name, p.species, p.breed,
            s.name AS service_name, s.duration
       FROM grooming_bookings gb
       JOIN users  u ON u.id  = gb.user_id
       JOIN pets   p ON p.id  = gb.pet_id
       JOIN grooming_services s ON s.id = gb.service_id
      WHERE gb.date = ? AND gb.status IN ('confirmed','in_progress')
      ORDER BY gb.time ASC",
    [$today]
);

// ─── Pending approval requests ────────────────────────────────────────
$pendingList = db_select(
    "SELECT gb.id, gb.date, gb.time, gb.notes, gb.created_at,
            u.name AS customer_name, u.phone,
            p.name AS pet_name, p.species, p.breed,
            s.name AS service_name, s.price
       FROM grooming_bookings gb
       JOIN users  u ON u.id  = gb.user_id
       JOIN pets   p ON p.id  = gb.pet_id
       JOIN grooming_services s ON s.id = gb.service_id
      WHERE gb.status = 'pending'
      ORDER BY gb.date ASC, gb.time ASC"
);

$pageTitle = 'Groomer Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<!-- Welcome header -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 fade-in">
    <div>
        <h1 class="h3 mb-1 fw-bold text-purple">
            <i class="fas fa-cut me-2"></i>Welcome, <?= e($u['name']) ?>!
        </h1>
        <p class="text-muted mb-0">
            <?= e(fmt_date($today)) ?> · You have
            <strong class="text-purple"><?= $todayCount ?></strong> appointment<?= $todayCount === 1 ? '' : 's' ?> today
            and <strong class="text-warning"><?= $pendingCount ?></strong> pending request<?= $pendingCount === 1 ? '' : 's' ?>.
        </p>
    </div>
    <div class="mt-2 mt-md-0">
        <a href="<?= APP_URL ?>/groomer/appointments.php" class="btn btn-grad btn-sm">
            <i class="fas fa-calendar-check me-1"></i>All Appointments
        </a>
    </div>
</div>

<!-- Stat cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card bg-grad-purple h-100">
            <div class="stat-value"><?= $todayCount ?></div>
            <div class="stat-label">Today's Appointments</div>
            <i class="fas fa-calendar-day stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card bg-grad-amber h-100">
            <div class="stat-value"><?= $pendingCount ?></div>
            <div class="stat-label">Pending Approval</div>
            <i class="fas fa-hourglass-half stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card bg-grad-teal h-100">
            <div class="stat-value"><?= $weekCompleted ?></div>
            <div class="stat-label">Completed This Week</div>
            <i class="fas fa-check-double stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card bg-grad-pink h-100">
            <div class="stat-value"><?= $monthTotal ?></div>
            <div class="stat-label">Total This Month</div>
            <i class="fas fa-chart-line stat-icon"></i>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Today's Schedule -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-calendar-day me-2 text-purple"></i>Today's Schedule</span>
                <span class="badge bg-purple-soft text-purple"><?= count($todaySchedule) ?> booking<?= count($todaySchedule) === 1 ? '' : 's' ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (!$todaySchedule): ?>
                    <div class="empty-state">
                        <i class="fas fa-mug-hot"></i>
                        <h5 class="mt-2 mb-1">No appointments today</h5>
                        <p class="mb-0">Enjoy the breather or check pending requests.</p>
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead><tr>
                            <th>Time</th><th>Customer</th><th>Pet</th>
                            <th>Service</th><th>Status</th><th class="text-end">Action</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($todaySchedule as $b): ?>
                            <tr>
                                <td class="fw-600 text-purple"><?= e(date('g:i A', strtotime($b['time']))) ?></td>
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
                                    <small class="text-muted"><?= (int)$b['duration'] ?> min</small>
                                </td>
                                <td><?= status_badge($b['status']) ?></td>
                                <td class="text-end">
                                    <?php if ($b['status'] === 'confirmed'): ?>
                                    <form method="post" action="<?= APP_URL ?>/groomer/dashboard.php" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="start">
                                        <input type="hidden" name="booking_id" value="<?= e($b['id']) ?>">
                                        <button class="btn btn-sm btn-primary" title="Start session">
                                            <i class="fas fa-play me-1"></i>Start
                                        </button>
                                    </form>
                                    <?php elseif ($b['status'] === 'in_progress'): ?>
                                    <form method="post" action="<?= APP_URL ?>/groomer/dashboard.php" class="d-inline"
                                          data-confirm-submit="Mark this session as completed?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="complete">
                                        <input type="hidden" name="booking_id" value="<?= e($b['id']) ?>">
                                        <button class="btn btn-sm btn-success" title="Mark complete">
                                            <i class="fas fa-check me-1"></i>Complete
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Pending requests -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-hourglass-half me-2 text-warning"></i>Pending Requests</span>
                <span class="badge bg-warning text-dark"><?= count($pendingList) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (!$pendingList): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p class="mb-0">No pending requests. You're all caught up.</p>
                    </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($pendingList as $b): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="fw-600"><?= e($b['customer_name']) ?> <small class="text-muted">· <?= e($b['phone']) ?></small></div>
                                <div class="small text-muted">
                                    <?= e($b['pet_name']) ?> (<?= e($b['species']) ?>) · <?= e($b['service_name']) ?> · <?= money($b['price']) ?>
                                </div>
                                <div class="small text-purple fw-600">
                                    <i class="far fa-clock me-1"></i><?= e(fmt_date($b['date'])) ?> at <?= e(date('g:i A', strtotime($b['time']))) ?>
                                </div>
                                <?php if (!empty($b['notes'])): ?>
                                    <small class="text-muted d-block mt-1"><i class="fas fa-comment-dots me-1"></i><?= e(mb_strimwidth($b['notes'], 0, 80, '…')) ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <form method="post" action="<?= APP_URL ?>/groomer/dashboard.php" class="flex-fill">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="accept">
                                <input type="hidden" name="booking_id" value="<?= e($b['id']) ?>">
                                <button class="btn btn-sm btn-success w-100"><i class="fas fa-check me-1"></i>Accept</button>
                            </form>
                            <form method="post" action="<?= APP_URL ?>/groomer/dashboard.php" class="flex-fill"
                                  data-confirm-submit="Decline this booking request?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="decline">
                                <input type="hidden" name="booking_id" value="<?= e($b['id']) ?>">
                                <button class="btn btn-sm btn-outline-danger w-100"><i class="fas fa-times me-1"></i>Decline</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
