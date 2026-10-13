<?php
/** Reusable pieces of storefront markup. */

/** Product ids in the signed-in customer's wishlist. */
function wishlist_ids(): array
{
    static $ids = null;
    if ($ids !== null) { return $ids; }
    $ids = [];
    if (is_logged_in()) {
        foreach (fetch_all('SELECT product_id FROM wishlists WHERE user_id = :uid', ['uid' => current_user_id()]) as $row) {
            $ids[(int) $row['product_id']] = true;
        }
    }
    return $ids;
}

function is_wishlisted(int $productId): bool
{
    return isset(wishlist_ids()[$productId]);
}

function product_url(array $product): string
{
    return url('product.php?slug=' . urlencode((string) $product['slug']));
}

function category_url(array $category): string
{
    return url('category.php?slug=' . urlencode((string) $category['slug']));
}

/** Wishlist heart. Signed-out visitors are sent to the sign-in page. */
function wishlist_button(array $product, string $location = 'card'): string
{
    $id = (int) $product['id'];
    $on = is_wishlisted($id);
    $label = $on ? 'Remove ' . $product['name'] . ' from your wishlist' : 'Add ' . $product['name'] . ' to your wishlist';
    $heart = icon('heart', '', 18);

    if (!is_logged_in()) {
        return '<a class="wish-toggle" href="' . e(url('login.php?next=' . urlencode(product_url($product)))) . '"'
            . ' title="Sign in to save items" aria-label="' . e($label) . '">' . $heart . '</a>';
    }
    return '<form action="' . e(url('wishlist.php')) . '" method="post" data-wishlist-toggle>'
        . csrf_field()
        . '<input type="hidden" name="action" value="' . ($on ? 'remove' : 'add') . '">'
        . '<input type="hidden" name="product_id" value="' . $id . '">'
        . '<input type="hidden" name="return" value="' . e(current_relative_url()) . '">'
        . '<button type="submit" class="wish-toggle' . ($on ? ' is-on' : '') . '" aria-pressed="' . ($on ? 'true' : 'false') . '"'
        . ' title="' . e($label) . '" aria-label="' . e($label) . '">' . $heart . '</button>'
        . '</form>';
}

/** The card used on the home shelves, catalogue, search and category pages. */
function render_product_card(array $product, array $options = []): void
{
    $name = (string) $product['name'];
    $url = product_url($product);
    $discount = discount_percent($product);
    [$stockKey, $stockLabel] = stock_state($product);
    $rating = isset($product['average_rating']) ? (float) $product['average_rating'] : null;
    $reviews = (int) ($product['review_count'] ?? 0);
    $inStock = (int) $product['stock_quantity'] > 0;
    $extraClass = $options['class'] ?? '';
    ?>
    <article class="product-card <?= e($extraClass) ?>">
      <div class="card-media">
        <a href="<?= e($url) ?>" tabindex="-1" aria-hidden="true">
          <img src="<?= e(product_image_url($product)) ?>" alt="<?= e($name) ?> — cover image"
               width="400" height="600" loading="lazy" decoding="async">
        </a>
        <?php if ($discount > 0): ?>
          <span class="badge-discount"><?= (int) $discount ?>% off</span>
        <?php endif; ?>
        <div class="card-flags">
          <?php if (!empty($product['is_bestseller'])): ?><span class="flag flag-best">Bestseller</span><?php endif; ?>
          <?php if (!empty($product['is_new_arrival'])): ?><span class="flag flag-new">New</span><?php endif; ?>
          <?php if (!empty($product['is_staff_pick'])): ?><span class="flag flag-staff">Staff pick</span><?php endif; ?>
        </div>
        <div class="card-wish"><?= wishlist_button($product) ?></div>
      </div>

      <div class="card-body">
        <p class="card-cat"><?= e((string) ($product['category_name'] ?? 'Uncategorised')) ?></p>
        <h3 class="card-title"><a href="<?= e($url) ?>"><?= e($name) ?></a></h3>
        <?php if (!empty($product['creator'])): ?>
          <p class="card-creator">by <?= e((string) $product['creator']) ?></p>
        <?php endif; ?>

        <?php if ($rating !== null && $reviews > 0): ?>
          <p class="rating"><?= stars($rating) ?><span>(<?= (int) $reviews ?>)</span></p>
        <?php endif; ?>

        <div class="card-price">
          <span class="price"><?= e(money($product['price'])) ?></span>
          <?php if ((float) ($product['compare_at_price'] ?? 0) > (float) $product['price']): ?>
            <span class="price-was"><?= e(money($product['compare_at_price'])) ?></span>
          <?php endif; ?>
        </div>
        <p class="card-stock stock-<?= e($stockKey) ?>"><?= e($stockLabel) ?></p>
      </div>

      <div class="card-actions">
        <?php if ($inStock): ?>
          <form action="<?= e(url('cart.php')) ?>" method="post" data-ajax-cart>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
            <input type="hidden" name="quantity" value="1">
            <input type="hidden" name="return" value="<?= e(current_relative_url()) ?>">
            <button type="submit" class="btn btn-primary btn-sm btn-block">
              <?= icon('cart', '', 16) ?> Add to cart<span class="sr-only"> — <?= e($name) ?></span>
            </button>
          </form>
        <?php else: ?>
          <button type="button" class="btn btn-ghost btn-sm btn-block" disabled>Out of stock</button>
        <?php endif; ?>
        <a class="btn btn-ghost btn-sm" href="<?= e($url) ?>">Details<span class="sr-only"> for <?= e($name) ?></span></a>
      </div>
    </article>
    <?php
}

/** Breadcrumbs: [['label' => 'Home', 'url' => 'index.php'], ['label' => 'Now']]. */
function render_breadcrumb(array $items): void
{
    if (!$items) { return; }
    ?>
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <div class="container">
        <ol>
          <?php foreach ($items as $i => $item): ?>
            <li>
              <?php if (!empty($item['url']) && $i < count($items) - 1): ?>
                <a href="<?= e(url($item['url'])) ?>"><?= e($item['label']) ?></a>
              <?php else: ?>
                <span aria-current="page"><?= e($item['label']) ?></span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
    </nav>
    <?php
}

function render_empty_state(string $iconName, string $title, string $message, array $actions = []): void
{
    ?>
    <div class="empty-state">
      <div class="empty-icon"><?= icon($iconName, '', 28) ?></div>
      <h3><?= e($title) ?></h3>
      <p><?= e($message) ?></p>
      <?php if ($actions): ?>
        <p class="cluster" style="justify-content:center">
          <?php foreach ($actions as $a): ?>
            <a class="btn <?= e($a['class'] ?? 'btn-primary') ?>" href="<?= e($a['url']) ?>"><?= e($a['label']) ?></a>
          <?php endforeach; ?>
        </p>
      <?php endif; ?>
    </div>
    <?php
}

/** Paginate links while carrying the current filters across. */
function render_pagination(array $result, array $query): void
{
    if (($result['pages'] ?? 1) <= 1) { return; }
    $query = array_filter($query, function ($v) { return $v !== '' && $v !== null; });
    unset($query['page']);
    echo pagination_links($result['paginator'], $result['base_path'] ?? 'products.php', $query);
}

/** Human-readable "posted on" line for reviews. */
function review_meta(array $review): string
{
    return format_date($review['created_at'], 'M j, Y');
}
