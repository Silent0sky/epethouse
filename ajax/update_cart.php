<?php
/**
 * ajax/update_cart.php — Update quantity of a cart item.
 *
 * POST body: { cart_id: string, quantity: int }
 *   - quantity <= 0  → delete the row
 *   - quantity > 0   → update (clamped to available stock)
 *
 * Returns: { success:true, cart_count:int }  or  { success:false, message:string }
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

$cartId   = trim($body['cart_id'] ?? '');
$quantity = (int) ($body['quantity'] ?? 0);

if ($cartId === '') {
    json_response(['success' => false, 'message' => 'Missing cart id.'], 422);
}

// Make sure the cart row belongs to the current user
$cart = db_select_one(
    'SELECT c.id, c.quantity, p.stock_qty, p.in_stock
       FROM cart_items c
       JOIN products p ON p.id = c.product_id
      WHERE c.id = ? AND c.user_id = ?
      LIMIT 1',
    [$cartId, $u['id']]
);
if (!$cart) {
    json_response(['success' => false, 'message' => 'Cart item not found.'], 404);
}

if ($quantity <= 0) {
    db_execute('DELETE FROM cart_items WHERE id = ?', [$cartId]);
} else {
    $max = (int) $cart['stock_qty'];
    if ($max < 1) $max = 1;
    $quantity = min($quantity, $max);
    db_execute(
        'UPDATE cart_items SET quantity = ? WHERE id = ?',
        [$quantity, $cartId]
    );
}

$count = cart_count($u['id']);
json_response(['success' => true, 'cart_count' => $count]);
