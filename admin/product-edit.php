<?php
/**
 * Create or edit a product, including a strictly validated cover image upload.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/admin-header.php';
require_admin();

$productId = get_int('id');
$product = $productId > 0 ? product_by_id($productId, false) : null;
if ($productId > 0 && !$product) {
    aklatan_abort(404, 'That product does not exist', 'It may have been deleted. Return to the product list.');
}

$errors = [];
$form = [
    'name' => '', 'slug' => '', 'sku' => '', 'category_id' => '', 'creator' => '', 'isbn' => '',
    'publisher' => '', 'product_format' => '', 'language' => 'English', 'pages' => '',
    'publication_date' => '', 'short_description' => '', 'description' => '',
    'price' => '', 'compare_at_price' => '', 'cost_price' => '', 'stock_quantity' => '0',
    'low_stock_threshold' => '', 'is_active' => '1', 'is_featured' => '0', 'is_bestseller' => '0',
    'is_new_arrival' => '0', 'is_staff_pick' => '0', 'image' => '',
];
if ($product) {
    foreach (array_keys($form) as $key) {
        $form[$key] = (string) ($product[$key] ?? '');
    }
}

if (is_post() && post('action') === 'save_product') {
    csrf_guard();

    $form['name'] = truncate(post_raw('name'), 200);
    $form['slug'] = strtolower(preg_replace('/[^a-z0-9\-]/', '', str_replace(' ', '-', trim((string) post_raw('slug')))));
    $form['sku'] = truncate(post_raw('sku'), 60);
    $form['category_id'] = (string) post_int('category_id');
    $form['creator'] = truncate(post_raw('creator'), 160);
    $form['isbn'] = truncate(post_raw('isbn'), 20);
    $form['publisher'] = truncate(post_raw('publisher'), 160);
    $form['product_format'] = truncate(post_raw('product_format'), 60);
    $form['language'] = truncate(post_raw('language'), 60);
    $form['pages'] = post('pages') !== '' ? (string) max(0, post_int('pages')) : '';
    $form['publication_date'] = truncate(post_raw('publication_date'), 10);
    $form['short_description'] = truncate(post_raw('short_description'), 300);
    $form['description'] = truncate(post_raw('description'), 6000);
    $form['price'] = post('price');
    $form['compare_at_price'] = post('compare_at_price');
    $form['cost_price'] = post('cost_price');
    $form['stock_quantity'] = (string) max(0, post_int('stock_quantity'));
    $form['low_stock_threshold'] = post('low_stock_threshold') !== '' ? (string) max(0, post_int('low_stock_threshold')) : '';
    foreach (['is_active', 'is_featured', 'is_bestseller', 'is_new_arrival', 'is_staff_pick'] as $flag) {
        $form[$flag] = checkbox_post($flag) ? '1' : '0';
    }

    if ($form['name'] === '') { $errors[] = 'Please enter the product name.'; }
    if ($form['slug'] === '') { $form['slug'] = slugify($form['name']); }
    if ($form['sku'] === '') { $form['sku'] = 'AKM-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)); }
    if (!is_numeric($form['price']) || (float) $form['price'] < 0) { $errors[] = 'Please enter a valid selling price.'; }
    if ($form['compare_at_price'] !== '' && (!is_numeric($form['compare_at_price']) || (float) $form['compare_at_price'] < 0)) {
        $errors[] = 'The compare-at price must be a number, or left blank.';
    }
    if (is_numeric($form['compare_at_price']) && (float) $form['compare_at_price'] > 0 && (float) $form['compare_at_price'] < (float) $form['price']) {
        $errors[] = 'The compare-at price should be higher than the selling price, or left blank.';
    }
    if ($form['publication_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $form['publication_date'])) {
        $errors[] = 'The publication date must look like 2026-01-31, or be left blank.';
    }
    if ($form['category_id'] !== '' && $form['category_id'] !== '0') {
        $catExists = fetch_value('SELECT id FROM categories WHERE id = :id', ['id' => (int) $form['category_id']]);
        if (!$catExists) { $errors[] = 'That category no longer exists.'; }
    }

    $duplicate = fetch_value('SELECT id FROM products WHERE slug = :slug' . ($product ? ' AND id <> :id' : ''),
        $product ? ['slug' => $form['slug'], 'id' => $productId] : ['slug' => $form['slug']]);
    if ($duplicate) { $errors[] = 'Another product already uses the web address (slug) “' . $form['slug'] . '”.'; }

    $duplicateSku = fetch_value('SELECT id FROM products WHERE sku = :sku' . ($product ? ' AND id <> :id' : ''),
        $product ? ['sku' => $form['sku'], 'id' => $productId] : ['sku' => $form['sku']]);
    if ($duplicateSku) { $errors[] = 'Another product already uses the SKU “' . $form['sku'] . '”.'; }

    $newImage = null;
    if (!empty($_FILES['image']['name'])) {
        [$uploadOk, $uploadMessage, $uploadedPath] = save_uploaded_image($_FILES['image'], 'products');
        if ($uploadOk) { $newImage = $uploadedPath; }
        else { $errors[] = $uploadMessage; }
    }

    if (!$errors) {
        $data = [
            'name' => $form['name'],
            'slug' => $form['slug'],
            'sku' => $form['sku'],
            'category_id' => ($form['category_id'] !== '' && $form['category_id'] !== '0') ? (int) $form['category_id'] : null,
            'creator' => $form['creator'] !== '' ? $form['creator'] : null,
            'isbn' => $form['isbn'] !== '' ? $form['isbn'] : null,
            'publisher' => $form['publisher'] !== '' ? $form['publisher'] : null,
            'product_format' => $form['product_format'] !== '' ? $form['product_format'] : null,
            'language' => $form['language'] !== '' ? $form['language'] : null,
            'pages' => $form['pages'] !== '' ? (int) $form['pages'] : null,
            'publication_date' => $form['publication_date'] !== '' ? $form['publication_date'] : null,
            'short_description' => $form['short_description'] !== '' ? $form['short_description'] : null,
            'description' => $form['description'] !== '' ? $form['description'] : null,
            'price' => round((float) $form['price'], 2),
            'compare_at_price' => $form['compare_at_price'] !== '' ? round((float) $form['compare_at_price'], 2) : null,
            'cost_price' => $form['cost_price'] !== '' ? round((float) $form['cost_price'], 2) : null,
            'stock_quantity' => (int) $form['stock_quantity'],
            'low_stock_threshold' => $form['low_stock_threshold'] !== '' ? (int) $form['low_stock_threshold'] : null,
            'is_active' => (int) $form['is_active'],
            'is_featured' => (int) $form['is_featured'],
            'is_bestseller' => (int) $form['is_bestseller'],
            'is_new_arrival' => (int) $form['is_new_arrival'],
            'is_staff_pick' => (int) $form['is_staff_pick'],
        ];
        if ($newImage !== null) { $data['image'] = $newImage; }

        if ($product) {
            $oldImage = (string) ($product['image'] ?? '');
            db_update('products', $data, 'id', $productId);
            if ($newImage !== null && $oldImage !== '' && $oldImage !== $newImage && strpos($oldImage, 'uploads/') === 0) {
                delete_upload($oldImage);
            }
            audit_log('product.updated', 'product', $productId, 'Updated ' . $data['name'] . ' (price ' . money($data['price']) . ')');
            flash_success($data['name'] . ' was updated.');
            redirect('admin/product-edit.php?id=' . $productId);
        }
        $newId = db_insert('products', $data);
        audit_log('product.created', 'product', $newId, 'Created ' . $data['name'] . ' (price ' . money($data['price']) . ')');
        flash_success($data['name'] . ' was added to the catalogue.');
        redirect('admin/product-edit.php?id=' . $newId);
    }
}

/* Removing a cover image (keeps the placeholder fallback in the storefront). */
if (is_post() && post('action') === 'remove_image' && $product) {
    csrf_guard();
    delete_upload((string) ($product['image'] ?? ''));
    db_update('products', ['image' => null], 'id', $productId);
    audit_log('product.image_removed', 'product', $productId, 'Cover image cleared');
    flash_success('Cover image removed. The storefront placeholder will be shown instead.');
    redirect('admin/product-edit.php?id=' . $productId);
}

