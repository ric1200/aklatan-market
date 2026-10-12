<?php
/** Inventory: bulk stock editing and a low-stock worklist. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/admin-header.php';
require_admin();

$threshold = setting_int('low_stock_threshold', 5);

if (is_post() && post('action') === 'save_stock') {
    csrf_guard();
    $stock = post_raw('stock', []);
    $restock = post_raw('restock', []);
    $changed = [];
    $products = fetch_all('SELECT id, name, stock_quantity FROM products');
    $byId = [];
    foreach ($products as $row) { $byId[(int) $row['id']] = $row; }

    foreach ((array) $stock as $productId => $value) {
        $productId = (int) $productId;
        if (!isset($byId[$productId]) || !is_numeric($value) || (int) $value < 0) { continue; }
        $add = isset($restock[$productId]) && is_numeric($restock[$productId]) ? max(0, (int) $restock[$productId]) : 0;
        $newValue = (int) $value + $add;
        if ($newValue === (int) $byId[$productId]['stock_quantity']) { continue; }
        q('UPDATE products SET stock_quantity = :q WHERE id = :id', ['q' => $newValue, 'id' => $productId]);
        $changed[] = $byId[$productId]['name'] . ': ' . (int) $byId[$productId]['stock_quantity'] . ' → ' . $newValue;
    }

    if ($changed) {
        audit_log('inventory.updated', 'product', null, count($changed) . ' stock level(s) changed: ' . implode('; ', array_slice($changed, 0, 12)));
        flash_success(count($changed) . ' stock ' . (count($changed) === 1 ? 'level' : 'levels') . ' updated.');
    } else {
        flash_info('No stock levels changed.');
    }
    redirect('admin/inventory.php' . (get_param('view') !== '' ? '?view=' . urlencode(get_param('view')) : ''));
}

$view = get_param('view') === 'low' ? 'low' : 'all';
$keyword = truncate(get_param('q'), 80);

$where = ['1=1'];
$params = [];
if ($view === 'low') { $where[] = 'p.stock_quantity <= :t'; $params['t'] = $threshold; }
if ($keyword !== '') {
    $where[] = '(p.name LIKE :kw OR p.sku LIKE :kw)';
    $params['kw'] = '%' . $keyword . '%';
}
$products = fetch_all('SELECT p.*, c.name AS category_name FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY p.stock_quantity ASC, p.name ASC LIMIT 200', $params);

$summary = [
    'total' => (int) fetch_value('SELECT COUNT(*) FROM products', [], 0),
    'active' => (int) fetch_value('SELECT COUNT(*) FROM products WHERE is_active = 1', [], 0),
    'out' => (int) fetch_value('SELECT COUNT(*) FROM products WHERE stock_quantity <= 0', [], 0),
    'low' => (int) fetch_value('SELECT COUNT(*) FROM products WHERE stock_quantity > 0 AND stock_quantity <= :t', ['t' => $threshold], 0),
    'units' => (int) fetch_value('SELECT COALESCE(SUM(stock_quantity),0) FROM products', [], 0),
    'cost' => (float) fetch_value('SELECT COALESCE(SUM(stock_quantity * COALESCE(cost_price, price * 0.6)),0) FROM products', [], 0),
];

render_admin_start([
    'current' => 'inventory',
    'title' => 'Inventory',
    'subtitle' => 'Adjust quantities in bulk and keep an eye on titles about to run out.',
    'crumbs' => [['label' => 'Dashboard', 'url' => 'admin/index.php'], ['label' => 'Inventory']],
    'actions' => '<a class="btn btn-ghost btn-sm" href="' . e(url('admin/inventory.php?view=' . ($view === 'low' ? 'all' : 'low'))) . '">'
        . ($view === 'low' ? icon('list', '', 16) . ' Show all products' : icon('alert', '', 16) . ' Only low stock') . '</a>',
]);
?>
<div class="kpi-grid">
  <div class="kpi"><p class="kpi-label">Listed titles</p><p class="kpi-value"><?= number_format($summary['total']) ?></p><p class="kpi-note"><?= number_format($summary['active']) ?> active</p></div>
  <div class="kpi <?= $summary['out'] ? 'kpi-bad' : '' ?>"><p class="kpi-label">Out of stock</p><p class="kpi-value"><?= number_format($summary['out']) ?></p><p class="kpi-note">Cannot be ordered</p></div>
  <div class="kpi <?= $summary['low'] ? 'kpi-warn' : '' ?>"><p class="kpi-label">Low stock</p><p class="kpi-value"><?= number_format($summary['low']) ?></p><p class="kpi-note">At or below <?= (int) $threshold ?> units</p></div>
  <div class="kpi"><p class="kpi-label">Units on hand</p><p class="kpi-value"><?= number_format($summary['units']) ?></p><p class="kpi-note">Across all titles</p></div>
  <div class="kpi"><p class="kpi-label">Estimated stock value</p><p class="kpi-value"><?= e(money($summary['cost'])) ?></p><p class="kpi-note">Uses cost price where known</p></div>
  <div class="kpi"><p class="kpi-label">Low-stock threshold</p><p class="kpi-value"><?= (int) $threshold ?></p><p class="kpi-note"><a href="<?= e(url('admin/settings.php')) ?>">Change in settings</a></p></div>
</div>

<form class="admin-toolbar admin-panel" method="get" action="<?= e(url('admin/inventory.php')) ?>" style="padding:14px 16px">
  <input type="hidden" name="view" value="<?= e($view) ?>">
  <div class="field">
    <label for="q">Search titles</label>
    <input id="q" type="search" name="q" value="<?= e($keyword) ?>" placeholder="Product name or SKU">
  </div>
  <button type="submit" class="btn btn-secondary btn-sm">Search</button>
  <a class="btn btn-quiet btn-sm" href="<?= e(url('admin/inventory.php')) ?>">Reset</a>
</form>

<form method="post" action="<?= e(url('admin/inventory.php?view=' . urlencode($view))) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save_stock">
  <div class="admin-panel">
    <div class="admin-panel-head">
      <h2><?= $view === 'low' ? 'Running low' : 'All products' ?> (<?= count($products) ?>)</h2>
      <p class="panel-note">Set a new quantity, or add a delivery on top of the current figure.</p>
    </div>
    <div class="admin-panel-body flush">
      <?php if (!$products): ?>
        <div style="padding:16px">
          <?php render_empty_state('box', $view === 'low' ? 'Nothing is running low' : 'No products matched',
              $view === 'low' ? 'Every title is above the low-stock threshold of ' . $threshold . ' units.' : 'Try a different search term.',
              [['label' => 'Show all products', 'url' => url('admin/inventory.php')]]); ?>
        </div>
      <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <caption class="sr-only">Bulk stock editor</caption>
            <thead>
              <tr>
                <th scope="col">Product</th><th scope="col">Category</th>
                <th scope="col" class="num">Current</th>
                <th scope="col" class="num">New quantity</th>
                <th scope="col" class="num">Add delivery</th>
                <th scope="col" class="num">Result</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($products as $product): ?>
                <?php $current = (int) $product['stock_quantity']; ?>
                <tr>
                  <td>
                    <a href="<?= e(url('admin/product-edit.php?id=' . (int) $product['id'])) ?>"><?= e($product['name']) ?></a><br>
                    <span class="tiny muted"><?= e((string) $product['sku']) ?><?= empty($product['is_active']) ? ' · hidden' : '' ?></span>
                  </td>
                  <td class="tiny"><?= e((string) ($product['category_name'] ?? '—')) ?></td>
                  <td class="num">
                    <span class="pill <?= $current <= 0 ? 'pill-bad' : ($current <= $threshold ? 'pill-warn' : 'pill-ok') ?>"><?= $current ?></span>
                  </td>
                  <td class="num">
                    <label class="sr-only" for="stock-<?= (int) $product['id'] ?>">New quantity for <?= e($product['name']) ?></label>
                    <input id="stock-<?= (int) $product['id'] ?>" type="number" name="stock[<?= (int) $product['id'] ?>]"
                           value="<?= $current ?>" min="0" style="width:88px;min-height:36px;padding:6px 8px;text-align:right">
                  </td>
                  <td class="num">
                    <label class="sr-only" for="restock-<?= (int) $product['id'] ?>">Delivery quantity for <?= e($product['name']) ?></label>
                    <input id="restock-<?= (int) $product['id'] ?>" type="number" name="restock[<?= (int) $product['id'] ?>]"
                           value="" min="0" placeholder="0" style="width:88px;min-height:36px;padding:6px 8px;text-align:right">
                  </td>
                  <td class="num tiny muted">new = quantity + delivery</td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div style="padding:14px 16px" class="cluster">
          <button type="submit" class="btn btn-primary btn-sm"><?= icon('check', '', 15) ?> Save stock changes</button>
          <span class="tiny muted">Every change is written to the audit log.</span>
        </div>
      <?php endif; ?>
    </div>
  </div>
</form>
<?php render_admin_end(); ?>
