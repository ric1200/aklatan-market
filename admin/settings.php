<?php
/** Store settings — everything the owner can change without touching code. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/admin-header.php';
$admin = require_admin();

/** key => [label, type, hint] */
$fields = [
    'store_name' => ['Store name', 'text', 'Shown in the masthead, footer and page titles.'],
    'tagline' => ['Tagline', 'text', 'The short line under the store name.'],
    'meta_description' => ['Default meta description', 'textarea', 'Used for search engines on pages without their own description.'],
    'announcement_enabled' => ['Show the announcement strip', 'bool', 'A slim bar above the main header.'],
    'announcement_text' => ['Announcement text', 'text', 'For example: Free shipping over ₱1,500.'],
    'announcement_link' => ['Announcement link', 'text', 'A page inside the store, e.g. shipping-returns.php. Blank hides the link.'],
    'announcement_link_label' => ['Announcement link label', 'text', 'Wording of the announcement link.'],
    'footer_note' => ['Footer note', 'textarea', 'A sentence about the shop in the footer.'],
    'store_email' => ['Public e-mail address', 'text', 'Shown on the contact page and in the footer.'],
    'store_phone' => ['Public phone number', 'text', 'Displayed as text; use your real store number.'],
    'store_address' => ['Store address', 'text', 'Shown on the contact page and in the footer.'],
    'store_hours' => ['Opening hours', 'text', 'For example: Monday to Saturday, 9:00 AM to 7:00 PM.'],

    'shipping_fee' => ['Standard shipping fee (₱)', 'number', 'Charged at checkout below the free-shipping threshold.'],
    'free_shipping_threshold' => ['Free shipping from (₱)', 'number', 'Baskets at or above this amount ship free. Use 0 to disable.'],
    'low_stock_threshold' => ['Low-stock warning level', 'number', 'Products at or below this stock level are flagged.'],
    'products_per_page' => ['Products per page', 'number', 'Used by the catalogue and search pages.'],
    'orders_per_page' => ['Orders per page (admin)', 'number', 'Row count in the management order list.'],

    'cod_enabled' => ['Enable cash on delivery', 'bool', 'Customers pay the courier in cash.'],
    'gcash_enabled' => ['Enable manual GCash payments', 'bool', 'Customers send the total and submit a reference number.'],
    'pickup_enabled' => ['Enable store pickup', 'bool', 'Customers collect and pay at the counter.'],
    'gcash_account_name' => ['GCash account name', 'text', 'The name registered on the receiving GCash wallet.'],
    'gcash_account_number' => ['GCash account number', 'text', 'Shown to the customer at checkout. 11 digits, no spaces needed.'],
    'gcash_instructions' => ['Manual payment instructions', 'textarea', 'Explain exactly how to send the payment and what to enter next.'],
    'pickup_address' => ['Pickup address', 'textarea', 'Where customers should collect their orders.'],
    'pickup_instructions' => ['Pickup instructions', 'textarea', 'What the customer should bring and expect.'],
    'payment_note' => ['Payment safety reminder', 'text', 'Reassures customers that you never ask for PINs or OTPs.'],

    'company_legal_name' => ['Legal / trading name', 'text', 'Used in the privacy notice.'],
    'business_address' => ['Registered business address', 'text', 'Used in the privacy notice.'],
    'dpo_contact' => ['Privacy contact e-mail', 'text', 'Where data privacy requests are sent.'],
    'about_intro' => ['About page introduction', 'textarea', 'The opening paragraph of the about page.'],
];

if (is_post() && post('action') === 'save_settings') {
    csrf_guard();
    $changed = [];
    foreach ($fields as $key => $meta) {
        $type = $meta[1];
        $posted = post_raw($key, null);
        if ($type === 'bool') {
            $value = checkbox_post($key) ? '1' : '0';
        } else {
            if ($posted === null) { continue; }
            if ($type === 'number') {
                $value = is_numeric($posted) ? (string) max(0, (float) $posted) : (string) setting_float($key, 0);
                if ($key === 'products_per_page') { $value = (string) max(4, min(48, (int) $value)); }
                if ($key === 'orders_per_page') { $value = (string) max(5, min(100, (int) $value)); }
            } else {
                $value = truncate($posted, $type === 'textarea' ? 3000 : 300);
            }
        }
        if ((string) setting($key, '') !== $value) {
            setting_set($key, $value);
            $changed[] = $meta[0];
        }
    }
    if ($changed) {
        audit_log('settings.updated', 'settings', null, 'Changed: ' . implode(', ', $changed));
        flash_success(count($changed) . ' setting' . (count($changed) === 1 ? '' : 's') . ' updated: ' . implode(', ', $changed) . '.');
    } else {
        flash_info('No changes to save.');
    }
    redirect('admin/settings.php');
}

