<?php
/** Full details for a single customer order. */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_login('Please sign in to open your order.');

$orderNumber = truncate(get_param('order'), 40);
$order = $orderNumber !== '' ? order_by_number($orderNumber) : null;
if (!$order) {
    aklatan_abort(404, 'We could not find that order',
        'Check the order number and try again, or open your order history for the full list.');
}
if (!customer_can_view_order($order)) {
    log_error('Blocked cross-account order view: user #' . (int) current_user_id() . ' requested order ' . $order['order_number']);
    aklatan_abort(403, 'That order belongs to another account',
        'For everyone\'s privacy an order can only be opened by the customer who placed it and by store staff.');
}

$redirectTo = 'account/order-details.php?order=' . urlencode((string) $order['order_number']);
handle_customer_payment_submission($order, $redirectTo);
$order = order_by_number($orderNumber);

/* ----------------------------------------------------------- cancellation -- */
if (is_post() && post('action') === 'cancel_order') {
    csrf_guard();
    if ((int) $order['user_id'] !== (int) current_user_id() && !is_admin()) {
        aklatan_abort(403, 'That order belongs to another account', 'You can only cancel your own orders.');
    }
    if (!in_array($order['status'], ['pending', 'confirmed'], true)) {
        flash_error('This order can no longer be cancelled because it is already '
            . strtolower(order_status_label((string) $order['status'])) . '.');
        redirect($redirectTo);
    }
    $pdo = db();
    try {
        $pdo->beginTransaction();
        if (!(int) $order['stock_restored']) {
            foreach (order_items_for((int) $order['id']) as $item) {
                if (!empty($item['product_id'])) {
                    q('UPDATE products SET stock_quantity = stock_quantity + :qty WHERE id = :id',
                        ['qty' => (int) $item['quantity'], 'id' => (int) $item['product_id']]);
                }
            }
        }
        db_update('orders', ['status' => 'cancelled', 'stock_restored' => 1], 'id', (int) $order['id']);
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        log_error('cancel order failed: ' . $ex->getMessage());
        flash_error('We could not cancel that order just now. Please contact the store.');
        redirect($redirectTo);
    }
    audit_log('order.cancelled_by_customer', 'order', (int) $order['id'], 'Order ' . $order['order_number']);
    flash_success('Order ' . $order['order_number'] . ' was cancelled and the stock returned to the shelf.');
    redirect($redirectTo);
}

$items = order_items_for((int) $order['id']);
$payments = order_payments((int) $order['id']);
$payment = $payments ? $payments[0] : null;
$timeline = order_timeline($order);
$canCancel = in_array($order['status'], ['pending', 'confirmed'], true);
$needsReference = $order['payment_method'] === 'gcash' && in_array($order['payment_status'], ['unpaid', 'rejected'], true);

// Which items in this order can still be reviewed? (delivered orders only)
$reviewable = [];
if ($order['status'] === 'delivered') {
    $productIds = [];
    foreach ($items as $item) {
        if (!empty($item['product_id'])) { $productIds[] = (int) $item['product_id']; }
    }
    if ($productIds) {
        $in = implode(',', array_fill(0, count($productIds), '?'));
        $existing = [];
        foreach (fetch_all('SELECT product_id FROM reviews WHERE user_id = ? AND product_id IN (' . $in . ')',
                    array_merge([(int) $user['id']], $productIds)) as $row) {
            $existing[(int) $row['product_id']] = true;
        }
        $slugs = [];
        foreach (fetch_all('SELECT id, slug FROM products WHERE id IN (' . $in . ')', $productIds) as $row) {
            $slugs[(int) $row['id']] = (string) $row['slug'];
        }
        foreach ($items as $item) {
            $pid = (int) ($item['product_id'] ?? 0);
            if ($pid > 0 && empty($existing[$pid])) {
                $item['product_slug'] = $slugs[$pid] ?? '';
                $reviewable[] = $item;
            }
        }
    }
}

