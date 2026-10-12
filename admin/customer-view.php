<?php
/** One customer: profile, addresses, orders and reviews. No secrets shown. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/admin-header.php';
require_admin();

$customerId = get_int('id');
$customer = $customerId > 0
    ? fetch_one('SELECT id, first_name, last_name, email, phone, role, status, notes, created_at, updated_at, last_login_at
                 FROM users WHERE id = :id', ['id' => $customerId])
    : null;
if (!$customer) {
    aklatan_abort(404, 'That customer does not exist', 'The account may have been deleted. Return to the customer list.');
}

if (is_post() && post('action') === 'save_notes') {
    csrf_guard();
    $notes = truncate(post_raw('notes'), 2000);
    db_update('users', ['notes' => $notes !== '' ? $notes : null], 'id', $customerId);
    audit_log('customer.notes_updated', 'user', $customerId, 'Support notes updated');
    flash_success('Support notes saved.');
    redirect('admin/customer-view.php?id=' . $customerId);
}

$addresses = fetch_all('SELECT * FROM user_addresses WHERE user_id = :id ORDER BY is_default DESC, id DESC', ['id' => $customerId]);
$orders = fetch_all('SELECT * FROM orders WHERE user_id = :id ORDER BY created_at DESC LIMIT 25', ['id' => $customerId]);
$reviews = fetch_all('SELECT r.*, p.name AS product_name FROM reviews r JOIN products p ON p.id = r.product_id
    WHERE r.user_id = :id ORDER BY r.created_at DESC LIMIT 15', ['id' => $customerId]);
$totals = [
    'orders' => (int) fetch_value('SELECT COUNT(*) FROM orders WHERE user_id = :id', ['id' => $customerId], 0),
    'spend' => (float) fetch_value("SELECT COALESCE(SUM(total),0) FROM orders WHERE user_id = :id AND status <> 'cancelled'",
        ['id' => $customerId], 0),
    'delivered' => (int) fetch_value("SELECT COUNT(*) FROM orders WHERE user_id = :id AND status = 'delivered'", ['id' => $customerId], 0),
    'open' => (int) fetch_value("SELECT COUNT(*) FROM orders WHERE user_id = :id AND status IN ('pending','confirmed','processing','shipped')",
        ['id' => $customerId], 0),
];
$logs = fetch_all('SELECT a.* FROM audit_logs a WHERE a.entity_type = :t AND a.entity_id = :id ORDER BY a.created_at DESC LIMIT 10',
    ['t' => 'user', 'id' => (string) $customerId]);

render_admin_start([
    'current' => 'customers',
    'title' => $customer['first_name'] . ' ' . $customer['last_name'],
    'subtitle' => $customer['email'] . ' · joined ' . format_date($customer['created_at'])
        . ($customer['last_login_at'] ? ' · last seen ' . time_ago($customer['last_login_at']) : ' · never signed in'),
    'crumbs' => [
        ['label' => 'Dashboard', 'url' => 'admin/index.php'],
        ['label' => 'Customers', 'url' => 'admin/customers.php'],
        ['label' => 'Profile'],
    ],
    'actions' => '<a class="btn btn-ghost btn-sm" href="mailto:' . e($customer['email']) . '">' . icon('mail', '', 16) . ' E-mail</a>',
]);
?>
<div class="kpi-grid">
  <div class="kpi"><p class="kpi-label">Orders</p><p class="kpi-value"><?= number_format($totals['orders']) ?></p><p class="kpi-note"><?= (int) $totals['open'] ?> open</p></div>
  <div class="kpi"><p class="kpi-label">Delivered</p><p class="kpi-value"><?= number_format($totals['delivered']) ?></p><p class="kpi-note">Completed orders</p></div>
  <div class="kpi kpi-ok"><p class="kpi-label">Lifetime value</p><p class="kpi-value"><?= e(money($totals['spend'])) ?></p><p class="kpi-note">Excludes cancelled</p></div>
  <div class="kpi"><p class="kpi-label">Saved addresses</p><p class="kpi-value"><?= count($addresses) ?></p><p class="kpi-note">Delivery points</p></div>
</div>

<div class="admin-grid-2">
  <div>
    <div class="admin-panel">
      <div class="admin-panel-head"><h2>Account</h2></div>
      <div class="admin-panel-body">
        <div class="detail-list">
          <div><span class="k">Name</span><span><?= e($customer['first_name'] . ' ' . $customer['last_name']) ?></span></div>
          <div><span class="k">E-mail</span><span><?= e($customer['email']) ?></span></div>
          <div><span class="k">Phone</span><span><?= e((string) ($customer['phone'] ?: '—')) ?></span></div>
          <div><span class="k">Role</span><span><?= $customer['role'] === 'admin' ? 'Administrator' : 'Customer' ?></span></div>
          <div><span class="k">Status</span><span><?= $customer['status'] === 'active' ? 'Active' : 'Disabled' ?></span></div>
          <div><span class="k">Registered</span><span><?= e(format_datetime($customer['created_at'])) ?></span></div>
          <div><span class="k">Last sign-in</span><span><?= $customer['last_login_at'] ? e(format_datetime($customer['last_login_at'])) : 'Never' ?></span></div>
        </div>
        <p class="tiny muted mt-1">Password hashes are deliberately not shown or exported — they are never needed for support.</p>
      </div>
    </div>

    <div class="admin-panel">
      <div class="admin-panel-head"><h2>Saved addresses</h2></div>
      <div class="admin-panel-body">
        <?php if (!$addresses): ?>
          <p class="muted small">No saved addresses.</p>
        <?php else: ?>
          <div class="detail-list">
            <?php foreach ($addresses as $address): ?>
              <div>
                <span class="k"><?= e($address['label']) ?><?= !empty($address['is_default']) ? ' (default)' : '' ?></span>
                <span><?= e($address['recipient_name']) ?> · <?= e($address['phone']) ?><br>
                  <span class="muted"><?= e($address['address_line1']) ?><?= $address['address_line2'] ? ', ' . e($address['address_line2']) : '' ?>,
                  <?= e($address['city']) ?>, <?= e($address['province']) ?></span></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="admin-panel">
      <div class="admin-panel-head"><h2>Support notes</h2><p class="panel-note">Visible to staff only.</p></div>
      <div class="admin-panel-body">
        <form method="post" action="<?= e(url('admin/customer-view.php?id=' . $customerId)) ?>" class="form-grid">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save_notes">
          <div class="field">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" rows="4" maxlength="2000"><?= e((string) ($customer['notes'] ?? '')) ?></textarea>
          </div>
          <button type="submit" class="btn btn-secondary btn-sm"><?= icon('check', '', 15) ?> Save notes</button>
        </form>
      </div>
    </div>
  </div>

  <div>
    <div class="admin-panel">
      <div class="admin-panel-head"><h2>Orders</h2><p class="panel-note">25 most recent</p></div>
      <div class="admin-panel-body flush">
        <?php if (!$orders): ?>
          <div style="padding:16px"><p class="muted small">This customer has not ordered yet.</p></div>
        <?php else: ?>
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead><tr><th scope="col">Order</th><th scope="col" class="num">Total</th><th scope="col">Status</th></tr></thead>
              <tbody>
                <?php foreach ($orders as $order): ?>
                  <tr>
                    <td><a href="<?= e(url('admin/order-view.php?order=' . urlencode((string) $order['order_number']))) ?>"><?= e($order['order_number']) ?></a><br>
                      <span class="tiny muted"><?= e(format_date($order['created_at'])) ?></span></td>
                    <td class="num"><?= e(money($order['total'])) ?></td>
                    <td><?= order_status_badge((string) $order['status']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="admin-panel">
      <div class="admin-panel-head"><h2>Reviews written</h2></div>
      <div class="admin-panel-body">
        <?php if (!$reviews): ?>
          <p class="muted small">No reviews from this customer.</p>
        <?php else: ?>
          <ul class="audit-list">
            <?php foreach ($reviews as $review): ?>
              <li>
                <span class="audit-action"><?= e($review['product_name']) ?> — <?= (int) $review['rating'] ?>/5</span>
                <span class="audit-meta"><?= e(truncate((string) $review['body'], 120)) ?></span>
                <span class="audit-meta"><?= e(format_date($review['created_at'])) ?> · <?= e(ucfirst((string) $review['status'])) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>

    <div class="admin-panel">
      <div class="admin-panel-head"><h2>Activity</h2></div>
      <div class="admin-panel-body">
        <?php if (!$logs): ?>
          <p class="muted small">No recorded activity.</p>
        <?php else: ?>
          <ul class="audit-list">
            <?php foreach ($logs as $log): ?>
              <li>
                <span class="audit-action"><?= e(str_replace(['.', '_'], [' ', ' '], (string) $log['action'])) ?></span>
                <span class="audit-meta"><?= e((string) ($log['details'] ?? '')) ?> · <?= e(format_datetime($log['created_at'])) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php render_admin_end(); ?>
