<?php
/**
 * ajax/mark_notification_read.php — Mark a notification as read.
 *
 * POST body: { notification_id: string }
 * Returns: { success:true }
 */
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

$u = current_user();
if (!$u) {
    json_response(['success' => false, 'message' => 'Please log in to continue.'], 401);
}

csrf_verify_ajax();

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) $body = $_POST;

$id = trim($body['notification_id'] ?? '');
if ($id === '') {
    json_response(['success' => false, 'message' => 'Missing notification id.'], 422);
}

// Owned-by-user guard
db_execute(
    'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?',
    [$id, $u['id']]
);

json_response(['success' => true]);
