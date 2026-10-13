<?php
/**
 * ONE-TIME FIRST ADMINISTRATOR SETUP.
 *
 * This file only works while the users table contains no administrator at all.
 * As soon as one exists it locks itself. Delete this file from the server after
 * you have created your account — see INSTALLATION_GUIDE.txt, step 8.
 */
require_once __DIR__ . '/includes/bootstrap.php';

$adminCount = (int) fetch_value("SELECT COUNT(*) FROM users WHERE role = 'admin'", [], 0);
$locked = $adminCount > 0;
$errors = [];
$done = false;
$values = ['first_name' => '', 'last_name' => '', 'email' => ''];

if (!$locked && is_post() && post('action') === 'create_admin') {
    csrf_guard();
    $values = [
        'first_name' => truncate(post_raw('first_name'), 60),
        'last_name' => truncate(post_raw('last_name'), 60),
        'email' => strtolower(truncate(post_raw('email'), 190)),
    ];
    $password = (string) post_raw('password');
    $confirm = (string) post_raw('password_confirm');
    $phone = normalize_phone(truncate(post_raw('phone'), 30));
    $removeDemo = checkbox_post('remove_demo');

    if ($values['first_name'] === '' || $values['last_name'] === '') { $errors[] = 'Please enter your first and last name.'; }
    if (!is_valid_email($values['email'])) { $errors[] = 'Please enter a valid e-mail address — this is your sign-in name.'; }
    if (strlen($password) < 10) { $errors[] = 'Choose a password of at least 10 characters. Longer is better than complicated.'; }
    if ($password !== $confirm) { $errors[] = 'The two passwords do not match.'; }
    if (in_array(strtolower($password), ['password', 'password123', 'admin', 'admin123', '12345678910', 'aklatan123'], true)) {
        $errors[] = 'That password is too easy to guess. Please choose something unique.';
    }
    if (fetch_value('SELECT id FROM users WHERE email = :email', ['email' => $values['email']])) {
        $errors[] = 'An account already uses that e-mail address. Sign in with it instead, then promote it in the database.';
    }

    if (!$errors) {
        $userId = db_insert('users', [
            'first_name' => $values['first_name'],
            'last_name' => $values['last_name'],
            'email' => $values['email'],
            'phone' => $phone !== '' ? $phone : null,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'admin',
            'status' => 'active',
        ]);

        audit_log('admin.created', 'user', $userId, 'First administrator created through setup_admin.php');

        if ($removeDemo) {
            $demoUsers = fetch_all("SELECT id, email FROM users WHERE email LIKE '%@example.com'");
            $demoIds = array_map(static fn(array $row): int => (int) $row['id'], $demoUsers);
            $ordersRemoved = 0;

            // orders.user_id is ON DELETE SET NULL, so the sample orders must be
            // removed first - otherwise the shop would keep a pile of orphaned
            // demo orders after the sample customers disappear. Their order
            // items and payment records follow through ON DELETE CASCADE.
            if ($demoIds) {
                $placeholders = implode(',', array_fill(0, count($demoIds), '?'));
                $ordersRemoved = (int) fetch_value(
                    "SELECT COUNT(*) FROM orders WHERE user_id IN ($placeholders)", $demoIds, 0);
                q("DELETE FROM orders WHERE user_id IN ($placeholders)", $demoIds);
            }

            // Carts, wishlists, reviews, addresses and redemptions cascade from users.
            $removed = 0;
            foreach ($demoUsers as $demoUser) {
                q('DELETE FROM users WHERE id = :id', ['id' => (int) $demoUser['id']]);
                $removed++;
            }
            q("DELETE FROM contact_messages WHERE email LIKE '%@example.com'");
            audit_log('system.demo_data_removed', 'user', null,
                $removed . ' sample customer account(s), ' . $ordersRemoved . ' sample order(s) and their reviews removed');
        }

        $user = fetch_one('SELECT * FROM users WHERE id = :id', ['id' => $userId]);
        complete_login($user);
        $done = true;
        $locked = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Create the first administrator · Aklatan Market</title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="site">
<main id="main" class="container auth-shell">
  <div class="auth-card" style="max-width:560px">
    <?php if ($done): ?>
      <p class="eyebrow">Setup complete</p>
      <h1>Your administrator account is ready</h1>
      <p class="muted">You are signed in as <strong><?= e(user_full_name()) ?></strong>.
        This file has now locked itself because an administrator exists.</p>

      <div class="notice notice-warning mt-2">
        <?= icon('alert', 'notice-icon', 18) ?>
        <div class="notice-body">
          <strong>Delete <code>setup_admin.php</code> from your server now.</strong>
          Use the File Manager in your hosting control panel: open the <code>htdocs</code> folder, tick
          <code>setup_admin.php</code> and delete it. Until you do, anyone who guesses this address could create
          another administrator if your admin account is ever removed.
        </div>
      </div>

      <p class="cluster mt-2">
        <a class="btn btn-primary btn-lg" href="<?= e(url('admin/index.php')) ?>">Open the admin dashboard</a>
        <a class="btn btn-ghost" href="<?= e(url('index.php')) ?>">View the storefront</a>
      </p>
    <?php elseif ($locked): ?>
      <p class="eyebrow">Already set up</p>
      <h1>An administrator already exists</h1>
      <p class="muted">This one-time setup page is locked, exactly as it should be. Nothing can be created here now.</p>
      <div class="notice notice-info">
        <?= icon('shield', 'notice-icon', 18) ?>
        <div class="notice-body">
          <strong>Best practice: delete this file.</strong>
          If you need a second administrator later, either promote an existing customer account by editing its
          <code>role</code> column in phpMyAdmin, or temporarily restore this file, create the account and delete it again.
        </div>
      </div>
      <p class="cluster mt-2">
        <a class="btn btn-primary" href="<?= e(url('login.php')) ?>">Sign in</a>
        <a class="btn btn-ghost" href="<?= e(url('index.php')) ?>">Back to the storefront</a>
      </p>
    <?php else: ?>
      <p class="eyebrow">One-time setup</p>
      <h1>Create the first administrator</h1>
      <p class="muted">This page works exactly once, while the store has no administrator account.
        Use a real e-mail address you control and a long, unique password.</p>

      <?php if ($errors): ?>
        <div class="notice notice-error" role="alert">
          <?= icon('alert', 'notice-icon', 18) ?>
          <div class="notice-body"><strong>Please check the form</strong>
            <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        </div>
      <?php endif; ?>

      <form method="post" action="<?= e(url('setup_admin.php')) ?>" class="form-grid mt-2">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_admin">
        <div class="field-row">
          <div class="field">
            <label for="first_name">First name</label>
            <input id="first_name" type="text" name="first_name" required maxlength="60" value="<?= e($values['first_name']) ?>" autocomplete="given-name">
          </div>
          <div class="field">
            <label for="last_name">Last name</label>
            <input id="last_name" type="text" name="last_name" required maxlength="60" value="<?= e($values['last_name']) ?>" autocomplete="family-name">
          </div>
        </div>
        <div class="field">
          <label for="email">Administrator e-mail (your sign-in name)</label>
          <input id="email" type="email" name="email" required maxlength="190" value="<?= e($values['email']) ?>" autocomplete="username">
        </div>
        <div class="field">
          <label for="phone">Mobile number <span class="hint">(optional)</span></label>
          <input id="phone" type="tel" name="phone" maxlength="30" autocomplete="tel">
        </div>
        <div class="field-row">
          <div class="field">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" required minlength="10" autocomplete="new-password">
            <span class="hint">At least 10 characters. A memorable phrase is stronger than a short scramble.</span>
          </div>
          <div class="field">
            <label for="password_confirm">Confirm password</label>
            <input id="password_confirm" type="password" name="password_confirm" required minlength="10" autocomplete="new-password">
          </div>
        </div>
        <div class="check">
          <input id="remove_demo" type="checkbox" name="remove_demo" value="1" checked>
          <label for="remove_demo">Also delete the sample customers, orders and reviews that came with the
            starter database (recommended before you sell anything).</label>
        </div>
        <button type="submit" class="btn btn-primary btn-lg">Create my administrator account</button>
      </form>

      <p class="auth-footer-note">After this page succeeds you will be asked to delete
        <code>setup_admin.php</code>. Nothing here can be undone from the storefront, so keep the password safe.</p>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
