<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Server error &middot; <?= function_exists('setting') ? e(setting('store_name', 'Aklatan Market')) : 'Aklatan Market' ?></title>
<link rel="stylesheet" href="<?= function_exists('url') ? e(url('assets/css/style.css')) : 'assets/css/style.css' ?>">
</head>
<body class="page-error">
<main class="error-shell" id="main">
  <div class="error-card">
    <p class="error-code" aria-hidden="true">500</p>
    <h1><?= e($safeHeadline ?? 'Something went wrong on our side') ?></h1>
    <p class="lede">The page could not be completed. This is almost always temporary.
      Please try again in a moment, or return to the storefront and continue browsing.</p>
    <?php if (defined('APP_DEBUG') && APP_DEBUG): ?>
      <p class="notice notice-warn">Debug mode is ON. Check <code>storage/error.log</code> for the technical details and turn
        <code>APP_DEBUG</code> back to <code>false</code> afterwards.</p>
    <?php else: ?>
      <p class="muted">Technical details were written to <code>storage/error.log</code> for the site owner.</p>
    <?php endif; ?>
    <p class="error-actions">
      <a class="btn btn-primary" href="<?= function_exists('url') ? e(url('index.php')) : 'index.php' ?>">Back to the storefront</a>
    </p>
  </div>
</main>
</body>
</html>
