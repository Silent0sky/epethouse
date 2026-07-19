<?php
/**
 * header.php — Top HTML shell. Expects $page_title to be set before include.
 */
require_once __DIR__ . '/auth.php';

$currentUser = current_user();
$pageTitle   = $pageTitle ?? APP_NAME;
$unread      = $currentUser ? unread_count($currentUser['id']) : 0;
$cartN       = $currentUser ? cart_count($currentUser['id']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
    <meta name="description" content="<?= e(APP_NAME) ?> - <?= e(APP_TAGLINE) ?> in <?= e(APP_CITY) ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- App CSS -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>
<?php if ($currentUser): ?>
<!-- Top navbar -->
<nav class="navbar navbar-expand-lg ph-navbar shadow-sm sticky-top">
    <div class="container-fluid">
        <button class="btn btn-link text-white d-lg-none" id="sidebarToggle" type="button">
            <i class="fas fa-bars fa-lg"></i>
        </button>
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= APP_URL ?>/dashboard.php">
            <span class="ph-logo"><i class="fas fa-paw"></i></span>
            <span class="fw-bold text-white"><?= e(APP_NAME) ?></span>
        </a>
        <div class="d-flex align-items-center gap-3 ms-auto">
            <?php if (is_customer($currentUser)): ?>
            <a href="<?= APP_URL ?>/customer/cart.php" class="position-relative text-white text-decoration-none">
                <i class="fas fa-shopping-cart fa-lg"></i>
                <?php if ($cartN > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $cartN ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
            <a href="<?= APP_URL ?>/customer/notifications.php" class="position-relative text-white text-decoration-none">
                <i class="fas fa-bell fa-lg"></i>
                <?php if ($unread > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $unread ?></span>
                <?php endif; ?>
            </a>
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle"
                   data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="ph-avatar me-2"><?= e(initials($currentUser['name'])) ?></span>
                    <span class="d-none d-md-inline fw-medium"><?= e($currentUser['name']) ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li><span class="dropdown-item-text small text-muted"><?= e($currentUser['email']) ?></span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?= APP_URL ?>/customer/profile.php"><i class="fas fa-user me-2"></i>My Profile</a></li>
                    <?php if (is_customer($currentUser)): ?>
                    <li><a class="dropdown-item" href="<?= APP_URL ?>/customer/orders.php"><i class="fas fa-box me-2"></i>My Orders</a></li>
                    <li><a class="dropdown-item" href="<?= APP_URL ?>/customer/wishlist.php"><i class="fas fa-heart me-2"></i>Wishlist</a></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>
<?php endif; ?>

<div class="d-flex" id="wrapper">
    <?php if ($currentUser): include __DIR__ . '/sidebar.php'; endif; ?>
    <div id="page-content-wrapper" class="flex-grow-1">
