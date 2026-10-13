<?php
/**
 * Shopping cart.
 *
 * Guests keep a session cart. Signed-in customers keep a database cart so the
 * basket survives across devices and sessions. Signing in merges the two.
 */

function cart_is_account_cart(): bool
{
    return current_user_id() !== null;
}

/** Cart id for the signed-in customer (created on demand). */
function cart_ensure(): ?int
{
    $userId = current_user_id();
    if ($userId === null) { return null; }

    $cartId = fetch_value('SELECT id FROM carts WHERE user_id = :uid', ['uid' => $userId]);
    if ($cartId) { return (int) $cartId; }

    return db_insert('carts', ['user_id' => $userId]);
}

/** Raw cart as [product_id => quantity]. */
function cart_raw(): array
{
    if (!cart_is_account_cart()) {
        $raw = $_SESSION['guest_cart'] ?? [];
        return is_array($raw) ? array_map('intval', $raw) : [];
    }
    $cartId = cart_ensure();
    if ($cartId === null) { return []; }
    $out = [];
    foreach (fetch_all('SELECT product_id, quantity FROM cart_items WHERE cart_id = :cid', ['cid' => $cartId]) as $row) {
        $out[(int) $row['product_id']] = (int) $row['quantity'];
    }
    return $out;
}

function cart_write_raw(int $productId, int $quantity): void
{
    $quantity = max(0, $quantity);
    if (!cart_is_account_cart()) {
        if (!isset($_SESSION['guest_cart']) || !is_array($_SESSION['guest_cart'])) { $_SESSION['guest_cart'] = []; }
        if ($quantity === 0) { unset($_SESSION['guest_cart'][$productId]); }
        else { $_SESSION['guest_cart'][$productId] = $quantity; }
        return;
    }
    $cartId = cart_ensure();
    if ($cartId === null) { return; }
    if ($quantity === 0) {
        q('DELETE FROM cart_items WHERE cart_id = :cid AND product_id = :pid', ['cid' => $cartId, 'pid' => $productId]);
        return;
    }
    q('INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (:cid, :pid, :qty)
       ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)',
        ['cid' => $cartId, 'pid' => $productId, 'qty' => $quantity]);
}

/** @return array{0:bool,1:string} */
function cart_add(int $productId, int $quantity = 1): array
{
    $quantity = max(1, min(99, $quantity));
    $product = product_by_id($productId, true);
    if (!$product) { return [false, 'That item is no longer available.']; }

    $stock = (int) $product['stock_quantity'];
    if ($stock <= 0) { return [false, e($product['name']) . ' is out of stock right now.']; }

    $raw = cart_raw();
    $current = (int) ($raw[$productId] ?? 0);
    $wanted = $current + $quantity;

    if ($wanted > $stock) {
        cart_write_raw($productId, $stock);
        return [false, 'Only ' . $stock . ' ' . ($stock === 1 ? 'copy' : 'copies') . ' of ' . e($product['name']) . ' are in stock, so your cart was adjusted.'];
    }
    cart_write_raw($productId, $wanted);
    return [true, e($product['name']) . ' was added to your cart.'];
}

/** @return array{0:bool,1:string} */
function cart_update(int $productId, int $quantity): array
{
    $product = product_by_id($productId, true);
    if (!$product) {
        cart_write_raw($productId, 0);
        return [false, 'That item was removed because it is no longer available.'];
    }
    if ($quantity <= 0) {
        cart_write_raw($productId, 0);
        return [true, e($product['name']) . ' was removed from your cart.'];
    }
    $stock = (int) $product['stock_quantity'];
    if ($quantity > $stock) {
        cart_write_raw($productId, max(0, $stock));
        return [false, 'Only ' . $stock . ' ' . ($stock === 1 ? 'copy' : 'copies') . ' of ' . e($product['name']) . ' are in stock.'];
    }
    cart_write_raw($productId, $quantity);
    return [true, 'Cart updated.'];
}

function cart_remove(int $productId): void
{
    cart_write_raw($productId, 0);
}

