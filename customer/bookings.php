<?php
/**
 * customer/bookings.php — Book grooming / boarding / walking services.
 *
 * Three tabs, each with: list of available services/rooms, a booking
 * form, and a list of the customer's existing bookings (with cancel).
 *
 * All POST handlers branch on the hidden `form_type` field.
 */
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(ROLE_CUSTOMER);

$uid      = $u['id'];
$activeTab = $_GET['tab'] ?? 'grooming';
if (!in_array($activeTab, ['grooming', 'boarding', 'walking'], true)) $activeTab = 'grooming';

$preselectService = $_GET['service'] ?? '';

// ─── POST handler ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $formType = $_POST['form_type'] ?? '';

    // ── Grooming booking ──
    if ($formType === 'grooming_book') {
        $serviceId = trim($_POST['service_id'] ?? '');
        $petId     = trim($_POST['pet_id'] ?? '');
        $date      = trim($_POST['date'] ?? '');
        $time      = trim($_POST['time'] ?? '');
        $notes     = clean($_POST['notes'] ?? '');

        $svc = db_select_one('SELECT id, name FROM grooming_services WHERE id = ? AND active = 1', [$serviceId]);
        $pet = db_select_one('SELECT id, name FROM pets WHERE id = ? AND user_id = ?', [$petId, $uid]);

        if (!$svc)      flash('danger', 'Please select a valid grooming service.');
        elseif (!$pet)  flash('danger', 'Please select one of your pets.');
        elseif (!$date || !$time) flash('danger', 'Please pick a date and time.');
        elseif (strtotime($date . ' ' . $time) < time()) flash('danger', 'Cannot book in the past.');
        else {
            $id = gen_id('gb_');
            db_insert('grooming_bookings', [
                'id'         => $id,
                'user_id'    => $uid,
                'pet_id'     => $petId,
                'service_id' => $serviceId,
                'date'       => $date,
                'time'       => $time,
                'status'     => 'pending',
                'notes'      => $notes ?: null,
            ]);
            notify($uid, 'Grooming Booking Created',
                'Your grooming appointment for ' . $pet['name'] . ' (' . $svc['name'] . ') on ' . fmt_date($date) . ' at ' . $time . ' is pending confirmation.',
                'booking');
            flash('success', 'Grooming booking requested! We will confirm shortly.');
            redirect(APP_URL . '/customer/bookings.php?tab=grooming');
        }
    }

    // ── Boarding reservation ──
    if ($formType === 'boarding_book') {
        $roomId   = trim($_POST['room_id'] ?? '');
        $petId    = trim($_POST['pet_id'] ?? '');
        $checkIn  = trim($_POST['check_in'] ?? '');
        $checkOut = trim($_POST['check_out'] ?? '');
        $notes    = clean($_POST['notes'] ?? '');

        $room = db_select_one('SELECT id, name FROM boarding_rooms WHERE id = ? AND active = 1', [$roomId]);
        $pet  = db_select_one('SELECT id, name FROM pets WHERE id = ? AND user_id = ?', [$petId, $uid]);

        if (!$room)         flash('danger', 'Please select a valid boarding room.');
        elseif (!$pet)      flash('danger', 'Please select one of your pets.');
        elseif (!$checkIn || !$checkOut) flash('danger', 'Please pick check-in and check-out dates.');
        elseif (strtotime($checkOut) <= strtotime($checkIn)) flash('danger', 'Check-out must be after check-in.');
        else {
            $id = gen_id('br_');
            db_insert('boarding_reservations', [
                'id'        => $id,
                'user_id'   => $uid,
                'pet_id'    => $petId,
                'room_id'   => $roomId,
                'check_in'  => $checkIn,
                'check_out' => $checkOut,
                'status'    => 'pending',
                'notes'     => $notes ?: null,
            ]);
            notify($uid, 'Boarding Reservation Created',
                'Your boarding reservation for ' . $pet['name'] . ' (' . $room['name'] . ') from ' . fmt_date($checkIn) . ' to ' . fmt_date($checkOut) . ' is pending confirmation.',
                'booking');
            flash('success', 'Boarding reservation requested!');
            redirect(APP_URL . '/customer/bookings.php?tab=boarding');
        }
    }

    // ── Walking booking ──
    if ($formType === 'walking_book') {
        $petId    = trim($_POST['pet_id'] ?? '');
        $date     = trim($_POST['date'] ?? '');
        $time     = trim($_POST['time'] ?? '');
        $duration = (int) ($_POST['duration'] ?? 0);
        $notes    = clean($_POST['notes'] ?? '');

        if (!in_array($duration, [30, 45, 60], true)) $duration = 30;
        $pet = db_select_one('SELECT id, name FROM pets WHERE id = ? AND user_id = ?', [$petId, $uid]);

        if (!$pet)         flash('danger', 'Please select one of your pets.');
        elseif (!$date || !$time) flash('danger', 'Please pick a date and time.');
        elseif (strtotime($date . ' ' . $time) < time()) flash('danger', 'Cannot book in the past.');
        else {
            $id = gen_id('wb_');
            db_insert('walking_bookings', [
                'id'       => $id,
                'user_id'  => $uid,
                'pet_id'   => $petId,
                'date'     => $date,
                'time'     => $time,
                'duration' => $duration,
                'status'   => 'pending',
                'notes'    => $notes ?: null,
            ]);
            notify($uid, 'Walking Booking Created',
                'Your walking session for ' . $pet['name'] . ' on ' . fmt_date($date) . ' at ' . $time . ' (' . $duration . ' min) is pending confirmation.',
                'booking');
            flash('success', 'Walking session booked!');
            redirect(APP_URL . '/customer/bookings.php?tab=walking');
        }
    }

    // ── Cancel handlers ──
    if ($formType === 'cancel_grooming') {
        $bid = $_POST['booking_id'] ?? '';
        db_execute(
            "UPDATE grooming_bookings SET status = 'cancelled'
              WHERE id = ? AND user_id = ? AND status IN ('pending','confirmed')",
            [$bid, $uid]
        );
        flash('info', 'Grooming booking cancelled.');
        redirect(APP_URL . '/customer/bookings.php?tab=grooming');
    }
    if ($formType === 'cancel_boarding') {
        $bid = $_POST['booking_id'] ?? '';
        db_execute(
            "UPDATE boarding_reservations SET status = 'cancelled'
              WHERE id = ? AND user_id = ? AND status IN ('pending','confirmed')",
            [$bid, $uid]
        );
        flash('info', 'Boarding reservation cancelled.');
        redirect(APP_URL . '/customer/bookings.php?tab=boarding');
    }
    if ($formType === 'cancel_walking') {
        $bid = $_POST['booking_id'] ?? '';
        db_execute(
            "UPDATE walking_bookings SET status = 'cancelled'
              WHERE id = ? AND user_id = ? AND status IN ('pending','confirmed')",
            [$bid, $uid]
        );
        flash('info', 'Walking booking cancelled.');
        redirect(APP_URL . '/customer/bookings.php?tab=walking');
    }
}

