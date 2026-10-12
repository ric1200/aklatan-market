<?php
/**
 * Shared catalogue view used by products.php, search.php and category.php.
 * One implementation means filters, sorting and pagination behave identically
 * everywhere and can never drift apart.
 */

/**
 * @param array{
 *   filters: array, base: string, heading: string, intro?: string,
 *   locked_category?: ?array, show_keyword?: bool, empty_title?: string, empty_message?: string
 * } $opts
 */
function render_catalog_page(array $opts): void
{
    $filters = $opts['filters'];
    $base = $opts['base'];
    $lockedCategory = $opts['locked_category'] ?? null;
    $result = catalog_search($filters);
    $categories = catalog_categories();
    $bounds = catalog_price_bounds();

    $query = [];
    foreach (['q', 'category', 'min_price', 'max_price', 'availability', 'sort', 'per_page', 'featured'] as $key) {
        if (!empty($filters[$key])) { $query[$key] = $filters[$key]; }
    }

    $activeFilters = [];
    if (!empty($filters['q'])) {
        $activeFilters[] = ['label' => 'Search: “' . $filters['q'] . '”', 'url' => catalog_filter_url($base, $query, 'q')];
    }
    if (!$lockedCategory && !empty($filters['category'])) {
        $activeFilters[] = ['label' => 'Category: ' . ($filters['category_name'] ?? $filters['category']), 'url' => catalog_filter_url($base, $query, 'category')];
    }
    if ($filters['min_price'] !== '' || $filters['max_price'] !== '') {
        $activeFilters[] = [
            'label' => 'Price: ' . money($filters['min_price'] !== '' ? $filters['min_price'] : $bounds['min'])
                . ' – ' . ($filters['max_price'] !== '' ? money($filters['max_price']) : 'any'),
            'url' => catalog_filter_url($base, $query, 'min_price', ['max_price']),
        ];
    }
    if (!empty($filters['availability'])) {
        $labels = ['in_stock' => 'In stock only', 'out_of_stock' => 'Out of stock', 'on_sale' => 'On sale'];
        $activeFilters[] = ['label' => $labels[$filters['availability']] ?? 'Availability', 'url' => catalog_filter_url($base, $query, 'availability')];
    }
    if (!empty($filters['featured'])) {
        $featLabels = ['bestseller' => 'Bestsellers', 'new' => 'New arrivals', 'staff' => 'Staff picks', 'featured' => 'Featured'];
        $activeFilters[] = ['label' => $featLabels[$filters['featured']] ?? 'Curated', 'url' => catalog_filter_url($base, $query, 'featured')];
    }
    ?>
    <div class="catalog-layout">
      <div>
        <button type="button" class="btn btn-ghost filter-toggle" aria-controls="filter-panel">
          <?= icon('filter', '', 17) ?> Filters
        </button>
        <form class="filter-panel" id="filter-panel" method="get" action="<?= e(url($base)) ?>">
          <?php if (!empty($filters['q'])): ?>
            <input type="hidden" name="q" value="<?= e($filters['q']) ?>">
          <?php endif; ?>
          <?php if (!empty($filters['featured'])): ?>
            <input type="hidden" name="featured" value="<?= e($filters['featured']) ?>">
          <?php endif; ?>

          <h2>Refine your browse</h2>

          <?php if (!$lockedCategory): ?>
            <div class="filter-group">
              <h3>Category</h3>
              <ul class="filter-list">
                <li>
                  <a class="<?= empty($filters['category']) ? 'is-current' : '' ?>"
                     href="<?= e(catalog_filter_url($base, $query, 'category')) ?>">
                    <span>All categories</span>
                  </a>
                </li>
                <?php foreach ($categories as $cat): ?>
                  <li>
                    <a class="<?= (string) $filters['category'] === $cat['slug'] ? 'is-current' : '' ?>"
                       href="<?= e(catalog_filter_url($base, $query, null, [], ['category' => $cat['slug']])) ?>">
                      <span><?= e($cat['name']) ?></span>
                      <span class="count"><?= (int) $cat['product_count'] ?></span>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <div class="filter-group">
            <h3>Price range (₱)</h3>
            <div class="price-range">
              <div class="field">
                <label class="sr-only" for="min_price">Minimum price</label>
                <input id="min_price" type="number" name="min_price" min="0" step="10" inputmode="numeric"
                       placeholder="<?= (int) $bounds['min'] ?>" value="<?= e((string) $filters['min_price']) ?>">
              </div>
              <div class="field">
                <label class="sr-only" for="max_price">Maximum price</label>
                <input id="max_price" type="number" name="max_price" min="0" step="10" inputmode="numeric"
                       placeholder="<?= (int) $bounds['max'] ?>" value="<?= e((string) $filters['max_price']) ?>">
              </div>
            </div>
            <p class="hint">Most titles cost between <?= e(money($bounds['min'])) ?> and <?= e(money($bounds['max'])) ?>.</p>
          </div>

          <div class="filter-group">
            <h3>Availability</h3>
            <div class="field">
              <label class="sr-only" for="availability">Availability</label>
              <select id="availability" name="availability">
                <option value="">All items</option>
                <option value="in_stock" <?= $filters['availability'] === 'in_stock' ? 'selected' : '' ?>>In stock only</option>
                <option value="on_sale" <?= $filters['availability'] === 'on_sale' ? 'selected' : '' ?>>On sale</option>
                <option value="out_of_stock" <?= $filters['availability'] === 'out_of_stock' ? 'selected' : '' ?>>Out of stock</option>
              </select>
            </div>
          </div>

          <div class="filter-group">
            <h3>Sort by</h3>
            <div class="field">
              <label class="sr-only" for="sort">Sort products</label>
              <select id="sort" name="sort">
                <?php foreach (PRODUCT_SORTS as $key => $label): ?>
                  <option value="<?= e($key) ?>" <?= ($filters['sort'] ?? 'relevance') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="filter-group">
            <button type="submit" class="btn btn-primary btn-block">Apply filters</button>
            <?php if ($activeFilters): ?>
              <a class="btn btn-quiet btn-block mt-1" href="<?= e(url($base) . ($lockedCategory ? '?slug=' . urlencode($lockedCategory['slug']) : (($filters['q'] ?? '') !== '' ? '?q=' . urlencode($filters['q']) : ''))) ?>">
                Clear all filters
              </a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <div>
        <div class="toolbar">
          <p class="result-count">
            <?php if ($result['total'] === 0): ?>
              No matching titles
            <?php else: ?>
              Showing <strong><?= (int) $result['paginator']['from'] ?>–<?= (int) $result['paginator']['to'] ?></strong>
              of <strong><?= (int) $result['total'] ?></strong>
              <?= $result['total'] === 1 ? 'title' : 'titles' ?>
            <?php endif; ?>
          </p>
          <form class="sort-form" method="get" action="<?= e(url($base)) ?>">
            <?php foreach ($query as $k => $v): ?>
              <?php if ($k !== 'sort'): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endif; ?>
            <?php endforeach; ?>
            <?php if ($lockedCategory): ?><input type="hidden" name="slug" value="<?= e($lockedCategory['slug']) ?>"><?php endif; ?>
            <label for="toolbar-sort">Sort</label>
            <select id="toolbar-sort" name="sort" data-autosubmit>
              <?php foreach (PRODUCT_SORTS as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= ($filters['sort'] ?? 'relevance') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <noscript><button type="submit" class="btn btn-ghost btn-sm">Sort</button></noscript>
          </form>
        </div>

        <?php if ($activeFilters): ?>
          <div class="active-filters">
            <?php foreach ($activeFilters as $f): ?>
              <a class="chip" href="<?= e($f['url']) ?>"><?= e($f['label']) ?> <?= icon('close', '', 13) ?></a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if (!$result['items']): ?>
          <?php render_empty_state(
              'search',
              $opts['empty_title'] ?? 'No titles matched that browse',
              $opts['empty_message'] ?? 'Try removing a filter, searching a shorter word, or browsing the full catalogue instead.',
              [
                  ['label' => 'Clear filters', 'url' => url($base), 'class' => 'btn-primary'],
                  ['label' => 'Browse all products', 'url' => url('products.php'), 'class' => 'btn-ghost'],
              ]); ?>
        <?php else: ?>
          <div class="product-grid">
            <?php foreach ($result['items'] as $product) { render_product_card($product); } ?>
          </div>
          <?php
          $result['base_path'] = $base;
          render_pagination($result, $query + ($lockedCategory ? ['slug' => $lockedCategory['slug']] : []));
          ?>
        <?php endif; ?>
      </div>
    </div>
    <?php
}

/**
 * Build a filter URL. Passing $dropKey removes that parameter; $set adds or
 * replaces others. Never emits user-controlled HTML.
 */
function catalog_filter_url(string $base, array $query, ?string $dropKey = null, array $dropKeys = [], array $set = []): string
{
    foreach (array_merge($dropKey !== null ? [$dropKey] : [], $dropKeys) as $key) {
        unset($query[$key]);
    }
    foreach ($set as $k => $v) {
        if ($v === '' || $v === null) { unset($query[$k]); } else { $query[$k] = $v; }
    }
    unset($query['page']);
    $qs = http_build_query($query);
    return url($base) . ($qs !== '' ? '?' . $qs : '');
}

/** Read the filters from the query string in one place, with validation. */
function catalog_filters_from_request(array $overrides = []): array
{
    $sort = (string) get_param('sort', 'relevance');
    if (!array_key_exists($sort, PRODUCT_SORTS)) { $sort = 'relevance'; }

    $availability = (string) get_param('availability');
    if (!in_array($availability, ['', 'in_stock', 'on_sale', 'out_of_stock'], true)) { $availability = ''; }

    $min = get_param('min_price');
    $max = get_param('max_price');
    $min = ($min !== '' && is_numeric($min) && (float) $min >= 0) ? (float) $min : '';
    $max = ($max !== '' && is_numeric($max) && (float) $max >= 0) ? (float) $max : '';

    $featured = (string) get_param('featured');
    $featuredMap = ['bestseller' => 'is_bestseller', 'new' => 'is_new_arrival', 'staff' => 'is_staff_pick', 'featured' => 'is_featured'];

    $filters = [
        'q' => truncate(get_param('q'), 120),
        'category' => preg_replace('/[^a-z0-9\-]/', '', strtolower((string) get_param('category'))),
        'min_price' => $min,
        'max_price' => $max,
        'availability' => $availability,
        'sort' => $sort,
        'page' => max(1, get_int('page', 1)),
        'per_page' => setting_int('products_per_page', 12),
        'featured' => $featured,
    ];

    if ($filters['category'] !== '') {
        $cat = category_by_slug($filters['category']);
        if ($cat) {
            $filters['category_id'] = (int) $cat['id'];
            $filters['category_name'] = $cat['name'];
        } else {
            $filters['category'] = '';
        }
    }
    if (isset($featuredMap[$featured])) {
        $filters[$featuredMap[$featured]] = 1;
    }

    return array_merge($filters, $overrides);
}
