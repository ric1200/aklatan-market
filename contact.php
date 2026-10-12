<?php
/** Contact form. Saves to the database — no SMTP or mail server required. */
require_once __DIR__ . '/includes/bootstrap.php';

$errors = [];
$sent = false;
$values = ['name' => '', 'email' => '', 'phone' => '', 'subject' => '', 'message' => ''];

if (is_logged_in()) {
    $values['name'] = user_full_name();
    $values['email'] = (string) (current_user()['email'] ?? '');
    $values['phone'] = (string) (current_user()['phone'] ?? '');
}

if (is_post() && post('action') === 'contact') {
    csrf_guard();
    foreach (array_keys($values) as $key) {
        $values[$key] = truncate(post_raw($key), $key === 'message' ? 3000 : 190);
    }
    // Simple, effective bot trap: the hidden field must stay empty.
    if (post('website') !== '') {
        $errors[] = 'That submission looked automated. Please try again.';
    }
    if ($values['name'] === '') { $errors[] = 'Please tell us your name.'; }
    if (!is_valid_email($values['email'])) { $errors[] = 'Please enter a valid e-mail address so we can reply.'; }
    if ($values['subject'] === '') { $errors[] = 'Please choose or type a subject.'; }
    if (mb_strlen($values['message']) < 10) { $errors[] = 'Please write a message of at least a few words.'; }

    $recent = (int) fetch_value('SELECT COUNT(*) FROM contact_messages WHERE ip_address = :ip AND created_at > (NOW() - INTERVAL 1 HOUR)',
        ['ip' => client_ip()], 0);
    if ($recent >= 5) { $errors[] = 'You have sent several messages in the last hour. Please give us a moment to reply.'; }

    if (!$errors) {
        $id = db_insert('contact_messages', [
            'name' => $values['name'],
            'email' => $values['email'],
            'phone' => $values['phone'] !== '' ? $values['phone'] : null,
            'subject' => $values['subject'],
            'message' => $values['message'],
            'status' => 'new',
            'user_id' => current_user_id(),
            'ip_address' => client_ip(),
        ]);
        audit_log('contact.message_received', 'contact_message', $id, 'Subject: ' . $values['subject']);
        $sent = true;
        $values = ['name' => '', 'email' => '', 'phone' => '', 'subject' => '', 'message' => ''];
    }
}

$subjects = [
    'Order or delivery question',
    'Reserve a title',
    'Bulk or school order',
    'Return or damaged item',
    'Question about a product',
    'Something else',
];

render_header([
    'title' => 'Contact us',
    'description' => 'Message the Aklatan Market team about orders, reservations, bulk orders or anything else.',
]);
render_breadcrumb([['label' => 'Home', 'url' => 'index.php'], ['label' => 'Contact']]);
?>
<div class="container">
  <header class="page-head">
    <p class="eyebrow">We read every message</p>
    <h1>Contact Aklatan Market</h1>
    <p class="lede">Questions about an order, a title you cannot find, or a bulk order for a school or office?
      Send us a note and we will reply during store hours. Your message is saved on this site, so nothing gets lost
      even if e-mail delivery is unavailable.</p>
  </header>

  <div class="contact-layout">
    <div>
      <?php if ($sent): ?>
        <div class="notice notice-success" role="status">
          <?= icon('check-circle', 'notice-icon', 18) ?>
          <div class="notice-body">
            <strong>Thank you — your message is on its way to us.</strong>
            We usually reply within one working day. You can also check
            <a href="<?= e(url('account/orders.php')) ?>">your order history</a> for order-specific updates.
          </div>
        </div>
      <?php endif; ?>

      <?php if ($errors): ?>
        <div class="notice notice-error" role="alert">
          <?= icon('alert', 'notice-icon', 18) ?>
          <div class="notice-body">
            <strong>Please check the form</strong>
            <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
          </div>
        </div>
      <?php endif; ?>

      <div class="form-card">
        <h2>Send us a message</h2>
        <form method="post" action="<?= e(url('contact.php')) ?>" class="form-grid">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="contact">
          <div class="field" aria-hidden="true" style="position:absolute;left:-9999px">
            <label for="website">Website (leave blank)</label>
            <input id="website" type="text" name="website" tabindex="-1" autocomplete="off">
          </div>
          <div class="field-row">
            <div class="field">
              <label for="c-name">Your name</label>
              <input id="c-name" type="text" name="name" required maxlength="120" value="<?= e($values['name']) ?>" autocomplete="name">
            </div>
            <div class="field">
              <label for="c-email">E-mail address</label>
              <input id="c-email" type="email" name="email" required maxlength="190" value="<?= e($values['email']) ?>" autocomplete="email">
            </div>
          </div>
          <div class="field-row">
            <div class="field">
              <label for="c-phone">Mobile number <span class="hint">(optional)</span></label>
              <input id="c-phone" type="tel" name="phone" maxlength="30" value="<?= e($values['phone']) ?>" autocomplete="tel">
            </div>
            <div class="field">
              <label for="c-subject">Subject</label>
              <select id="c-subject" name="subject" required>
                <option value="">Choose a topic…</option>
                <?php foreach ($subjects as $subject): ?>
                  <option value="<?= e($subject) ?>" <?= $values['subject'] === $subject ? 'selected' : '' ?>><?= e($subject) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="field">
            <label for="c-message">Message</label>
            <textarea id="c-message" name="message" required maxlength="3000"
                      placeholder="Include your order number if your question is about an existing order."><?= e($values['message']) ?></textarea>
            <span class="hint">We only use these details to answer your enquiry. See our <a href="<?= e(url('privacy.php')) ?>">privacy notice</a>.</span>
          </div>
          <button type="submit" class="btn btn-primary btn-lg"><?= icon('mail', '', 18) ?> Send message</button>
        </form>
      </div>
    </div>

    <aside class="contact-side">
      <div class="info-card">
        <div class="info-icon"><?= icon('phone', '', 20) ?></div>
        <h3>Store details</h3>
        <ul class="stack small">
          <?php if (setting('store_email')): ?>
            <li><?= icon('mail', '', 15) ?> <a href="mailto:<?= e(setting('store_email')) ?>"><?= e(setting('store_email')) ?></a></li>
          <?php endif; ?>
          <?php if (setting('store_phone')): ?>
            <li><?= icon('phone', '', 15) ?> <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', setting('store_phone'))) ?>"><?= e(setting('store_phone')) ?></a></li>
          <?php endif; ?>
          <?php if (setting('store_address')): ?>
            <li><?= icon('pin', '', 15) ?> <?= e(setting('store_address')) ?></li>
          <?php endif; ?>
          <?php if (setting('store_hours')): ?>
            <li><?= icon('clock', '', 15) ?> <?= e(setting('store_hours')) ?></li>
          <?php endif; ?>
        </ul>
      </div>

      <div class="info-card">
        <div class="info-icon"><?= icon('package', '', 20) ?></div>
        <h3>Order questions</h3>
        <p class="small muted">The fastest route is your order history — every status change is recorded there,
          including manual payment verification.</p>
        <p class="mt-1"><a class="link-arrow" href="<?= e(url('account/orders.php')) ?>">Open my orders <?= icon('arrow-right', '', 14) ?></a></p>
      </div>

      <div class="map-note">
        <?= icon('info', '', 16) ?>
        <p class="mt-1">This shop ships nationwide from Metro Manila. Store pickup is arranged by appointment —
          select “pay and pick up in store” at checkout if it is enabled.</p>
      </div>
    </aside>
  </div>
</div>
<?php render_footer(); ?>
