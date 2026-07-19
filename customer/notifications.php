<?php
/**
 * customer/notifications.php — User notifications list.
 *
 * - Unread highlighted with bg-purple-soft
 * - Click a notification → AJAX marks is_read=1
 * - "Mark all read" button (POST)
 */
require_once __DIR__ . '/../includes/auth.php';
$u = require_role(ROLE_CUSTOMER);
$uid = $u['id'];

// ─── POST: mark all read ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_all_read') {
        db_execute(
            'UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0',
            [$uid]
        );
        flash('success', 'All notifications marked as read.');
        redirect(APP_URL . '/customer/notifications.php');
    }
    if ($action === 'mark_read') {
        $id = $_POST['notification_id'] ?? '';
        db_execute(
            'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?',
            [$id, $uid]
        );
        redirect(APP_URL . '/customer/notifications.php');
    }
}

$pageTitle = 'Notifications';
include __DIR__ . '/../includes/header.php';

$notifications = db_select(
    'SELECT id, title, message, type, is_read, created_at
       FROM notifications
      WHERE user_id = ?
      ORDER BY created_at DESC
      LIMIT 100',
    [$uid]
);

$iconMap = [
    'booking' => ['fa-calendar-check', 'text-primary'],
    'order'   => ['fa-box',            'text-warning'],
    'reward'  => ['fa-gift',           'text-success'],
    'general' => ['fa-bell',           'text-purple'],
];

function notif_icon(string $type): array {
    global $iconMap;
    return $iconMap[$type] ?? ['fa-bell', 'text-purple'];
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="h3 fw-bold text-purple mb-0">
        <i class="fas fa-bell me-2"></i>Notifications
        <?php if ($unread = unread_count($uid)): ?>
            <span class="badge bg-danger ms-1"><?= $unread ?> new</span>
        <?php endif; ?>
    </h1>
    <?php if ($notifications && unread_count($uid) > 0): ?>
    <form method="post" action="<?= APP_URL ?>/customer/notifications.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="mark_all_read">
        <button class="btn btn-outline-primary btn-sm">
            <i class="fas fa-check-double me-1"></i>Mark all read
        </button>
    </form>
    <?php endif; ?>
</div>

<?php if (!$notifications): ?>
    <div class="card"><div class="card-body empty-state">
        <i class="far fa-bell-slash"></i>
        <h4>No notifications</h4>
        <p class="mb-0">You're all caught up! New notifications about orders, bookings and rewards will show up here.</p>
    </div></div>
<?php else: ?>
    <div class="card">
        <div class="list-group list-group-flush">
            <?php foreach ($notifications as $n):
                [$icon, $color] = notif_icon($n['type']);
                $unread = (int)$n['is_read'] === 0;
            ?>
            <div class="list-group-item list-group-item-action <?= $unread ? 'bg-purple-soft' : '' ?>"
                 style="cursor:pointer;border-left:<?= $unread ? '4px solid var(--ph-purple)' : '4px solid transparent' ?>;"
                 onclick="markRead('<?= e($n['id']) ?>', this)">
                <div class="d-flex gap-3 align-items-start">
                    <div class="flex-shrink-0 d-flex align-items-center justify-content-center"
                         style="width:42px;height:42px;border-radius:50%;background:var(--ph-purple-50);">
                        <i class="fas <?= $icon ?> <?= $color ?> fa-lg"></i>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <h6 class="mb-1 <?= $unread ? 'fw-bold' : '' ?>"><?= e($n['title']) ?></h6>
                            <small class="text-muted text-nowrap">
                                <?= e(time_ago($n['created_at'])) ?>
                                <?php if ($unread): ?>
                                    <span class="badge bg-danger ms-1">NEW</span>
                                <?php endif; ?>
                            </small>
                        </div>
                        <p class="mb-0 small text-muted"><?= e($n['message']) ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<script>
async function markRead(id, el) {
    try {
        await window.api(window.APP_URL + '/ajax/mark_notification_read.php', {
            method: 'POST',
            body: { notification_id: id }
        });
        // Visually mark as read
        if (el) {
            el.classList.remove('bg-purple-soft');
            el.style.borderLeftColor = 'transparent';
            const title = el.querySelector('h6');
            if (title) title.classList.remove('fw-bold');
            const badge = el.querySelector('.badge.bg-danger');
            if (badge) badge.remove();
        }
        // Decrement navbar bell badge
        const bellBadge = document.querySelector('a[href*="notifications"] .badge');
        if (bellBadge) {
            const v = parseInt(bellBadge.textContent, 10) - 1;
            if (v <= 0) bellBadge.remove();
            else bellBadge.textContent = v;
        }
    } catch (e) {
        // Silent fail — non-critical
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