render_account_start([
    'current' => 'orders',
    'title' => 'Order ' . $order['order_number'],
    'eyebrow' => 'Placed ' . format_datetime($order['created_at']),
    'description' => '',
    'breadcrumb' => [
        ['label' => 'Home', 'url' => 'index.php'],
        ['label' => 'My account', 'url' => 'account/index.php'],
        ['label' => 'Orders', 'url' => 'account/orders.php'],
        ['label' => $order['order_number']],
    ],
]);
?>
<p class="cluster">
  <?= order_status_badge((string) $order['status']) ?>
  <?= payment_status_badge((string) $order['payment_status']) ?>
  <span class="pill"><?= e(payment_method_label((string) $order['payment_method'])) ?></span>
</p>

<?php if ($needsReference): ?>
  <div class="panel mt-2">
    <div class="panel-head"><h2>Submit your GCash payment</h2></div>
    <?php if ($order['payment_status'] === 'rejected'): ?>
      <div class="notice notice-error" role="alert">
        <?= icon('alert', 'notice-icon', 18) ?>
        <div class="notice-body"><strong>The previous reference could not be verified.</strong>
          <?= $payment && $payment['admin_note'] ? e((string) $payment['admin_note']) : 'Please check the number and submit it again.' ?></div>
      </div>
    <?php endif; ?>
    <div class="pay-instructions">
      <p><strong>Send <?= e(money($order['total'])) ?> to</strong></p>
      <dl>
        <dt>Account name</dt><dd><?= e(setting('gcash_account_name', 'Aklatan Market')) ?></dd>
        <dt>GCash number</dt><dd><?= e(setting('gcash_account_number', '—')) ?>
          <?php if (setting('gcash_account_number')): ?>
            <button type="button" class="btn btn-quiet btn-sm" data-copy="<?= e(setting('gcash_account_number')) ?>">Copy</button>
          <?php endif; ?>
        </dd>
      </dl>
      <p class="small mt-1"><?= e(setting('gcash_instructions', '')) ?></p>
    </div>
    <form method="post" action="<?= e(url($redirectTo)) ?>" enctype="multipart/form-data" class="form-grid mt-2">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="submit_payment">
      <input type="hidden" name="method" value="gcash">
      <input type="hidden" name="amount" value="<?= e(money_plain($order['total'])) ?>">
      <div class="field-row">
        <div class="field">
          <label for="reference_number">GCash reference number</label>
          <input id="reference_number" type="text" name="reference_number" maxlength="80" required autocomplete="off">
        </div>
        <div class="field">
          <label for="payer_name">Name on the GCash account <span class="hint">(optional)</span></label>
          <input id="payer_name" type="text" name="payer_name" maxlength="160" autocomplete="off">
        </div>
      </div>
      <div class="field">
        <label for="payment_proof">Screenshot of your receipt <span class="hint">(optional, JPG/PNG up to <?= e(human_bytes((int) UPLOAD_MAX_BYTES)) ?>)</span></label>
        <input id="payment_proof" type="file" name="payment_proof" accept="image/jpeg,image/png,image/gif,image/webp" data-preview="proof-preview3">
        <img id="proof-preview3" alt="Preview of the receipt you selected" hidden style="max-width:200px;margin-top:8px;border-radius:10px">
      </div>
      <button type="submit" class="btn btn-primary">Submit payment details</button>
    </form>
  </div>
<?php endif; ?>

<div class="panel mt-2">
  <div class="panel-head"><h2>Progress</h2></div>
  <ul class="timeline">
    <?php foreach ($timeline as $step): ?>
      <li class="<?= $step['state'] === 'done' ? 'is-done' : ($step['state'] === 'current' ? 'is-current' : '') ?>">
        <span class="dot"><?= icon($step['state'] === 'done' ? 'check' : 'clock', '', 13) ?></span>
        <span>
          <span class="tl-title"><?= e($step['label']) ?></span>
          <span class="tl-note"><?= e($step['note']) ?></span>
        </span>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php if (!empty($order['admin_notes'])): ?>
    <p class="small muted">Message from the shop: <?= e((string) $order['admin_notes']) ?></p>
  <?php endif; ?>
</div>

