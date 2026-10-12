<?php
/** Customer order history with status filter, search and cancellation. */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_login('Please sign in to see your orders.');
$userId = (int) $user['id'];

/* ------------------------------------------------------------ cancellation */
if (is_post() && post('action') === 'cancel_order') {
    csrf_guard();
    $order = order_by_number((string) post('order_number'));
    if (!$order || (int) $order['user_id'] !== $userId) {
        aklatan_abort(403, 'That order belongs to another account', 'You can only change your own orders.');
    }
    if (!in_array($order['status'], ['pending', 'confirmed'], true)) {
        flash_error('Order ' . $order['order_number'] . ' can no longer be cancelled because it is already '
            . strtolower(order_status_label((string) $order['status'])) . '. Please contact us instead.');
        redirect('account/orders.php');
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
        log_error('customer cancel failed: ' . $ex->getMessage());
        flash_error('We could not cancel that order just now. Please try again or contact the store.');
        redirect('account/orders.php');
    }
    audit_log('order.cancelled_by_customer', 'order', (int) $order['id'], 'Order ' . $order['order_number']);
    flash_success('Order ' . $order['order_number'] . ' was cancelled and the stock was returned to the shelf.');
    redirect('account/orders.php');
}

/* ----------------------------------------------------------------- listing */
$status = (string) get_param('status');
$allowedStatuses = array_keys(ORDER_STATUSES);
if (!in_array($status, $allowedStatuses, true)) { $status = ''; }
$keyword = truncate(get_param('q'), 60);
$page = max(1, get_int('page', 1));

$where = ['user_id = :uid'];
$params = ['uid' => $userId];
if ($status !== '') { $where[] = 'status = :status'; $params['status'] = $status; }
if ($keyword !== '') {
    $where[] = like_or_clause(['order_number', 'customer_name'], $keyword, $params, 'kw');
}
$whereSql = implode(' AND ', $where);

$total = (int) fetch_value('SELECT COUNT(*) FROM orders WHERE ' . $whereSql, $params, 0);
$p = paginate($total, 10, $page);
$orders = fetch_all('SELECT * FROM orders WHERE ' . $whereSql . ' ORDER BY created_at DESC LIMIT ' . (int) $p['perPage'] . ' OFFSET ' . (int) $p['offset'], $params);

$counts = [];
foreach (fetch_all('SELECT status, COUNT(*) AS c FROM orders WHERE user_id = :uid GROUP BY status', ['uid' => $userId]) as $row) {
    $counts[$row['status']] = (int) $row['c'];
}
$grandTotal = array_sum($counts);

render_account_start([
    'current' => 'orders',
    'title' => 'My orders',
    'eyebrow' => 'Order history',
    'description' => 'Every order you have placed, with live status and payment verification updates.',
    'breadcrumb' => [['label' => 'Home', 'url' => 'index.php'], ['label' => 'My account', 'url' => 'account/index.php'], ['label' => 'Orders']],
]);
?>
<form class="toolbar" method="get" action="<?= e(url('account/orders.php')) ?>">
  <div class="field" style="flex:1 1 220px">
    <label class="sr-only" for="order-search">Search your orders</label>
    <input id="order-search" type="search" name="q" value="<?= e($keyword) ?>" placeholder="Search by order number">
  </div>
  <div class="field">
    <label class="sr-only" for="order-status">Filter by status</label>
    <select id="order-status" name="status" data-autosubmit>
      <option value="">All statuses (<?= (int) $grandTotal ?>)</option>
      <?php foreach (ORDER_STATUSES as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?> (<?= (int) ($counts[$key] ?? 0) ?>)</option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="btn btn-ghost btn-sm">Search</button>
</form>

<?php if (!$orders): ?>
  <?php render_empty_state(
      'package',
      $keyword !== '' || $status !== '' ? 'No orders matched that search' : 'You have not placed an order yet',
      $keyword !== '' || $status !== ''
          ? 'Try a different order number, or clear the status filter to see your whole history.'
          : 'Your first order will appear here the moment it is placed, with a status you can follow to your door.',
      [
          ['label' => 'Browse the shelves', 'url' => url('products.php'), 'class' => 'btn-primary'],
          ['label' => 'Clear filters', 'url' => url('account/orders.php'), 'class' => 'btn-ghost'],
      ]); ?>
<?php else: ?>
  <div class="stack" style="--gap:14px">
    <?php foreach ($orders as $order):
        $items = order_items_for((int) $order['id']);
        $canCancel = in_array($order['status'], ['pending', 'confirmed'], true);
        $awaitingReview = $order['status'] === 'delivered'; ?>
      <article class="order-card">
        <div class="order-card-head">
          <div>
            <span class="order-no"><?= e($order['order_number']) ?></span>
            <p class="tiny muted">Placed <?= e(format_datetime($order['created_at'])) ?></p>
          </div>
          <span class="cluster"><?= order_status_badge((string) $order['status']) ?> <?= payment_status_badge((string) $order['payment_status']) ?></span>
        </div>
        <div class="order-card-body">
          <div class="cluster">
            <?php foreach (array_slice($items, 0, 4) as $item): ?>
              <span class="pill"><?= (int) $item['quantity'] ?> × <?= e(truncate($item['product_name'], 34)) ?></span>
            <?php endforeach; ?>
            <?php if (count($items) > 4): ?><span class="pill">+<?= count($items) - 4 ?> more</span><?php endif; ?>
          </div>
          <p class="small muted"><?= e(payment_method_label((string) $order['payment_method'])) ?> ·
            Delivering to <?= e($order['city']) ?>, <?= e($order['province']) ?></p>
        </div>
        <div class="order-card-foot">
          <span class="strong"><?= e(money($order['total'])) ?></span>
          <span class="cluster">
            <?php if ($awaitingReview): ?>
              <a class="btn btn-ghost btn-sm" href="<?= e(url('account/order-details.php?order=' . urlencode((string) $order['order_number']) . '#review')) ?>">Write a review</a>
            <?php endif; ?>
            <?php if ($order['payment_status'] === 'pending_verification'): ?>
              <span class="tiny muted"><?= icon('clock', '', 14) ?> Payment being verified</span>
            <?php endif; ?>
            <?php if ($canCancel): ?>
              <form method="post" action="<?= e(url('account/orders.php')) ?>"
                    data-confirm="Cancel order <?= e($order['order_number']) ?>? The stock will be returned to the shelf.">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="cancel_order">
                <input type="hidden" name="order_number" value="<?= e($order['order_number']) ?>">
                <button type="submit" class="btn btn-quiet btn-sm" style="color:var(--danger)">Cancel order</button>
              </form>
            <?php endif; ?>
            <a class="btn btn-secondary btn-sm" href="<?= e(url('account/order-details.php?order=' . urlencode((string) $order['order_number']))) ?>">View details</a>
          </span>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <?= pagination_links($p, 'account/orders.php', array_filter(['status' => $status, 'q' => $keyword], function ($v) { return $v !== ''; })) ?>
<?php endif; ?>
<?php render_account_end(); ?>
