<?php
/**
 * Order detail and the management actions for it:
 * status transitions (server-checked), internal notes and manual payment
 * verification with a full audit trail.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/admin-header.php';
$admin = require_admin();

$orderNumber = truncate(get_param('order'), 40);
$order = $orderNumber !== '' ? order_by_number($orderNumber) : null;
if (!$order) {
    aklatan_abort(404, 'That order does not exist', 'It may have been deleted. Return to the order list.');
}
$orderId = (int) $order['id'];
$redirectTo = 'admin/order-view.php?order=' . urlencode((string) $order['order_number']);

if (is_post()) {
    csrf_guard();
    $action = post('action');

    /* ------------------------------------------------------ status change -- */
    if ($action === 'update_status') {
        $newStatus = (string) post('status');
        $current = (string) $order['status'];
        $allowed = allowed_next_statuses($current);

        if ($newStatus === $current) {
            flash_info('The order is already ' . strtolower(order_status_label($current)) . '.');
            redirect($redirectTo);
        }
        if (!in_array($newStatus, $allowed, true)) {
            log_error('Rejected invalid order transition ' . $current . ' → ' . $newStatus . ' on order ' . $order['order_number']);
            flash_error(order_status_label($current) . ' orders can only move to: '
                . ($allowed ? implode(', ', array_map('order_status_label', $allowed)) : 'no other status') . '.');
            redirect($redirectTo);
        }

        $pdo = db();
        try {
            $pdo->beginTransaction();
            // Cancelling returns the reserved units to the shelf, once only.
            if (status_releases_stock($newStatus) && !(int) $order['stock_restored']) {
                foreach (order_items_for($orderId) as $item) {
                    if (!empty($item['product_id'])) {
                        q('UPDATE products SET stock_quantity = stock_quantity + :qty WHERE id = :id',
                            ['qty' => (int) $item['quantity'], 'id' => (int) $item['product_id']]);
                    }
                }
                db_update('orders', ['stock_restored' => 1], 'id', $orderId);
            }
            db_update('orders', ['status' => $newStatus], 'id', $orderId);
            $pdo->commit();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            log_error('order status update failed: ' . $ex->getMessage());
            flash_error('The status could not be updated. Please try again.');
            redirect($redirectTo);
        }

        audit_log('order.status_changed', 'order', $orderId,
            order_status_label($current) . ' → ' . order_status_label($newStatus) . ' on ' . $order['order_number']);
        flash_success('Order ' . $order['order_number'] . ' is now ' . strtolower(order_status_label($newStatus)) . '.');
        redirect($redirectTo);
    }

    /* ------------------------------------------------------------- notes -- */
    if ($action === 'save_notes') {
        $notes = truncate(post_raw('admin_notes'), 2000);
        db_update('orders', ['admin_notes' => $notes !== '' ? $notes : null], 'id', $orderId);
        audit_log('order.notes_updated', 'order', $orderId, 'Internal notes updated');
        flash_success('Internal notes saved. Customers do not see these.');
        redirect($redirectTo);
    }

    /* ------------------------------------------------- payment decisions -- */
    if ($action === 'verify_payment' || $action === 'reject_payment') {
        $paymentId = post_int('payment_id');
        $payment = fetch_one('SELECT * FROM payments WHERE id = :id AND order_id = :oid', ['id' => $paymentId, 'oid' => $orderId]);
        if (!$payment) {
            flash_error('That payment record could not be found on this order.');
            redirect($redirectTo);
        }
        $note = truncate(post_raw('admin_note'), 500);
        $status = $action === 'verify_payment' ? 'verified' : 'rejected';

        if ($status === 'rejected' && $note === '') {
            flash_error('Please write a short reason so the customer knows what to do next.');
            redirect($redirectTo);
        }

        db_update('payments', [
            'status' => $status,
            'admin_note' => $note !== '' ? $note : ($status === 'verified' ? 'Verified against the transaction list.' : null),
            'verified_by' => (int) $admin['id'],
            'verified_at' => date('Y-m-d H:i:s'),
        ], 'id', $paymentId);
        db_update('orders', ['payment_status' => $status], 'id', $orderId);

        audit_log('order.payment_' . $status, 'payment', $paymentId,
            'Order ' . $order['order_number'] . ' — ' . payment_method_label((string) $payment['method'])
            . ($payment['reference_number'] ? ', ref ' . $payment['reference_number'] : '')
            . ($note !== '' ? ' — ' . $note : ''));

        flash_success($status === 'verified'
            ? 'Payment verified. The customer can see this immediately in their order history.'
            : 'Payment marked as rejected. The customer will be asked to check the reference.');
        redirect($redirectTo);
    }
}

