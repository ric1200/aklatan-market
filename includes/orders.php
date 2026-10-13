<?php
/** Order lookups and the customer-facing manual payment submission. */

function order_by_number(string $number): ?array
{
    $number = trim($number);
    if ($number === '' || strlen($number) > 40) { return null; }
    return fetch_one('SELECT * FROM orders WHERE order_number = :n', ['n' => $number]);
}

function order_by_id(int $id): ?array
{
    return fetch_one('SELECT * FROM orders WHERE id = :id', ['id' => $id]);
}

function order_items_for(int $orderId): array
{
    return fetch_all('SELECT * FROM order_items WHERE order_id = :oid ORDER BY id ASC', ['oid' => $orderId]);
}

function order_payments(int $orderId): array
{
    return fetch_all('SELECT * FROM payments WHERE order_id = :oid ORDER BY created_at DESC, id DESC', ['oid' => $orderId]);
}

function order_latest_payment(int $orderId): ?array
{
    return fetch_one('SELECT * FROM payments WHERE order_id = :oid ORDER BY id DESC LIMIT 1', ['oid' => $orderId]);
}

/** True when the signed-in customer is allowed to see this order. */
function customer_can_view_order(array $order): bool
{
    $userId = current_user_id();
    if ($userId === null) { return false; }
    if (is_admin()) { return true; }
    return (int) ($order['user_id'] ?? 0) === $userId;
}

/**
 * Handle the "I have paid by GCash" form.
 * Only the owning customer (or an administrator) may submit, and only while the
 * payment is still unverified. The status moves to pending_verification and a
 * bookseller has to approve it — nothing is auto-verified.
 */
function handle_customer_payment_submission(array $order, string $redirectPath): void
{
    if (!is_post() || post('action') !== 'submit_payment') { return; }
    csrf_guard();
    require_login('Please sign in to submit your payment details.');

    if (!customer_can_view_order($order)) {
        aklatan_abort(403, 'That order belongs to another account',
            'You can only manage payments for your own orders.');
    }
    if ($order['payment_status'] === 'verified') {
        flash_info('This payment has already been verified — nothing more to do. Thank you!');
        redirect($redirectPath);
    }

    $method = post('method', (string) $order['payment_method']);
    if (!in_array($method, ['gcash', 'bank', 'cod', 'pickup'], true)) {
        $method = (string) $order['payment_method'];
    }
    $reference = truncate(post_raw('reference_number'), 80);
    $payerName = truncate(post_raw('payer_name'), 160);
    $note = truncate(post_raw('customer_note'), 500);
    $amount = post('amount');

    $errors = [];
    if ($method === 'gcash' && strlen((string) preg_replace('/\s+/', '', $reference)) < 6) {
        $errors[] = 'Please enter the reference number from your GCash receipt (it is usually 13 digits).';
    }
    if ($amount !== '' && is_numeric($amount) && abs((float) $amount - (float) $order['total']) > 0.01) {
        $errors[] = 'The amount you entered does not match the order total of ' . money($order['total']) . '.';
    }

    $proofPath = null;
    if (!empty($_FILES['payment_proof']['name'])) {
        [$ok, $message, $uploaded] = save_uploaded_image($_FILES['payment_proof'], 'payments');
        if ($ok) { $proofPath = $uploaded; }
        else { $errors[] = $message; }
    }

    if ($errors) {
        foreach ($errors as $error) { flash_error($error); }
        redirect($redirectPath);
    }

    $payment = order_latest_payment((int) $order['id']);
    $newStatus = $method === 'cod' || $method === 'pickup' ? 'unpaid' : 'pending_verification';

    if ($payment && in_array($payment['status'], ['unpaid', 'rejected', 'pending_verification'], true)) {
        $update = [
            'method' => $method,
            'reference_number' => $reference !== '' ? $reference : $payment['reference_number'],
            'payer_name' => $payerName !== '' ? $payerName : $payment['payer_name'],
            'customer_note' => $note !== '' ? $note : $payment['customer_note'],
            'status' => $newStatus,
            'admin_note' => $payment['status'] === 'rejected' ? null : $payment['admin_note'],
        ];
        if ($proofPath !== null) { $update['proof_image'] = $proofPath; }
        db_update('payments', $update, 'id', (int) $payment['id']);
        $paymentId = (int) $payment['id'];
    } else {
        $paymentId = db_insert('payments', [
            'order_id' => (int) $order['id'],
            'method' => $method,
            'amount' => (float) $order['total'],
            'reference_number' => $reference !== '' ? $reference : null,
            'payer_name' => $payerName !== '' ? $payerName : null,
            'proof_image' => $proofPath,
            'status' => $newStatus,
            'customer_note' => $note !== '' ? $note : null,
        ]);
    }

    db_update('orders', [
        'payment_status' => $newStatus,
        'payment_method' => $method,
    ], 'id', (int) $order['id']);

    audit_log('order.payment_submitted', 'payment', $paymentId,
        'Order ' . $order['order_number'] . ' — ' . payment_method_label($method)
        . ($reference !== '' ? ', reference ' . $reference : ''));

    flash_success('Thank you. Your payment details were saved and are now waiting for a bookseller to verify them.');
    redirect($redirectPath);
}

