<?php
/** Customer and administrator sign-in. */
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/index.php' : 'account/index.php');
}

$errors = [];
$email = '';
$next = get_param('next');

if (is_post() && post('action') === 'login') {
    csrf_guard();
    $email = truncate(post_raw('email'), 190);
    $password = (string) post_raw('password');
    $next = post('next');

    if ($email === '' || $password === '') {
        $errors[] = 'Please enter both your e-mail address and your password.';
    } else {
        [$ok, $message] = attempt_login($email, $password);
        if ($ok) {
            $target = is_admin() ? 'admin/index.php' : 'account/index.php';
            flash_success($message);
            redirect(safe_next_url($next, $target));
        }
        $errors[] = $message;
    }
}

render_header([
    'title' => 'Sign in',
    'description' => 'Sign in to your Aklatan Market account.',
    'robots' => 'noindex, nofollow',
]);
render_breadcrumb([['label' => 'Home', 'url' => 'index.php'], ['label' => 'Sign in']]);
?>
<div class="container auth-shell">
  <div class="auth-card">
    <p class="eyebrow">Welcome back</p>
    <h1>Sign in</h1>
    <p class="muted">Use the e-mail address you registered with.</p>

    <?php if ($errors): ?>
      <div class="notice notice-error" role="alert">
        <?= icon('alert', 'notice-icon', 18) ?>
        <div class="notice-body">
          <strong>Sign-in failed</strong>
          <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
          <p class="tiny mt-1">Forgotten your password? Ask the shop to reset it for you — password reset by e-mail
            needs a working mail server, which free hosting often does not provide.</p>
        </div>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('login.php')) ?>" class="form-grid mt-2" autocomplete="on">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="login">
      <input type="hidden" name="next" value="<?= e($next) ?>">
      <div class="field">
        <label for="email">E-mail address</label>
        <input id="email" type="email" name="email" required maxlength="190" autocomplete="username"
               value="<?= e($email) ?>" autofocus>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn btn-primary btn-lg">Sign in</button>
    </form>

    <p class="auth-footer-note">
      New to Aklatan Market?
      <a href="<?= e(url('register.php' . ($next !== '' ? '?next=' . urlencode($next) : ''))) ?>">Create an account</a>.
    </p>
  </div>

  <aside class="auth-aside">
    <h2>Two kinds of account</h2>
    <ul>
      <li><?= icon('user', '', 18) ?><span><strong>Customers</strong> see their own cart, wishlist, profile and orders — and nothing else.</span></li>
      <li><?= icon('shield', '', 18) ?><span><strong>Administrators</strong> are signed in with an account whose role is set to admin in the database. The management area re-checks that role on every single page.</span></li>
    </ul>
    <p class="demo-note">Failed attempts are recorded and rate-limited: after <?= (int) LOGIN_MAX_ATTEMPTS ?> wrong
      tries, sign-in pauses for <?= (int) LOGIN_LOCKOUT_MINUTES ?> minutes. If you are testing the sample data,
      see <code>INSTALLATION_GUIDE.txt</code> for the demo customer password.</p>
  </aside>
</div>
<?php render_footer(); ?>
