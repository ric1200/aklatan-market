<?php
/** Customer registration. */
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) { redirect('account/index.php'); }

$errors = [];
$values = ['first_name' => '', 'last_name' => '', 'email' => '', 'phone' => ''];

if (is_post() && post('action') === 'register') {
    csrf_guard();
    $values = [
        'first_name' => truncate(post_raw('first_name'), 60),
        'last_name' => truncate(post_raw('last_name'), 60),
        'email' => truncate(post_raw('email'), 190),
        'phone' => truncate(post_raw('phone'), 30),
    ];
    $input = $values + [
        'password' => (string) post_raw('password'),
        'password_confirm' => (string) post_raw('password_confirm'),
        'agree' => checkbox_post('agree'),
    ];

    [$ok, $message, $newId] = register_customer($input);
    if ($ok && $newId) {
        $user = fetch_one('SELECT * FROM users WHERE id = :id', ['id' => $newId]);
        complete_login($user);
        flash_success('Welcome to Aklatan Market, ' . e($values['first_name']) . '! Your account is ready.');
        redirect(safe_next_url(get_param('next'), 'account/index.php'));
    }
    $errors[] = $message;
}

render_header([
    'title' => 'Create an account',
    'description' => 'Create a free Aklatan Market account to track orders, save titles and check out faster.',
    'robots' => 'noindex, follow',
]);
render_breadcrumb([['label' => 'Home', 'url' => 'index.php'], ['label' => 'Create an account']]);
?>
<div class="container auth-shell">
  <div class="auth-card">
    <p class="eyebrow">Join the shop</p>
    <h1>Create your account</h1>
    <p class="muted">It takes a minute and it makes checkout much faster next time.</p>

    <?php if ($errors): ?>
      <div class="notice notice-error" role="alert">
        <?= icon('alert', 'notice-icon', 18) ?>
        <div class="notice-body">
          <strong>We could not create your account</strong>
          <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('register.php' . (get_param('next') !== '' ? '?next=' . urlencode(get_param('next')) : ''))) ?>" class="form-grid mt-2">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="register">
      <div class="field-row">
        <div class="field">
          <label for="first_name">First name</label>
          <input id="first_name" type="text" name="first_name" required maxlength="60" autocomplete="given-name" value="<?= e($values['first_name']) ?>">
        </div>
        <div class="field">
          <label for="last_name">Last name</label>
          <input id="last_name" type="text" name="last_name" required maxlength="60" autocomplete="family-name" value="<?= e($values['last_name']) ?>">
        </div>
      </div>
      <div class="field">
        <label for="email">E-mail address</label>
        <input id="email" type="email" name="email" required maxlength="190" autocomplete="email" value="<?= e($values['email']) ?>">
        <span class="hint">We use this to sign you in and to reference your orders.</span>
      </div>
      <div class="field">
        <label for="phone">Mobile number <span class="hint">(optional)</span></label>
        <input id="phone" type="tel" name="phone" maxlength="30" autocomplete="tel" placeholder="09171234567" value="<?= e($values['phone']) ?>">
        <span class="hint">Used by the courier for delivery updates. You can add it later.</span>
      </div>
      <div class="field-row">
        <div class="field">
          <label for="password">Password</label>
          <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password">
          <span class="hint">At least 8 characters. A short phrase beats a single word.</span>
        </div>
        <div class="field">
          <label for="password_confirm">Confirm password</label>
          <input id="password_confirm" type="password" name="password_confirm" required minlength="8" autocomplete="new-password">
        </div>
      </div>
      <div class="check">
        <input id="agree" type="checkbox" name="agree" value="1" required>
        <label for="agree">I have read the <a href="<?= e(url('privacy.php')) ?>" target="_blank" rel="noopener">privacy notice</a>
          and agree to Aklatan Market keeping the details needed to process my orders.</label>
      </div>
      <button type="submit" class="btn btn-primary btn-lg">Create my account</button>
    </form>
    <p class="auth-footer-note">Already have an account?
      <a href="<?= e(url('login.php' . (get_param('next') !== '' ? '?next=' . urlencode(get_param('next')) : ''))) ?>">Sign in instead</a>.</p>
  </div>

  <aside class="auth-aside">
    <h2>Why create an account?</h2>
    <ul>
      <li><?= icon('package', '', 18) ?><span>Follow every order from pending to delivered, including manual payment verification.</span></li>
      <li><?= icon('heart', '', 18) ?><span>Keep a wishlist that stays with you on any device.</span></li>
      <li><?= icon('truck', '', 18) ?><span>Save delivery addresses so checkout takes seconds.</span></li>
      <li><?= icon('message', '', 18) ?><span>Review the titles you have received.</span></li>
    </ul>
    <p class="demo-note">New accounts can shop immediately. Checkout requires an account so that only you can see
      your orders — we never expose one customer's order to another.</p>
  </aside>
</div>
<?php render_footer(); ?>
