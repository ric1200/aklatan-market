<?php
/** Shared storefront header: <head>, announcement strip, masthead, main nav. */

function render_header(array $options = []): void
{
    $storeName = setting('store_name', 'Aklatan Market');
    $title = $options['title'] ?? '';
    $description = $options['description'] ?? setting('meta_description', 'Aklatan Market — ' . setting('tagline', 'Stories, knowledge, and discoveries in one place.'));
    $bodyClass = $options['body_class'] ?? '';
    $robots = $options['robots'] ?? 'index, follow';
    $active = $options['active'] ?? '';
    $fullTitle = $title !== '' ? $title . ' · ' . $storeName : $storeName . ' — ' . setting('tagline', 'Stories, knowledge, and discoveries in one place.');
    $cartCount = function_exists('cart_count') ? cart_count() : 0;
    $wishlistCount = 0;
    if (is_logged_in()) {
        $wishlistCount = (int) fetch_value('SELECT COUNT(*) FROM wishlists WHERE user_id = :uid', ['uid' => current_user_id()], 0);
    }
    $categories = function_exists('catalog_categories') ? catalog_categories() : [];
    $navCategories = array_slice($categories, 0, 7);
    ?>
<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($fullTitle) ?></title>
<meta name="description" content="<?= e(truncate($description, 160)) ?>">
<meta name="robots" content="<?= e($robots) ?>">
<meta name="theme-color" content="#172B4D">
<meta name="generator" content="Aklatan Market <?= e(AKLATAN_VERSION) ?>">
<link rel="canonical" href="<?= e(absolute_url(ltrim(current_relative_url(), '/'))) ?>">
<link rel="icon" href="<?= e(url('assets/images/favicon.svg')) ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= e(url('assets/images/favicon.svg')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap"></noscript>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
<script>document.documentElement.className = document.documentElement.className.replace('no-js','has-js');</script>
<?php if (!empty($options['head_extra'])) { echo $options['head_extra']; } ?>
</head>
<body class="site <?= e($bodyClass) ?>">
<a class="skip-link" href="#main">Skip to main content</a>

<?php if (setting_bool('announcement_enabled', true) && setting('announcement_text', '') !== ''): ?>
  <div class="announcement" role="region" aria-label="Store announcement">
    <div class="container announcement-inner">
      <?= icon('sparkle', 'announcement-icon', 16) ?>
      <p><?= e(setting('announcement_text')) ?></p>
      <?php if (setting('announcement_link', '') !== ''): ?>
        <a href="<?= e(url(setting('announcement_link'))) ?>"><?= e(setting('announcement_link_label', 'Learn more')) ?></a>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<header class="masthead">
  <div class="container masthead-inner">
    <a class="brand" href="<?= e(url('index.php')) ?>" aria-label="<?= e($storeName) ?> home">
      <?= brand_mark(34) ?>
      <span class="brand-text">
        <span class="brand-name"><?= e($storeName) ?></span>
        <span class="brand-tagline"><?= e(setting('tagline', 'Stories, knowledge, and discoveries')) ?></span>
      </span>
    </a>

    <form class="search-form" action="<?= e(url('search.php')) ?>" method="get" role="search">
      <label class="sr-only" for="site-search">Search books, music and movies</label>
      <div class="search-field">
        <?= icon('search', 'search-icon', 18) ?>
        <input id="site-search" type="search" name="q" placeholder="Search titles, authors, ISBN…"
               value="<?= e($options['search_value'] ?? get_param('q')) ?>" autocomplete="off" maxlength="120">
      </div>
      <button class="btn btn-primary search-submit" type="submit">Search</button>
    </form>

    <div class="masthead-actions">
      <a class="icon-link" href="<?= e(url('wishlist.php')) ?>">
        <?= icon('heart', '', 21) ?>
        <span class="icon-link-label">Wishlist</span>
        <?php if ($wishlistCount > 0): ?>
          <span class="count-badge" aria-hidden="true"><?= (int) $wishlistCount ?></span>
          <span class="sr-only"><?= (int) $wishlistCount ?> items</span>
        <?php endif; ?>
      </a>
      <a class="icon-link" href="<?= e(url('cart.php')) ?>">
        <?= icon('cart', '', 21) ?>
        <span class="icon-link-label">Cart</span>
        <span class="count-badge <?= $cartCount > 0 ? '' : 'is-empty' ?>" aria-hidden="true"><?= (int) $cartCount ?></span>
        <span class="sr-only"><?= (int) $cartCount ?> items in cart</span>
      </a>
      <div class="account-menu" data-account-menu>
        <?php if (is_logged_in()): ?>
          <button type="button" class="icon-link account-toggle" aria-expanded="false" aria-controls="account-panel">
            <?= icon('user', '', 21) ?>
            <span class="icon-link-label"><?= e(explode(' ', trim(user_full_name()))[0]) ?></span>
            <?= icon('chevron-down', 'chev', 14) ?>
          </button>
          <div class="account-panel" id="account-panel" hidden>
            <p class="account-panel-name">Signed in as<br><strong><?= e(user_full_name()) ?></strong></p>
            <a href="<?= e(url('account/index.php')) ?>"><?= icon('grid', '', 16) ?> My account</a>
            <a href="<?= e(url('account/orders.php')) ?>"><?= icon('package', '', 16) ?> My orders</a>
            <a href="<?= e(url('wishlist.php')) ?>"><?= icon('heart', '', 16) ?> Wishlist</a>
            <?php if (is_admin()): ?>
              <a href="<?= e(url('admin/index.php')) ?>"><?= icon('settings', '', 16) ?> Admin dashboard</a>
            <?php endif; ?>
            <form action="<?= e(url('logout.php')) ?>" method="post">
              <?= csrf_field() ?>
              <button type="submit" class="account-panel-logout"><?= icon('logout', '', 16) ?> Sign out</button>
            </form>
          </div>
        <?php else: ?>
          <a class="icon-link" href="<?= e(url('login.php')) ?>">
            <?= icon('user', '', 21) ?>
            <span class="icon-link-label">Sign in</span>
          </a>
        <?php endif; ?>
      </div>
    </div>

    <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="mobile-nav" data-nav-toggle>
      <?= icon('menu', 'nav-toggle-open', 22) ?>
      <?= icon('close', 'nav-toggle-close', 22) ?>
      <span class="sr-only">Browse categories</span>
    </button>
  </div>

  <nav class="main-nav" aria-label="Product categories" id="mobile-nav" data-mobile-nav>
    <div class="container main-nav-inner">
      <ul class="nav-list">
        <li><a class="<?= $active === 'home' ? 'is-active' : '' ?>" href="<?= e(url('index.php')) ?>"><?= icon('home', '', 16) ?> Home</a></li>
        <li><a class="<?= $active === 'products' ? 'is-active' : '' ?>" href="<?= e(url('products.php')) ?>"><?= icon('grid', '', 16) ?> All products</a></li>
        <?php foreach ($navCategories as $cat): ?>
          <li>
            <a href="<?= e(url('category.php?slug=' . urlencode($cat['slug']))) ?>">
              <?= icon($cat['icon'] ?: 'book', '', 16) ?> <?= e($cat['name']) ?>
            </a>
          </li>
        <?php endforeach; ?>
        <li><a class="<?= $active === 'new' ? 'is-active' : '' ?>" href="<?= e(url('products.php?sort=newest')) ?>"><?= icon('sparkle', '', 16) ?> New arrivals</a></li>
        <li><a href="<?= e(url('about.php')) ?>"><?= icon('newspaper', '', 16) ?> About</a></li>
      </ul>
      <?php if (count($categories) > count($navCategories)): ?>
        <a class="nav-more" href="<?= e(url('products.php')) ?>">All <?= (int) count($categories) ?> categories <?= icon('arrow-right', '', 14) ?></a>
      <?php endif; ?>
    </div>
  </nav>
</header>

<main id="main" class="site-main">
  <div class="container">
    <?= render_flashes() ?>
  </div>
<?php
}