/** The status timeline shown to the customer. */
function order_timeline(array $order): array
{
    $status = (string) $order['status'];
    if ($status === 'cancelled') {
        return [
            ['key' => 'pending', 'label' => 'Order received', 'note' => format_datetime($order['created_at']), 'state' => 'done'],
            ['key' => 'cancelled', 'label' => 'Order cancelled', 'note' => 'Stock was returned to the shelf.', 'state' => 'current'],
        ];
    }
    $sequence = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];
    $notes = [
        'pending' => 'We have your order and are checking stock.',
        'confirmed' => 'Stock reserved and the order accepted.',
        'processing' => 'Being packed at the shop.',
        'shipped' => 'Handed to the courier.',
        'delivered' => 'Received. Thank you for shopping with us.',
    ];
    $currentIndex = array_search($status, $sequence, true);
    if ($currentIndex === false) { $currentIndex = 0; }

    $out = [];
    foreach ($sequence as $i => $key) {
        $state = 'todo';
        if ($i < $currentIndex) { $state = 'done'; }
        elseif ($i === $currentIndex) { $state = 'current'; }
        $out[] = [
            'key' => $key,
            'label' => order_status_label($key),
            'note' => $notes[$key],
            'state' => $state,
        ];
    }
    return $out;
}

/** Small helper for rendering an itemised order block in either area. */
function render_order_items_table(array $items, bool $showImage = true): void
{
    ?>
    <div class="table-wrap">
      <table class="data">
        <caption class="sr-only">Items in this order</caption>
        <thead>
          <tr>
            <th scope="col">Item</th>
            <th scope="col" class="num">Unit price</th>
            <th scope="col" class="num">Qty</th>
            <th scope="col" class="num">Line total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td>
                <div class="cluster" style="flex-wrap:nowrap">
                  <?php if ($showImage): ?>
                    <img src="<?= e(product_image_url(['image' => $item['product_image']])) ?>" alt=""
                         width="34" height="51" loading="lazy" style="width:34px;border-radius:5px;aspect-ratio:2/3;object-fit:cover">
                  <?php endif; ?>
                  <span>
                    <strong><?= e($item['product_name']) ?></strong><br>
                    <span class="tiny muted">SKU <?= e((string) ($item['product_sku'] ?? '—')) ?></span>
                  </span>
                </div>
              </td>
              <td class="num"><?= e(money($item['unit_price'])) ?></td>
              <td class="num"><?= (int) $item['quantity'] ?></td>
              <td class="num"><?= e(money($item['line_total'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
}

/** Totals block shared by the success page and the account order view. */
function render_order_totals(array $order): void
{
    ?>
    <div class="summary-lines">
      <div><span>Subtotal</span><span><?= e(money($order['subtotal'])) ?></span></div>
      <?php if ((float) $order['discount_amount'] > 0): ?>
        <div><span>Discount<?= $order['discount_code'] ? ' (' . e($order['discount_code']) . ')' : '' ?></span>
          <span class="stock-ok">−<?= e(money($order['discount_amount'])) ?></span></div>
      <?php endif; ?>
      <div><span>Shipping</span><span><?= (float) $order['shipping_fee'] > 0 ? e(money($order['shipping_fee'])) : 'Free' ?></span></div>
    </div>
    <div class="summary-lines summary-total">
      <span>Total</span><span class="price"><?= e(money($order['total'])) ?></span>
    </div>
    <?php
}
