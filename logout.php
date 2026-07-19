<?php
/**
 * logout.php — End session and redirect to login.
 */
require_once __DIR__ . '/includes/auth.php';
logout();
redirect(APP_URL . '/login.php');
