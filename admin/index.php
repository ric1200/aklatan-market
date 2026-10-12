<?php
/** Admin dashboard: live figures straight from the database. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/admin-header.php';
require_admin();

$qualifying = "('confirmed','processing','shipped','delivered')";

$stats = [
    'orders_total' => (int) fetch_value('SELECT COUNT(*) FROM orders', [], 0),
    'orders_pending' => (int) fetch_value("SELECT COUNT(*) FROM orders WHERE status = 'pending'", [], 0),
    'orders_open' => (int) fetch_value("SELECT COUNT(*) FROM orders WHERE status IN ('pending','confirmed','processing','shipped')", [], 0),
    'sales' => (float) fetch_value('SELECT COALESCE(SUM(total),0) FROM orders WHERE status IN ' . $qualifying, [], 0),
    'sales_month' => (float) fetch_value("SELECT COALESCE(SUM(total),0) FROM orders
        WHERE status IN " . $qualifying . " AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')", [], 0),
    'products_active' => (int) fetch_value('SELECT COUNT(*) FROM products WHERE is_active = 1', [], 0),
    'products_total' => (int) fetch_value('SELECT COUNT(*) FROM products', [], 0),
    'low_stock' => (int) fetch_value('SELECT COUNT(*) FROM products WHERE is_active = 1 AND stock_quantity <= :t',
        ['t' => setting_int('low_stock_threshold', 5)], 0),
    'out_of_stock' => (int) fetch_value('SELECT COUNT(*) FROM products WHERE is_active = 1 AND stock_quantity <= 0', [], 0),
    'customers' => (int) fetch_value("SELECT COUNT(*) FROM users WHERE role = 'customer'", [], 0),
    'reviews_pending' => (int) fetch_value("SELECT COUNT(*) FROM reviews WHERE status = 'pending'", [], 0),
    'payments_pending' => (int) fetch_value("SELECT COUNT(*) FROM payments WHERE status = 'pending_verification'", [], 0),
    'messages_new' => (int) fetch_value("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'", [], 0),
];

$recentOrders = fetch_all('SELECT o.*, u.first_name, u.last_name FROM orders o
    LEFT JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC LIMIT 8');

$lowStock = fetch_all('SELECT p.*, c.name AS category_name FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.is_active = 1 AND p.stock_quantity <= :t
    ORDER BY p.stock_quantity ASC, p.name ASC LIMIT 8',
    ['t' => setting_int('low_stock_threshold', 5)]);

$pendingPayments = fetch_all('SELECT p.*, o.order_number FROM payments p
    JOIN orders o ON o.id = p.order_id
    WHERE p.status = :st ORDER BY p.created_at ASC LIMIT 6', ['st' => 'pending_verification']);

// Last 14 days of sales for a simple bar chart.
$daily = fetch_all("SELECT DATE(created_at) AS day, COALESCE(SUM(total),0) AS amount, COUNT(*) AS orders
    FROM orders WHERE status IN " . $qualifying . " AND created_at >= (NOW() - INTERVAL 14 DAY)
    GROUP BY DATE(created_at) ORDER BY day ASC");
$dayMap = [];
foreach ($daily as $row) { $dayMap[(string) $row['day']] = $row; }
$chart = [];
$chartMax = 1.0;
for ($i = 13; $i >= 0; $i--) {
    $key = date('Y-m-d', strtotime('-' . $i . ' days'));
    $row = $dayMap[$key] ?? null;
    $amount = $row ? (float) $row['amount'] : 0.0;
    $chartMax = max($chartMax, $amount);
    $chart[] = ['day' => $key, 'amount' => $amount, 'orders' => $row ? (int) $row['orders'] : 0];
}

$topProducts = fetch_all("SELECT oi.product_name, SUM(oi.quantity) AS units, SUM(oi.line_total) AS revenue
    FROM order_items oi JOIN orders o ON o.id = oi.order_id
    WHERE o.status IN " . $qualifying . "
    GROUP BY oi.product_name ORDER BY units DESC LIMIT 6");

render_admin_start([
    'current' => 'dashboard',
    'title' => 'Dashboard',
    'subtitle' => 'Welcome back, ' . user_full_name() . '. Here is how the shop looks right now.',
    'actions' => '<a class="btn btn-primary btn-sm" href="' . e(url('admin/product-edit.php')) . '">' . icon('plus', '', 16) . ' Add a product</a>'
        . '<a class="btn btn-ghost btn-sm" href="' . e(url('admin/orders.php?status=pending')) . '">' . icon('package', '', 16) . ' Review pending orders</a>',
]);
?>

<div class="kpi-grid">
  <div class="kpi">
    <p class="kpi-label"><?= icon('package', '', 14) ?> Total orders</p>
    <p class="kpi-value"><?= number_format($stats['orders_total']) ?></p>
    <p class="kpi-note"><?= (int) $stats['orders_open'] ?> still open</p>
  </div>
  <div class="kpi <?= $stats['orders_pending'] > 0 ? 'kpi-warn' : '' ?>">
    <p class="kpi-label"><?= icon('clock', '', 14) ?> Pending orders</p>
    <p class="kpi-value"><?= number_format($stats['orders_pending']) ?></p>
    <p class="kpi-note">Awaiting confirmation</p>
  </div>
  <div class="kpi kpi-ok">
    <p class="kpi-label"><?= icon('chart', '', 14) ?> Sales (confirmed+)</p>
    <p class="kpi-value"><?= e(money($stats['sales'])) ?></p>
    <p class="kpi-note"><?= e(money($stats['sales_month'])) ?> this month</p>
  </div>
  <div class="kpi">
    <p class="kpi-label"><?= icon('book', '', 14) ?> Active products</p>
    <p class="kpi-value"><?= number_format($stats['products_active']) ?></p>
    <p class="kpi-note">of <?= (int) $stats['products_total'] ?> listed</p>
  </div>
  <div class="kpi <?= $stats['low_stock'] > 0 ? 'kpi-warn' : '' ?>">
    <p class="kpi-label"><?= icon('alert', '', 14) ?> Low stock</p>
    <p class="kpi-value"><?= number_format($stats['low_stock']) ?></p>
    <p class="kpi-note"><?= (int) $stats['out_of_stock'] ?> out of stock</p>
  </div>
  <div class="kpi">
    <p class="kpi-label"><?= icon('users', '', 14) ?> Customers</p>
    <p class="kpi-value"><?= number_format($stats['customers']) ?></p>
    <p class="kpi-note">Registered accounts</p>
  </div>
</div>

<?php if ($stats['payments_pending'] || $stats['messages_new'] || $stats['reviews_pending']): ?>
  <div class="admin-panel">
    <div class="admin-panel-head">
      <h2><?= icon('alert', '', 17) ?> Waiting for you</h2>
      <p class="panel-note">Tasks a human has to do — nothing here happens automatically.</p>
    </div>
    <div class="admin-panel-body">
      <div class="cluster">
        <?php if ($stats['payments_pending']): ?>
          <a class="pill pill-warn" href="<?= e(url('admin/payments.php?status=pending_verification')) ?>">
            <?= icon('shield', '', 14) ?> <?= (int) $stats['payments_pending'] ?> manual payment<?= $stats['payments_pending'] === 1 ? '' : 's' ?> to verify
          </a>
        <?php endif; ?>
        <?php if ($stats['reviews_pending']): ?>
          <a class="pill pill-warn" href="<?= e(url('admin/reviews.php?status=pending')) ?>">
            <?= icon('message', '', 14) ?> <?= (int) $stats['reviews_pending'] ?> review<?= $stats['reviews_pending'] === 1 ? '' : 's' ?> to moderate
          </a>
        <?php endif; ?>
        <?php if ($stats['messages_new']): ?>
          <a class="pill pill-warn" href="<?= e(url('admin/messages.php?status=new')) ?>">
            <?= icon('mail', '', 14) ?> <?= (int) $stats['messages_new'] ?> new enquir<?= $stats['messages_new'] === 1 ? 'y' : 'ies' ?>
          </a>
        <?php endif; ?>
        <?php if ($stats['orders_pending']): ?>
          <a class="pill pill-warn" href="<?= e(url('admin/orders.php?status=pending')) ?>">
            <?= icon('package', '', 14) ?> <?= (int) $stats['orders_pending'] ?> order<?= $stats['orders_pending'] === 1 ? '' : 's' ?> to confirm
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="admin-panel">
  <div class="admin-panel-head">
    <h2>Sales, last 14 days</h2>
    <p class="panel-note">Orders in confirmed, processing, shipped or delivered state.</p>
  </div>
  <div class="admin-panel-body">
    <div class="chart-bars">
      <?php foreach ($chart as $entry): $pct = (int) round(($entry['amount'] / $chartMax) * 100); ?>
        <div class="chart-bar">
          <span><?= e(date('D j M', strtotime($entry['day']))) ?></span>
          <span class="bar"><span style="width: <?= max($entry['amount'] > 0 ? 2 : 0, $pct) ?>%"></span></span>
          <span class="amount"><?= e(money($entry['amount'])) ?> <span class="tiny muted">(<?= (int) $entry['orders'] ?>)</span></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="admin-grid-2">
  <div class="admin-panel">
    <div class="admin-panel-head">
      <h2>Recent orders</h2>
      <a class="btn btn-ghost btn-xs" href="<?= e(url('admin/orders.php')) ?>">All orders</a>
    </div>
    <div class="admin-panel-body">
      <?php if (!$recentOrders): ?>
        <p class="muted small">No orders yet. Place a test order from the storefront to see the flow end to end.</p>
      <?php else: ?>
        <ul class="audit-list">
          <?php foreach ($recentOrders as $order): ?>
            <li>
              <span class="audit-action">
                <a href="<?= e(url('admin/order-view.php?order=' . urlencode((string) $order['order_number']))) ?>"><?= e($order['order_number']) ?></a>
                — <?= e(money($order['total'])) ?>
              </span>
              <span class="audit-meta">
                <?= e(trim((string) $order['customer_name'])) ?> ·
                <?= e(payment_method_label((string) $order['payment_method'])) ?> ·
                <?= e(time_ago($order['created_at'])) ?>
              </span>
              <span><?= order_status_badge((string) $order['status']) ?> <?= payment_status_badge((string) $order['payment_status']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

  <div class="admin-panel">
    <div class="admin-panel-head">
      <h2>Low stock</h2>
      <a class="btn btn-ghost btn-xs" href="<?= e(url('admin/inventory.php')) ?>">Inventory</a>
    </div>
    <div class="admin-panel-body">
      <?php if (!$lowStock): ?>
        <p class="muted small">Nothing is running low. Everything above the
          <?= (int) setting_int('low_stock_threshold', 5) ?>-unit threshold.</p>
      <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <caption class="sr-only">Products running low on stock</caption>
            <thead><tr><th scope="col">Product</th><th scope="col" class="num">Left</th></tr></thead>
            <tbody>
              <?php foreach ($lowStock as $product): ?>
                <tr>
                  <td>
                    <a href="<?= e(url('admin/product-edit.php?id=' . (int) $product['id'])) ?>"><?= e($product['name']) ?></a><br>
                    <span class="tiny muted"><?= e((string) ($product['category_name'] ?? 'Uncategorised')) ?> · <?= e((string) $product['sku']) ?></span>
                  </td>
                  <td class="num">
                    <span class="pill <?= (int) $product['stock_quantity'] <= 0 ? 'pill-bad' : 'pill-warn' ?>"><?= (int) $product['stock_quantity'] ?></span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="admin-grid-2">
  <div class="admin-panel">
    <div class="admin-panel-head">
      <h2>Manual payments to verify</h2>
      <a class="btn btn-ghost btn-xs" href="<?= e(url('admin/payments.php')) ?>">All payments</a>
    </div>
    <div class="admin-panel-body">
      <?php if (!$pendingPayments): ?>
        <p class="muted small">No manual payments are waiting. Verified and rejected payments are kept in the record.</p>
      <?php else: ?>
        <ul class="audit-list">
          <?php foreach ($pendingPayments as $payment): ?>
            <li>
              <span class="audit-action">
                <a href="<?= e(url('admin/order-view.php?order=' . urlencode((string) $payment['order_number']))) ?>"><?= e($payment['order_number']) ?></a>
                — <?= e(money($payment['amount'])) ?>
              </span>
              <span class="audit-meta">
                <?= e(payment_method_label((string) $payment['method'])) ?> ·
                <?= $payment['reference_number'] ? 'ref ' . e((string) $payment['reference_number']) : 'no reference yet' ?> ·
                <?= e(time_ago($payment['created_at'])) ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

  <div class="admin-panel">
    <div class="admin-panel-head">
      <h2>Best sellers</h2>
      <p class="panel-note">By units ordered.</p>
    </div>
    <div class="admin-panel-body">
      <?php if (!$topProducts): ?>
        <p class="muted small">No qualifying orders yet.</p>
      <?php else: ?>
        <div class="chart-bars">
          <?php $topMax = max(1, max(array_map(function ($r) { return (int) $r['units']; }, $topProducts))); ?>
          <?php foreach ($topProducts as $row): ?>
            <div class="chart-bar">
              <span title="<?= e($row['product_name']) ?>"><?= e(truncate($row['product_name'], 16)) ?></span>
              <span class="bar"><span style="width: <?= (int) round(((int) $row['units'] / $topMax) * 100) ?>%"></span></span>
              <span class="amount"><?= (int) $row['units'] ?> · <?= e(money($row['revenue'])) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php render_admin_end(); ?>
