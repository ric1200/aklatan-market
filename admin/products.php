<?php
/** Product list with search, filters and safe activate / deactivate / delete. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/admin-header.php';
require_admin();

/* ------------------------------------------------------------- bulk action */
if (is_post() && post('action') === 'toggle_active') {
    csrf_guard();
    $product = product_by_id(post_int('product_id'), false);
    if ($product) {
        $newState = (int) $product['is_active'] === 1 ? 0 : 1;
        db_update('products', ['is_active' => $newState], 'id', (int) $product['id']);
        audit_log('product.' . ($newState ? 'activated' : 'deactivated'), 'product', (int) $product['id'], $product['name']);
        flash_success($product['name'] . ' is now ' . ($newState ? 'active and visible in the shop.' : 'hidden from the shopfront.'));
    } else {
        flash_error('That product could not be found.');
    }
    redirect('admin/products.php');
}

if (is_post() && post('action') === 'delete_product') {
    csrf_guard();
    $product = product_by_id(post_int('product_id'), false);
    if (!$product) {
        flash_error('That product could not be found.');
        redirect('admin/products.php');
    }
    $sold = (int) fetch_value('SELECT COUNT(*) FROM order_items WHERE product_id = :id', ['id' => (int) $product['id']], 0);
    q('DELETE FROM products WHERE id = :id', ['id' => (int) $product['id']]);
    delete_upload((string) ($product['image'] ?? ''));
    audit_log('product.deleted', 'product', (int) $product['id'], $product['name'] . ' (' . $sold . ' order lines kept as snapshots)');
    flash_success($product['name'] . ' was deleted. '
        . ($sold ? $sold . ' past order line' . ($sold === 1 ? '' : 's') . ' kept a snapshot of the name and price.' : ''));
    redirect('admin/products.php');
}

/* ----------------------------------------------------------------- listing */
$keyword = truncate(get_param('q'), 80);
$categoryId = get_int('category');
$status = (string) get_param('status');
$sort = (string) get_param('sort', 'name');
$page = max(1, get_int('page', 1));

$where = ['1=1'];
$params = [];
if ($keyword !== '') {
    $where[] = like_or_clause(['p.name', 'p.creator', 'p.sku', 'p.isbn'], $keyword, $params, 'kw');
}
if ($categoryId > 0) { $where[] = 'p.category_id = :cat'; $params['cat'] = $categoryId; }
if ($status === 'active') { $where[] = 'p.is_active = 1'; }
if ($status === 'inactive') { $where[] = 'p.is_active = 0'; }
if ($status === 'low') { $where[] = 'p.stock_quantity <= :low'; $params['low'] = setting_int('low_stock_threshold', 5); }
if ($status === 'out') { $where[] = 'p.stock_quantity <= 0'; }
if ($status === 'sale') { $where[] = 'p.compare_at_price IS NOT NULL AND p.compare_at_price > p.price'; }

$sortMap = [
    'name' => 'p.name ASC',
    'newest' => 'p.created_at DESC',
    'updated' => 'p.updated_at DESC',
    'price_asc' => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'stock_asc' => 'p.stock_quantity ASC',
    'stock_desc' => 'p.stock_quantity DESC',
];
$orderBy = $sortMap[$sort] ?? $sortMap['name'];
$whereSql = implode(' AND ', $where);

$total = (int) fetch_value('SELECT COUNT(*) FROM products p WHERE ' . $whereSql, $params, 0);
$p = paginate($total, 20, $page);
$products = fetch_all('SELECT p.*, c.name AS category_name FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE ' . $whereSql . ' ORDER BY ' . $orderBy . ' LIMIT ' . (int) $p['perPage'] . ' OFFSET ' . (int) $p['offset'], $params);
$categories = catalog_categories(false);

$query = array_filter(['q' => $keyword, 'category' => $categoryId ?: '', 'status' => $status, 'sort' => $sort !== 'name' ? $sort : ''],
    function ($v) { return $v !== '' && $v !== null; });

render_admin_start([
    'current' => 'products',
    'title' => 'Products',
    'subtitle' => number_format($total) . ' matching ' . ($total === 1 ? 'product' : 'products') . ' · edit prices, stock and visibility.',
    'crumbs' => [['label' => 'Dashboard', 'url' => 'admin/index.php'], ['label' => 'Products']],
    'actions' => '<a class="btn btn-primary btn-sm" href="' . e(url('admin/product-edit.php')) . '">' . icon('plus', '', 16) . ' Add a product</a>'
        . '<a class="btn btn-ghost btn-sm" href="' . e(url('admin/inventory.php')) . '">' . icon('box', '', 16) . ' Inventory</a>',
]);
?>

