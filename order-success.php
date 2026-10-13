<?php
/** Order confirmation: receipt, next steps and manual payment submission. */
require_once __DIR__ . '/includes/bootstrap.php';

$orderNumber = truncate(get_param('order'), 40);
$order = $orderNumber !== '' ? order_by_number($orderNumber) : null;
if (!$order) {
    aklatan_abort(404, 'We could not find that order',
        'Check the order number in your confirmation message, or open your order history.');
}

require_login('Please sign in to open that order — orders are only visible to the account that placed them.');
if (!customer_can_view_order($order)) {
    aklatan_abort(403, 'That order belongs to another account',
        'For everyone\'s privacy, an order can only be viewed by the customer who placed it and by store staff.');
}

handle_customer_payment_submission($order, 'order-success.php?order=' . urlencode((string) $order['order_number']));

$order = order_by_number($orderNumber);
$items = order_items_for((int) $order['id']);
$payment = order_latest_payment((int) $order['id']);
$timeline = order_timeline($order);
$isGcash = $order['payment_method'] === 'gcash';
$needsReference = $isGcash && in_array($order['payment_status'], ['unpaid', 'rejected'], true);

render_header([
    'title' => 'Order ' . $order['order_number'],
    'description' => 'Your Aklatan Market order confirmation.',
    'robots' => 'noindex, nofollow',
]);
render_breadcrumb([['label' => 'Home', 'url' => 'index.php'], ['label' => 'Order confirmation']]);
?>
<div class="container">
  <header class="page-head">
    <p class="eyebrow">Order received</p>
    <h1>Thank you — we have your order</h1>
    <p class="lede">Your reference number is <strong><?= e($order['order_number']) ?></strong>.
      Keep it handy; it is the quickest way for us to look up your order.</p>
  </header>

  <div class="cluster mb-2">
    <?= order_status_badge((string) $order['status']) ?>
    <?= payment_status_badge((string) $order['payment_status']) ?>
    <span class="pill"><?= e(payment_method_label((string) $order['payment_method'])) ?></span>
  </div>

  <div class="account-layout">
    <div>
      <?php if ($needsReference): ?>
        <div class="panel">
          <div class="panel-head"><h2>Next step: send your GCash payment</h2></div>
          <p>Your order is reserved. Send <strong><?= e(money($order['total'])) ?></strong> and then enter the
            reference number from your GCash receipt below. A bookseller checks every manual payment by hand,
            usually within one working day.</p>
          <div class="pay-instructions">
            <dl>
              <dt>GCash account name</dt><dd><?= e(setting('gcash_account_name', 'Aklatan Market')) ?></dd>
              <dt>GCash number</dt><dd><?= e(setting('gcash_account_number', '—')) ?>
                <?php if (setting('gcash_account_number')): ?>
                  <button type="button" class="btn btn-quiet btn-sm" data-copy="<?= e(setting('gcash_account_number')) ?>">Copy</button>
                <?php endif; ?>
              </dd>
              <dt>Amount</dt><dd><?= e(money($order['total'])) ?></dd>
            </dl>
            <p class="small mt-1"><?= e(setting('gcash_instructions', '')) ?></p>
            <p class="small mt-1"><strong><?= e(setting('payment_note', 'We never ask for your GCash PIN or OTP.')) ?></strong></p>
          </div>

          <?php if ($order['payment_status'] === 'rejected'): ?>
            <div class="notice notice-error mt-2" role="alert">
              <?= icon('alert', 'notice-icon', 18) ?>
              <div class="notice-body"><strong>A bookseller could not verify the previous reference.</strong>
                <?= $payment && $payment['admin_note'] ? e((string) $payment['admin_note']) : 'Please double-check the reference number and submit it again.' ?></div>
            </div>
          <?php endif; ?>

          <form method="post" action="<?= e(url('order-success.php?order=' . urlencode((string) $order['order_number']))) ?>"
                enctype="multipart/form-data" class="form-grid mt-2">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="submit_payment">
            <input type="hidden" name="method" value="gcash">
            <input type="hidden" name="amount" value="<?= e(money_plain($order['total'])) ?>">
            <div class="field-row">
              <div class="field">
                <label for="reference_number">GCash reference number</label>
                <input id="reference_number" type="text" name="reference_number" maxlength="80" required
                       placeholder="e.g. 0031 550 118 442" autocomplete="off">
              </div>
              <div class="field">
                <label for="payer_name">Name on the GCash account <span class="hint">(optional)</span></label>
                <input id="payer_name" type="text" name="payer_name" maxlength="160" autocomplete="off">
              </div>
            </div>
            <div class="field">
              <label for="payment_proof">Screenshot of your receipt <span class="hint">(optional, JPG/PNG up to <?= e(human_bytes((int) UPLOAD_MAX_BYTES)) ?>)</span></label>
              <input id="payment_proof" type="file" name="payment_proof" accept="image/jpeg,image/png,image/gif,image/webp" data-preview="proof-preview2">
              <img id="proof-preview2" alt="Preview of the receipt you selected" hidden style="max-width:220px;margin-top:8px;border-radius:10px">
            </div>
            <div class="field">
              <label for="customer_note">Note for the shop <span class="hint">(optional)</span></label>
              <input id="customer_note" type="text" name="customer_note" maxlength="500" placeholder="For example: sent from my partner's GCash account">
            </div>
            <button type="submit" class="btn btn-primary btn-lg">Submit payment details</button>
          </form>
        </div>
      <?php elseif ($isGcash && $order['payment_status'] === 'pending_verification'): ?>
        <div class="notice notice-info">
          <?= icon('clock', 'notice-icon', 18) ?>
          <div class="notice-body"><strong>Your payment reference is waiting for verification.</strong>
            Nothing else is needed from you. We will update the order as soon as a bookseller confirms the transfer —
            usually within one working day.
            <span class="small"><?= e(setting('payment_note', 'We never ask for your GCash PIN or OTP.')) ?></span></div>
        </div>
      <?php elseif ($order['payment_status'] === 'verified'): ?>
        <div class="notice notice-success">
          <?= icon('check-circle', 'notice-icon', 18) ?>
          <div class="notice-body"><strong>Payment verified.</strong> Thank you! We are preparing your parcel.</div>
        </div>
      <?php elseif ($order['payment_method'] === 'cod'): ?>
        <div class="notice notice-info">
          <?= icon('truck', 'notice-icon', 18) ?>
          <div class="notice-body"><strong>Please prepare <?= e(money($order['total'])) ?> in cash.</strong>
            The courier collects payment on delivery. Nothing to pay online.</div>
        </div>
      <?php elseif ($order['payment_method'] === 'pickup'): ?>
        <div class="notice notice-info">
          <?= icon('pin', 'notice-icon', 18) ?>
          <div class="notice-body"><strong>Pay when you collect.</strong> Bring your order number and a valid ID to
            <?= e((string) ($order['pickup_location'] ?: setting('pickup_address', 'the store'))) ?>.</div>
        </div>
      <?php endif; ?>

      <?php if ($payment && $payment['proof_image']): ?>
        <p class="small muted mt-2"><?= icon('image', '', 15) ?> A receipt image is attached to this order and can be
          seen by store staff only.</p>
      <?php endif; ?>

      <div class="panel mt-2">
        <div class="panel-head"><h2>What happens next</h2></div>
        <ul class="timeline">
          <?php foreach ($timeline as $step): ?>
            <li class="<?= $step['state'] === 'done' ? 'is-done' : ($step['state'] === 'current' ? 'is-current' : '') ?>">
              <span class="dot"><?= icon($step['state'] === 'done' ? 'check' : 'clock', '', 13) ?></span>
              <span>
                <span class="tl-title"><?= e($step['label']) ?></span>
                <span class="tl-note"><?= e($step['note']) ?></span>
                <?php if ($step['key'] === 'pending' && !empty($order['created_at'])): ?>
                  <span class="tl-note">Placed <?= e(format_datetime($order['created_at'])) ?></span>
                <?php endif; ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div class="panel mt-2">
        <div class="panel-head"><h2>Items in this order</h2></div>
        <?php render_order_items_table($items); ?>
      </div>
    </div>

    <aside>
      <div class="summary-card">
        <h2>Order summary</h2>
        <?php render_order_totals($order); ?>

        <h3 class="mt-2">Delivering to</h3>
        <p class="small">
          <strong><?= e($order['customer_name']) ?></strong><br>
          <?= e($order['address_line1']) ?><?= $order['address_line2'] ? ', ' . e($order['address_line2']) : '' ?><br>
          <?= e($order['city']) ?>, <?= e($order['province']) ?><?= $order['postal_code'] ? ' ' . e($order['postal_code']) : '' ?><br>
          <?= e($order['phone']) ?><br>
          <?= e($order['email']) ?>
        </p>
        <?php if (!empty($order['delivery_notes'])): ?>
          <p class="small mt-1"><strong>Delivery notes:</strong> <?= e($order['delivery_notes']) ?></p>
        <?php endif; ?>

        <a class="btn btn-ghost btn-block mt-2" href="<?= e(url('account/order-details.php?order=' . urlencode((string) $order['order_number']))) ?>">
          <?= icon('package', '', 17) ?> Open full order details
        </a>
        <a class="btn btn-quiet btn-block mt-1" href="<?= e(url('products.php')) ?>">Continue shopping</a>
      </div>
    </aside>
  </div>
</div>
<?php render_footer(); ?>
