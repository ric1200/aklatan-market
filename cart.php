<?php
/**
 * Shopping cart: add, update, remove, discount codes and the basket overview.
 * Every state change is a POST protected by a CSRF token; nothing is ever
 * changed by following a link.
 */
require_once __DIR__ . '/includes/bootstrap.php';

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

/** Reply either as JSON (fetch) or with a redirect back to where we came from. */
function cart_respond(bool $ok, string $message, string $return): void
{
    global $isAjax;
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode([
            'ok' => $ok,
            'message' => strip_tags($message),
            'count' => cart_count(),
            'subtotal' => money(cart_totals()['subtotal']),
        ]);
        exit;
    }
    if ($ok) { flash_success($message); } else { flash_error($message); }
    redirect($return);
}

if (is_post()) {
    csrf_guard();
    $action = post('action');
    $return = safe_next_url(post('return'), 'cart.php');

    switch ($action) {
        case 'add': {
            $productId = post_int('product_id');
            $quantity = max(1, min(99, post_int('quantity', 1)));
            [$ok, $message] = cart_add($productId, $quantity);
            cart_respond($ok, $message, $return);
            break;
        }
        case 'update': {
            // Accepts either a single product_id or quantities[] from the cart form.
            $quantities = post_raw('quantities', []);
            $messages = [];
            $ok = true;
            if (is_array($quantities)) {
                foreach ($quantities as $pid => $qty) {
                    [$rowOk, $rowMessage] = cart_update((int) $pid, (int) $qty);
                    if (!$rowOk) { $ok = false; $messages[] = $rowMessage; }
                }
            } else {
                [$ok, $message] = cart_update(post_int('product_id'), post_int('quantity'));
                $messages[] = $message;
            }
            cart_respond($ok, $messages ? implode(' ', $messages) : 'Cart updated.', 'cart.php');
            break;
        }
        case 'remove': {
            $productId = post_int('product_id');
            $product = product_by_id($productId, false);
            cart_remove($productId);
            cart_respond(true, ($product ? $product['name'] : 'That item') . ' was removed from your cart.', $return);
            break;
        }
        case 'clear': {
            cart_clear();
            discount_clear();
            cart_respond(true, 'Your cart is now empty.', 'cart.php');
            break;
        }
        case 'apply_discount': {
            $code = strtoupper(truncate(post_raw('discount_code'), 40));
            $totals = cart_totals();
            $check = discount_validate($code, (float) $totals['subtotal']);
            if ($check['ok']) {
                $_SESSION['discount_code'] = $check['code']['code'];
                cart_respond(true, $check['message'], 'cart.php');
            }
            cart_respond(false, $check['message'], 'cart.php');
            break;
        }
        case 'remove_discount': {
            discount_clear();
            cart_respond(true, 'Discount code removed.', 'cart.php');
            break;
        }
        default:
            cart_respond(false, 'That cart action is not recognised.', 'cart.php');
    }
    exit;
}

$items = cart_items();
$totals = cart_totals();
$threshold = setting_float('free_shipping_threshold', 1500);
$remaining = max(0, $threshold - (float) $totals['subtotal']);
$progress = $threshold > 0 ? min(100, (int) round(((float) $totals['subtotal'] / $threshold) * 100)) : 100;

