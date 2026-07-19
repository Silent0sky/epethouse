<?php
/**
 * ajax/wishlist.php — Toggle a product on the user's wishlist.
 *
 * POST body: { product_id: string }
 * Returns: { success:true, action:'added'|'removed' }
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

$productId = trim($body['product_id'] ?? '');
if ($productId === '') {
    json_response(['success' => false, 'message' => 'Missing product id.'], 422);
}

// Validate the product actually exists
$exists = db_scalar('SELECT id FROM products WHERE id = ? LIMIT 1', [$productId]);
if (!$exists) {
    json_response(['success' => false, 'message' => 'Product not found.'], 404);
}

$row = db_select_one(
    'SELECT id FROM wishlist_items WHERE user_id = ? AND product_id = ? LIMIT 1',
    [$u['id'], $productId]
);

if ($row) {
    db_execute('DELETE FROM wishlist_items WHERE id = ?', [$row['id']]);
    json_response(['success' => true, 'action' => 'removed']);
}

db_insert('wishlist_items', [
    'id'         => gen_id('wl_'),
    'user_id'    => $u['id'],
    'product_id' => $productId,
]);
json_response(['success' => true, 'action' => 'added']);