function cart_clear(): void
{
    if (!cart_is_account_cart()) {
        unset($_SESSION['guest_cart']);
        return;
    }
    $cartId = cart_ensure();
    if ($cartId !== null) { q('DELETE FROM cart_items WHERE cart_id = :cid', ['cid' => $cartId]); }
}

/**
 * Full cart rows joined with live product data.
 * Inactive or deleted products are pruned from the stored cart automatically.
 *
 * @return array<int,array{product:array,quantity:int,line_total:float,available:bool}>
 */
function cart_items(): array
{
    $raw = array_filter(cart_raw(), function ($qty) { return (int) $qty > 0; });
    if (!$raw) { return []; }

    $ids = array_map('intval', array_keys($raw));
    $in = implode(',', array_fill(0, count($ids), '?'));
    $products = fetch_all(
        'SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM products p LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.id IN (' . $in . ')', $ids);

    $byId = [];
    foreach ($products as $p) { $byId[(int) $p['id']] = $p; }

    $items = [];
    foreach ($raw as $pid => $qty) {
        $pid = (int) $pid;
        if (!isset($byId[$pid]) || (int) $byId[$pid]['is_active'] !== 1) {
            cart_write_raw($pid, 0);
            continue;
        }
        $product = $byId[$pid];
        $stock = (int) $product['stock_quantity'];
        $qty = (int) $qty;
        $available = $stock > 0;
        if ($available && $qty > $stock) { $qty = $stock; cart_write_raw($pid, $qty); }
        $items[] = [
            'product' => $product,
            'quantity' => $qty,
            'line_total' => round((float) $product['price'] * $qty, 2),
            'available' => $available,
            'max_quantity' => min($stock, 99),
        ];
    }
    return $items;
}

function cart_count(): int
{
    $count = 0;
    foreach (cart_raw() as $qty) { $count += (int) $qty; }
    return $count;
}

/** @return array{subtotal:float,shipping:float,discount:float,total:float,item_count:int,payable_subtotal:float} */
function cart_totals(string $method = 'cod'): array
{
    $items = cart_items();
    $subtotal = 0.0;
    $count = 0;
    foreach ($items as $item) {
        if (!$item['available']) { continue; }
        $subtotal += $item['line_total'];
        $count += (int) $item['quantity'];
    }
    $subtotal = round($subtotal, 2);

    $discount = 0.0;
    $applied = applied_discount();
    if ($applied) {
        $check = discount_validate($applied['code'], $subtotal);
        if ($check['ok']) {
            $discount = round((float) $check['discount'], 2);
        } else {
            unset($_SESSION['discount_code']);
        }
    }

    $shipping = shipping_fee_for($subtotal, $method);
    $total = max(0.0, round($subtotal - $discount + $shipping, 2));

    return [
        'subtotal' => $subtotal,
        'shipping' => round($shipping, 2),
        'discount' => $discount,
        'total' => $total,
        'item_count' => $count,
    ];
}

/** Fold a guest session cart into the customer's stored cart after sign-in. */
function merge_guest_cart_into_account(int $userId, array $guestCart): void
{
    if (!$guestCart) { return; }
    $cartId = cart_ensure();
    if ($cartId === null) { return; }

    foreach ($guestCart as $pid => $qty) {
        $pid = (int) $pid;
        $qty = (int) $qty;
        if ($pid <= 0 || $qty <= 0) { continue; }
        $product = product_by_id($pid, true);
        if (!$product || (int) $product['stock_quantity'] <= 0) { continue; }

        $existing = (int) fetch_value('SELECT quantity FROM cart_items WHERE cart_id = :cid AND product_id = :pid',
            ['cid' => $cartId, 'pid' => $pid], 0);
        $merged = min($existing + $qty, (int) $product['stock_quantity'], 99);
        if ($merged <= 0) { continue; }
        q('INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (:cid, :pid, :qty)
           ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)',
            ['cid' => $cartId, 'pid' => $pid, 'qty' => $merged]);
    }
}
