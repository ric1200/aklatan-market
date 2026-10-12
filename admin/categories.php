<?php
/** Category management: create, rename, reorder, hide and delete. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/admin-header.php';
require_admin();

$iconChoices = ['book', 'book-open', 'clipboard', 'newspaper', 'sparkle', 'gift', 'list', 'music', 'film', 'pen', 'box', 'tag', 'star', 'heart'];
$accentChoices = ['ink', 'teal', 'amber', 'sage'];

$errors = [];
$editing = null;
$form = ['name' => '', 'slug' => '', 'description' => '', 'icon' => 'book', 'accent' => 'ink', 'sort_order' => '0', 'is_active' => '1'];

if (is_post()) {
    csrf_guard();
    $action = post('action');

    if ($action === 'save') {
        $categoryId = post_int('category_id');
        $form['name'] = truncate(post_raw('name'), 120);
        $form['slug'] = strtolower(preg_replace('/[^a-z0-9\-]/', '', str_replace(' ', '-', trim((string) post_raw('slug')))));
        $form['description'] = truncate(post_raw('description'), 600);
        $form['icon'] = in_array(post('icon'), $iconChoices, true) ? (string) post('icon') : 'book';
        $form['accent'] = in_array(post('accent'), $accentChoices, true) ? (string) post('accent') : 'ink';
        $form['sort_order'] = (string) post_int('sort_order');
        $form['is_active'] = checkbox_post('is_active') ? '1' : '0';

        if ($form['name'] === '') { $errors[] = 'Please enter the category name.'; }
        if ($form['slug'] === '') { $form['slug'] = slugify($form['name']); }

        $duplicate = fetch_value('SELECT id FROM categories WHERE slug = :slug' . ($categoryId ? ' AND id <> :id' : ''),
            $categoryId ? ['slug' => $form['slug'], 'id' => $categoryId] : ['slug' => $form['slug']]);
        if ($duplicate) { $errors[] = 'Another category already uses the slug “' . $form['slug'] . '”.'; }

        $existing = $categoryId > 0 ? fetch_one('SELECT * FROM categories WHERE id = :id', ['id' => $categoryId]) : null;
        if ($categoryId > 0 && !$existing) { $errors[] = 'That category could not be found.'; }

        if (!$errors) {
            $data = [
                'name' => $form['name'],
                'slug' => $form['slug'],
                'description' => $form['description'] !== '' ? $form['description'] : null,
                'icon' => $form['icon'],
                'accent' => $form['accent'],
                'sort_order' => (int) $form['sort_order'],
                'is_active' => (int) $form['is_active'],
            ];
            if ($existing) {
                db_update('categories', $data, 'id', $categoryId);
                audit_log('category.updated', 'category', $categoryId, 'Updated ' . $data['name']);
                flash_success($data['name'] . ' was updated.');
            } else {
                $data['id'] = null;
                unset($data['id']);
                $newId = db_insert('categories', $data);
                audit_log('category.created', 'category', $newId, 'Created ' . $data['name']);
                flash_success($data['name'] . ' was created.');
            }
            redirect('admin/categories.php');
        }
        $editing = ['id' => $categoryId];
    }

    if ($action === 'toggle') {
        $category = fetch_one('SELECT * FROM categories WHERE id = :id', ['id' => post_int('category_id')]);
        if ($category) {
            $state = (int) $category['is_active'] === 1 ? 0 : 1;
            db_update('categories', ['is_active' => $state], 'id', (int) $category['id']);
            audit_log('category.' . ($state ? 'activated' : 'deactivated'), 'category', (int) $category['id'], $category['name']);
            flash_success($category['name'] . ' is now ' . ($state ? 'visible in the shop.' : 'hidden (products keep their category but the shelf is hidden).'));
        }
        redirect('admin/categories.php');
    }

    if ($action === 'delete') {
        $category = fetch_one('SELECT * FROM categories WHERE id = :id', ['id' => post_int('category_id')]);
        if ($category) {
            $affected = (int) fetch_value('SELECT COUNT(*) FROM products WHERE category_id = :id', ['id' => (int) $category['id']], 0);
            q('DELETE FROM categories WHERE id = :id', ['id' => (int) $category['id']]);
            audit_log('category.deleted', 'category', (int) $category['id'], $category['name'] . ' (' . $affected . ' products moved to uncategorised)');
            flash_success($category['name'] . ' was deleted. '
                . ($affected ? $affected . ' product' . ($affected === 1 ? '' : 's') . ' are now uncategorised — reassign them when you can.' : ''));
        }
        redirect('admin/categories.php');
    }
}

if (array_key_exists('edit', $_GET)) {
    $editId = get_int('edit');
    if ($editId > 0) {
        $record = fetch_one('SELECT * FROM categories WHERE id = :id', ['id' => $editId]);
        if ($record) {
            $editing = $record;
            foreach (array_keys($form) as $key) {
                if (isset($record[$key])) { $form[$key] = (string) ($record[$key] ?? ''); }
            }
            $form['is_active'] = !empty($record['is_active']) ? '1' : '0';
        }
    } else {
        $editing = ['id' => 0];
    }
}

$categories = fetch_all('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS total_products,
    (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.is_active = 1) AS active_products
    FROM categories c ORDER BY c.sort_order ASC, c.name ASC');

render_admin_start([
    'current' => 'categories',
    'title' => 'Categories',
    'subtitle' => count($categories) . ' shelves organising the catalogue.',
    'crumbs' => [['label' => 'Dashboard', 'url' => 'admin/index.php'], ['label' => 'Categories']],
    'actions' => '<a class="btn btn-primary btn-sm" href="' . e(url('admin/categories.php?edit=0')) . '">' . icon('plus', '', 16) . ' New category</a>',
]);
?>
<?php if ($errors): ?>
  <div class="notice notice-error" role="alert">
    <?= icon('alert', 'notice-icon', 18) ?>
    <div class="notice-body"><strong>Please fix the following</strong>
      <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
  </div>
<?php endif; ?>

<?php if ($editing): ?>
  <div class="admin-panel">
    <div class="admin-panel-head">
      <h2><?= (int) ($editing['id'] ?? 0) > 0 ? 'Edit category' : 'New category' ?></h2>
      <a class="btn btn-quiet btn-xs" href="<?= e(url('admin/categories.php')) ?>">Close form</a>
    </div>
    <div class="admin-panel-body">
      <form method="post" action="<?= e(url('admin/categories.php')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="category_id" value="<?= (int) ($editing['id'] ?? 0) ?>">
        <div class="field-row">
          <div class="field">
            <label for="name">Name</label>
            <input id="name" type="text" name="name" required maxlength="120" value="<?= e($form['name']) ?>">
          </div>
          <div class="field">
            <label for="slug">Slug</label>
            <input id="slug" type="text" name="slug" maxlength="140" value="<?= e($form['slug']) ?>" placeholder="generated from the name">
          </div>
        </div>
        <div class="field">
          <label for="description">Description</label>
          <input id="description" type="text" name="description" maxlength="600" value="<?= e($form['description']) ?>">
          <span class="hint">Shown at the top of the category page and used as its meta description.</span>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="icon">Icon</label>
            <select id="icon" name="icon">
              <?php foreach ($iconChoices as $choice): ?>
                <option value="<?= e($choice) ?>" <?= $form['icon'] === $choice ? 'selected' : '' ?>><?= e($choice) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="accent">Accent colour</label>
            <select id="accent" name="accent">
              <?php foreach ($accentChoices as $choice): ?>
                <option value="<?= e($choice) ?>" <?= $form['accent'] === $choice ? 'selected' : '' ?>><?= e(ucfirst($choice)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="sort_order">Sort order</label>
            <input id="sort_order" type="number" name="sort_order" value="<?= e($form['sort_order']) ?>">
            <span class="hint">Lower numbers appear first in the navigation.</span>
          </div>
          <div class="field" style="justify-content:flex-end">
            <div class="check"><input id="is_active" type="checkbox" name="is_active" value="1" <?= $form['is_active'] === '1' ? 'checked' : '' ?>>
              <label for="is_active">Visible in the shop</label></div>
          </div>
        </div>
        <div class="cluster">
          <button type="submit" class="btn btn-primary"><?= icon('check', '', 17) ?> Save category</button>
          <a class="btn btn-ghost" href="<?= e(url('admin/categories.php')) ?>">Cancel</a>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<div class="admin-panel">
  <div class="admin-panel-head"><h2>All categories</h2></div>
  <div class="admin-panel-body flush">
    <div class="admin-table-wrap">
      <table class="admin-table">
        <caption class="sr-only">Categories with product counts and controls</caption>
        <thead>
          <tr>
            <th scope="col">#</th><th scope="col">Category</th><th scope="col">Slug</th>
            <th scope="col" class="num">Products</th><th scope="col">Visible</th><th scope="col" class="actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($categories as $cat): ?>
            <tr>
              <td class="num"><?= (int) $cat['sort_order'] ?></td>
              <td>
                <div class="cluster" style="flex-wrap:nowrap">
                  <span class="cat-icon" style="width:32px;height:32px;border-radius:9px"><?= icon($cat['icon'], '', 16) ?></span>
                  <span>
                    <strong><?= e($cat['name']) ?></strong><br>
                    <span class="tiny muted"><?= e(truncate((string) $cat['description'], 70)) ?></span>
                  </span>
                </div>
              </td>
              <td><code class="tiny"><?= e($cat['slug']) ?></code></td>
              <td class="num"><?= (int) $cat['active_products'] ?> live<br><span class="tiny muted"><?= (int) $cat['total_products'] ?> total</span></td>
              <td><?= (int) $cat['is_active'] === 1 ? '<span class="pill pill-ok">Visible</span>' : '<span class="pill pill-muted">Hidden</span>' ?></td>
              <td class="actions">
                <span class="admin-actions">
                  <a class="btn btn-ghost btn-xs" href="<?= e(url('category.php?slug=' . urlencode((string) $cat['slug']))) ?>" target="_blank" rel="noopener">View</a>
                  <a class="btn btn-ghost btn-xs" href="<?= e(url('admin/categories.php?edit=' . (int) $cat['id'])) ?>">Edit</a>
                  <form method="post" action="<?= e(url('admin/categories.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="category_id" value="<?= (int) $cat['id'] ?>">
                    <button type="submit" class="btn btn-quiet btn-xs"><?= (int) $cat['is_active'] === 1 ? 'Hide' : 'Show' ?></button>
                  </form>
                  <form method="post" action="<?= e(url('admin/categories.php')) ?>"
                        data-confirm="Delete &quot;<?= e($cat['name']) ?>&quot;? <?= (int) $cat['total_products'] ?> product(s) would become uncategorised.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="category_id" value="<?= (int) $cat['id'] ?>">
                    <button type="submit" class="btn btn-quiet btn-xs" style="color:var(--danger)">Delete</button>
                  </form>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php render_admin_end(); ?>
