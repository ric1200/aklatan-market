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
        case 'selection': {
            cart_save_selection(post_raw('selected_ids', []));
            // Native POST/redirect also works without JavaScript.
            redirect('cart.php');
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
            if (post('selection_present') === '1') {
                cart_save_selection(post_raw('selected_ids', []));
            }
            if (post('checkout_selected') === '1') {
                if (!$ok) {
                    cart_respond(false, implode(' ', $messages), 'cart.php');
                }
                if (!cart_selected_items()) {
                    cart_respond(false, 'Select at least one available item to check out.', 'cart.php');
                }
                redirect('checkout.php');
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
            $totals = cart_totals('cod', cart_selected_items());
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
// Prune choices that have become unavailable since the previous visit.
cart_save_selection(cart_selected_ids($items, true), $items);
$selectedIds = cart_selected_ids($items);
$selectedItems = cart_selected_items($items);
$totals = cart_totals('cod', $selectedItems);
$availableCount = count(array_filter($items, function ($item) { return $item['available']; }));
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
        <form id="cart-selection-form" method="post" action="<?= e(url('cart.php')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="selection_present" value="1">
          <div class="cart-select-toolbar">
            <label class="cart-select-label">
              <input type="checkbox" data-select-all <?= $availableCount > 0 && count($selectedIds) === $availableCount ? 'checked' : '' ?> <?= $availableCount === 0 ? 'disabled' : '' ?>>
              <span>Select all available items</span>
            </label>
            <span class="small muted" data-selection-status role="status" aria-live="polite"><?= count($selectedIds) ?> selected</span>
          </div>
          <p class="small muted mb-2">Only checked items will be ordered. Unchecked items stay in your cart.</p>
          <div class="cart-list">
            <?php foreach ($items as $item): $product = $item['product']; ?>
              <div class="cart-row has-selection <?= $item['available'] ? '' : 'is-unavailable' ?>">
                <label class="cart-item-select">
                  <input type="checkbox" name="selected_ids[]" value="<?= (int) $product['id'] ?>" data-cart-select
                         aria-label="Select <?= e($product['name']) ?> for checkout"
                         <?= in_array((int) $product['id'], $selectedIds, true) && $item['available'] ? 'checked' : '' ?>
                         <?= !$item['available'] ? 'disabled' : '' ?>>
                </label>
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
                      <span class="stock-out">Out of stock — cannot be selected</span>
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
        <div class="summary-card sticky-summary" data-cart-summary>
          <h2>Selected items summary</h2>
          <div class="summary-lines">
            <div><span>Subtotal (<?= (int) $totals['item_count'] ?> selected items)</span><span><?= e(money($totals['subtotal'])) ?></span></div>
            <?php if ($totals['discount'] > 0): ?>
              <div><span>Discount</span><span class="stock-ok">−<?= e(money($totals['discount'])) ?></span></div>
            <?php endif; ?>
            <div>
              <span>Shipping</span>
              <span><?= $totals['item_count'] === 0 ? '—' : ($totals['shipping'] > 0 ? e(money($totals['shipping'])) : 'Free') ?></span>
            </div>
          </div>
          <div class="summary-lines summary-total">
            <span>Total</span>
            <span class="price"><?= e(money($totals['total'])) ?></span>
          </div>

          <?php if ($threshold > 0 && $totals['item_count'] > 0): ?>
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

          <button type="submit" form="cart-selection-form" name="checkout_selected" value="1"
                  class="btn btn-primary btn-block btn-lg" data-selected-checkout <?= !$selectedItems ? 'disabled' : '' ?>>
            <?= icon('arrow-right', '', 18) ?> Checkout selected items
          </button>
          <?php if (!$selectedItems): ?>
            <p class="summary-note">Select at least one available item to continue.</p>
          <?php endif; ?>
          <p class="summary-note">Prices, stock and totals are confirmed again on the server at checkout. Nothing is
            charged automatically on this site.</p>
        </div>
      </aside>
    </div>
  <?php endif; ?>
</div>
<style>
.cart-select-toolbar { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:12px; margin-bottom:10px; }
.cart-select-label { display:inline-flex; align-items:center; gap:10px; cursor:pointer; }
.cart-item-select { display:flex; align-items:flex-start; justify-content:center; padding-top:4px; }
.cart-select-label input[type="checkbox"], .cart-item-select input[type="checkbox"] { width:20px; height:20px; flex:0 0 20px; margin:0; accent-color:var(--teal, #286f63); cursor:pointer; }
.cart-row.has-selection { grid-template-columns:20px 74px minmax(0, 1fr); }
[data-cart-summary][aria-busy="true"] { opacity:.7; }
[data-selected-checkout]:disabled { opacity:.6; cursor:not-allowed; }
@media (min-width:600px) { .cart-row.has-selection { grid-template-columns:20px 96px minmax(0, 1fr); } }
@media (max-width:380px) { .cart-row.has-selection { gap:8px; padding:10px; grid-template-columns:20px 52px minmax(0, 1fr); } .cart-row.has-selection > img { width:52px; } }
</style>
<script>
(() => {
  'use strict';
  const form = document.getElementById('cart-selection-form');
  if (!form) return;
  const all = form.querySelector('[data-select-all]');
  const status = form.querySelector('[data-selection-status]');
  const choices = [...form.querySelectorAll('[data-cart-select]:not(:disabled)')];
  let running = false, queued = false, timer;
  function updateState(message) {
    const n = choices.filter(input => input.checked).length;
    all.checked = choices.length > 0 && n === choices.length;
    all.indeterminate = n > 0 && n < choices.length;
    status.textContent = message || `${n} selected`;
    const button = document.querySelector('[data-selected-checkout]');
    if (button) button.disabled = n === 0 || running || queued;
  }
  function markBusy() {
    const summary = document.querySelector('[data-cart-summary]');
    if (summary) { summary.inert = true; summary.setAttribute('aria-busy', 'true'); }
  }
  async function save() {
    if (running) { queued = true; return; }
    running = true;
    queued = false;
    markBusy();
    updateState('Updating selected totals…');
    const body = new FormData(form);
    body.set('action', 'selection');
    try {
      const response = await fetch(form.getAttribute('action') || window.location.href, { method:'POST', body, credentials:'same-origin', cache:'no-store' });
      if (!response.ok) throw new Error('Selection update failed');
      const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
      const summary = doc.querySelector('[data-cart-summary]');
      if (!summary) throw new Error('Cart response missing');
      if (!queued) document.querySelector('[data-cart-summary]').replaceWith(summary);
      status.textContent = '';
    } catch (error) {
      queued = false;
      // Do not allow checkout against a visibly stale summary.
      running = false;
      markBusy();
      updateState('Could not update totals. Click Update quantities to save and retry.');
      const button = document.querySelector('[data-selected-checkout]');
      if (button) button.disabled = true;
      return;
    }
    running = false;
    if (queued) { clearTimeout(timer); save(); }
    else updateState();
  }
  function changed() {
    queued = true;
    markBusy();
    updateState('Updating selected totals…');
    clearTimeout(timer);
    timer = setTimeout(save, 200);
  }
  all.addEventListener('change', () => { choices.forEach(input => { input.checked = all.checked; }); changed(); });
  choices.forEach(input => input.addEventListener('change', changed));
  // Pending background writes must finish before a native form navigation.
  // Quantity updates and checkout always submit selections again server-side.
  form.addEventListener('submit', event => {
    if (running || queued) {
      event.preventDefault();
      updateState('Please wait for selected totals to finish updating.');
    }
  });
  updateState();
})();
</script>

<?php render_footer(); ?>
