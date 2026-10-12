<?php
/** Product detail page: full description, specification, reviews and buying controls. */
require_once __DIR__ . '/includes/bootstrap.php';

$slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) get_param('slug')));
$product = $slug !== '' ? product_by_slug($slug, true) : null;
if (!$product) {
    aklatan_abort(404, 'We could not find that title',
        'The book or item may have been sold out of the catalogue. Try searching for it, or browse the shelves.');
}
$productId = (int) $product['id'];

/* ------------------------------------------------------------- reviews ---- */
$reviewErrors = [];
if (is_post() && post('action') === 'submit_review') {
    csrf_guard();
    $user = require_login('Please sign in to write a review.');

    $rating = post_int('rating');
    $title = truncate(post('title'), 140);
    $body = truncate(post_raw('body'), 2000);

    // A review is only accepted from a customer whose order for this title was delivered.
    $purchase = fetch_one(
        "SELECT o.id FROM orders o
         JOIN order_items oi ON oi.order_id = o.id
         WHERE o.user_id = :uid AND oi.product_id = :pid AND o.status = 'delivered'
         ORDER BY o.created_at DESC LIMIT 1",
        ['uid' => $user['id'], 'pid' => $productId]);

    if (!$purchase) {
        $reviewErrors[] = 'Reviews are open to customers who have received this item. Once your order is marked delivered you can review it from your order history.';
    }
    $existing = fetch_value('SELECT id FROM reviews WHERE user_id = :uid AND product_id = :pid',
        ['uid' => $user['id'], 'pid' => $productId]);
    if ($existing) {
        $reviewErrors[] = 'You have already reviewed this title. Thank you!';
    }
    if ($rating < 1 || $rating > 5) { $reviewErrors[] = 'Please choose a star rating from 1 to 5.'; }
    if ($body === '' || mb_strlen($body) < 10) { $reviewErrors[] = 'Please write at least a short sentence about the item.'; }

    if (!$reviewErrors) {
        $newId = db_insert('reviews', [
            'product_id' => $productId,
            'user_id' => (int) $user['id'],
            'order_id' => (int) $purchase['id'],
            'rating' => $rating,
            'title' => $title !== '' ? $title : null,
            'body' => $body,
            'status' => 'pending',
        ]);
        audit_log('review.submitted', 'review', $newId, 'Review for product #' . $productId);
        flash_success('Thank you! Your review was submitted and will appear once a bookseller approves it.');
        redirect('product.php?slug=' . urlencode($product['slug']));
    }
}

$ratingSummary = product_rating_summary($productId);
$reviews = fetch_all(
    'SELECT r.*, u.first_name, u.last_name
     FROM reviews r JOIN users u ON u.id = r.user_id
     WHERE r.product_id = :pid AND r.status = :st
     ORDER BY r.created_at DESC LIMIT 30',
    ['pid' => $productId, 'st' => 'approved']);

$hasReviewed = false;
$canReview = false;
if (is_logged_in()) {
    $hasReviewed = (bool) fetch_value('SELECT id FROM reviews WHERE user_id = :uid AND product_id = :pid',
        ['uid' => current_user_id(), 'pid' => $productId]);
    $canReview = !$hasReviewed && (bool) fetch_one(
        "SELECT o.id FROM orders o JOIN order_items oi ON oi.order_id = o.id
         WHERE o.user_id = :uid AND oi.product_id = :pid AND o.status = 'delivered' LIMIT 1",
        ['uid' => current_user_id(), 'pid' => $productId]);
}

$related = related_products($product, 4);
$discount = discount_percent($product);
[$stockKey, $stockLabel] = stock_state($product);
$inStock = (int) $product['stock_quantity'] > 0;
$maxQty = max(1, min(99, (int) $product['stock_quantity']));

