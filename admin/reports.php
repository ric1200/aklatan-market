<?php
/** Sales reporting with a date range and a safe CSV export. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/admin-header.php';
require_admin();

$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) get_param('from')) ? (string) get_param('from') : date('Y-m-01');
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) get_param('to')) ? (string) get_param('to') : date('Y-m-d');
if (strtotime($from) > strtotime($to)) { [$from, $to] = [$to, $from]; }
$rangeStart = $from . ' 00:00:00';
$rangeEnd = $to . ' 23:59:59';
$params = ['start' => $rangeStart, 'end' => $rangeEnd];

$qualifying = "('confirmed','processing','shipped','delivered')";

/* --------------------------------------------------------------- CSV out -- */
if (get_param('export') === 'csv') {
    $lines = fetch_all("SELECT oi.product_name, oi.product_sku, SUM(oi.quantity) AS units,
            SUM(oi.line_total) AS revenue, COUNT(DISTINCT o.id) AS orders
        FROM order_items oi JOIN orders o ON o.id = oi.order_id
        WHERE o.status IN " . $qualifying . " AND o.created_at BETWEEN :start AND :end
        GROUP BY oi.product_name, oi.product_sku ORDER BY units DESC", $params);

    audit_log('report.exported', 'report', null, 'Sales report ' . $from . ' to ' . $to . ' exported to CSV');

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="aklatan-sales-' . $from . '-to-' . $to . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Aklatan Market sales report', $from . ' to ' . $to]);
    fputcsv($out, []);
    fputcsv($out, ['Product', 'SKU', 'Units sold', 'Revenue (PHP)', 'Orders containing it']);
    foreach ($lines as $line) {
        fputcsv($out, [$line['product_name'], $line['product_sku'], $line['units'], money_plain($line['revenue']), $line['orders']]);
    }
    fclose($out);
    exit;
}

/* --------------------------------------------------------------- figures -- */
$totals = fetch_one("SELECT COUNT(*) AS orders, COALESCE(SUM(total),0) AS revenue,
        COALESCE(SUM(discount_amount),0) AS discounts, COALESCE(SUM(shipping_fee),0) AS shipping
    FROM orders WHERE status IN " . $qualifying . " AND created_at BETWEEN :start AND :end", $params);

$units = (int) fetch_value("SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi JOIN orders o ON o.id = oi.order_id
    WHERE o.status IN " . $qualifying . " AND o.created_at BETWEEN :start AND :end", $params, 0);

$allOrders = fetch_one('SELECT COUNT(*) AS c, COALESCE(SUM(total),0) AS t FROM orders WHERE created_at BETWEEN :start AND :end', $params);
$cancelled = (int) fetch_value("SELECT COUNT(*) FROM orders WHERE status = 'cancelled' AND created_at BETWEEN :start AND :end", $params, 0);

$topProducts = fetch_all("SELECT oi.product_name, oi.product_sku, SUM(oi.quantity) AS units, SUM(oi.line_total) AS revenue
    FROM order_items oi JOIN orders o ON o.id = oi.order_id
    WHERE o.status IN " . $qualifying . " AND o.created_at BETWEEN :start AND :end
    GROUP BY oi.product_name, oi.product_sku ORDER BY units DESC LIMIT 12", $params);

$topCategories = fetch_all("SELECT COALESCE(c.name, 'Uncategorised') AS category_name, SUM(oi.quantity) AS units, SUM(oi.line_total) AS revenue
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    LEFT JOIN products p ON p.id = oi.product_id
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE o.status IN " . $qualifying . " AND o.created_at BETWEEN :start AND :end
    GROUP BY COALESCE(c.name, 'Uncategorised') ORDER BY revenue DESC LIMIT 10", $params);

$byMethod = fetch_all("SELECT payment_method, COUNT(*) AS c, COALESCE(SUM(total),0) AS t
    FROM orders WHERE created_at BETWEEN :start AND :end GROUP BY payment_method ORDER BY t DESC", $params);

$byStatus = fetch_all('SELECT status, COUNT(*) AS c, COALESCE(SUM(total),0) AS t
    FROM orders WHERE created_at BETWEEN :start AND :end GROUP BY status ORDER BY c DESC', $params);

$daily = fetch_all("SELECT DATE(created_at) AS day, COUNT(*) AS c, COALESCE(SUM(total),0) AS t
    FROM orders WHERE status IN " . $qualifying . " AND created_at BETWEEN :start AND :end
    GROUP BY DATE(created_at) ORDER BY day ASC", $params);
$dailyMax = 1.0;
foreach ($daily as $row) { $dailyMax = max($dailyMax, (float) $row['t']); }

$avgOrder = (float) $totals['orders'] > 0 ? (float) $totals['revenue'] / (int) $totals['orders'] : 0.0;

render_admin_start([
    'current' => 'reports',
    'title' => 'Reports',
    'subtitle' => 'Confirmed, processing, shipped and delivered orders between '
        . format_date($from . ' 00:00:00') . ' and ' . format_date($to . ' 23:59:59') . '.',
    'crumbs' => [['label' => 'Dashboard', 'url' => 'admin/index.php'], ['label' => 'Reports']],
    'actions' => '<a class="btn btn-ghost btn-sm" href="' . e(url('admin/reports.php?' . http_build_query(['from' => $from, 'to' => $to, 'export' => 'csv']))) . '">'
        . icon('download', '', 16) . ' Export product lines (CSV)</a>',
]);
?>
<form class="admin-toolbar admin-panel" method="get" action="<?= e(url('admin/reports.php')) ?>" style="padding:14px 16px">
  <div class="field tight">
    <label for="from">From</label>
    <input id="from" type="date" name="from" value="<?= e($from) ?>">
  </div>
  <div class="field tight">
    <label for="to">To</label>
    <input id="to" type="date" name="to" value="<?= e($to) ?>">
  </div>
  <button type="submit" class="btn btn-secondary btn-sm">Apply range</button>
  <a class="btn btn-quiet btn-sm" href="<?= e(url('admin/reports.php?from=' . date('Y-m-d', strtotime('-29 days')) . '&to=' . date('Y-m-d'))) ?>">Last 30 days</a>
  <a class="btn btn-quiet btn-sm" href="<?= e(url('admin/reports.php?from=' . date('Y-01-01') . '&to=' . date('Y-m-d'))) ?>">Year to date</a>
</form>

<div class="kpi-grid">
  <div class="kpi kpi-ok"><p class="kpi-label">Revenue</p><p class="kpi-value"><?= e(money($totals['revenue'])) ?></p><p class="kpi-note">Qualifying orders</p></div>
  <div class="kpi"><p class="kpi-label">Orders</p><p class="kpi-value"><?= number_format((int) $totals['orders']) ?></p><p class="kpi-note"><?= (int) $allOrders['c'] ?> placed in range</p></div>
  <div class="kpi"><p class="kpi-label">Average order</p><p class="kpi-value"><?= e(money($avgOrder)) ?></p><p class="kpi-note">Revenue ÷ orders</p></div>
  <div class="kpi"><p class="kpi-label">Units sold</p><p class="kpi-value"><?= number_format($units) ?></p><p class="kpi-note">Across all titles</p></div>
  <div class="kpi"><p class="kpi-label">Discounts given</p><p class="kpi-value"><?= e(money($totals['discounts'])) ?></p><p class="kpi-note">Applied at checkout</p></div>
  <div class="kpi <?= $cancelled ? 'kpi-warn' : '' ?>"><p class="kpi-label">Cancelled orders</p><p class="kpi-value"><?= number_format($cancelled) ?></p><p class="kpi-note">Stock was returned</p></div>
</div>

<?php if ((int) $totals['orders'] === 0): ?>
  <div class="admin-panel"><div class="admin-panel-body">
    <?php render_empty_state('chart', 'No sales in this range',
        'Nothing was ordered between these dates, or the orders are still pending. Widen the range to look further back.',
        [['label' => 'Show year to date', 'url' => url('admin/reports.php?from=' . date('Y-01-01') . '&to=' . date('Y-m-d'))]]); ?>
  </div></div>
<?php else: ?>
  <div class="admin-grid-2">
    <div class="admin-panel">
      <div class="admin-panel-head"><h2>Daily revenue</h2><p class="panel-note"><?= count($daily) ?> trading day(s)</p></div>
      <div class="admin-panel-body">
        <div class="chart-bars">
          <?php foreach ($daily as $row): ?>
            <div class="chart-bar">
              <span><?= e(format_date((string) $row['day'], 'D j M')) ?></span>
              <span class="bar"><span style="width: <?= max(2, (int) round(((float) $row['t'] / $dailyMax) * 100)) ?>%"></span></span>
              <span class="amount"><?= e(money($row['t'])) ?> <span class="tiny muted">(<?= (int) $row['c'] ?>)</span></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="admin-panel">
      <div class="admin-panel-head"><h2>Revenue by category</h2></div>
      <div class="admin-panel-body">
        <?php if (!$topCategories): ?>
          <p class="muted small">No categorised sales in this range.</p>
        <?php else: ?>
          <?php $catMax = max(1.0, max(array_map(function ($r) { return (float) $r['revenue']; }, $topCategories))); ?>
          <div class="chart-bars">
            <?php foreach ($topCategories as $row): ?>
              <div class="chart-bar">
                <span><?= e(truncate((string) $row['category_name'], 16)) ?></span>
                <span class="bar"><span style="width: <?= max(2, (int) round(((float) $row['revenue'] / $catMax) * 100)) ?>%"></span></span>
                <span class="amount"><?= (int) $row['units'] ?> u · <?= e(money($row['revenue'])) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="admin-panel">
    <div class="admin-panel-head"><h2>Best-selling titles</h2><p class="panel-note">Top 12 by units</p></div>
    <div class="admin-panel-body flush">
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th scope="col">#</th><th scope="col">Title</th><th scope="col">SKU</th>
            <th scope="col" class="num">Units</th><th scope="col" class="num">Revenue</th></tr></thead>
          <tbody>
            <?php foreach ($topProducts as $index => $row): ?>
              <tr>
                <td class="num"><?= $index + 1 ?></td>
                <td><?= e($row['product_name']) ?></td>
                <td class="tiny"><?= e((string) ($row['product_sku'] ?? '—')) ?></td>
                <td class="num"><?= (int) $row['units'] ?></td>
                <td class="num"><?= e(money($row['revenue'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="admin-grid-2">
    <div class="admin-panel">
      <div class="admin-panel-head"><h2>Payment methods</h2></div>
      <div class="admin-panel-body">
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th scope="col">Method</th><th scope="col" class="num">Orders</th><th scope="col" class="num">Value</th></tr></thead>
            <tbody>
              <?php foreach ($byMethod as $row): ?>
                <tr><td><?= e(payment_method_label((string) $row['payment_method'])) ?></td>
                  <td class="num"><?= (int) $row['c'] ?></td>
                  <td class="num"><?= e(money($row['t'])) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="admin-panel">
      <div class="admin-panel-head"><h2>Orders by status</h2><p class="panel-note">All orders in range, including pending</p></div>
      <div class="admin-panel-body">
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th scope="col">Status</th><th scope="col" class="num">Orders</th><th scope="col" class="num">Value</th></tr></thead>
            <tbody>
              <?php foreach ($byStatus as $row): ?>
                <tr><td><?= order_status_badge((string) $row['status']) ?></td>
                  <td class="num"><?= (int) $row['c'] ?></td>
                  <td class="num"><?= e(money($row['t'])) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <p class="small muted">Figures are computed live from the orders and order_items tables. The CSV export contains
    product lines only; use the order list export for one row per order. Both are written to the audit log.</p>
<?php endif; ?>
<?php render_admin_end(); ?>
