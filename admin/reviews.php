<?php
/** Review moderation: approve, reject or delete. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/admin-header.php';
$admin = require_admin();

if (is_post()) {
    csrf_guard();
    $review = fetch_one('SELECT r.*, p.name AS product_name FROM reviews r JOIN products p ON p.id = r.product_id WHERE r.id = :id',
        ['id' => post_int('review_id')]);
    if (!$review) {
        flash_error('That review could not be found.');
        redirect('admin/reviews.php');
    }
    $action = post('action');
    $note = truncate(post_raw('admin_note'), 300);

    if ($action === 'approve') {
        db_update('reviews', ['status' => 'approved', 'admin_note' => $note !== '' ? $note : null], 'id', (int) $review['id']);
        audit_log('review.approved', 'review', (int) $review['id'], 'Approved review on ' . $review['product_name']);
        flash_success('Review published on the product page.');
    } elseif ($action === 'reject') {
        if ($note === '') {
            flash_error('Please add a short reason so the customer understands why the review was not published.');
            redirect('admin/reviews.php?status=pending');
        }
        db_update('reviews', ['status' => 'rejected', 'admin_note' => $note], 'id', (int) $review['id']);
        audit_log('review.rejected', 'review', (int) $review['id'], 'Rejected review on ' . $review['product_name'] . ' — ' . $note);
        flash_success('Review rejected. The customer can see the reason in their account.');
    } elseif ($action === 'delete') {
        q('DELETE FROM reviews WHERE id = :id', ['id' => (int) $review['id']]);
        audit_log('review.deleted', 'review', (int) $review['id'], 'Deleted review on ' . $review['product_name']);
        flash_success('Review deleted permanently.');
    } elseif ($action === 'unpublish') {
        db_update('reviews', ['status' => 'pending'], 'id', (int) $review['id']);
        audit_log('review.unpublished', 'review', (int) $review['id'], 'Returned to the moderation queue');
        flash_success('Review moved back to the moderation queue and is no longer public.');
    }
    redirect('admin/reviews.php?' . http_build_query(array_filter(['status' => get_param('status'), 'rating' => get_param('rating')])));
}

$status = (string) get_param('status');
if (!in_array($status, ['pending', 'approved', 'rejected'], true)) { $status = ''; }
$rating = get_int('rating');
$page = max(1, get_int('page', 1));

$where = ['1=1'];
$params = [];
if ($status !== '') { $where[] = 'r.status = :status'; $params['status'] = $status; }
if ($rating >= 1 && $rating <= 5) { $where[] = 'r.rating = :rating'; $params['rating'] = $rating; }
$whereSql = implode(' AND ', $where);

$total = (int) fetch_value('SELECT COUNT(*) FROM reviews r WHERE ' . $whereSql, $params, 0);
$p = paginate($total, 20, $page);
$reviews = fetch_all('SELECT r.*, p.name AS product_name, p.slug AS product_slug, u.first_name, u.last_name, u.email, o.order_number
    FROM reviews r
    JOIN products p ON p.id = r.product_id
    JOIN users u ON u.id = r.user_id
    LEFT JOIN orders o ON o.id = r.order_id
    WHERE ' . $whereSql . ' ORDER BY (r.status = \'pending\') DESC, r.created_at DESC
    LIMIT ' . (int) $p['perPage'] . ' OFFSET ' . (int) $p['offset'], $params);

$counts = [];
foreach (fetch_all('SELECT status, COUNT(*) AS c FROM reviews GROUP BY status') as $row) { $counts[$row['status']] = (int) $row['c']; }
$average = (float) fetch_value("SELECT COALESCE(AVG(rating),0) FROM reviews WHERE status = 'approved'", [], 0);

render_admin_start([
    'current' => 'reviews',
    'title' => 'Reviews',
    'subtitle' => number_format($total) . ' matching · '
        . (int) ($counts['pending'] ?? 0) . ' waiting for a decision · average published rating '
        . number_format($average, 2),
    'crumbs' => [['label' => 'Dashboard', 'url' => 'admin/index.php'], ['label' => 'Reviews']],
]);
?>
<form class="admin-toolbar admin-panel" method="get" action="<?= e(url('admin/reviews.php')) ?>" style="padding:14px 16px">
  <div class="field tight">
    <label for="status">Moderation state</label>
    <select id="status" name="status">
      <option value="">All reviews</option>
      <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Awaiting approval (<?= (int) ($counts['pending'] ?? 0) ?>)</option>
      <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Published (<?= (int) ($counts['approved'] ?? 0) ?>)</option>
      <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected (<?= (int) ($counts['rejected'] ?? 0) ?>)</option>
    </select>
  </div>
  <div class="field tight">
    <label for="rating">Stars</label>
    <select id="rating" name="rating">
      <option value="">Any rating</option>
      <?php for ($i = 5; $i >= 1; $i--): ?>
        <option value="<?= $i ?>" <?= $rating === $i ? 'selected' : '' ?>><?= $i ?> star<?= $i === 1 ? '' : 's' ?></option>
      <?php endfor; ?>
    </select>
  </div>
  <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
  <a class="btn btn-quiet btn-sm" href="<?= e(url('admin/reviews.php')) ?>">Reset</a>
</form>

<div class="admin-panel">
  <div class="admin-panel-head">
    <h2>Moderation queue</h2>
    <p class="panel-note">Only approved reviews are visible to shoppers. Reviews are tied to a delivered order.</p>
  </div>
  <div class="admin-panel-body">
    <?php if (!$reviews): ?>
      <?php render_empty_state('message', 'No reviews matched',
          'Nothing is waiting for moderation with the current filters.',
          [['label' => 'Show all reviews', 'url' => url('admin/reviews.php')]]); ?>
    <?php else: ?>
      <?php foreach ($reviews as $review): ?>
        <div class="admin-panel" style="margin-bottom:14px">
          <div class="admin-panel-head">
            <div>
              <h3 style="font-size:.98rem;margin:0">
                <a href="<?= e(url('product.php?slug=' . urlencode((string) $review['product_slug']) . '#reviews')) ?>" target="_blank" rel="noopener"><?= e($review['product_name']) ?></a>
              </h3>
              <p class="tiny muted">
                <?= e($review['first_name'] . ' ' . substr((string) $review['last_name'], 0, 1) . '.') ?>
                (<?= e($review['email']) ?>)
                <?= $review['order_number'] ? ' · order ' . e($review['order_number']) : ' · no linked order' ?>
                · <?= e(format_datetime($review['created_at'])) ?>
              </p>
            </div>
            <div class="cluster">
              <?= stars((float) $review['rating']) ?>
              <?php if ($review['status'] === 'approved'): ?><span class="pill pill-ok">Published</span>
              <?php elseif ($review['status'] === 'pending'): ?><span class="pill pill-warn">Awaiting approval</span>
              <?php else: ?><span class="pill pill-bad">Rejected</span><?php endif; ?>
            </div>
          </div>
          <div class="admin-panel-body">
            <?php if (!empty($review['title'])): ?><p class="strong"><?= e((string) $review['title']) ?></p><?php endif; ?>
            <p><?= nl2br(e((string) $review['body'])) ?></p>
            <?php if (!empty($review['admin_note'])): ?>
              <p class="tiny muted">Moderator note: <?= e((string) $review['admin_note']) ?></p>
            <?php endif; ?>

            <form method="post" action="<?= e(url('admin/reviews.php?' . http_build_query(array_filter(['status' => $status, 'rating' => $rating ?: ''])))) ?>" class="inline-form mt-2">
              <?= csrf_field() ?>
              <input type="hidden" name="review_id" value="<?= (int) $review['id'] ?>">
              <div class="field">
                <label class="sr-only" for="note-<?= (int) $review['id'] ?>">Moderator note</label>
                <input id="note-<?= (int) $review['id'] ?>" type="text" name="admin_note" maxlength="300"
                       placeholder="Reason (required to reject)">
              </div>
              <?php if ($review['status'] !== 'approved'): ?>
                <button type="submit" name="action" value="approve" class="btn btn-primary btn-xs"><?= icon('check', '', 14) ?> Approve</button>
              <?php endif; ?>
              <?php if ($review['status'] === 'pending'): ?>
                <button type="submit" name="action" value="reject" class="btn btn-danger btn-xs"><?= icon('x-circle', '', 14) ?> Reject</button>
              <?php endif; ?>
              <?php if ($review['status'] === 'approved'): ?>
                <button type="submit" name="action" value="unpublish" class="btn btn-quiet btn-xs"><?= icon('eye-off', '', 14) ?> Unpublish</button>
              <?php endif; ?>
              <button type="submit" name="action" value="delete" class="btn btn-quiet btn-xs" style="color:var(--danger)"
                      data-confirm="Delete this review permanently?">Delete</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?= pagination_links($p, 'admin/reviews.php', array_filter(['status' => $status, 'rating' => $rating ?: ''], function ($v) { return $v !== ''; })) ?>
<?php render_admin_end(); ?>
