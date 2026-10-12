<?php
/** Shared chrome for every page inside /account. */

function account_nav_items(): array
{
    return [
        ['key' => 'overview', 'label' => 'Overview', 'icon' => 'grid', 'url' => 'account/index.php'],
        ['key' => 'orders', 'label' => 'My orders', 'icon' => 'package', 'url' => 'account/orders.php'],
        ['key' => 'wishlist', 'label' => 'Wishlist', 'icon' => 'heart', 'url' => 'wishlist.php'],
        ['key' => 'reviews', 'label' => 'My reviews', 'icon' => 'message', 'url' => 'account/reviews.php'],
        ['key' => 'addresses', 'label' => 'Addresses', 'icon' => 'pin', 'url' => 'account/addresses.php'],
        ['key' => 'profile', 'label' => 'Profile & password', 'icon' => 'user', 'url' => 'account/profile.php'],
    ];
}

function render_account_start(array $options): void
{
    $current = (string) ($options['current'] ?? '');
    $title = (string) ($options['title'] ?? 'My account');
    $eyebrow = (string) ($options['eyebrow'] ?? 'Your account');
    $description = (string) ($options['description'] ?? '');
    $breadcrumb = $options['breadcrumb'] ?? [['label' => 'Home', 'url' => 'index.php'], ['label' => 'My account', 'url' => 'account/index.php']];

    render_header([
        'title' => $title . ' — my account',
        'robots' => 'noindex, nofollow',
    ]);
    render_breadcrumb($breadcrumb);
    ?>
    <div class="container">
      <header class="page-head">
        <p class="eyebrow"><?= e($eyebrow) ?></p>
        <h1><?= e($title) ?></h1>
        <?php if ($description !== ''): ?><p class="lede"><?= e($description) ?></p><?php endif; ?>
      </header>

      <div class="account-layout">
        <nav class="account-nav" aria-label="Account sections">
          <h2>Signed in as</h2>
          <p class="small strong" style="padding:0 12px 8px"><?= e(user_full_name()) ?></p>
          <?php foreach (account_nav_items() as $item): ?>
            <a href="<?= e(url($item['url'])) ?>" class="<?= $current === $item['key'] ? 'is-current' : '' ?>">
              <?= icon($item['icon'], '', 17) ?> <?= e($item['label']) ?>
            </a>
          <?php endforeach; ?>
          <hr>
          <?php if (is_admin()): ?>
            <a href="<?= e(url('admin/index.php')) ?>"><?= icon('settings', '', 17) ?> Admin dashboard</a>
          <?php endif; ?>
          <form action="<?= e(url('logout.php')) ?>" method="post">
            <?= csrf_field() ?>
            <button type="submit" class="account-panel-logout" style="display:flex;align-items:center;gap:10px;width:100%;padding:10px 12px;border:0;background:transparent;border-radius:8px;font-size:.93rem;font-weight:500;text-align:left;color:var(--danger)">
              <?= icon('logout', '', 17) ?> Sign out
            </button>
          </form>
        </nav>

        <div>
    <?php
}

function render_account_end(): void
{
    ?>
        </div>
      </div>
    </div>
    <?php
    render_footer();
}