render_header([
    'title' => $product['name'],
    'description' => truncate((string) ($product['short_description'] ?: strip_tags((string) $product['description'])), 155),
    'search_value' => '',
]);
render_breadcrumb([
    ['label' => 'Home', 'url' => 'index.php'],
    ['label' => 'All products', 'url' => 'products.php'],
    ['label' => $product['category_name'] ?? 'Uncategorised', 'url' => 'category.php?slug=' . urlencode((string) $product['category_slug'])],
    ['label' => $product['name']],
]);
?>
<div class="container">
  <div class="pdp">
    <div class="pdp-media">
      <div class="pdp-cover">
        <img src="<?= e(product_image_url($product)) ?>" alt="<?= e($product['name']) ?> — cover image" width="400" height="600">
      </div>
    </div>

    <div class="pdp-info">
      <p class="eyebrow"><?= e((string) ($product['category_name'] ?? 'Uncategorised')) ?></p>
      <h1><?= e($product['name']) ?></h1>

      <?php if (!empty($product['creator'])): ?>
        <p class="pdp-creator">by <strong><?= e((string) $product['creator']) ?></strong></p>
      <?php endif; ?>

      <?php if ($ratingSummary['count'] > 0): ?>
        <p class="rating">
          <?= stars($ratingSummary['average']) ?>
          <span><?= e(number_format($ratingSummary['average'], 1)) ?> out of 5 · <?= (int) $ratingSummary['count'] ?>
            <?= $ratingSummary['count'] === 1 ? 'review' : 'reviews' ?></span>
        </p>
      <?php else: ?>
        <p class="rating muted">No reviews yet — be the first to write one.</p>
      <?php endif; ?>

      <div class="pdp-price">
        <span class="price"><?= e(money($product['price'])) ?></span>
        <?php if ($discount > 0): ?>
          <span class="price-was"><?= e(money($product['compare_at_price'])) ?></span>
          <span class="pill pill-amber">Save <?= (int) $discount ?>%</span>
        <?php endif; ?>
      </div>
      <p class="card-stock stock-<?= e($stockKey) ?>"><?= e($stockLabel) ?><?= $inStock ? ' · SKU ' . e((string) $product['sku']) : '' ?></p>

      <?php if (!empty($product['short_description'])): ?>
        <p class="lede mt-2"><?= e((string) $product['short_description']) ?></p>
      <?php endif; ?>

      <?php if ($inStock): ?>
        <form class="pdp-buy" action="<?= e(url('cart.php')) ?>" method="post" data-ajax-cart>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="product_id" value="<?= $productId ?>">
          <input type="hidden" name="return" value="<?= e(current_relative_url()) ?>">
          <div class="qty" data-qty>
            <button type="button" data-step="-1" aria-label="Decrease quantity"><?= icon('minus', '', 16) ?></button>
            <label class="sr-only" for="pdp-qty">Quantity</label>
            <input id="pdp-qty" type="number" name="quantity" value="1" min="1" max="<?= $maxQty ?>" inputmode="numeric">
            <button type="button" data-step="1" aria-label="Increase quantity"><?= icon('plus', '', 16) ?></button>
          </div>
          <button type="submit" class="btn btn-primary btn-lg"><?= icon('cart', '', 18) ?> Add to cart</button>
          <a class="btn btn-ghost btn-lg" href="<?= e(url('cart.php')) ?>">View cart</a>
        </form>
      <?php else: ?>
        <div class="pdp-buy">
          <button type="button" class="btn btn-ghost btn-lg" disabled>Out of stock</button>
          <a class="btn btn-secondary btn-lg" href="<?= e(url('contact.php')) ?>">Ask us to restock it</a>
        </div>
      <?php endif; ?>

      <div class="cluster mt-2">
        <?= wishlist_button($product) ?>
        <span class="small muted">Save for later</span>
      </div>

      <dl class="pdp-meta">
        <?php if (!empty($product['isbn'])): ?><div><dt>ISBN</dt><dd><?= e((string) $product['isbn']) ?></dd></div><?php endif; ?>
        <?php if (!empty($product['publisher'])): ?><div><dt>Publisher</dt><dd><?= e((string) $product['publisher']) ?></dd></div><?php endif; ?>
        <?php if (!empty($product['product_format'])): ?><div><dt>Format</dt><dd><?= e((string) $product['product_format']) ?></dd></div><?php endif; ?>
        <?php if (!empty($product['language'])): ?><div><dt>Language</dt><dd><?= e((string) $product['language']) ?></dd></div><?php endif; ?>
        <?php if (!empty($product['pages'])): ?><div><dt>Pages</dt><dd><?= (int) $product['pages'] ?></dd></div><?php endif; ?>
        <?php if (!empty($product['publication_date'])): ?><div><dt>Published</dt><dd><?= e(format_date($product['publication_date'], 'F Y')) ?></dd></div><?php endif; ?>
        <div><dt>Category</dt><dd><a href="<?= e(url('category.php?slug=' . urlencode((string) $product['category_slug']))) ?>"><?= e((string) ($product['category_name'] ?? 'Uncategorised')) ?></a></dd></div>
      </dl>

      <div class="pdp-notes">
        <p class="pdp-note"><?= icon('truck', '', 17) ?><span>Free shipping once your basket reaches
          <?= e(money(setting_float('free_shipping_threshold', 1500))) ?>, otherwise a flat
          <?= e(money(setting_float('shipping_fee', 99))) ?> nationwide.</span></p>
        <p class="pdp-note"><?= icon('shield', '', 17) ?><span>Pay by cash on delivery or a manual GCash transfer.
          We never ask for card numbers, PINs or one-time passwords.</span></p>
        <p class="pdp-note"><?= icon('refresh', '', 17) ?><span>Damaged in transit? Tell us within 7 days and we will
          replace it. See <a href="<?= e(url('shipping-returns.php')) ?>">shipping &amp; returns</a>.</span></p>
      </div>
    </div>
  </div>

  <section class="pdp-tabs" aria-labelledby="about-title">
    <div class="tab-list">
      <span class="is-current">About this item</span>
      <a href="#reviews">Reviews (<?= (int) $ratingSummary['count'] ?>)</a>
    </div>
    <div class="prose mt-2">
      <h2 id="about-title" class="sr-only">About this item</h2>
      <?php foreach (preg_split('/\n+/', (string) $product['description']) as $para): ?>
        <?php if (trim($para) !== ''): ?><p><?= e(trim($para)) ?></p><?php endif; ?>
      <?php endforeach; ?>
      <?php if (empty(trim((string) $product['description']))): ?>
        <p class="muted">A full description for this title is being written. Message us if you need details before ordering.</p>
      <?php endif; ?>

      <p class="notice notice-info mt-2">
        <?= icon('info', 'notice-icon', 18) ?>
        <span class="notice-body">Sample catalogue text. Stock levels, prices and availability shown here come from the
          store database — please confirm your own inventory and prices before selling.</span>
      </p>
    </div>
  </section>

  <section class="section" id="reviews" aria-labelledby="reviews-title">
    <div class="section-title">
      <div>
        <p class="eyebrow">Reader notes</p>
        <h2 id="reviews-title">Reviews and ratings</h2>
      </div>
    </div>

    <?php if ($ratingSummary['count'] > 0): ?>
      <div class="rating-summary">
        <div>
          <p class="rating-big"><?= e(number_format($ratingSummary['average'], 1)) ?></p>
          <?= stars($ratingSummary['average']) ?>
          <p class="small muted"><?= (int) $ratingSummary['count'] ?> verified <?= $ratingSummary['count'] === 1 ? 'review' : 'reviews' ?></p>
        </div>
        <div class="rating-bars">
          <?php for ($star = 5; $star >= 1; $star--):
              $count = (int) fetch_value('SELECT COUNT(*) FROM reviews WHERE product_id = :pid AND status = :st AND rating = :r',
                  ['pid' => $productId, 'st' => 'approved', 'r' => $star], 0);
              $pct = $ratingSummary['count'] > 0 ? round($count / $ratingSummary['count'] * 100) : 0; ?>
            <p class="rating-bar">
              <span><?= $star ?> star</span>
              <span class="bar"><span style="width: <?= (int) $pct ?>%"></span></span>
              <span><?= (int) $count ?></span>
            </p>
          <?php endfor; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($reviewErrors): ?>
      <div class="notice notice-error" role="alert">
        <?= icon('alert', 'notice-icon', 18) ?>
        <div class="notice-body">
          <strong>Your review could not be saved</strong>
          <ul><?php foreach ($reviewErrors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($reviews): ?>
      <div class="review-list">
        <?php foreach ($reviews as $review): ?>
          <article class="review">
            <div class="review-head">
              <div>
                <p class="review-author"><?= e($review['first_name'] . ' ' . substr((string) $review['last_name'], 0, 1) . '.') ?></p>
                <p class="tiny muted">Verified purchase · <?= e(review_meta($review)) ?></p>
              </div>
              <?= stars((float) $review['rating']) ?>
            </div>
            <?php if (!empty($review['title'])): ?><h3 class="card-title"><?= e((string) $review['title']) ?></h3><?php endif; ?>
            <p class="review-body"><?= nl2br(e((string) $review['body'])) ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-state">
        <div class="empty-icon"><?= icon('message', '', 26) ?></div>
        <h3>No reviews yet</h3>
        <p>Reviews come from customers whose orders were delivered. If you have received this item, you can write the first review.</p>
      </div>
    <?php endif; ?>

    <div class="form-card mt-3">
      <h3>Write a review</h3>
      <?php if (!is_logged_in()): ?>
        <p class="muted">Please <a href="<?= e(url('login.php?next=' . urlencode(current_relative_url()))) ?>">sign in</a> to review this title.</p>
      <?php elseif ($hasReviewed): ?>
        <p class="muted">You have already reviewed this title. Thank you for helping other readers.</p>
      <?php elseif (!$canReview): ?>
        <p class="muted">Reviews open once an order containing this item has been marked <strong>delivered</strong>.
          You can review it from <a href="<?= e(url('account/orders.php')) ?>">your order history</a> afterwards.</p>
      <?php else: ?>
        <form method="post" action="<?= e(url('product.php?slug=' . urlencode((string) $product['slug']))) ?>" class="form-grid">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="submit_review">
          <div class="field">
            <span class="field-label" id="rating-label">Your rating</span>
            <div class="star-input" role="radiogroup" aria-labelledby="rating-label">
              <?php for ($i = 5; $i >= 1; $i--): ?>
                <input type="radio" id="rating-<?= $i ?>" name="rating" value="<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?>>
                <label for="rating-<?= $i ?>" title="<?= $i ?> star<?= $i === 1 ? '' : 's' ?>">
                  <?= icon('star', '', 22) ?><span class="sr-only"><?= $i ?> star<?= $i === 1 ? '' : 's' ?></span>
                </label>
              <?php endfor; ?>
            </div>
          </div>
          <div class="field">
            <label for="review-title">Headline <span class="hint">(optional)</span></label>
            <input id="review-title" type="text" name="title" maxlength="140" placeholder="Sum up your experience">
          </div>
          <div class="field">
            <label for="review-body">Your review</label>
            <textarea id="review-body" name="body" maxlength="2000" required
                      placeholder="What did you think of the writing, the binding, the delivery?"></textarea>
            <span class="hint">Reviews are checked by a bookseller before they appear on the site.</span>
          </div>
          <button type="submit" class="btn btn-primary">Submit review</button>
        </form>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($related): ?>
    <section class="section" aria-labelledby="related-title">
      <div class="section-title">
        <div>
          <p class="eyebrow">You may also like</p>
          <h2 id="related-title">More from this shelf</h2>
        </div>
        <a class="link-arrow" href="<?= e(url('category.php?slug=' . urlencode((string) $product['category_slug']))) ?>">
          All <?= e((string) $product['category_name']) ?> <?= icon('arrow-right', '', 15) ?>
        </a>
      </div>
      <div class="product-grid">
        <?php foreach ($related as $relatedProduct) { render_product_card($relatedProduct); } ?>
      </div>
    </section>
  <?php endif; ?>
</div>
<?php render_footer(); ?>
