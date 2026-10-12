<?php
/** Single category listing: /category.php?slug=fiction-novels */
require_once __DIR__ . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/catalog-view.php';

$slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) get_param('slug')));
$category = $slug !== '' ? category_by_slug($slug) : null;
if (!$category) {
    aklatan_abort(404, 'That category does not exist',
        'The shelf you were looking for has been renamed or removed. Browse the full catalogue to find your titles.');
}

$filters = catalog_filters_from_request([
    'category' => $category['slug'],
    'category_id' => (int) $category['id'],
    'category_name' => $category['name'],
]);

render_header([
    'title' => $category['name'],
    'description' => truncate((string) ($category['description'] ?: $category['name'] . ' at Aklatan Market'), 155),
    'active' => 'category',
]);
render_breadcrumb([
    ['label' => 'Home', 'url' => 'index.php'],
    ['label' => 'All products', 'url' => 'products.php'],
    ['label' => $category['name']],
]);
?>
<div class="container">
  <header class="page-head">
    <p class="eyebrow"><?= icon($category['icon'] ?: 'book', '', 15) ?> Shelf</p>
    <h1><?= e($category['name']) ?></h1>
    <?php if (!empty($category['description'])): ?>
      <p class="lede"><?= e($category['description']) ?></p>
    <?php endif; ?>
  </header>
  <?php render_catalog_page([
      'filters' => $filters,
      'base' => 'category.php',
      'heading' => $category['name'],
      'locked_category' => $category,
      'empty_title' => 'Nothing on this shelf right now',
      'empty_message' => 'This category has no active titles at the moment. Have a look at the rest of the shop instead.',
  ]); ?>
</div>
<?php render_footer(); ?>
