<?php
/** Order list with filters, export and a fast path into each order. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/admin-header.php';
require_admin();

/* ------------------------------------------------------------ CSV export -- */
if (get_param('export') === 'csv') {
    $status = (string) get_param('status');
    $paymentStatus = (string) get_param('payment');
    $keyword = truncate(get_param('q'), 80);
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) get_param('from')) ? (string) get_param('from') : '';
    $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) get_param('to')) ? (string) get_param('to') : '';

    $where = ['1=1'];
    $params = [];
    if (array_key_exists($status, ORDER_STATUSES)) { $where[] = 'o.status = :status'; $params['status'] = $status; }
    if (array_key_exists($paymentStatus, PAYMENT_STATUSES)) { $where[] = 'o.payment_status = :pay'; $params['pay'] = $paymentStatus; }
    if ($keyword !== '') {
        $where[] = like_or_clause(['o.order_number', 'o.customer_name', 'o.email'], $keyword, $params, 'kw');
    }
    if ($from !== '') { $where[] = 'o.created_at >= :from'; $params['from'] = $from . ' 00:00:00'; }
    if ($to !== '') { $where[] = 'o.created_at <= :to'; $params['to'] = $to . ' 23:59:59'; }

    $rows = fetch_all('SELECT o.order_number, o.created_at, o.status, o.payment_method, o.payment_status,
        o.customer_name, o.email, o.phone, o.city, o.province, o.subtotal, o.discount_amount, o.shipping_fee, o.total
        FROM orders o WHERE ' . implode(' AND ', $where) . ' ORDER BY o.created_at DESC LIMIT 5000', $params);

    audit_log('order.exported', 'order', null, count($rows) . ' orders exported to CSV');

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="aklatan-orders-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads the peso sign correctly
    fputcsv($out, ['Order number', 'Placed', 'Order status', 'Payment method', 'Payment status', 'Customer',
        'Email', 'Phone', 'City', 'Province', 'Subtotal', 'Discount', 'Shipping', 'Total']);
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['order_number'], $row['created_at'], order_status_label((string) $row['status']),
            payment_method_label((string) $row['payment_method']), payment_status_label((string) $row['payment_status']),
            $row['customer_name'], $row['email'], $row['phone'], $row['city'], $row['province'],
            money_plain($row['subtotal']), money_plain($row['discount_amount']),
            money_plain($row['shipping_fee']), money_plain($row['total']),
        ]);
    }
    fclose($out);
    exit;
}

/* ----------------------------------------------------------------- filters */
$status = (string) get_param('status');
$paymentStatus = (string) get_param('payment');
$keyword = truncate(get_param('q'), 80);
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) get_param('from')) ? (string) get_param('from') : '';
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) get_param('to')) ? (string) get_param('to') : '';
$page = max(1, get_int('page', 1));

$where = ['1=1'];
$params = [];
if (array_key_exists($status, ORDER_STATUSES)) { $where[] = 'o.status = :status'; $params['status'] = $status; }
if (array_key_exists($paymentStatus, PAYMENT_STATUSES)) { $where[] = 'o.payment_status = :pay'; $params['pay'] = $paymentStatus; }
if ($keyword !== '') {
    $where[] = like_or_clause(['o.order_number', 'o.customer_name', 'o.email', 'o.phone'], $keyword, $params, 'kw');
}
if ($from !== '') { $where[] = 'o.created_at >= :from'; $params['from'] = $from . ' 00:00:00'; }
if ($to !== '') { $where[] = 'o.created_at <= :to'; $params['to'] = $to . ' 23:59:59'; }
$whereSql = implode(' AND ', $where);