render_header([
    'title' => 'Your cart',
    'description' => 'Review your basket before checkout.',
    'robots' => 'noindex, nofollow',
]);
render_breadcrumb([['label' => 'Home', 'url' => 'index.php'], ['label' => 'Shopping cart']]);
?>
<div class="container">
  <header class="page-head">
    <p class="eyebrow">Step 1 of 2</p>
    <h1>Your cart</h1>
    <p class="lede">
      <?php if ($items): ?>
        <?= cart_count() ?> <?= cart_count() === 1 ? 'item' : 'items' ?> ready to go.
      <?php else: ?>
        Nothing here yet — your basket is empty.
      <?php endif; ?>
    </p>
  </header>

  <?php if (!$items): ?>
    <?php render_empty_state(
        'cart',
        'Your cart is empty',
        'Browse the shelves and add a few titles. Anything you add is kept while you shop, and saved to your account once you sign in.',
        [
            ['label' => 'Browse all products', 'url' => url('products.php'), 'class' => 'btn-primary'],
            ['label' => 'See bestsellers', 'url' => url('products.php?sort=popular'), 'class' => 'btn-ghost'],
        ]); ?>
  <?php else: ?>
    <div class="cart-layout">
      <div>
        <form method="post" action="<?= e(url('cart.php')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update">
          <div class="cart-list">
            <?php foreach ($items as $item): $product = $item['product']; ?>
              <div class="cart-row <?= $item['available'] ? '' : 'is-unavailable' ?>">
                <img src="<?= e(product_image_url($product)) ?>" alt="<?= e($product['name']) ?> cover" width="400" height="600" loading="lazy">
                <div class="cart-row-main">
                  <p class="cart-row-title"><a href="<?= e(product_url($product)) ?>"><?= e($product['name']) ?></a></p>
                  <p class="cart-row-meta">
                    <?= e((string) ($product['creator'] ?? '')) ?>
                    <?php if (!empty($product['category_name'])): ?> · <?= e((string) $product['category_name']) ?><?php endif; ?>
                    · SKU <?= e((string) $product['sku']) ?>
                  </p>
                  <p class="cart-row-meta">
                    <?php if ($item['available']): ?>
                      <span class="stock-ok">In stock</span> · <?= e(money($product['price'])) ?> each
                    <?php else: ?>
                      <span class="stock-out">Out of stock — remove to continue</span>
                    <?php endif; ?>
                  </p>
                  <div class="cart-row-controls">
                    <div class="qty" data-qty>
                      <button type="button" data-step="-1" aria-label="Decrease quantity of <?= e($product['name']) ?>"><?= icon('minus', '', 16) ?></button>
                      <label class="sr-only" for="qty-<?= (int) $product['id'] ?>">Quantity of <?= e($product['name']) ?></label>
                      <input id="qty-<?= (int) $product['id'] ?>" type="number" name="quantities[<?= (int) $product['id'] ?>]"
                             value="<?= (int) $item['quantity'] ?>" min="0" max="<?= (int) $item['max_quantity'] ?>" inputmode="numeric">
                      <button type="button" data-step="1" aria-label="Increase quantity of <?= e($product['name']) ?>"><?= icon('plus', '', 16) ?></button>
                    </div>
                    <span class="cart-row-price"><?= e(money($item['line_total'])) ?></span>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="cluster mt-2">
            <button type="submit" class="btn btn-secondary"><?= icon('refresh', '', 17) ?> Update quantities</button>
            <a class="btn btn-quiet" href="<?= e(url('products.php')) ?>">Continue shopping</a>
          </div>
        </form>

        <form method="post" action="<?= e(url('cart.php')) ?>" class="mt-2" data-confirm="Empty your whole cart?">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="clear">
          <button type="submit" class="btn btn-quiet btn-sm"><?= icon('trash', '', 16) ?> Empty cart</button>
        </form>
      </div>

      <aside>
        <div class="summary-card sticky-summary">
          <h2>Order summary</h2>
          <div class="summary-lines">
            <div><span>Subtotal (<?= cart_count() ?> items)</span><span><?= e(money($totals['subtotal'])) ?></span></div>
            <?php if ($totals['discount'] > 0): ?>
              <div><span>Discount</span><span class="stock-ok">−<?= e(money($totals['discount'])) ?></span></div>
            <?php endif; ?>
            <div>
              <span>Shipping</span>
              <span><?= $totals['shipping'] > 0 ? e(money($totals['shipping'])) : 'Free' ?></span>
            </div>
          </div>
          <div class="summary-lines summary-total">
            <span>Total</span>
            <span class="price"><?= e(money($totals['total'])) ?></span>
          </div>

          <?php if ($threshold > 0): ?>
            <div class="shipping-progress">
              <?php if ($remaining > 0): ?>
                <p>Add <strong><?= e(money($remaining)) ?></strong> more for free shipping.</p>
              <?php else: ?>
                <p class="stock-ok">You have qualified for free shipping.</p>
              <?php endif; ?>
              <div class="bar" role="progressbar" aria-valuenow="<?= (int) $progress ?>" aria-valuemin="0" aria-valuemax="100"
                   aria-label="Progress toward free shipping"><span style="width: <?= (int) $progress ?>%"></span></div>
            </div>
          <?php endif; ?>

          <?php $applied = applied_discount(); ?>
          <?php if ($applied): ?>
            <div class="applied-code">
              <span><?= icon('tag', '', 15) ?> Code <strong><?= e($applied['code']) ?></strong> applied</span>
              <form method="post" action="<?= e(url('cart.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="remove_discount">
                <button type="submit">Remove</button>
              </form>
            </div>
          <?php else: ?>
            <form class="discount-form" method="post" action="<?= e(url('cart.php')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="apply_discount">
              <label class="sr-only" for="discount_code">Discount code</label>
              <input id="discount_code" type="text" name="discount_code" maxlength="40" placeholder="Discount code" autocomplete="off">
              <button type="submit" class="btn btn-ghost">Apply</button>
            </form>
          <?php endif; ?>

          <a class="btn btn-primary btn-block btn-lg" href="<?= e(url('checkout.php')) ?>">
            <?= icon('arrow-right', '', 18) ?> Proceed to checkout
          </a>
          <p class="summary-note">Prices, stock and totals are confirmed again on the server at checkout. Nothing is
            charged automatically on this site.</p>
        </div>
      </aside>
    </div>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