<div class="panel" id="review">
  <div class="panel-head"><h2>Items (<?= count($items) ?>)</h2></div>
  <?php render_order_items_table($items); ?>
  <div class="summary-card mt-2" style="box-shadow:none">
    <?php render_order_totals($order); ?>
  </div>

  <?php if ($reviewable): ?>
    <h3 class="mt-2">Review what you received</h3>
    <p class="small muted">Thank you for your order. Reviews help other readers decide — they appear publicly once a bookseller approves them.</p>
    <div class="cluster mt-1">
      <?php foreach ($reviewable as $item): ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('product.php?slug=' . urlencode((string) $item['product_slug']) . '#reviews')) ?>">
          <?= icon('star', '', 15) ?> Review <?= e(truncate($item['product_name'], 30)) ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php elseif ($order['status'] === 'delivered'): ?>
    <p class="small muted mt-2"><?= icon('check-circle', '', 15) ?> Every item in this order has already been reviewed. Thank you!</p>
  <?php endif; ?>
</div>

<div class="info-grid mt-2">
  <div class="info-card">
    <div class="info-icon"><?= icon('pin', '', 20) ?></div>
    <h3>Delivery address</h3>
    <p class="small"><?= e($order['customer_name']) ?><br>
      <?= e($order['address_line1']) ?><?= $order['address_line2'] ? ', ' . e($order['address_line2']) : '' ?><br>
      <?= e($order['city']) ?>, <?= e($order['province']) ?><?= $order['postal_code'] ? ' ' . e($order['postal_code']) : '' ?><br>
      <?= e($order['phone']) ?></p>
    <?php if (!empty($order['delivery_notes'])): ?>
      <p class="tiny muted">Note: <?= e($order['delivery_notes']) ?></p>
    <?php endif; ?>
  </div>

  <div class="info-card">
    <div class="info-icon"><?= icon('shield', '', 20) ?></div>
    <h3>Payment record</h3>
    <?php if ($payments): ?>
      <?php foreach ($payments as $index => $record): ?>
        <p class="small">
          <span class="pill pill-<?= $record['status'] === 'verified' ? 'ok' : ($record['status'] === 'rejected' ? 'bad' : ($record['status'] === 'pending_verification' ? 'warn' : 'muted')) ?>">
            <?= e(payment_status_label((string) $record['status'])) ?></span>
          <?= e(payment_method_label((string) $record['method'])) ?> · <?= e(money($record['amount'])) ?><br>
          <?php if (!empty($record['reference_number'])): ?>
            Reference <?= e((string) $record['reference_number']) ?><br>
          <?php endif; ?>
          <span class="tiny muted">Submitted <?= e(format_datetime($record['created_at'])) ?>
            <?= $record['verified_at'] ? ' · checked ' . e(format_datetime($record['verified_at'])) : '' ?></span>
          <?php if (!empty($record['admin_note']) && $record['status'] !== 'rejected'): ?>
            <br><span class="tiny muted">Shop note: <?= e((string) $record['admin_note']) ?></span>
          <?php endif; ?>
        </p>
        <?php if ($index === 0) { echo '<hr class="divider" style="margin:12px 0">'; } ?>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="small muted">No payment record yet.</p>
    <?php endif; ?>
  </div>

  <div class="info-card">
    <div class="info-icon"><?= icon('info', '', 20) ?></div>
    <h3>Need help with this order?</h3>
    <p class="small muted">Quote your order number <strong><?= e($order['order_number']) ?></strong> and we will pick it
      up straight away.</p>
    <p class="mt-1"><a class="link-arrow" href="<?= e(url('contact.php')) ?>">Contact the store <?= icon('arrow-right', '', 14) ?></a></p>
  </div>
</div>

<div class="cluster mt-2">
  <a class="btn btn-ghost" href="<?= e(url('account/orders.php')) ?>"><?= icon('chevron-left', '', 16) ?> Back to my orders</a>
  <?php if ($canCancel): ?>
    <form method="post" action="<?= e(url($redirectTo)) ?>" data-confirm="Cancel this order? The stock will be returned to the shelf.">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="cancel_order">
      <button type="submit" class="btn btn-quiet" style="color:var(--danger)">Cancel this order</button>
    </form>
  <?php endif; ?>
</div>
<?php render_account_end(); ?>
