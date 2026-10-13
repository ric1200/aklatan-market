<?php
/** Ends the session. POST-only so a link or image cannot sign a customer out. */
require_once __DIR__ . '/includes/bootstrap.php';

if (is_post()) {
    csrf_guard();
    logout_user();
    flash_success('You have been signed out.');
    redirect('index.php');
}

render_header(['title' => 'Sign out', 'robots' => 'noindex, nofollow']);
?>
<div class="container auth-shell">
  <div class="auth-card">
    <h1>Sign out of Aklatan Market?</h1>
    <p class="muted">Your cart is saved to your account, so it will still be here next time.</p>
    <form method="post" action="<?= e(url('logout.php')) ?>" class="cluster mt-2">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-danger"><?= icon('logout', '', 18) ?> Yes, sign me out</button>
      <a class="btn btn-ghost" href="<?= e(url('index.php')) ?>">Stay signed in</a>
    </form>
  </div>
</div>
<?php render_footer(); ?>
