<?php
/**
 * sidebar.php — Role-aware sidebar navigation.
 * Uses $currentUser (set in header.php).
 */
$role = $currentUser['role'];
$current = basename($_SERVER['PHP_SELF']);

/** Build nav items per role. */
$nav = match ($role) {
    ROLE_ADMIN => [
        ['dashboard.php', 'fa-tachometer-alt', 'Dashboard'],
        ['bookings.php',  'fa-calendar-check', 'All Bookings'],
        ['services.php',  'fa-scissors',       'Grooming Services'],
        ['products.php',  'fa-box',            'Products'],
        ['orders.php',    'fa-shopping-bag',   'Orders'],
        ['users.php',     'fa-users',          'Users'],
        ['coupons.php',   'fa-ticket-alt',     'Coupons'],
        ['blog.php',      'fa-newspaper',      'Blog Posts'],
        ['adoption.php',  'fa-heart',          'Adoption'],
        ['tickets.php',   'fa-life-ring',      'Support'],
        ['faqs.php',      'fa-question-circle','FAQs'],
        ['settings.php',  'fa-cog',            'Settings'],
    ],
    ROLE_CUSTOMER => [
        ['dashboard.php',     'fa-home',           'Home'],
        ['shop.php',          'fa-shopping-bag',   'Shop'],
        ['bookings.php',      'fa-calendar-check', 'Services'],
        ['cart.php',          'fa-shopping-cart',  'Cart'],
        ['orders.php',        'fa-box',            'Orders'],
        ['pets.php',          'fa-paw',            'My Pets'],
        ['wishlist.php',      'fa-heart',          'Wishlist'],
        ['rewards.php',       'fa-gift',           'Rewards'],
        ['notifications.php', 'fa-bell',           'Notifications'],
        ['profile.php',       'fa-user',           'Profile'],
    ],
    ROLE_GROOMER => [
        ['dashboard.php',     'fa-tachometer-alt', 'Dashboard'],
        ['appointments.php',  'fa-calendar-check', 'Appointments'],
        ['history.php',       'fa-history',        'History'],
        ['profile.php',       'fa-user',           'Profile'],
    ],
    ROLE_DELIVERY => [
        ['dashboard.php',     'fa-tachometer-alt', 'Dashboard'],
        ['assigned.php',      'fa-clipboard-list', 'Assigned Orders'],
        ['history.php',       'fa-history',        'Delivery History'],
        ['profile.php',       'fa-user',           'Profile'],
    ],
    default => [],
};

// Resolve each item's URL prefix based on role
$base = match ($role) {
    ROLE_ADMIN    => APP_URL . '/admin/',
    ROLE_CUSTOMER => APP_URL . '/customer/',
    ROLE_GROOMER  => APP_URL . '/groomer/',
    ROLE_DELIVERY => APP_URL . '/delivery/',
    default       => APP_URL . '/',
};
?>
<div class="bg-white border-end ph-sidebar" id="sidebar">
    <div class="list-group list-group-flush">
        <?php foreach ($nav as $item):
            [$file, $icon, $label] = $item;
            $url = $base . $file;
            $activeCls = $current === $file ? 'active' : '';
        ?>
        <a href="<?= e($url) ?>" class="list-group-item list-group-item-action ph-nav-item <?= $activeCls ?>">
            <i class="fas <?= $icon ?> fa-fw"></i>
            <span><?= e($label) ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <div class="p-3 mt-auto">
        <a href="<?= APP_URL ?>/logout.php" class="btn btn-outline-danger w-100 btn-sm">
            <i class="fas fa-sign-out-alt me-1"></i> Logout
        </a>
    </div>
</div>