$order = order_by_number($orderNumber);
$items = order_items_for($orderId);
$payments = order_payments($orderId);
$customer = $order['user_id'] ? fetch_one('SELECT * FROM users WHERE id = :id', ['id' => (int) $order['user_id']]) : null;
$allowed = allowed_next_statuses((string) $order['status']);
$logs = fetch_all('SELECT a.*, u.first_name, u.last_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
    WHERE (a.entity_type = :t AND a.entity_id = :id) OR a.details LIKE :like
    ORDER BY a.created_at DESC LIMIT 15',
    ['t' => 'order', 'id' => (string) $orderId, 'like' => '%' . $order['order_number'] . '%']);

render_admin_start([
    'current' => 'orders',
    'title' => 'Order ' . $order['order_number'],
    'subtitle' => 'Placed ' . format_datetime($order['created_at']) . ' · ' . count($items) . ' line(s) · '
        . money($order['total']) . ' total',
    'crumbs' => [
        ['label' => 'Dashboard', 'url' => 'admin/index.php'],
        ['label' => 'Orders', 'url' => 'admin/orders.php'],
        ['label' => $order['order_number']],
    ],
    'actions' => '<button type="button" class="btn btn-ghost btn-sm" data-print>' . icon('download', '', 16) . ' Print</button>'
        . '<a class="btn btn-ghost btn-sm" href="' . e(url('admin/orders.php')) . '">Back to list</a>',
]);
?>
<p class="cluster">
  <?= order_status_badge((string) $order['status']) ?>
  <?= payment_status_badge((string) $order['payment_status']) ?>
  <span class="pill"><?= e(payment_method_label((string) $order['payment_method'])) ?></span>
  <?php if ((int) $order['stock_restored']): ?><span class="pill pill-muted">Stock returned</span><?php endif; ?>
</p>

