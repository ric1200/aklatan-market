<?php
/** Customer account overview. */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_login('Please sign in to open your account.');

$stats = [
    'orders' => (int) fetch_value('SELECT COUNT(*) FROM orders WHERE user_id = :uid', ['uid' => $user['id']], 0),
    'open' => (int) fetch_value("SELECT COUNT(*) FROM orders WHERE user_id = :uid AND status IN ('pending','confirmed','processing','shipped')",
        ['uid' => $user['id']], 0),
    'delivered' => (int) fetch_value("SELECT COUNT(*) FROM orders WHERE user_id = :uid AND status = 'delivered'",
        ['uid' => $user['id']], 0),
    'spent' => (float) fetch_value("SELECT COALESCE(SUM(total),0) FROM orders WHERE user_id = :uid AND status <> 'cancelled'",
        ['uid' => $user['id']], 0),
    'wishlist' => (int) fetch_value('SELECT COUNT(*) FROM wishlists WHERE user_id = :uid', ['uid' => $user['id']], 0),
    'reviews' => (int) fetch_value('SELECT COUNT(*) FROM reviews WHERE user_id = :uid', ['uid' => $user['id']], 0),
];

$recentOrders = fetch_all(
    'SELECT * FROM orders WHERE user_id = :uid ORDER BY created_at DESC LIMIT 4', ['uid' => $user['id']]);
$defaultAddress = fetch_one(
    'SELECT * FROM user_addresses WHERE user_id = :uid ORDER BY is_default DESC, id DESC LIMIT 1', ['uid' => $user['id']]);
$awaitingPayment = fetch_one(
    "SELECT * FROM orders WHERE user_id = :uid AND payment_status = 'pending_verification' ORDER BY created_at DESC LIMIT 1",
    ['uid' => $user['id']]);

render_account_start([
    'current' => 'overview',
    'title' => 'Hello, ' . $user['first_name'] . '!',
    'eyebrow' => 'Your account',
    'description' => 'Everything about your orders, saved titles and delivery details, in one place.',
]);
?>
<div class="stat-grid">
  <div class="stat-card">
    <p class="stat-label">Orders placed</p>
    <p class="stat-value"><?= (int) $stats['orders'] ?></p>
    <p class="stat-note"><?= (int) $stats['open'] ?> still in progress</p>
  </div>
  <div class="stat-card">
    <p class="stat-label">Delivered</p>
    <p class="stat-value"><?= (int) $stats['delivered'] ?></p>
    <p class="stat-note">Received and reviewed?</p>
  </div>
  <div class="stat-card">
    <p class="stat-label">Lifetime spend</p>
    <p class="stat-value"><?= e(money($stats['spent'])) ?></p>
    <p class="stat-note">Excludes cancelled orders</p>
  </div>
  <div class="stat-card">
    <p class="stat-label">Saved titles</p>
    <p class="stat-value"><?= (int) $stats['wishlist'] ?></p>
    <p class="stat-note"><a href="<?= e(url('wishlist.php')) ?>">Open wishlist</a></p>
  </div>
</div>

<?php if ($awaitingPayment): ?>
  <div class="notice notice-warning mt-2">
    <?= icon('clock', 'notice-icon', 18) ?>
    <div class="notice-body">
      <strong>Order <?= e($awaitingPayment['order_number']) ?> is waiting for payment verification.</strong>
      We have your GCash reference and a bookseller is checking it.
      <a href="<?= e(url('account/order-details.php?order=' . urlencode((string) $awaitingPayment['order_number']))) ?>">Open the order</a>.
    </div>
  </div>
<?php endif; ?>

<div class="panel mt-2">
  <div class="panel-head">
    <h2>Recent orders</h2>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('account/orders.php')) ?>">All orders</a>
  </div>
  <?php if (!$recentOrders): ?>
    <?php render_empty_state('package', 'No orders yet',
        'When you place your first order it will appear here with live status updates.',
        [['label' => 'Start browsing', 'url' => url('products.php')]]); ?>
  <?php else: ?>
    <div class="stack" style="--gap:12px">
      <?php foreach ($recentOrders as $order): ?>
        <div class="order-card">
          <div class="order-card-head">
            <span class="order-no"><?= e($order['order_number']) ?></span>
            <span class="cluster"><?= order_status_badge((string) $order['status']) ?> <?= payment_status_badge((string) $order['payment_status']) ?></span>
          </div>
          <div class="order-card-body">
            <p class="small muted">Placed <?= e(format_datetime($order['created_at'])) ?> ·
              <?= e(payment_method_label((string) $order['payment_method'])) ?></p>
            <p class="strong"><?= e(money($order['total'])) ?></p>
          </div>
          <div class="order-card-foot">
            <span class="tiny muted"><?= (int) fetch_value('SELECT COUNT(*) FROM order_items WHERE order_id = :oid', ['oid' => $order['id']], 0) ?> items</span>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('account/order-details.php?order=' . urlencode((string) $order['order_number']))) ?>">View details</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="info-grid mt-2">
  <div class="info-card">
    <div class="info-icon"><?= icon('pin', '', 20) ?></div>
    <h3>Default delivery address</h3>
    <?php if ($defaultAddress): ?>
      <p class="small"><?= e($defaultAddress['recipient_name']) ?><br>
        <?= e($defaultAddress['address_line1']) ?><?= $defaultAddress['address_line2'] ? ', ' . e($defaultAddress['address_line2']) : '' ?><br>
        <?= e($defaultAddress['city']) ?>, <?= e($defaultAddress['province']) ?><br>
        <?= e($defaultAddress['phone']) ?></p>
    <?php else: ?>
      <p class="small muted">No saved address yet. Add one to speed up checkout.</p>
    <?php endif; ?>
    <p class="mt-1"><a class="link-arrow" href="<?= e(url('account/addresses.php')) ?>">Manage addresses <?= icon('arrow-right', '', 14) ?></a></p>
  </div>

  <div class="info-card">
    <div class="info-icon"><?= icon('user', '', 20) ?></div>
    <h3>Profile</h3>
    <p class="small"><strong><?= e(user_full_name()) ?></strong><br>
      <?= e((string) $user['email']) ?><br>
      <?= $user['phone'] ? e((string) $user['phone']) : '<span class="muted">No mobile number saved</span>' ?></p>
    <p class="mt-1"><a class="link-arrow" href="<?= e(url('account/profile.php')) ?>">Edit profile &amp; password <?= icon('arrow-right', '', 14) ?></a></p>
  </div>

  <div class="info-card">
    <div class="info-icon"><?= icon('message', '', 20) ?></div>
    <h3>Reviews</h3>
    <p class="small">You have written <strong><?= (int) $stats['reviews'] ?></strong> review<?= $stats['reviews'] === 1 ? '' : 's' ?>.
      Reviews open once an order containing the title has been delivered.</p>
    <p class="mt-1"><a class="link-arrow" href="<?= e(url('account/reviews.php')) ?>">See my reviews <?= icon('arrow-right', '', 14) ?></a></p>
  </div>
</div>
<?php render_account_end(); ?>
