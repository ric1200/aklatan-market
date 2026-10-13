<?php
/** Shared chrome for the management area. Every admin page calls render_admin_start(). */

function admin_nav_groups(): array
{
    return [
        'Overview' => [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'grid', 'url' => 'admin/index.php'],
            ['key' => 'reports', 'label' => 'Reports', 'icon' => 'chart', 'url' => 'admin/reports.php'],
        ],
        'Catalogue' => [
            ['key' => 'products', 'label' => 'Products', 'icon' => 'book', 'url' => 'admin/products.php'],
            ['key' => 'categories', 'label' => 'Categories', 'icon' => 'list', 'url' => 'admin/categories.php'],
            ['key' => 'inventory', 'label' => 'Inventory', 'icon' => 'box', 'url' => 'admin/inventory.php'],
            ['key' => 'discounts', 'label' => 'Discount codes', 'icon' => 'tag', 'url' => 'admin/discount-codes.php'],
        ],
        'Sales' => [
            ['key' => 'orders', 'label' => 'Orders', 'icon' => 'package', 'url' => 'admin/orders.php', 'badge' => 'orders'],
            ['key' => 'payments', 'label' => 'Payments', 'icon' => 'shield', 'url' => 'admin/payments.php', 'badge' => 'payments'],
            ['key' => 'customers', 'label' => 'Customers', 'icon' => 'users', 'url' => 'admin/customers.php'],
        ],
        'Content' => [
            ['key' => 'reviews', 'label' => 'Reviews', 'icon' => 'message', 'url' => 'admin/reviews.php', 'badge' => 'reviews'],
            ['key' => 'messages', 'label' => 'Enquiries', 'icon' => 'mail', 'url' => 'admin/messages.php', 'badge' => 'messages'],
        ],
        'Store' => [
            ['key' => 'settings', 'label' => 'Store settings', 'icon' => 'settings', 'url' => 'admin/settings.php'],
            ['key' => 'audit', 'label' => 'Audit log', 'icon' => 'clipboard', 'url' => 'admin/audit-logs.php'],
            ['key' => 'profile', 'label' => 'My admin account', 'icon' => 'user', 'url' => 'admin/profile.php'],
        ],
    ];
}

/** Counts for the sidebar badges. Cheap aggregate queries, cached per request. */
function admin_badges(): array
{
    static $badges = null;
    if ($badges !== null) { return $badges; }
    $badges = [
        'orders' => (int) fetch_value("SELECT COUNT(*) FROM orders WHERE status = 'pending'", [], 0),
        'payments' => (int) fetch_value("SELECT COUNT(*) FROM payments WHERE status = 'pending_verification'", [], 0),
        'reviews' => (int) fetch_value("SELECT COUNT(*) FROM reviews WHERE status = 'pending'", [], 0),
        'messages' => (int) fetch_value("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'", [], 0),
    ];
    return $badges;
}

