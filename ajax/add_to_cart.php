<?php
/**
 * ajax/add_to_cart.php — Add (or upsert) a product to the user's cart.
 *
 * POST body (JSON or form-encoded):
 *   product_id : string
 *   quantity   : int (optional, default 1)
 *
 * Returns: { success:true, cart_count:int }  or  { success:false, message:string }
 */
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// ─── Auth gate (AJAX-friendly: 401 JSON instead of redirect) ────────
$u = current_user();
if (!$u) {
    json_response(['success' => false, 'message' => 'Please log in to continue.'], 401);
}

// ─── CSRF ───────────────────────────────────────────────────────────
csrf_verify_ajax();

// ─── Read JSON body (app.js sends JSON) ─────────────────────────────
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) $body = $_POST;

$productId = trim($body['product_id'] ?? '');
$quantity  = max(1, (int) ($body['quantity'] ?? 1));

if ($productId === '') {
    json_response(['success' => false, 'message' => 'Missing product id.'], 422);
}

// ─── Validate product exists & is in stock ──────────────────────────
$product = db_select_one(
    'SELECT id, name, price, in_stock, stock_qty FROM products WHERE id = ? LIMIT 1',
    [$productId]
);
if (!$product) {
    json_response(['success' => false, 'message' => 'Product not found.'], 404);
}
if (!$product['in_stock'] || (int) $product['stock_qty'] <= 0) {
    json_response(['success' => false, 'message' => 'Out of stock.'], 422);
}

// ─── Upsert into cart_items (unique user_id + product_id) ───────────
$existing = db_select_one(
    'SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ? LIMIT 1',
    [$u['id'], $productId]
);

if ($existing) {
    $newQty = (int) $existing['quantity'] + $quantity;
    // Don't exceed available stock
    if ($newQty > (int) $product['stock_qty']) {
        $newQty = (int) $product['stock_qty'];
    }
    db_execute(
        'UPDATE cart_items SET quantity = ? WHERE id = ?',
        [$newQty, $existing['id']]
    );
} else {
    $qty = min($quantity, (int) $product['stock_qty']);
    db_insert('cart_items', [
        'id'         => gen_id('ci_'),
        'user_id'    => $u['id'],
        'product_id' => $productId,
        'quantity'   => $qty,
    ]);
}

$count = cart_count($u['id']);
json_response(['success' => true, 'cart_count' => $count]);