$pageTitle = 'Book a Service';
include __DIR__ . '/../includes/header.php';

// ─── Data for the page ─────────────────────────────────────────────
$pets     = db_select('SELECT id, name, species, breed FROM pets WHERE user_id = ? ORDER BY name', [$uid]);
$services = db_select('SELECT id, name, description, price, duration, category FROM grooming_services WHERE active = 1 ORDER BY price ASC');
$rooms    = db_select('SELECT id, name, type, price, capacity, amenities FROM boarding_rooms WHERE active = 1 ORDER BY price ASC');

$myGrooming = db_select(
    'SELECT b.id, b.date, b.time, b.status, b.notes, b.created_at,
            s.name AS service_name, s.price, s.duration,
            p.name AS pet_name, p.species
       FROM grooming_bookings b
       JOIN grooming_services s ON s.id = b.service_id
       JOIN pets p ON p.id = b.pet_id
      WHERE b.user_id = ?
      ORDER BY b.created_at DESC',
    [$uid]
);

$myBoarding = db_select(
    'SELECT r.id, r.check_in, r.check_out, r.status, r.notes, r.created_at,
            rm.name AS room_name, rm.type AS room_type, rm.price,
            p.name AS pet_name, p.species
       FROM boarding_reservations r
       JOIN boarding_rooms rm ON rm.id = r.room_id
       JOIN pets p ON p.id = r.pet_id
      WHERE r.user_id = ?
      ORDER BY r.created_at DESC',
    [$uid]
);

$myWalking = db_select(
    'SELECT w.id, w.date, w.time, w.duration, w.status, w.notes, w.created_at,
            p.name AS pet_name, p.species
       FROM walking_bookings w
       JOIN pets p ON p.id = w.pet_id
      WHERE w.user_id = ?
      ORDER BY w.created_at DESC',
    [$uid]
);

