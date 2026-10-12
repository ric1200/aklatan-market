<?php
/** Customer wishlist. Works for signed-in customers only, and answers AJAX calls with JSON. */
require_once __DIR__ . '/includes/bootstrap.php';

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (is_post()) {
    csrf_guard();
    if (!is_logged_in()) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'on' => false, 'message' => 'Please sign in to use your wishlist.']);
            exit;
        }
        require_login('Please sign in to save items to your wishlist.');
    }

    $productId = post_int('product_id');
    $action = post('action');
    $return = safe_next_url(post('return'), 'wishlist.php');
    $product = product_by_id($productId, false);

    if (!$product) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'on' => false, 'message' => 'That item no longer exists.']);
            exit;
        }
        flash_error('That item no longer exists.');
        redirect($return);
    }

    $userId = (int) current_user_id();
    $exists = (bool) fetch_value('SELECT id FROM wishlists WHERE user_id = :uid AND product_id = :pid',
        ['uid' => $userId, 'pid' => $productId]);

    if ($action === 'remove' || ($action === 'toggle' && $exists)) {
        q('DELETE FROM wishlists WHERE user_id = :uid AND product_id = :pid', ['uid' => $userId, 'pid' => $productId]);
        $on = false;
        $message = $product['name'] . ' was removed from your wishlist.';
    } else {
        if ($exists) {
            $on = true;
            $message = $product['name'] . ' is already on your wishlist.';
        } else {
            db_insert('wishlists', ['user_id' => $userId, 'product_id' => $productId]);
            $on = true;
            $message = $product['name'] . ' was added to your wishlist.';
        }
    }

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => true, 'on' => $on, 'message' => $message]);
        exit;
    }
    flash_success($message);
    redirect($return);
}

$user = require_login('Please sign in to open your wishlist.');

$rows = fetch_all(
    'SELECT p.*, c.name AS category_name, c.slug AS category_slug, w.created_at AS saved_at
     FROM wishlists w
     JOIN products p ON p.id = w.product_id
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE w.user_id = :uid AND p.is_active = 1
     ORDER BY w.created_at DESC',
    ['uid' => $user['id']]);

render_account_start([
    'current' => 'wishlist',
    'title' => 'My wishlist',
    'eyebrow' => 'Saved for later',
    'description' => $rows
        ? count($rows) . ' ' . (count($rows) === 1 ? 'title' : 'titles') . ' saved. Move them to your cart whenever you are ready.'
        : 'Titles you save will appear here, on any device you sign in from.',
    'breadcrumb' => [['label' => 'Home', 'url' => 'index.php'], ['label' => 'My wishlist']],
]);
?>
<?php if (!$rows): ?>
  <?php render_empty_state(
      'heart',
      'Your wishlist is empty',
      'Tap the heart on any product to save it here. Wishlists follow your account, so a title you save on your phone will still be waiting on your laptop.',
      [
          ['label' => 'Browse all products', 'url' => url('products.php'), 'class' => 'btn-primary'],
          ['label' => 'See staff picks', 'url' => url('search.php?featured=staff'), 'class' => 'btn-ghost'],
      ]); ?>
<?php else: ?>
  <div class="panel">
    <div class="panel-head">
      <h2><?= count($rows) ?> saved <?= count($rows) === 1 ? 'title' : 'titles' ?></h2>
      <a class="btn btn-ghost btn-sm" href="<?= e(url('products.php')) ?>">Keep browsing</a>
    </div>
    <div class="product-grid">
      <?php foreach ($rows as $product) { render_product_card($product); } ?>
    </div>
    <p class="small muted mt-2">Saved items are reminders only — they are not reserved. Stock is only held once an
      order is placed.</p>
  </div>
<?php endif; ?>
<?php render_account_end(); ?>