$categories = catalog_categories(false);
render_admin_start([
    'current' => 'products',
    'title' => $product ? 'Edit: ' . $product['name'] : 'Add a product',
    'subtitle' => $product ? 'SKU ' . $product['sku'] . ' · last edited ' . format_datetime($product['updated_at']) : 'Fill in the details shoppers will see.',
    'crumbs' => [
        ['label' => 'Dashboard', 'url' => 'admin/index.php'],
        ['label' => 'Products', 'url' => 'admin/products.php'],
        ['label' => $product ? 'Edit' : 'New'],
    ],
    'actions' => $product
        ? '<a class="btn btn-ghost btn-sm" href="' . e(url('product.php?slug=' . urlencode((string) $product['slug']))) . '" target="_blank" rel="noopener">'
            . icon('external', '', 16) . ' View in shop</a>'
        : '',
]);
?>
<?php if ($errors): ?>
  <div class="notice notice-error" role="alert">
    <?= icon('alert', 'notice-icon', 18) ?>
    <div class="notice-body"><strong>Please fix the following</strong>
      <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
  </div>
<?php endif; ?>

<form method="post" action="<?= e(url('admin/product-edit.php' . ($product ? '?id=' . $productId : ''))) ?>"
      enctype="multipart/form-data" class="admin-grid-2">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save_product">

  <div>
    <div class="admin-panel">
      <div class="admin-panel-head"><h2>Basics</h2></div>
      <div class="admin-panel-body form-grid">
        <div class="field">
          <label for="name">Product name</label>
          <input id="name" type="text" name="name" required maxlength="200" value="<?= e($form['name']) ?>">
        </div>
        <div class="field-row">
          <div class="field">
            <label for="slug">Web address (slug)</label>
            <input id="slug" type="text" name="slug" maxlength="220" value="<?= e($form['slug']) ?>" placeholder="generated from the name">
            <span class="hint">Lowercase letters, numbers and dashes only. Used in the product link.</span>
          </div>
          <div class="field">
            <label for="sku">SKU</label>
            <input id="sku" type="text" name="sku" maxlength="60" value="<?= e($form['sku']) ?>" placeholder="left blank = generated">
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="category_id">Category</label>
            <select id="category_id" name="category_id">
              <option value="">Uncategorised</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= (int) $cat['id'] ?>" <?= $form['category_id'] === (string) $cat['id'] ? 'selected' : '' ?>>
                  <?= e($cat['name']) ?><?= empty($cat['is_active']) ? ' (hidden)' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="creator">Author / artist / label</label>
            <input id="creator" type="text" name="creator" maxlength="160" value="<?= e($form['creator']) ?>">
          </div>
        </div>
        <div class="field">
          <label for="short_description">Short description <span class="hint">(shown on cards and in search results)</span></label>
          <input id="short_description" type="text" name="short_description" maxlength="300" value="<?= e($form['short_description']) ?>">
        </div>
        <div class="field">
          <label for="description">Full description</label>
          <textarea id="description" name="description" maxlength="6000" rows="8"><?= e($form['description']) ?></textarea>
          <span class="hint">Blank lines become paragraphs on the product page.</span>
        </div>
      </div>
    </div>

    <div class="admin-panel">
      <div class="admin-panel-head"><h2>Bookshop details</h2><p class="panel-note">Optional — leave blank for non-book items.</p></div>
      <div class="admin-panel-body form-grid">
        <div class="field-row">
          <div class="field">
            <label for="isbn">ISBN</label>
            <input id="isbn" type="text" name="isbn" maxlength="20" value="<?= e($form['isbn']) ?>">
          </div>
          <div class="field">
            <label for="publisher">Publisher / label</label>
            <input id="publisher" type="text" name="publisher" maxlength="160" value="<?= e($form['publisher']) ?>">
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="product_format">Format</label>
            <input id="product_format" type="text" name="product_format" maxlength="60" value="<?= e($form['product_format']) ?>" placeholder="Paperback, Hardcover, Vinyl, DVD">
          </div>
          <div class="field">
            <label for="language">Language</label>
            <input id="language" type="text" name="language" maxlength="60" value="<?= e($form['language']) ?>">
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="pages">Pages</label>
            <input id="pages" type="number" name="pages" min="0" value="<?= e($form['pages']) ?>">
          </div>
          <div class="field">
            <label for="publication_date">Publication date</label>
            <input id="publication_date" type="date" name="publication_date" value="<?= e($form['publication_date']) ?>">
          </div>
        </div>
      </div>
    </div>
  </div>

  <div>
    <div class="admin-panel">
      <div class="admin-panel-head"><h2>Price &amp; stock</h2></div>
      <div class="admin-panel-body form-grid">
        <div class="field-row">
          <div class="field">
            <label for="price">Selling price (₱)</label>
            <input id="price" type="number" name="price" step="0.01" min="0" required value="<?= e($form['price']) ?>">
          </div>
          <div class="field">
            <label for="compare_at_price">Compare-at price (₱)</label>
            <input id="compare_at_price" type="number" name="compare_at_price" step="0.01" min="0" value="<?= e($form['compare_at_price']) ?>">
            <span class="hint">Shown struck through. Must be higher than the selling price.</span>
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="stock_quantity">Stock on hand</label>
            <input id="stock_quantity" type="number" name="stock_quantity" min="0" required value="<?= e($form['stock_quantity']) ?>">
          </div>
          <div class="field">
            <label for="low_stock_threshold">Low-stock warning at</label>
            <input id="low_stock_threshold" type="number" name="low_stock_threshold" min="0" value="<?= e($form['low_stock_threshold']) ?>" placeholder="<?= (int) setting_int('low_stock_threshold', 5) ?>">
          </div>
        </div>
        <div class="field">
          <label for="cost_price">Cost price (₱) <span class="hint">(private — never shown to shoppers)</span></label>
          <input id="cost_price" type="number" name="cost_price" step="0.01" min="0" value="<?= e($form['cost_price']) ?>">
        </div>
      </div>
    </div>

    <div class="admin-panel">
      <div class="admin-panel-head"><h2>Visibility</h2></div>
      <div class="admin-panel-body form-grid">
        <div class="check"><input id="is_active" type="checkbox" name="is_active" value="1" <?= $form['is_active'] === '1' ? 'checked' : '' ?>>
          <label for="is_active">Active — visible and purchasable in the shop</label></div>
        <div class="check"><input id="is_featured" type="checkbox" name="is_featured" value="1" <?= $form['is_featured'] === '1' ? 'checked' : '' ?>>
          <label for="is_featured">Featured — appears in the homepage hero</label></div>
        <div class="check"><input id="is_bestseller" type="checkbox" name="is_bestseller" value="1" <?= $form['is_bestseller'] === '1' ? 'checked' : '' ?>>
          <label for="is_bestseller">Bestseller — shows the bestseller badge and shelf</label></div>
        <div class="check"><input id="is_new_arrival" type="checkbox" name="is_new_arrival" value="1" <?= $form['is_new_arrival'] === '1' ? 'checked' : '' ?>>
          <label for="is_new_arrival">New arrival</label></div>
        <div class="check"><input id="is_staff_pick" type="checkbox" name="is_staff_pick" value="1" <?= $form['is_staff_pick'] === '1' ? 'checked' : '' ?>>
          <label for="is_staff_pick">Staff pick</label></div>
      </div>
    </div>

    <div class="admin-panel">
      <div class="admin-panel-head"><h2>Cover image</h2><p class="panel-note">JPG, PNG, GIF or WebP · max <?= e(human_bytes((int) UPLOAD_MAX_BYTES)) ?></p></div>
      <div class="admin-panel-body form-grid">
        <img id="image-preview" src="<?= e(product_image_url($product ?: ['image' => $form['image']])) ?>"
             alt="Current cover image" style="width:120px;aspect-ratio:2/3;object-fit:cover;border-radius:8px;border:1px solid var(--line)">
        <div class="field">
          <label for="image">Upload a new cover</label>
          <input id="image" type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp" data-preview="image-preview">
          <span class="hint">The file is re-checked on the server: real image bytes only, random file name, and stored in
            <code>uploads/products/</code> where scripts cannot run.</span>
        </div>
        <?php if ($product && !empty($product['image'])): ?>
          <p class="tiny muted">Current file: <code><?= e((string) $product['image']) ?></code></p>
        <?php else: ?>
          <p class="tiny muted">No image uploaded — the storefront shows a clean placeholder cover.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="cluster">
      <button type="submit" class="btn btn-primary"><?= icon('check', '', 17) ?> <?= $product ? 'Save changes' : 'Create product' ?></button>
      <a class="btn btn-ghost" href="<?= e(url('admin/products.php')) ?>">Cancel</a>
      <?php if ($product): ?>
        <a class="btn btn-quiet" href="<?= e(url('admin/inventory.php')) ?>">Stock overview</a>
      <?php endif; ?>
    </div>
  </div>