$total = (int) fetch_value('SELECT COUNT(*) FROM orders o WHERE ' . $whereSql, $params, 0);
$p = paginate($total, (int) setting_int('orders_per_page', 20), $page);
$orders = fetch_all('SELECT o.*, (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_lines
    FROM orders o WHERE ' . $whereSql . ' ORDER BY o.created_at DESC LIMIT ' . (int) $p['perPage'] . ' OFFSET ' . (int) $p['offset'], $params);

$filterTotals = fetch_one('SELECT COUNT(*) AS c, COALESCE(SUM(o.total),0) AS sum_total FROM orders o WHERE ' . $whereSql, $params);
$statusCounts = [];
foreach (fetch_all('SELECT status, COUNT(*) AS c FROM orders GROUP BY status') as $row) { $statusCounts[$row['status']] = (int) $row['c']; }

$query = array_filter(['status' => $status, 'payment' => $paymentStatus, 'q' => $keyword, 'from' => $from, 'to' => $to],
    function ($v) { return $v !== ''; });

render_admin_start([
    'current' => 'orders',
    'title' => 'Orders',
    'subtitle' => number_format($total) . ' order' . ($total === 1 ? '' : 's') . ' matching · total value '
        . money((float) $filterTotals['sum_total']),
    'crumbs' => [['label' => 'Dashboard', 'url' => 'admin/index.php'], ['label' => 'Orders']],
    'actions' => '<a class="btn btn-ghost btn-sm" href="' . e(url('admin/orders.php?' . http_build_query($query + ['export' => 'csv']))) . '">'
        . icon('download', '', 16) . ' Export CSV</a>'
        . '<a class="btn btn-ghost btn-sm" href="' . e(url('admin/payments.php')) . '">' . icon('shield', '', 16) . ' Payments</a>',
]);
?>
<form class="admin-toolbar admin-panel" method="get" action="<?= e(url('admin/orders.php')) ?>" style="padding:14px 16px">
  <div class="field">
    <label for="q">Order, customer, e-mail or phone</label>
    <input id="q" type="search" name="q" value="<?= e($keyword) ?>" placeholder="AKL-20261012-AB123">
  </div>
  <div class="field tight">
    <label for="status">Order status</label>
    <select id="status" name="status">
      <option value="">Any status</option>
      <?php foreach (ORDER_STATUSES as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?> (<?= (int) ($statusCounts[$key] ?? 0) ?>)</option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field tight">
    <label for="payment">Payment</label>
    <select id="payment" name="payment">
      <option value="">Any payment state</option>
      <?php foreach (PAYMENT_STATUSES as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= $paymentStatus === $key ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field tight">
    <label for="from">From</label>
    <input id="from" type="date" name="from" value="<?= e($from) ?>">
  </div>
  <div class="field tight">
    <label for="to">To</label>
    <input id="to" type="date" name="to" value="<?= e($to) ?>">
  </div>
  <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
  <a class="btn btn-quiet btn-sm" href="<?= e(url('admin/orders.php')) ?>">Reset</a>
</form>

<div class="admin-panel">
  <div class="admin-panel-head">
    <h2>Order list</h2>
    <p class="panel-note">Open an order to change its status, add notes or verify a payment.</p>
  </div>
  <div class="admin-panel-body flush">
    <?php if (!$orders): ?>
      <div style="padding:16px">
        <?php render_empty_state('package', 'No orders matched',
            'Adjust the filters, or check the storefront to make sure orders can be placed.',
            [['label' => 'Reset filters', 'url' => url('admin/orders.php')]]); ?>
      </div>
    <?php else: ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <caption class="sr-only">Orders matching the current filters</caption>
          <thead>
            <tr>
              <th scope="col">Order</th><th scope="col">Customer</th><th scope="col">Deliver to</th>
              <th scope="col">Payment</th><th scope="col" class="num">Items</th><th scope="col" class="num">Total</th>
              <th scope="col">Status</th><th scope="col" class="actions">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orders as $order): ?>
              <tr>
                <td>
                  <a href="<?= e(url('admin/order-view.php?order=' . urlencode((string) $order['order_number']))) ?>"><strong><?= e($order['order_number']) ?></strong></a><br>
                  <span class="tiny muted"><?= e(format_datetime($order['created_at'])) ?> · <?= e(time_ago($order['created_at'])) ?></span>
                </td>
                <td>
                  <?= e($order['customer_name']) ?><br>
                  <span class="tiny muted"><?= e($order['email']) ?><br><?= e($order['phone']) ?></span>
                  <?php if ($order['user_id']): ?>
                    <br><a class="tiny" href="<?= e(url('admin/customer-view.php?id=' . (int) $order['user_id'])) ?>">Customer record</a>
                  <?php else: ?>
                    <br><span class="tiny muted">Account deleted</span>
                  <?php endif; ?>
                </td>
                <td class="tiny"><?= e($order['city']) ?>, <?= e($order['province']) ?><br>
                  <span class="muted"><?= e(truncate((string) $order['address_line1'], 32)) ?></span></td>
                <td class="tiny">
                  <?= e(payment_method_label((string) $order['payment_method'])) ?><br>
                  <?= payment_status_badge((string) $order['payment_status']) ?>
                </td>
                <td class="num"><?= (int) $order['item_lines'] ?></td>
                <td class="num"><strong><?= e(money($order['total'])) ?></strong><br>
                  <span class="tiny muted">incl. <?= e(money($order['shipping_fee'])) ?> ship</span></td>
                <td><?= order_status_badge((string) $order['status']) ?></td>
                <td class="actions">
                  <a class="btn btn-ghost btn-xs" href="<?= e(url('admin/order-view.php?order=' . urlencode((string) $order['order_number']))) ?>">Open</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?= pagination_links($p, 'admin/orders.php', $query) ?>
<?php render_admin_end(); ?>