$sections = [
    'Brand &amp; storefront' => ['store_name', 'tagline', 'meta_description', 'footer_note',
        'announcement_enabled', 'announcement_text', 'announcement_link', 'announcement_link_label'],
    'Contact details' => ['store_email', 'store_phone', 'store_address', 'store_hours'],
    'Shipping &amp; inventory' => ['shipping_fee', 'free_shipping_threshold', 'low_stock_threshold', 'products_per_page', 'orders_per_page'],
    'Payment methods' => ['cod_enabled', 'gcash_enabled', 'pickup_enabled', 'gcash_account_name', 'gcash_account_number',
        'gcash_instructions', 'pickup_address', 'pickup_instructions', 'payment_note'],
    'Legal &amp; about' => ['company_legal_name', 'business_address', 'dpo_contact', 'about_intro'],
];

render_admin_start([
    'current' => 'settings',
    'title' => 'Store settings',
    'subtitle' => 'These values drive the storefront, the checkout and the shipping rules. Every change is written to the audit log.',
    'crumbs' => [['label' => 'Dashboard', 'url' => 'admin/index.php'], ['label' => 'Store settings']],
]);
?>
<form method="post" action="<?= e(url('admin/settings.php')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save_settings">

  <?php foreach ($sections as $sectionTitle => $keys): ?>
    <div class="admin-panel">
      <div class="admin-panel-head"><h2><?= $sectionTitle ?></h2></div>
      <div class="admin-panel-body form-grid">
        <?php foreach ($keys as $key): ?>
          <?php if (!isset($fields[$key])) { continue; } ?>
          <?php [$label, $type, $hint] = $fields[$key]; $value = (string) setting($key, ''); ?>
          <?php if ($type === 'bool'): ?>
            <div class="check">
              <input id="<?= e($key) ?>" type="checkbox" name="<?= e($key) ?>" value="1" <?= $value === '1' ? 'checked' : '' ?>>
              <label for="<?= e($key) ?>"><?= e($label) ?> <span class="hint">— <?= e($hint) ?></span></label>
            </div>
          <?php elseif ($type === 'textarea'): ?>
            <div class="field">
              <label for="<?= e($key) ?>"><?= e($label) ?></label>
              <textarea id="<?= e($key) ?>" name="<?= e($key) ?>" rows="3" maxlength="3000"><?= e($value) ?></textarea>
              <span class="hint"><?= e($hint) ?></span>
            </div>
          <?php else: ?>
            <div class="field">
              <label for="<?= e($key) ?>"><?= e($label) ?></label>
              <input id="<?= e($key) ?>" type="<?= $type === 'number' ? 'number' : 'text' ?>"
                     name="<?= e($key) ?>" <?= $type === 'number' ? 'step="0.01" min="0"' : 'maxlength="300"' ?>
                     value="<?= e($value) ?>">
              <span class="hint"><?= e($hint) ?></span>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="cluster">
    <button type="submit" class="btn btn-primary"><?= icon('check', '', 17) ?> Save all settings</button>
    <a class="btn btn-ghost" href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener">Open the storefront</a>
  </div>
</form>

<div class="admin-panel mt-3">
  <div class="admin-panel-head">
    <h2>Manual payment safety</h2>
    <p class="panel-note">Read this before switching GCash on for real customers.</p>
  </div>
  <div class="admin-panel-body">
    <ul class="prose" style="padding-left:18px;list-style:disc">
      <li>Only the reference number and an optional receipt photo are ever collected. The store never asks for PINs, OTPs or card numbers.</li>
      <li>Nothing is auto-verified. Every GCash payment stays <strong>pending verification</strong> until you compare it with your own GCash transaction list and mark it verified.</li>
      <li>Rejecting a payment requires a reason, which the customer sees in their order history.</li>
      <li>Receipt images are stored in <code>uploads/payments/</code>, which Apache is configured to block; only an administrator can stream them through <code>admin/payment-proof.php</code>.</li>
      <li>Use a dedicated GCash account for the shop if you can, and reconcile at the end of each day while orders are still fresh.</li>
    </ul>
  </div>
</div>
<?php render_admin_end(); ?>
