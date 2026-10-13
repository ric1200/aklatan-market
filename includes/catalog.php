<?php
/** Catalog queries shared by the storefront and the admin area. */

const PRODUCT_SORTS = [
    'relevance'  => 'Most relevant',
    'newest'     => 'Newest arrivals',
    'price_asc'  => 'Price: low to high',
    'price_desc' => 'Price: high to low',
    'title_asc'  => 'Title: A to Z',
    'title_desc' => 'Title: Z to A',
    'popular'    => 'Most ordered',
    'rating'     => 'Highest rated',
];

function catalog_categories(bool $activeOnly = true): array
{
    $sql = 'SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.is_active = 1) AS product_count
            FROM categories c' . ($activeOnly ? ' WHERE c.is_active = 1' : '') . '
            ORDER BY c.sort_order ASC, c.name ASC';
    return fetch_all($sql);
}

function category_by_slug(string $slug, bool $activeOnly = true): ?array
{
    $sql = 'SELECT * FROM categories WHERE slug = :slug' . ($activeOnly ? ' AND is_active = 1' : '');
    return fetch_one($sql, ['slug' => $slug]);
}

function product_by_slug(string $slug, bool $activeOnly = true): ?array
{
    $sql = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug
            FROM products p LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.slug = :slug' . ($activeOnly ? ' AND p.is_active = 1' : '');
    return fetch_one($sql, ['slug' => $slug]);
}

function product_by_id(int $id, bool $activeOnly = true): ?array
{
    $sql = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug
            FROM products p LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.id = :id' . ($activeOnly ? ' AND p.is_active = 1' : '');
    return fetch_one($sql, ['id' => $id]);
}

/** Public URL of a product image, falling back to the bundled placeholder. */
function product_image_url(?array $product): string
{
    $path = is_array($product) ? trim((string) ($product['image'] ?? '')) : '';
    if ($path !== '' && strpos($path, '..') === false && is_file(APP_ROOT . '/' . ltrim($path, '/'))) {
        return url(ltrim($path, '/'));
    }
    return url('assets/images/placeholder-cover.svg');
}

function product_image_exists(?array $product): bool
{
    $path = is_array($product) ? trim((string) ($product['image'] ?? '')) : '';
    return $path !== '' && strpos($path, '..') === false && is_file(APP_ROOT . '/' . ltrim($path, '/'));
}

function discount_percent(?array $product): int
{
    if (!$product) { return 0; }
    $price = (float) $product['price'];
    $compare = (float) ($product['compare_at_price'] ?? 0);
    if ($compare > $price && $compare > 0) {
        return (int) round((($compare - $price) / $compare) * 100);
    }
    return 0;
}

function stock_state(array $product): array
{
    $stock = (int) ($product['stock_quantity'] ?? 0);
    if ($stock <= 0) { return ['out', 'Out of stock', 'bad']; }
    $low = setting_int('low_stock_threshold', 5);
    if ($stock <= $low) { return ['low', 'Only ' . $stock . ' left', 'warn']; }
    return ['in', 'In stock', 'ok'];
}

function product_rating_summary(int $productId): array
{
    $row = fetch_one(
        'SELECT COUNT(*) AS review_count, COALESCE(AVG(rating), 0) AS average_rating
         FROM reviews WHERE product_id = :pid AND status = :st',
        ['pid' => $productId, 'st' => 'approved']);
    return [
        'count' => (int) ($row['review_count'] ?? 0),
        'average' => round((float) ($row['average_rating'] ?? 0), 1),
    ];
}

/**
 * Search / browse the catalog with safe filters.
 *
 * @return array{items:array,total:int,page:int,perPage:int,pages:int}
 */
