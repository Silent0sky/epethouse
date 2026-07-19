<?php
/**
 * dashboard.php — Role router. Sends each role to its own dashboard.
 */
require_once __DIR__ . '/includes/auth.php';
$u = require_login();
redirect(dashboard_url($u['role']));
