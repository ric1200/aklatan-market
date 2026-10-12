<?php
/** Everything this customer has written, with moderation status. */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_login('Please sign in to see your reviews.');

if (is_post() && post('action') === 'delete_review') {
    csrf_guard();
    $reviewId = post_int('review_id');
    $review = fetch_one('SELECT * FROM reviews WHERE id = :id AND user_id = :uid', ['id' => $reviewId, 'uid' => $user['id']]);
    if (!$review) {
        flash_error('That review could not be found on your account.');
        redirect('account/reviews.php');
    }
    q('DELETE FROM reviews WHERE id = :id AND user_id = :uid', ['id' => $reviewId, 'uid' => $user['id']]);
    audit_log('review.deleted_by_author', 'review', $reviewId);
    flash_success('Your review was removed.');
    redirect('account/reviews.php');
}

$reviews = fetch_all(
    'SELECT r.*, p.name AS product_name, p.slug AS product_slug, p.image AS product_image
     FROM reviews r JOIN products p ON p.id = r.product_id
     WHERE r.user_id = :uid ORDER BY r.created_at DESC',
    ['uid' => $user['id']]);

render_account_start([
    'current' => 'reviews',
    'title' => 'My reviews',
    'eyebrow' => 'Your reading notes',
    'description' => 'Reviews you have written. A bookseller reads every one before it appears on the product page.',
    'breadcrumb' => [['label' => 'Home', 'url' => 'index.php'], ['label' => 'My account', 'url' => 'account/index.php'], ['label' => 'Reviews']],
]);
?>
<?php if (!$reviews): ?>
  <?php render_empty_state('message', 'You have not written a review yet',
      'Once an order is delivered you can review each title from your order details. It is the single most helpful thing you can do for other readers.',
      [
          ['label' => 'View my orders', 'url' => url('account/orders.php'), 'class' => 'btn-primary'],
          ['label' => 'Browse books', 'url' => url('products.php'), 'class' => 'btn-ghost'],
      ]); ?>
<?php else: ?>
  <div class="review-list">
    <?php foreach ($reviews as $review): ?>
      <article class="review">
        <div class="review-head">
          <div class="cluster">
            <img src="<?= e(product_image_url(['image' => $review['product_image']])) ?>" alt=""
                 width="34" height="51" style="width:34px;border-radius:5px;aspect-ratio:2/3;object-fit:cover">
            <div>
              <p class="review-author">
                <a href="<?= e(url('product.php?slug=' . urlencode((string) $review['product_slug']) . '#reviews')) ?>"><?= e($review['product_name']) ?></a>
              </p>
              <p class="tiny muted">Written <?= e(format_datetime($review['created_at'])) ?></p>
            </div>
          </div>
          <div class="cluster">
            <?= stars((float) $review['rating']) ?>
            <?php if ($review['status'] === 'approved'): ?>
              <span class="pill pill-ok">Published</span>
            <?php elseif ($review['status'] === 'pending'): ?>
              <span class="pill pill-warn">Awaiting approval</span>
            <?php else: ?>
              <span class="pill pill-bad">Not published</span>
            <?php endif; ?>
          </div>
        </div>
        <?php if (!empty($review['title'])): ?><h3 class="card-title"><?= e((string) $review['title']) ?></h3><?php endif; ?>
        <p class="review-body"><?= nl2br(e((string) $review['body'])) ?></p>
        <?php if ($review['status'] === 'rejected' && !empty($review['admin_note'])): ?>
          <p class="small muted mt-1">Reason from the shop: <?= e((string) $review['admin_note']) ?></p>
        <?php endif; ?>
        <form method="post" action="<?= e(url('account/reviews.php')) ?>" class="mt-1"
              data-confirm="Delete this review permanently?">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete_review">
          <input type="hidden" name="review_id" value="<?= (int) $review['id'] ?>">
          <button type="submit" class="btn btn-quiet btn-sm" style="color:var(--danger)"><?= icon('trash', '', 15) ?> Delete review</button>
        </form>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php render_account_end(); ?>
