<?php
/** Keyword search with the same filters as the catalogue. */
require_once __DIR__ . '/includes/bootstrap.php';
require_once APP_ROOT . '/includes/catalog-view.php';

$keyword = truncate(get_param('q'), 120);
$filters = catalog_filters_from_request();

if ($keyword === '' && empty($filters['category']) && empty($filters['featured'])
    && $filters['min_price'] === '' && $filters['max_price'] === '' && $filters['availability'] === '') {
    redirect('products.php');
}

$searchHeading = $keyword !== '' ? 'Results for “' . $keyword . '”' : 'Filtered results';

render_header([
    'title' => $keyword !== '' ? 'Search: ' . $keyword : 'Search results',
    'description' => 'Search books, manga, music, movies and stationery at Aklatan Market.',
    'robots' => 'noindex, follow',
    'search_value' => $keyword,
]);
render_breadcrumb([['label' => 'Home', 'url' => 'index.php'], ['label' => 'Search']]);
?>
<div class="container">
  <header class="page-head">
    <p class="eyebrow">Search the catalogue</p>
    <h1><?= e($searchHeading) ?></h1>
    <p class="lede">
      <?php if ($keyword !== ''): ?>
        We searched titles, authors, publishers, ISBNs and descriptions. Refine the results below if you
        need something narrower.
      <?php else: ?>
        Filtered browse. Add a keyword in the search box above for a narrower result set.
      <?php endif; ?>
    </p>
  </header>
  <?php render_catalog_page([
      'filters' => $filters,
      'base' => 'search.php',
      'heading' => $searchHeading,
      'empty_title' => $keyword !== '' ? 'No titles matched “' . $keyword . '”' : 'No titles matched those filters',
      'empty_message' => 'Check the spelling, try fewer words, or browse by category instead. You can also message us — we may be able to order a title for you.',
  ]); ?>
</div>
<?php render_footer(); ?>
