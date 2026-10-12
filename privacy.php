<?php
require_once __DIR__ . '/includes/bootstrap.php';
$updated = 'October 2026';
render_header([
    'title' => 'Privacy notice',
    'description' => 'What personal data Aklatan Market collects, why, how long it is kept, and how to ask us to remove it.',
]);
render_breadcrumb([['label' => 'Home', 'url' => 'index.php'], ['label' => 'Privacy notice']]);
?>
<section class="page-hero">
  <div class="container">
    <p class="eyebrow">Your data</p>
    <h1>Privacy notice</h1>
    <p class="lede">Last updated <?= e($updated) ?>. This notice explains, in plain language, what we collect and why.</p>
  </div>
</section>

<div class="container content-layout">
  <div class="prose">
    <p>This notice follows the Philippine Data Privacy Act of 2012 (Republic Act No. 10173). It covers the
      Aklatan Market website and the orders placed through it.</p>

    <h2>Who is responsible for your data</h2>
    <p><?= e(setting('company_legal_name', 'Aklatan Market Books & Media')) ?>,
      <?= e(setting('business_address', 'Quezon City, Metro Manila')) ?>. Privacy questions and requests:
      <a href="mailto:<?= e(setting('dpo_contact', 'privacy@example.com')) ?>"><?= e(setting('dpo_contact', 'privacy@example.com')) ?></a>.</p>

    <h2>What we collect</h2>
    <ul>
      <li><strong>Account details</strong> — your first and last name, e-mail address, optional mobile number and a
        password. Passwords are stored only as a one-way hash created with PHP's <code>password_hash()</code>;
        nobody at the shop can read your password.</li>
      <li><strong>Order and delivery details</strong> — recipient name, contact number, delivery address, city or
        municipality, province, postal code, delivery notes and the items you ordered.</li>
      <li><strong>Payment information</strong> — the payment method you chose, and for manual GCash transfers the
        reference number and, if you upload one, a photo of your receipt. We never collect or store GCash PINs,
        one-time passwords, card numbers or online banking credentials.</li>
      <li><strong>Wishlist, cart and reviews</strong> — the items you saved, your basket contents, and any review
        you submit.</li>
      <li><strong>Messages</strong> — the content of contact form enquiries.</li>
      <li><strong>Technical records</strong> — your IP address, browser user-agent string and timestamps for
        security purposes (failed sign-in attempts, administrative audit trail, and abuse prevention).</li>
    </ul>

    <h2>Why we use it</h2>
    <ul>
      <li>To create and secure your account, and to let you sign in.</li>
      <li>To accept, pack, deliver and support your orders, and to verify manual payments.</li>
      <li>To answer your enquiries and handle returns or refunds.</li>
      <li>To keep the site working and secure — detecting brute-force sign-in attempts and recording administrative
        actions.</li>
    </ul>
    <p>We do <strong>not</strong> sell or rent your personal data, and we do not use it for advertising profiles.</p>

    <h2>Cookies and sessions</h2>
    <p>The site sets one essential cookie, an encrypted-by-randomness session identifier, so that your basket and
      sign-in state survive between pages. It is marked <code>HttpOnly</code> and <code>SameSite=Lax</code>, and is
      sent over HTTPS when your site uses SSL. There are no advertising or third-party tracking cookies. The only
      external request the storefront makes is to Google Fonts for optional typography — the page works with system
      fonts if that request fails.</p>

    <h2>How long we keep it</h2>
    <ul>
      <li>Account and order records: while your account is active, and up to 5 years afterwards where tax and
        accounting rules require us to keep sales records.</li>
      <li>Contact messages: 12 months.</li>
      <li>Sign-in attempt logs: 2 days for security throttling.</li>
      <li>Administrative audit logs: 24 months.</li>
    </ul>

    <h2>Who else sees your data</h2>
    <p>Only the shop team, and only what each role needs. Delivery details (name, address, phone) are shared with
      the courier that carries your parcel. Our hosting provider stores the site and its database on our behalf.
      We disclose information to authorities only when the law requires it.</p>

    <h2>Your rights</h2>
    <p>You may ask to see the personal data we hold about you, correct anything wrong, object to certain uses,
      request a copy in a portable form, or ask us to delete your account. Write to
      <a href="mailto:<?= e(setting('dpo_contact', 'privacy@example.com')) ?>"><?= e(setting('dpo_contact', 'privacy@example.com')) ?></a>
      and we will respond within 15 working days. Note that we may need to keep minimal sales records even after a
      deletion request, as required by law.</p>
    <p>If you are not satisfied with our response you may complain to the National Privacy Commission.</p>

    <h2>Keeping your data safe</h2>
    <p>Passwords are hashed, database queries use prepared statements, forms carry cross-site request forgery
      tokens, and the administrative area is protected by server-side role checks. Uploaded images are validated and
      stored outside the code paths that can execute scripts. No system is perfect, so we also keep the software
      current and limit who can see customer records.</p>

    <h2>Children</h2>
    <p>The shop is intended for adults placing orders. Accounts for children's reading should be created and managed
      by a parent or guardian.</p>

    <h2>Changes</h2>
    <p>If this notice changes in a way that affects you, we will note the new date at the top of this page.</p>
  </div>
</div>
<?php render_footer(); ?>
