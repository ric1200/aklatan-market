<?php
/** Full catalogue with filters, sorting and pagination. */
require_once __DIR__ . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/catalog-view.php';

$filters = catalog_filters_from_request();
$pageTitle = 'All products';
if (!empty($filters['featured'])) {
    $pageTitle = ['bestseller' => 'Bestsellers', 'new' => 'New arrivals', 'staff' => 'Staff picks', 'featured' => 'Featured titles'][$filters['featured']] ?? 'All products';
}

render_header([
    'title' => $pageTitle,
    'description' => 'Browse every title at Aklatan Market — fiction, academics, Filipino literature, manga, magazines, music and movies.',
    'active' => 'products',
    'search_value' => $filters['q'],
]);
render_breadcrumb([['label' => 'Home', 'url' => 'index.php'], ['label' => $pageTitle]]);
?>
<div class="container">
  <header class="page-head">
    <p class="eyebrow">The whole shop</p>
    <h1><?= e($pageTitle) ?></h1>
    <p class="lede">Filter by shelf, price or availability. Every listing shows live stock straight from our inventory.</p>
  </header>
  <?php render_catalog_page([
      'filters' => $filters,
      'base' => 'products.php',
      'heading' => $pageTitle,
  ]); ?>
</div>
<?php render_footer(); ?>