<div class="admin-grid-2">
  <div>
    <div class="admin-panel">
      <div class="admin-panel-head">
        <h2>Items</h2>
        <p class="panel-note">Names and prices are snapshots taken when the order was placed.</p>
      </div>
      <div class="admin-panel-body flush">
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr><th scope="col">Item</th><th scope="col" class="num">Unit</th><th scope="col" class="num">Qty</th><th scope="col" class="num">Line total</th></tr>
            </thead>
            <tbody>
              <?php foreach ($items as $item): ?>
                <tr>
                  <td>
                    <div class="cluster" style="flex-wrap:nowrap">
                      <img class="thumb" src="<?= e(product_image_url(['image' => $item['product_image']])) ?>" alt="" width="34" height="51">
                      <span>
                        <?php if (!empty($item['product_id'])): ?>
                          <a href="<?= e(url('admin/product-edit.php?id=' . (int) $item['product_id'])) ?>"><?= e($item['product_name']) ?></a>
                        <?php else: ?>
                          <strong><?= e($item['product_name']) ?></strong>
                          <br><span class="tiny muted">Product since deleted</span>
                        <?php endif; ?>
                        <br><span class="tiny muted">SKU <?= e((string) ($item['product_sku'] ?? '—')) ?></span>
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
        <div class="admin-panel-body">
          <?php render_order_totals($order); ?>
        </div>
      </div>
    </div>

    <div class="admin-panel">
      <div class="admin-panel-head">
        <h2>Payments</h2>
        <p class="panel-note">Manual payments are never auto-verified — a person decides.</p>
      </div>
      <div class="admin-panel-body">
        <?php if (!$payments): ?>
          <p class="muted small">No payment record on this order.</p>
        <?php else: ?>
          <?php foreach ($payments as $payment): ?>
            <div class="admin-panel" style="margin-bottom:12px">
              <div class="admin-panel-head">
                <h3 style="font-size:.95rem;margin:0"><?= e(payment_method_label((string) $payment['method'])) ?> — <?= e(money($payment['amount'])) ?></h3>
                <?= payment_status_badge((string) $payment['status']) ?>
              </div>
              <div class="admin-panel-body">
                <div class="detail-list">
                  <div><span class="k">Reference number</span><span><?= e((string) ($payment['reference_number'] ?: '— not provided —')) ?></span></div>
                  <div><span class="k">Payer name</span><span><?= e((string) ($payment['payer_name'] ?: '—')) ?></span></div>
                  <div><span class="k">Customer note</span><span><?= e((string) ($payment['customer_note'] ?: '—')) ?></span></div>
                  <div><span class="k">Submitted</span><span><?= e(format_datetime($payment['created_at'])) ?></span></div>
                  <div><span class="k">Checked</span><span><?= $payment['verified_at'] ? e(format_datetime($payment['verified_at'])) . ' by admin #' . (int) $payment['verified_by'] : 'not checked yet' ?></span></div>
                  <?php if (!empty($payment['admin_note'])): ?>
                    <div><span class="k">Admin note</span><span><?= e((string) $payment['admin_note']) ?></span></div>
                  <?php endif; ?>
                  <div><span class="k">Receipt image</span>
                    <span>
                      <?php if (!empty($payment['proof_image'])): ?>
                        <button type="button" class="btn btn-quiet btn-xs" data-proof="<?= e(url('admin/payment-proof.php?payment=' . (int) $payment['id'])) ?>">
                          <?= icon('eye', '', 14) ?> View receipt
                        </button>
                      <?php else: ?>
                        <span class="muted">none uploaded</span>
                      <?php endif; ?>
                    </span>
                  </div>
                </div>

                <?php if ($payment['status'] !== 'verified'): ?>
                  <form method="post" action="<?= e(url($redirectTo)) ?>" class="form-grid mt-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>">
                    <div class="field">
                      <label for="admin_note_<?= (int) $payment['id'] ?>">Note for the customer <span class="hint">(required when rejecting)</span></label>
                      <input id="admin_note_<?= (int) $payment['id'] ?>" type="text" name="admin_note" maxlength="500"
                             placeholder="For example: matched against the GCash statement for 12 Oct.">
                    </div>
                    <div class="cluster">
                      <button type="submit" name="action" value="verify_payment" class="btn btn-primary btn-sm">
                        <?= icon('check-circle', '', 15) ?> Mark payment verified
                      </button>
                      <button type="submit" name="action" value="reject_payment" class="btn btn-danger btn-sm"
                              data-confirm="Reject this payment? The customer will be asked to check the reference.">
                        <?= icon('x-circle', '', 15) ?> Reject payment
                      </button>
                    </div>
                  </form>
                <?php else: ?>
                  <p class="small muted mt-1"><?= icon('check-circle', '', 15) ?> This payment has been verified. Verification cannot be undone here — add a note instead so the decision stays visible.</p>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="admin-panel">
      <div class="admin-panel-head"><h2>Internal notes</h2><p class="panel-note">Never shown to the customer.</p></div>
      <div class="admin-panel-body">
        <form method="post" action="<?= e(url($redirectTo)) ?>" class="form-grid">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save_notes">
          <div class="field">
            <label for="admin_notes">Notes</label>
            <textarea id="admin_notes" name="admin_notes" maxlength="2000" rows="4"
                      placeholder="Packing instructions, courier tracking number, anything the team should know."><?= e((string) ($order['admin_notes'] ?? '')) ?></textarea>
          </div>
          <button type="submit" class="btn btn-secondary btn-sm"><?= icon('check', '', 15) ?> Save notes</button>
        </form>
      </div>
    </div>
  </div>

  <div>
    <div class="admin-panel">
      <div class="admin-panel-head"><h2>Update status</h2></div>
      <div class="admin-panel-body">
        <?php if (!$allowed): ?>
          <p class="small muted">
            <?= e(order_status_label((string) $order['status'])) ?> is a final state, so there is nowhere to move from here.
          </p>
        <?php else: ?>
          <form method="post" action="<?= e(url($redirectTo)) ?>" class="form-grid"
                data-confirm="Change the status of <?= e($order['order_number']) ?>?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_status">
            <div class="field">
              <label for="status">Move this order to</label>
              <select id="status" name="status" required>
                <?php foreach ($allowed as $next): ?>
                  <option value="<?= e($next) ?>"><?= e(order_status_label($next)) ?></option>
                <?php endforeach; ?>
              </select>
              <span class="hint">
                <?= e(order_status_label((string) $order['status'])) ?> orders may move to
                <?= e(implode(', ', array_map('order_status_label', $allowed))) ?> only.
                <?= in_array('cancelled', $allowed, true) ? 'Cancelling returns the reserved stock to the shelf.' : '' ?>
              </span>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><?= icon('refresh', '', 15) ?> Update status</button>
          </form>
        <?php endif; ?>

        <ul class="timeline mt-2">
          <?php foreach (order_timeline($order) as $step): ?>
            <li class="<?= $step['state'] === 'done' ? 'is-done' : ($step['state'] === 'current' ? 'is-current' : '') ?>">
              <span class="dot"><?= icon($step['state'] === 'done' ? 'check' : 'clock', '', 13) ?></span>
              <span><span class="tl-title"><?= e($step['label']) ?></span><span class="tl-note"><?= e($step['note']) ?></span></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>

    <div class="admin-panel">
      <div class="admin-panel-head"><h2>Delivery details</h2></div>
      <div class="admin-panel-body">
        <div class="detail-list">
          <div><span class="k">Recipient</span><span><?= e($order['customer_name']) ?></span></div>
          <div><span class="k">Phone</span><span><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string) $order['phone'])) ?>"><?= e($order['phone']) ?></a></span></div>
          <div><span class="k">E-mail</span><span><a href="mailto:<?= e($order['email']) ?>"><?= e($order['email']) ?></a></span></div>
          <div><span class="k">Address</span><span>
            <?= e($order['address_line1']) ?><?= $order['address_line2'] ? ', ' . e($order['address_line2']) : '' ?><br>
            <?= e($order['city']) ?>, <?= e($order['province']) ?><?= $order['postal_code'] ? ' ' . e($order['postal_code']) : '' ?></span></div>
          <?php if (!empty($order['delivery_notes'])): ?>
            <div><span class="k">Customer note</span><span><?= e((string) $order['delivery_notes']) ?></span></div>
          <?php endif; ?>
          <?php if (!empty($order['pickup_location'])): ?>
            <div><span class="k">Pickup point</span><span><?= e((string) $order['pickup_location']) ?></span></div>
          <?php endif; ?>
        </div>
        <?php if ($customer): ?>
          <p class="mt-2"><a class="link-arrow" href="<?= e(url('admin/customer-view.php?id=' . (int) $customer['id'])) ?>">
            Open customer record <?= icon('arrow-right', '', 14) ?></a></p>
        <?php endif; ?>
      </div>
    </div>

    <div class="admin-panel">
      <div class="admin-panel-head"><h2>History</h2></div>
      <div class="admin-panel-body">
        <?php if (!$logs): ?>
          <p class="muted small">No recorded activity for this order yet.</p>
        <?php else: ?>
          <ul class="audit-list">
            <?php foreach ($logs as $log): ?>
              <li>
                <span class="audit-action"><?= e(str_replace(['.', '_'], [' ', ' '], (string) $log['action'])) ?></span>
                <span class="audit-meta"><?= e((string) ($log['details'] ?? '')) ?></span>
                <span class="audit-meta"><?= e(format_datetime($log['created_at'])) ?>
                  <?= $log['first_name'] ? '· ' . e(trim($log['first_name'] . ' ' . $log['last_name'])) : '· system' ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php render_admin_end(); ?>