</form>

<?php if ($product && !empty($product['image'])): ?>
  <form method="post" action="<?= e(url('admin/product-edit.php?id=' . $productId)) ?>" class="mt-2"
        data-confirm="Remove the current cover image?">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="remove_image">
    <button type="submit" class="btn btn-quiet btn-sm" style="color:var(--danger)"><?= icon('trash', '', 15) ?> Remove cover image</button>
  </form>
<?php endif; ?>

<?php if ($product): ?>
  <div class="admin-panel mt-3">
    <div class="admin-panel-head"><h2>History for this product</h2></div>
    <div class="admin-panel-body">
      <?php $logs = fetch_all('SELECT a.*, u.first_name, u.last_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
          WHERE a.entity_type = :t AND a.entity_id = :id ORDER BY a.created_at DESC LIMIT 10',
          ['t' => 'product', 'id' => (string) $productId]); ?>
      <?php if (!$logs): ?>
        <p class="muted small">No recorded changes yet.</p>
      <?php else: ?>
        <ul class="audit-list">
          <?php foreach ($logs as $log): ?>
            <li>
              <span class="audit-action"><?= e(str_replace('.', ' → ', (string) $log['action'])) ?></span>
              <span class="audit-meta"><?= e((string) ($log['details'] ?? '')) ?></span>
              <span class="audit-meta"><?= e(format_datetime($log['created_at'])) ?>
                <?= $log['first_name'] ? '· ' . e(trim($log['first_name'] . ' ' . $log['last_name'])) : '· system' ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>
<?php render_admin_end(); ?>