$today = date('Y-m-d');
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="h3 fw-bold text-purple mb-0"><i class="fas fa-calendar-check me-2"></i>Book a Service</h1>
    <?php if (!$pets): ?>
        <a href="<?= APP_URL ?>/customer/pets.php" class="btn btn-warning btn-sm">
            <i class="fas fa-plus me-1"></i>Add a Pet First
        </a>
    <?php endif; ?>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3" id="bookingsTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'grooming' ? 'active' : '' ?>" data-bs-toggle="tab"
                data-bs-target="#tab-grooming" type="button" role="tab">
            <i class="fas fa-scissors me-1"></i>Grooming
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'boarding' ? 'active' : '' ?>" data-bs-toggle="tab"
                data-bs-target="#tab-boarding" type="button" role="tab">
            <i class="fas fa-home me-1"></i>Boarding
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'walking' ? 'active' : '' ?>" data-bs-toggle="tab"
                data-bs-target="#tab-walking" type="button" role="tab">
            <i class="fas fa-walking me-1"></i>Walking
        </button>
    </li>
</ul>

<div class="tab-content">

<!-- ════════════════════════ GROOMING ════════════════════════ -->
<div class="tab-pane fade <?= $activeTab === 'grooming' ? 'show active' : '' ?>" id="tab-grooming">

    <!-- Available services -->
    <h2 class="section-title">Available Grooming Services</h2>
    <div class="row g-3 mb-4">
        <?php if (!$services): ?>
            <div class="col-12"><div class="card"><div class="card-body empty-state">
                <i class="fas fa-scissors"></i><p class="mb-0">No services available right now.</p>
            </div></div></div>
        <?php else: foreach ($services as $s): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card service-card h-100">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <h5 class="card-title mb-0"><?= e($s['name']) ?></h5>
                        <span class="badge bg-purple-soft text-purple"><?= e($s['category']) ?></span>
                    </div>
                    <p class="text-muted small flex-grow-1"><?= e($s['description']) ?></p>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold text-purple fs-5"><?= money($s['price']) ?></span>
                        <small class="text-muted"><i class="far fa-clock me-1"></i><?= (int)$s['duration'] ?> min</small>
                    </div>
                    <button type="button" class="btn btn-grad btn-sm w-100"
                            onclick="selectGroomingService('<?= e($s['id']) ?>')">
                        <i class="fas fa-check me-1"></i>Select
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- Booking form -->
    <div class="card mb-4" id="grooming-form">
        <div class="card-header"><i class="fas fa-calendar-plus me-2 text-purple"></i>Book Grooming Appointment</div>
        <div class="card-body">
            <?php if (!$pets): ?>
                <div class="alert alert-warning mb-0">
                    <i class="fas fa-paw me-1"></i>You need to <a href="<?= APP_URL ?>/customer/pets.php" class="alert-link">add a pet</a> first before booking.
                </div>
            <?php else: ?>
            <form method="post" action="<?= APP_URL ?>/customer/bookings.php?tab=grooming">
                <?= csrf_field() ?>
                <input type="hidden" name="form_type" value="grooming_book">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Service <span class="text-danger">*</span></label>
                        <select name="service_id" id="grooming_service_select" class="form-select" required>
                            <option value="">— Select service —</option>
                            <?php foreach ($services as $s): ?>
                                <option value="<?= e($s['id']) ?>" data-price="<?= e($s['price']) ?>"
                                    <?= $preselectService === $s['id'] ? 'selected' : '' ?>>
                                    <?= e($s['name']) ?> — <?= money($s['price']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Pet <span class="text-danger">*</span></label>
                        <select name="pet_id" class="form-select" required>
                            <option value="">— Select pet —</option>
                            <?php foreach ($pets as $p): ?>
                                <option value="<?= e($p['id']) ?>">
                                    <?= e($p['name']) ?> (<?= e($p['species']) ?> · <?= e($p['breed']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control" min="<?= e($today) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Time <span class="text-danger">*</span></label>
                        <input type="time" name="time" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Notes (optional)</label>
                        <input type="text" name="notes" class="form-control" placeholder="Any special requests?">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-grad">
                            <i class="fas fa-calendar-check me-1"></i>Request Booking
                        </button>
                    </div>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- My grooming bookings -->
    <h2 class="section-title">My Grooming Bookings</h2>
    <div class="card">
        <div class="card-body p-0">
            <?php if (!$myGrooming): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i><p class="mb-0">No grooming bookings yet.</p>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr>
                        <th>Service</th><th>Pet</th><th>Date</th><th>Time</th><th>Price</th><th>Status</th><th></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($myGrooming as $b): ?>
                    <tr>
                        <td class="fw-600"><?= e($b['service_name']) ?></td>
                        <td><?= e($b['pet_name']) ?> <small class="text-muted"><?= e($b['species']) ?></small></td>
                        <td><?= e(fmt_date($b['date'])) ?></td>
                        <td><?= e($b['time']) ?></td>
                        <td><?= money($b['price']) ?></td>
                        <td><?= status_badge($b['status']) ?></td>
                        <td class="text-end">
                            <?php if (in_array($b['status'], ['pending', 'confirmed'])): ?>
                            <form method="post" action="<?= APP_URL ?>/customer/bookings.php?tab=grooming" class="d-inline"
                                  data-confirm-submit="Cancel this grooming booking?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="form_type" value="cancel_grooming">
                                <input type="hidden" name="booking_id" value="<?= e($b['id']) ?>">
                                <button class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-times me-1"></i>Cancel
                                </button>
                            </form>
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

<!-- ════════════════════════ BOARDING ════════════════════════ -->
<div class="tab-pane fade <?= $activeTab === 'boarding' ? 'show active' : '' ?>" id="tab-boarding">

    <h2 class="section-title">Boarding Rooms</h2>
    <div class="row g-3 mb-4">
        <?php if (!$rooms): ?>
            <div class="col-12"><div class="card"><div class="card-body empty-state">
                <i class="fas fa-home"></i><p class="mb-0">No boarding rooms available right now.</p>
            </div></div></div>
        <?php else: foreach ($rooms as $r):
            $amenities = json_array($r['amenities']);
        ?>
        <div class="col-md-4">
            <div class="card service-card h-100">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <h5 class="card-title mb-0"><?= e($r['name']) ?></h5>
                        <span class="badge bg-purple-soft text-purple"><?= e($r['type']) ?></span>
                    </div>
                    <ul class="small text-muted mb-3 flex-grow-1 ps-3">
                        <?php foreach ($amenities as $a): ?>
                            <li><i class="fas fa-check text-success me-1"></i><?= e($a) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold text-purple fs-5"><?= money($r['price']) ?><small class="text-muted">/night</small></span>
                        <small class="text-muted"><i class="fas fa-users me-1"></i>Cap: <?= (int)$r['capacity'] ?></small>
                    </div>
                    <button type="button" class="btn btn-grad btn-sm w-100"
                            onclick="selectBoardingRoom('<?= e($r['id']) ?>')">
                        <i class="fas fa-check me-1"></i>Select
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>

    <div class="card mb-4" id="boarding-form">
        <div class="card-header"><i class="fas fa-calendar-plus me-2 text-purple"></i>Reserve a Boarding Room</div>
        <div class="card-body">
            <?php if (!$pets): ?>
                <div class="alert alert-warning mb-0">
                    <i class="fas fa-paw me-1"></i>You need to <a href="<?= APP_URL ?>/customer/pets.php" class="alert-link">add a pet</a> first before booking.
                </div>
            <?php else: ?>
            <form method="post" action="<?= APP_URL ?>/customer/bookings.php?tab=boarding">
                <?= csrf_field() ?>
                <input type="hidden" name="form_type" value="boarding_book">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Room <span class="text-danger">*</span></label>
                        <select name="room_id" id="boarding_room_select" class="form-select" required>
                            <option value="">— Select room —</option>
                            <?php foreach ($rooms as $r): ?>
                                <option value="<?= e($r['id']) ?>">
                                    <?= e($r['name']) ?> (<?= e($r['type']) ?>) — <?= money($r['price']) ?>/night
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Pet <span class="text-danger">*</span></label>
                        <select name="pet_id" class="form-select" required>
                            <option value="">— Select pet —</option>
                            <?php foreach ($pets as $p): ?>
                                <option value="<?= e($p['id']) ?>"><?= e($p['name']) ?> (<?= e($p['species']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Check-in <span class="text-danger">*</span></label>
                        <input type="date" name="check_in" class="form-control" min="<?= e($today) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Check-out <span class="text-danger">*</span></label>
                        <input type="date" name="check_out" class="form-control" min="<?= e($today) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Notes (optional)</label>
                        <input type="text" name="notes" class="form-control" placeholder="Special care instructions?">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-grad">
                            <i class="fas fa-calendar-check me-1"></i>Request Reservation
                        </button>
                    </div>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <h2 class="section-title">My Boarding Reservations</h2>
    <div class="card">
        <div class="card-body p-0">
            <?php if (!$myBoarding): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i><p class="mb-0">No boarding reservations yet.</p>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr>
                        <th>Room</th><th>Pet</th><th>Check-in</th><th>Check-out</th><th>Nights</th><th>Status</th><th></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($myBoarding as $r):
                        $nights = max(1, (int) round((strtotime($r['check_out']) - strtotime($r['check_in'])) / 86400));
                    ?>
                    <tr>
                        <td class="fw-600"><?= e($r['room_name']) ?> <small class="text-muted"><?= e($r['room_type']) ?></small></td>
                        <td><?= e($r['pet_name']) ?></td>
                        <td><?= e(fmt_date($r['check_in'])) ?></td>
                        <td><?= e(fmt_date($r['check_out'])) ?></td>
                        <td><?= $nights ?> <small class="text-muted">(<?= money($r['price'] * $nights) ?>)</small></td>
                        <td><?= status_badge($r['status']) ?></td>
                        <td class="text-end">
                            <?php if (in_array($r['status'], ['pending', 'confirmed'])): ?>
                            <form method="post" action="<?= APP_URL ?>/customer/bookings.php?tab=boarding" class="d-inline"
                                  data-confirm-submit="Cancel this boarding reservation?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="form_type" value="cancel_boarding">
                                <input type="hidden" name="booking_id" value="<?= e($r['id']) ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="fas fa-times me-1"></i>Cancel</button>
                            </form>
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

<!-- ════════════════════════ WALKING ════════════════════════ -->
<div class="tab-pane fade <?= $activeTab === 'walking' ? 'show active' : '' ?>" id="tab-walking">

    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-person-walking me-2 text-purple"></i>Book a Walking Session</div>
        <div class="card-body">
            <?php if (!$pets): ?>
                <div class="alert alert-warning mb-0">
                    <i class="fas fa-paw me-1"></i>You need to <a href="<?= APP_URL ?>/customer/pets.php" class="alert-link">add a pet</a> first before booking.
                </div>
            <?php else: ?>
            <form method="post" action="<?= APP_URL ?>/customer/bookings.php?tab=walking">
                <?= csrf_field() ?>
                <input type="hidden" name="form_type" value="walking_book">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Pet <span class="text-danger">*</span></label>
                        <select name="pet_id" class="form-select" required>
                            <option value="">— Select pet —</option>
                            <?php foreach ($pets as $p): ?>
                                <option value="<?= e($p['id']) ?>"><?= e($p['name']) ?> (<?= e($p['species']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Duration <span class="text-danger">*</span></label>
                        <select name="duration" class="form-select" required>
                            <option value="30">30 minutes — <?= money(149) ?></option>
                            <option value="45">45 minutes — <?= money(199) ?></option>
                            <option value="60">60 minutes — <?= money(249) ?></option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" name="date" class="form-control" min="<?= e($today) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Time <span class="text-danger">*</span></label>
                        <input type="time" name="time" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Notes (optional)</label>
                        <input type="text" name="notes" class="form-control" placeholder="Route / behaviour notes?">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-grad">
                            <i class="fas fa-walking me-1"></i>Book Walking
                        </button>
                    </div>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <h2 class="section-title">My Walking Bookings</h2>
    <div class="card">
        <div class="card-body p-0">
            <?php if (!$myWalking): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i><p class="mb-0">No walking bookings yet.</p>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr>
                        <th>Pet</th><th>Date</th><th>Time</th><th>Duration</th><th>Status</th><th></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($myWalking as $w): ?>
                    <tr>
                        <td class="fw-600"><?= e($w['pet_name']) ?> <small class="text-muted"><?= e($w['species']) ?></small></td>
                        <td><?= e(fmt_date($w['date'])) ?></td>
                        <td><?= e($w['time']) ?></td>
                        <td><?= (int)$w['duration'] ?> min</td>
                        <td><?= status_badge($w['status']) ?></td>
                        <td class="text-end">
                            <?php if (in_array($w['status'], ['pending', 'confirmed'])): ?>
                            <form method="post" action="<?= APP_URL ?>/customer/bookings.php?tab=walking" class="d-inline"
                                  data-confirm-submit="Cancel this walking booking?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="form_type" value="cancel_walking">
                                <input type="hidden" name="booking_id" value="<?= e($w['id']) ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="fas fa-times me-1"></i>Cancel</button>
                            </form>
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

</div><!-- /.tab-content -->

<script>
function selectGroomingService(id) {
    const sel = document.getElementById('grooming_service_select');
    if (sel) { sel.value = id; sel.focus(); }
    document.getElementById('grooming-form').scrollIntoView({behavior:'smooth', block:'start'});
}
function selectBoardingRoom(id) {
    const sel = document.getElementById('boarding_room_select');
    if (sel) { sel.value = id; sel.focus(); }
    document.getElementById('boarding-form').scrollIntoView({behavior:'smooth', block:'start'});
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