function catalog_search(array $f): array
{
    $where = [];
    $params = [];

    if (!empty($f['include_inactive'])) {
        $where[] = '1=1';
    } else {
        $where[] = 'p.is_active = 1';
    }

    $keyword = trim((string) ($f['q'] ?? ''));
    if ($keyword !== '') {
        $where[] = like_or_clause(
            ['p.name', 'p.creator', 'p.isbn', 'p.sku', 'p.description', 'p.publisher', 'c.name'],
            $keyword, $params, 'kw');
    }
    if (!empty($f['category_id'])) {
        $where[] = 'p.category_id = :cat_id';
        $params['cat_id'] = (int) $f['category_id'];
    }
    if (isset($f['min_price']) && $f['min_price'] !== '' && $f['min_price'] !== null) {
        $where[] = 'p.price >= :min_price';
        $params['min_price'] = (float) $f['min_price'];
    }
    if (isset($f['max_price']) && $f['max_price'] !== '' && $f['max_price'] !== null) {
        $where[] = 'p.price <= :max_price';
        $params['max_price'] = (float) $f['max_price'];
    }
    if (!empty($f['availability'])) {
        if ($f['availability'] === 'in_stock') { $where[] = 'p.stock_quantity > 0'; }
        if ($f['availability'] === 'out_of_stock') { $where[] = 'p.stock_quantity <= 0'; }
        if ($f['availability'] === 'on_sale') { $where[] = 'p.compare_at_price IS NOT NULL AND p.compare_at_price > p.price'; }
    }
    if (!empty($f['creator'])) {
        $where[] = 'p.creator LIKE :creator';
        $params['creator'] = '%' . trim((string) $f['creator']) . '%';
    }
    foreach ([
        'is_featured' => 'is_featured', 'is_bestseller' => 'is_bestseller',
        'is_new_arrival' => 'is_new_arrival', 'is_staff_pick' => 'is_staff_pick',
    ] as $flag => $column) {
        if (!empty($f[$flag])) { $where[] = 'p.' . $column . ' = 1'; }
    }

    $whereSql = implode(' AND ', $where);

    $sortKey = (string) ($f['sort'] ?? 'relevance');
    $sortSql = 'p.is_featured DESC, p.created_at DESC';
    switch ($sortKey) {
        case 'newest':     $sortSql = 'p.created_at DESC, p.id DESC'; break;
        case 'price_asc':  $sortSql = 'p.price ASC, p.name ASC'; break;
        case 'price_desc': $sortSql = 'p.price DESC, p.name ASC'; break;
        case 'title_asc':  $sortSql = 'p.name ASC'; break;
        case 'title_desc': $sortSql = 'p.name DESC'; break;
        case 'rating':     $sortSql = 'avg_rating DESC, review_count DESC, p.name ASC'; break;
        case 'popular':    $sortSql = 'sold_count DESC, p.name ASC'; break;
        case 'relevance':
        default:
            if ($keyword === '') { $sortSql = 'p.is_featured DESC, p.created_at DESC'; }
            else { $sortSql = 'p.is_featured DESC, p.name ASC'; }
            break;
    }

    // Only approved, active products are ever counted for popularity.
    $baseFrom = 'FROM products p
                 LEFT JOIN categories c ON c.id = p.category_id
                 LEFT JOIN (SELECT product_id, COUNT(*) AS review_count, AVG(rating) AS avg_rating
                            FROM reviews WHERE status = \'approved\' GROUP BY product_id) r ON r.product_id = p.id
                 LEFT JOIN (SELECT oi.product_id, SUM(oi.quantity) AS sold_count
                            FROM order_items oi
                            JOIN orders o ON o.id = oi.order_id
                            WHERE o.status IN (\'confirmed\',\'processing\',\'shipped\',\'delivered\')
                            GROUP BY oi.product_id) s ON s.product_id = p.id';

    $total = (int) fetch_value('SELECT COUNT(*) ' . $baseFrom . ' WHERE ' . $whereSql, $params, 0);

    $perPage = max(4, min(48, (int) ($f['per_page'] ?? setting_int('products_per_page', 12))));
    $p = paginate($total, $perPage, (int) ($f['page'] ?? 1));

    $sql = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug,
                   COALESCE(r.review_count, 0) AS review_count,
                   COALESCE(r.avg_rating, 0) AS average_rating,
                   COALESCE(s.sold_count, 0) AS sold_count
            ' . $baseFrom . '
            WHERE ' . $whereSql . '
            ORDER BY ' . $sortSql . '
            LIMIT ' . (int) $p['perPage'] . ' OFFSET ' . (int) $p['offset'];

    $items = fetch_all($sql, $params);

    return [
        'items' => $items,
        'total' => $total,
        'page' => $p['page'],
        'perPage' => $p['perPage'],
        'pages' => $p['pages'],
        'paginator' => $p,
    ];
}

/** Home page shelf: a named, database-driven product list. */
function catalog_shelf(string $flag, int $limit = 8): array
{
    $allowed = ['is_bestseller', 'is_new_arrival', 'is_staff_pick', 'is_featured'];
    if (!in_array($flag, $allowed, true)) { return []; }
    $limit = max(1, min(24, $limit));
    return fetch_all(
        'SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM products p LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.is_active = 1 AND p.' . $flag . ' = 1
         ORDER BY p.updated_at DESC, p.name ASC
         LIMIT ' . $limit);
}

function catalog_products_for_students(int $limit = 8): array
{
    $limit = max(1, min(24, $limit));
    return fetch_all(
        "SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM products p JOIN categories c ON c.id = p.category_id
         WHERE p.is_active = 1 AND c.slug IN ('academic-reference','filipino-books','childrens-books')
         ORDER BY p.is_bestseller DESC, p.name ASC
         LIMIT " . $limit);
}

function catalog_media(int $limit = 8): array
{
    $limit = max(1, min(24, $limit));
    return fetch_all(
        "SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM products p JOIN categories c ON c.id = p.category_id
         WHERE p.is_active = 1 AND c.slug IN ('music-cds-vinyl','movies-dvds','magazines')
         ORDER BY p.created_at DESC
         LIMIT " . $limit);
}

function catalog_price_bounds(): array
{
    $row = fetch_one('SELECT COALESCE(MIN(price), 0) AS min_price, COALESCE(MAX(price), 1000) AS max_price
                      FROM products WHERE is_active = 1');
    return [
        'min' => (int) floor((float) ($row['min_price'] ?? 0)),
        'max' => (int) ceil((float) ($row['max_price'] ?? 1000)),
    ];
}

function related_products(array $product, int $limit = 4): array
{
    $limit = max(1, min(12, $limit));
    $categoryId = (int) ($product['category_id'] ?? 0);
    if ($categoryId > 0) {
        return fetch_all(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug
             FROM products p LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.is_active = 1 AND p.id <> :id AND p.category_id = :cat
             ORDER BY p.is_bestseller DESC, RAND()
             LIMIT ' . $limit,
            ['id' => (int) $product['id'], 'cat' => $categoryId]);
    }
    return fetch_all(
        'SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM products p LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.is_active = 1 AND p.id <> :id
         ORDER BY p.is_bestseller DESC, RAND()
         LIMIT ' . $limit,
        ['id' => (int) $product['id']]);
}
