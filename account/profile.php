<?php
/** Profile details and password change. */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_login('Please sign in to manage your profile.');

$detailsErrors = [];
$passwordErrors = [];
$passwordDone = false;

if (is_post() && post('action') === 'update_profile') {
    csrf_guard();
    $firstName = truncate(post_raw('first_name'), 60);
    $lastName = truncate(post_raw('last_name'), 60);
    $email = strtolower(truncate(post_raw('email'), 190));
    $phone = normalize_phone(truncate(post_raw('phone'), 30));

    if ($firstName === '' || $lastName === '') { $detailsErrors[] = 'Please enter both your first and last name.'; }
    if (!is_valid_email($email)) { $detailsErrors[] = 'Please enter a valid e-mail address.'; }
    if ($phone !== '' && !is_valid_phone($phone)) { $detailsErrors[] = 'Please enter a valid mobile number, for example 09171234567.'; }
    $taken = fetch_value('SELECT id FROM users WHERE email = :email AND id <> :id', ['email' => $email, 'id' => $user['id']]);
    if ($taken) { $detailsErrors[] = 'Another account already uses that e-mail address.'; }

    if (!$detailsErrors) {
        db_update('users', [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone !== '' ? $phone : null,
        ], 'id', (int) $user['id']);
        audit_log('user.profile_updated', 'user', (int) $user['id'], 'Profile details changed');
        flash_success('Your profile has been updated.');
        redirect('account/profile.php');
    }
}

if (is_post() && post('action') === 'change_password') {
    csrf_guard();
    [$ok, $message] = change_password(
        (int) $user['id'],
        (string) post_raw('current_password'),
        (string) post_raw('new_password'),
        (string) post_raw('new_password_confirm'));
    if ($ok) {
        flash_success($message . ' Use the new password next time you sign in.');
        redirect('account/profile.php');
    }
    $passwordErrors[] = $message;
}

render_account_start([
    'current' => 'profile',
    'title' => 'Profile & password',
    'eyebrow' => 'Your details',
    'description' => 'Keep your contact details current so couriers can reach you, and change your password any time.',
    'breadcrumb' => [['label' => 'Home', 'url' => 'index.php'], ['label' => 'My account', 'url' => 'account/index.php'], ['label' => 'Profile']],
]);
?>
<div class="panel">
  <div class="panel-head"><h2>Contact details</h2></div>
  <?php if ($detailsErrors): ?>
    <div class="notice notice-error" role="alert">
      <?= icon('alert', 'notice-icon', 18) ?>
      <div class="notice-body"><strong>Please check the form</strong>
        <ul><?php foreach ($detailsErrors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    </div>
  <?php endif; ?>
  <form method="post" action="<?= e(url('account/profile.php')) ?>" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update_profile">
    <div class="field-row">
      <div class="field">
        <label for="first_name">First name</label>
        <input id="first_name" type="text" name="first_name" required maxlength="60" value="<?= e((string) $user['first_name']) ?>">
      </div>
      <div class="field">
        <label for="last_name">Last name</label>
        <input id="last_name" type="text" name="last_name" required maxlength="60" value="<?= e((string) $user['last_name']) ?>">
      </div>
    </div>
    <div class="field-row">
      <div class="field">
        <label for="email">E-mail address</label>
        <input id="email" type="email" name="email" required maxlength="190" value="<?= e((string) $user['email']) ?>">
        <span class="hint">This is the address you sign in with.</span>
      </div>
      <div class="field">
        <label for="phone">Mobile number</label>
        <input id="phone" type="tel" name="phone" maxlength="30" value="<?= e((string) ($user['phone'] ?? '')) ?>" placeholder="09171234567">
      </div>
    </div>
    <button type="submit" class="btn btn-primary">Save changes</button>
  </form>
</div>

<div class="panel">
  <div class="panel-head"><h2>Password</h2></div>
  <?php if ($passwordErrors): ?>
    <div class="notice notice-error" role="alert">
      <?= icon('alert', 'notice-icon', 18) ?>
      <div class="notice-body"><strong>Password not changed</strong>
        <ul><?php foreach ($passwordErrors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    </div>
  <?php endif; ?>
  <form method="post" action="<?= e(url('account/profile.php')) ?>" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="change_password">
    <div class="field">
      <label for="current_password">Current password</label>
      <input id="current_password" type="password" name="current_password" required autocomplete="current-password">
    </div>
    <div class="field-row">
      <div class="field">
        <label for="new_password">New password</label>
        <input id="new_password" type="password" name="new_password" required minlength="8" autocomplete="new-password">
        <span class="hint">At least 8 characters.</span>
      </div>
      <div class="field">
        <label for="new_password_confirm">Repeat new password</label>
        <input id="new_password_confirm" type="password" name="new_password_confirm" required minlength="8" autocomplete="new-password">
      </div>
    </div>
    <button type="submit" class="btn btn-secondary">Change password</button>
  </form>
  <p class="small muted mt-1">Changing your password keeps you signed in on this device only.</p>
</div>
<?php render_account_end(); ?>
