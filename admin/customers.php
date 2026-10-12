<?php
/** Customer list. Password hashes are never loaded, let alone displayed. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/admin-header.php';
$admin = require_admin();

if (is_post()) {
    csrf_guard();
    $target = fetch_one('SELECT id, first_name, last_name, role, status FROM users WHERE id = :id', ['id' => post_int('user_id')]);
    if (!$target) {
        flash_error('That account could not be found.');
        redirect('admin/customers.php');
    }
    if ((int) $target['id'] === (int) $admin['id']) {
        flash_error('You cannot change the status of your own account while signed in with it.');
        redirect('admin/customers.php');
    }
    if ($target['role'] === 'admin' && post('action') === 'deactivate') {
        $otherAdmins = (int) fetch_value("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active' AND id <> :id",
            ['id' => (int) $target['id']], 0);
        if ($otherAdmins === 0) {
            flash_error('That is the last active administrator. Create another admin before disabling this one.');
            redirect('admin/customers.php');
        }
        flash_error('Administrator accounts must be disabled from the database or by demoting the role first.');
        redirect('admin/customers.php');
    }
    if (post('action') === 'deactivate') {
        db_update('users', ['status' => 'inactive'], 'id', (int) $target['id']);
        audit_log('customer.deactivated', 'user', (int) $target['id'], $target['first_name'] . ' ' . $target['last_name']);
        flash_success('That account is now disabled and can no longer sign in.');
    } elseif (post('action') === 'activate') {
        db_update('users', ['status' => 'active'], 'id', (int) $target['id']);
        audit_log('customer.activated', 'user', (int) $target['id'], $target['first_name'] . ' ' . $target['last_name']);
        flash_success('That account has been re-enabled.');
    }
    redirect('admin/customers.php');
}

$keyword = truncate(get_param('q'), 80);
$role = (string) get_param('role');
$status = (string) get_param('status');
$page = max(1, get_int('page', 1));

$where = ['1=1'];
$params = [];
if ($keyword !== '') {
    $where[] = like_or_clause(['u.first_name', 'u.last_name', 'u.email', 'u.phone'], $keyword, $params, 'kw');
}
if (in_array($role, ['customer', 'admin'], true)) { $where[] = 'u.role = :role'; $params['role'] = $role; }
if (in_array($status, ['active', 'inactive'], true)) { $where[] = 'u.status = :status'; $params['status'] = $status; }
$whereSql = implode(' AND ', $where);

$total = (int) fetch_value('SELECT COUNT(*) FROM users u WHERE ' . $whereSql, $params, 0);
$p = paginate($total, 20, $page);
$customers = fetch_all('SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.role, u.status, u.created_at, u.last_login_at,
        (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS order_count,
        (SELECT COALESCE(SUM(o.total),0) FROM orders o WHERE o.user_id = u.id AND o.status <> \'cancelled\') AS spend
    FROM users u WHERE ' . $whereSql . ' ORDER BY u.created_at DESC LIMIT ' . (int) $p['perPage'] . ' OFFSET ' . (int) $p['offset'], $params);

render_admin_start([
    'current' => 'customers',
    'title' => 'Customers',
    'subtitle' => number_format($total) . ' account' . ($total === 1 ? '' : 's') . ' matching. Orders and spend are calculated live.',
    'crumbs' => [['label' => 'Dashboard', 'url' => 'admin/index.php'], ['label' => 'Customers']],
]);
?>
<form class="admin-toolbar admin-panel" method="get" action="<?= e(url('admin/customers.php')) ?>" style="padding:14px 16px">
  <div class="field">
    <label for="q">Name, e-mail or phone</label>
    <input id="q" type="search" name="q" value="<?= e($keyword) ?>">
  </div>
  <div class="field tight">
    <label for="role">Role</label>
    <select id="role" name="role">
      <option value="">Any role</option>
      <option value="customer" <?= $role === 'customer' ? 'selected' : '' ?>>Customers</option>
      <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Administrators</option>
    </select>
  </div>
  <div class="field tight">
    <label for="status">Account status</label>
    <select id="status" name="status">
      <option value="">Any status</option>
      <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
      <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Disabled</option>
    </select>
  </div>
  <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
  <a class="btn btn-quiet btn-sm" href="<?= e(url('admin/customers.php')) ?>">Reset</a>
</form>

<div class="admin-panel">
  <div class="admin-panel-head">
    <h2>Accounts</h2>
    <p class="panel-note">Only the details needed for support are shown. Password hashes are never queried here.</p>
  </div>
  <div class="admin-panel-body flush">
    <?php if (!$customers): ?>
      <div style="padding:16px">
        <?php render_empty_state('users', 'No accounts matched', 'Adjust the filters and try again.',
            [['label' => 'Show all accounts', 'url' => url('admin/customers.php')]]); ?>
      </div>
    <?php else: ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <caption class="sr-only">Customer accounts</caption>
          <thead>
            <tr><th scope="col">Customer</th><th scope="col">Contact</th><th scope="col">Role</th>
              <th scope="col" class="num">Orders</th><th scope="col" class="num">Spend</th>
              <th scope="col">Joined / last seen</th><th scope="col">Status</th><th scope="col" class="actions">Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($customers as $customer): ?>
              <tr>
                <td><a href="<?= e(url('admin/customer-view.php?id=' . (int) $customer['id'])) ?>">
                  <strong><?= e($customer['first_name'] . ' ' . $customer['last_name']) ?></strong></a></td>
                <td class="tiny"><?= e($customer['email']) ?><br><span class="muted"><?= e((string) ($customer['phone'] ?: 'no phone')) ?></span></td>
                <td><?= $customer['role'] === 'admin' ? '<span class="pill pill-amber">Admin</span>' : '<span class="pill">Customer</span>' ?></td>
                <td class="num"><?= (int) $customer['order_count'] ?></td>
                <td class="num"><?= e(money($customer['spend'])) ?></td>
                <td class="tiny"><?= e(format_date($customer['created_at'])) ?><br>
                  <span class="muted"><?= $customer['last_login_at'] ? 'seen ' . e(time_ago($customer['last_login_at'])) : 'never signed in' ?></span></td>
                <td><?= $customer['status'] === 'active' ? '<span class="pill pill-ok">Active</span>' : '<span class="pill pill-bad">Disabled</span>' ?></td>
                <td class="actions">
                  <form method="post" action="<?= e(url('admin/customers.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= (int) $customer['id'] ?>">
                    <?php if ($customer['status'] === 'active'): ?>
                      <button type="submit" name="action" value="deactivate" class="btn btn-quiet btn-xs"
                              data-confirm="Disable this account? The customer will not be able to sign in.">Disable</button>
                    <?php else: ?>
                      <button type="submit" name="action" value="activate" class="btn btn-ghost btn-xs">Re-enable</button>
                    <?php endif; ?>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?= pagination_links($p, 'admin/customers.php', array_filter(['q' => $keyword, 'role' => $role, 'status' => $status], function ($v) { return $v !== ''; })) ?>
<?php render_admin_end(); ?>