function render_admin_start(array $options): void
{
    $user = require_admin();
    $current = (string) ($options['current'] ?? '');
    $title = (string) ($options['title'] ?? 'Dashboard');
    $subtitle = (string) ($options['subtitle'] ?? '');
    $crumbs = $options['crumbs'] ?? [];
    $actions = $options['actions'] ?? '';
    $badges = admin_badges();
    $storeName = setting('store_name', 'Aklatan Market');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · Admin · <?= e($storeName) ?></title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#172B4D">
<link rel="icon" href="<?= e(url('assets/images/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" media="print" onload="this.media='all'">
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>">
</head>
<body class="admin">
<a class="skip-link" href="#admin-main">Skip to management content</a>

<div class="admin-shell">
  <header class="admin-topbar">
    <div class="admin-topbar-inner">
      <button type="button" class="admin-menu-toggle btn btn-quiet btn-sm" data-admin-menu aria-expanded="false" aria-controls="admin-sidebar"
              style="color:#EDE7D9;border-color:rgba(255,255,255,.2)">
        <?= icon('menu', '', 18) ?><span class="sr-only">Toggle management menu</span>
      </button>
      <a class="admin-brand" href="<?= e(url('admin/index.php')) ?>">
        <?= brand_mark(30) ?>
        <span class="admin-brand-text">
          <span class="admin-brand-name"><?= e($storeName) ?></span>
          <span class="admin-brand-sub">Management area</span>
        </span>
      </a>
      <div class="admin-topbar-actions">
        <a href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener">
          <?= icon('external', '', 16) ?> <span class="hide-sm">View store</span>
        </a>
        <a href="<?= e(url('admin/profile.php')) ?>"><?= icon('user', '', 16) ?> <span class="hide-sm"><?= e(user_full_name()) ?></span></a>
        <form action="<?= e(url('logout.php')) ?>" method="post">
          <?= csrf_field() ?>
          <button type="submit"><?= icon('logout', '', 16) ?> <span class="hide-sm">Sign out</span></button>
        </form>
      </div>
    </div>
  </header>

  <div class="admin-body">
    <nav class="admin-sidebar" id="admin-sidebar" aria-label="Management sections">
      <?php foreach (admin_nav_groups() as $group => $items): ?>
        <h2><?= e($group) ?></h2>
        <?php foreach ($items as $item): ?>
          <a href="<?= e(url($item['url'])) ?>" class="<?= $current === $item['key'] ? 'is-current' : '' ?>">
            <?= icon($item['icon'], '', 17) ?>
            <span><?= e($item['label']) ?></span>
            <?php $badgeKey = $item['badge'] ?? ''; ?>
            <?php if ($badgeKey !== '' && !empty($badges[$badgeKey])): ?>
              <span class="badge-count"><?= (int) $badges[$badgeKey] ?></span>
              <span class="sr-only"><?= (int) $badges[$badgeKey] ?> waiting</span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <h2>Session</h2>
      <p class="tiny" style="padding:0 11px;color:var(--muted)">
        Signed in as <?= e(user_full_name()) ?><br><?= e((string) $user['email']) ?><br>
        <?= e(ucfirst((string) $user['role'])) ?> account
      </p>
    </nav>

    <main class="admin-main" id="admin-main">
      <?php if ($crumbs): ?>
        <nav class="admin-crumbs" aria-label="Breadcrumb">
          <?php $last = count($crumbs) - 1; foreach ($crumbs as $i => $crumb): ?>
            <?php if (!empty($crumb['url']) && $i < $last): ?>
              <a href="<?= e(url($crumb['url'])) ?>"><?= e($crumb['label']) ?></a> /
            <?php else: ?>
              <span><?= e($crumb['label']) ?></span>
            <?php endif; ?>
          <?php endforeach; ?>
        </nav>
      <?php endif; ?>
      <div class="admin-head">
        <div>
          <h1><?= e($title) ?></h1>
          <?php if ($subtitle !== ''): ?><p class="admin-sub"><?= e($subtitle) ?></p><?php endif; ?>
        </div>
        <?php if ($actions !== ''): ?><div class="cluster"><?= $actions ?></div><?php endif; ?>
      </div>
      <?= render_flashes() ?>
    <?php
}

function render_admin_end(): void
{
    ?>
    </main>
  </div>
</div>

<!-- Shared modal used for image lightboxes and confirmations. -->
<div class="modal-backdrop" id="proof-modal" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="proof-modal-title">
    <button type="button" class="modal-close" data-modal-close aria-label="Close"><?= icon('close', '', 18) ?></button>
    <h2 id="proof-modal-title">Payment proof</h2>
    <p class="small muted">Submitted by the customer. Compare it with the transaction list before verifying.</p>
    <img id="proof-modal-image" src="" alt="Payment receipt submitted by the customer" style="max-width:100%">
  </div>
</div>

<div id="sr-announcer" class="sr-only" aria-live="polite"></div>
<script src="<?= e(url('assets/js/main.js')) ?>" defer></script>
<script src="<?= e(url('assets/js/admin.js')) ?>" defer></script>
</body>
</html>
<?php
}

/** Small helper for the "are you sure" submit buttons in list tables. */
function admin_confirm(string $message): string
{
    return ' data-confirm="' . e($message) . '"';
}
