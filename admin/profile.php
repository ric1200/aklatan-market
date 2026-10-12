<?php
/** The signed-in administrator's own details and password. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/admin-header.php';
$admin = require_admin();

$detailsErrors = [];
$passwordErrors = [];

if (is_post() && post('action') === 'update_admin') {
    csrf_guard();
    $firstName = truncate(post_raw('first_name'), 60);
    $lastName = truncate(post_raw('last_name'), 60);
    $email = strtolower(truncate(post_raw('email'), 190));
    $phone = normalize_phone(truncate(post_raw('phone'), 30));

    if ($firstName === '' || $lastName === '') { $detailsErrors[] = 'Please enter both your first and last name.'; }
    if (!is_valid_email($email)) { $detailsErrors[] = 'Please enter a valid e-mail address.'; }
    if ($phone !== '' && !is_valid_phone($phone)) { $detailsErrors[] = 'Please enter a valid mobile number.'; }
    $taken = fetch_value('SELECT id FROM users WHERE email = :email AND id <> :id', ['email' => $email, 'id' => $admin['id']]);
    if ($taken) { $detailsErrors[] = 'Another account already uses that e-mail address.'; }

    if (!$detailsErrors) {
        db_update('users', [
            'first_name' => $firstName, 'last_name' => $lastName, 'email' => $email,
            'phone' => $phone !== '' ? $phone : null,
        ], 'id', (int) $admin['id']);
        audit_log('admin.profile_updated', 'user', (int) $admin['id'], 'Administrator profile updated');
        flash_success('Your administrator details were updated.');
        redirect('admin/profile.php');
    }
}

if (is_post() && post('action') === 'change_admin_password') {
    csrf_guard();
    [$ok, $message] = change_password(
        (int) $admin['id'],
        (string) post_raw('current_password'),
        (string) post_raw('new_password'),
        (string) post_raw('new_password_confirm'));
    if ($ok) {
        audit_log('admin.password_changed', 'user', (int) $admin['id'], 'Administrator password changed');
        flash_success($message . ' Use the new password next time you sign in.');
        redirect('admin/profile.php');
    }
    $passwordErrors[] = $message;
}

$admins = fetch_all("SELECT id, first_name, last_name, email, status, created_at, last_login_at
    FROM users WHERE role = 'admin' ORDER BY created_at ASC");
$recentLogins = fetch_all('SELECT * FROM login_attempts ORDER BY attempted_at DESC LIMIT 15');

render_admin_start([
    'current' => 'profile',
    'title' => 'My admin account',
    'subtitle' => 'Your own details, password and a look at recent sign-in activity.',
    'crumbs' => [['label' => 'Dashboard', 'url' => 'admin/index.php'], ['label' => 'My admin account']],
]);
?>
<div class="admin-grid-2">
  <div>
    <div class="admin-panel">
      <div class="admin-panel-head"><h2>Your details</h2></div>
      <div class="admin-panel-body">
        <?php if ($detailsErrors): ?>
          <div class="notice notice-error" role="alert">
            <?= icon('alert', 'notice-icon', 18) ?>
            <div class="notice-body"><strong>Please check the form</strong>
              <ul><?php foreach ($detailsErrors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
          </div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('admin/profile.php')) ?>" class="form-grid">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_admin">
          <div class="field-row">
            <div class="field">
              <label for="first_name">First name</label>
              <input id="first_name" type="text" name="first_name" required maxlength="60" value="<?= e((string) $admin['first_name']) ?>">
            </div>
            <div class="field">
              <label for="last_name">Last name</label>
              <input id="last_name" type="text" name="last_name" required maxlength="60" value="<?= e((string) $admin['last_name']) ?>">
            </div>
          </div>
          <div class="field">
            <label for="email">E-mail address</label>
            <input id="email" type="email" name="email" required maxlength="190" value="<?= e((string) $admin['email']) ?>">
          </div>
          <div class="field">
            <label for="phone">Mobile number <span class="hint">(optional)</span></label>
            <input id="phone" type="tel" name="phone" maxlength="30" value="<?= e((string) ($admin['phone'] ?? '')) ?>">
          </div>
          <button type="submit" class="btn btn-primary btn-sm">Save details</button>
        </form>
      </div>
    </div>

    <div class="admin-panel">
      <div class="admin-panel-head"><h2>Change password</h2></div>
      <div class="admin-panel-body">
        <?php if ($passwordErrors): ?>
          <div class="notice notice-error" role="alert">
            <?= icon('alert', 'notice-icon', 18) ?>
            <div class="notice-body"><strong>Password not changed</strong>
              <ul><?php foreach ($passwordErrors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
          </div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('admin/profile.php')) ?>" class="form-grid">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="change_admin_password">
          <div class="field">
            <label for="current_password">Current password</label>
            <input id="current_password" type="password" name="current_password" required autocomplete="current-password">
          </div>
          <div class="field-row">
            <div class="field">
              <label for="new_password">New password</label>
              <input id="new_password" type="password" name="new_password" required minlength="8" autocomplete="new-password">
            </div>
            <div class="field">
              <label for="new_password_confirm">Repeat new password</label>
              <input id="new_password_confirm" type="password" name="new_password_confirm" required minlength="8" autocomplete="new-password">
            </div>
          </div>
          <button type="submit" class="btn btn-secondary btn-sm">Change password</button>
        </form>
      </div>
    </div>
  </div>

  <div>
    <div class="admin-panel">
      <div class="admin-panel-head"><h2>Administrator accounts</h2>
        <p class="panel-note">Create additional admins through <code>setup_admin.php</code> while it exists, or directly in the database.</p></div>
      <div class="admin-panel-body flush">
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th scope="col">Administrator</th><th scope="col">Status</th><th scope="col">Last sign-in</th></tr></thead>
            <tbody>
              <?php foreach ($admins as $row): ?>
                <tr>
                  <td><?= e($row['first_name'] . ' ' . $row['last_name']) ?><?= (int) $row['id'] === (int) $admin['id'] ? ' <span class="pill pill-amber">you</span>' : '' ?>
                    <br><span class="tiny muted"><?= e($row['email']) ?></span></td>
                  <td><?= $row['status'] === 'active' ? '<span class="pill pill-ok">Active</span>' : '<span class="pill pill-bad">Disabled</span>' ?></td>
                  <td class="tiny"><?= $row['last_login_at'] ? e(format_datetime($row['last_login_at'])) : 'never' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="admin-panel">
      <div class="admin-panel-head"><h2>Recent sign-in attempts</h2>
        <p class="panel-note">Failed attempts are throttled automatically.</p></div>
      <div class="admin-panel-body">
        <?php if (!$recentLogins): ?>
          <p class="muted small">No recorded sign-in activity yet.</p>
        <?php else: ?>
          <ul class="audit-list">
            <?php foreach ($recentLogins as $attempt): ?>
              <li>
                <span class="audit-action">
                  <?= $attempt['success'] ? '<span class="pill pill-ok">Success</span>' : '<span class="pill pill-bad">Failed</span>' ?>
                  <?= e($attempt['email']) ?>
                </span>
                <span class="audit-meta"><?= e(format_datetime($attempt['attempted_at'])) ?> · <?= e((string) ($attempt['ip_address'] ?? '')) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>

    <div class="notice notice-warning">
      <?= icon('alert', 'notice-icon', 18) ?>
      <div class="notice-body">
        <strong>Security reminders for the free plan.</strong>
        Enable the free SSL certificate and set <code>FORCE_HTTPS</code> to <code>true</code>, delete
        <code>setup_admin.php</code> once your admin exists, and remove the sample <code>@example.com</code> customers
        before selling to the public.
      </div>
    </div>
  </div>
</div>
<?php render_admin_end(); ?>