<form class="admin-toolbar admin-panel" method="get" action="<?= e(url('admin/products.php')) ?>" style="padding:14px 16px">
  <div class="field">
    <label for="q">Search</label>
    <input id="q" type="search" name="q" value="<?= e($keyword) ?>" placeholder="Title, author, SKU or ISBN">
  </div>
  <div class="field tight">
    <label for="category">Category</label>
    <select id="category" name="category">
      <option value="">All categories</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= (int) $cat['id'] ?>" <?= $categoryId === (int) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field tight">
    <label for="status">Status</label>
    <select id="status" name="status">
      <option value="">Any status</option>
      <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active only</option>
      <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Hidden only</option>
      <option value="low" <?= $status === 'low' ? 'selected' : '' ?>>Low stock</option>
      <option value="out" <?= $status === 'out' ? 'selected' : '' ?>>Out of stock</option>
      <option value="sale" <?= $status === 'sale' ? 'selected' : '' ?>>On sale</option>
    </select>
  </div>
  <div class="field tight">
    <label for="sort">Sort by</label>
    <select id="sort" name="sort">
      <?php foreach (['name' => 'Name A–Z', 'newest' => 'Newest', 'updated' => 'Recently edited', 'price_asc' => 'Price low to high',
                      'price_desc' => 'Price high to low', 'stock_asc' => 'Stock low to high', 'stock_desc' => 'Stock high to low'] as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= $sort === $key ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
  <a class="btn btn-quiet btn-sm" href="<?= e(url('admin/products.php')) ?>">Reset</a>
</form>

<div class="admin-panel">
  <div class="admin-panel-head">
    <h2>Product list</h2>
    <p class="panel-note">Hiding a product is always safer than deleting it — past orders already keep their own copy of the name and price.</p>
  </div>
  <div class="admin-panel-body flush">
    <?php if (!$products): ?>
      <div style="padding:16px">
        <?php render_empty_state('book', 'No products matched',
            'Adjust the filters, or add a new product to the catalogue.',
            [['label' => 'Add a product', 'url' => url('admin/product-edit.php')], ['label' => 'Reset filters', 'url' => url('admin/products.php'), 'class' => 'btn-ghost']]); ?>
      </div>
    <?php else: ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <caption class="sr-only">All products with visibility, stock and deletion controls</caption>
          <thead>
            <tr>
              <th scope="col">Product</th>
              <th scope="col">Category</th>
              <th scope="col" class="num">Price</th>
              <th scope="col" class="num">Stock</th>
              <th scope="col">Flags</th>
              <th scope="col">Status</th>
              <th scope="col" class="actions">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($products as $product): ?>
              <?php [$stockKey, $stockLabel] = stock_state($product); ?>
              <tr>
                <td>
                  <div class="cluster" style="flex-wrap:nowrap">
                    <img class="thumb" src="<?= e(product_image_url($product)) ?>" alt="" width="34" height="51" loading="lazy">
                    <span>
                      <a href="<?= e(url('admin/product-edit.php?id=' . (int) $product['id'])) ?>"><strong><?= e($product['name']) ?></strong></a><br>
                      <span class="tiny muted"><?= e((string) ($product['creator'] ?? '—')) ?> · <?= e((string) $product['sku']) ?></span>
                    </span>
                  </div>
                </td>
                <td><?= e((string) ($product['category_name'] ?? '—')) ?></td>
                <td class="num">
                  <?= e(money($product['price'])) ?>
                  <?php if ((float) $product['compare_at_price'] > (float) $product['price']): ?>
                    <br><span class="tiny muted" style="text-decoration:line-through"><?= e(money($product['compare_at_price'])) ?></span>
                  <?php endif; ?>
                </td>
                <td class="num"><span class="stock-<?= e($stockKey) ?>"><?= (int) $product['stock_quantity'] ?></span></td>
                <td class="tiny">
                  <?php if (!empty($product['is_featured'])): ?><span class="pill pill-amber">Featured</span> <?php endif; ?>
                  <?php if (!empty($product['is_bestseller'])): ?><span class="pill pill-ok">Best</span> <?php endif; ?>
                  <?php if (!empty($product['is_new_arrival'])): ?><span class="pill">New</span> <?php endif; ?>
                  <?php if (!empty($product['is_staff_pick'])): ?><span class="pill pill-muted">Staff</span> <?php endif; ?>
                </td>
                <td>
                  <?php if ((int) $product['is_active'] === 1): ?>
                    <span class="pill pill-ok">Live</span>
                  <?php else: ?>
                    <span class="pill pill-muted">Hidden</span>
                  <?php endif; ?>
                </td>
                <td class="actions">
                  <span class="admin-actions">
                    <a class="btn btn-ghost btn-xs" href="<?= e(url('product.php?slug=' . urlencode((string) $product['slug']))) ?>" target="_blank" rel="noopener">View</a>
                    <a class="btn btn-ghost btn-xs" href="<?= e(url('admin/product-edit.php?id=' . (int) $product['id'])) ?>">Edit</a>
                    <form method="post" action="<?= e(url('admin/products.php')) ?>">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="toggle_active">
                      <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                      <button type="submit" class="btn btn-quiet btn-xs"><?= (int) $product['is_active'] === 1 ? 'Hide' : 'Publish' ?></button>
                    </form>
                    <form method="post" action="<?= e(url('admin/products.php')) ?>"
                          data-confirm="Permanently delete &quot;<?= e($product['name']) ?>&quot;? This cannot be undone.">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete_product">
                      <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                      <button type="submit" class="btn btn-quiet btn-xs" style="color:var(--danger)">Delete</button>
                    </form>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?= pagination_links($p, 'admin/products.php', $query) ?>
<?php render_admin_end(); ?>
