<?php
require_once __DIR__ . '/includes/bootstrap.php';
render_header([
    'title' => 'Shipping & returns',
    'description' => 'Delivery times, shipping fees, payment options, returns and refunds at Aklatan Market.',
]);
render_breadcrumb([['label' => 'Home', 'url' => 'index.php'], ['label' => 'Shipping & returns']]);
$fee = setting_float('shipping_fee', 99.00);
$threshold = setting_float('free_shipping_threshold', 1500.00);
?>
<section class="page-hero">
  <div class="container">
    <p class="eyebrow">Before you order</p>
    <h1>Shipping, payment and returns</h1>
    <p class="lede">Everything about how your books leave the shop and what happens if something goes wrong.</p>
  </div>
</section>

<div class="container content-layout">
  <div class="prose">
    <h2 id="shipping">Delivery times and fees</h2>
    <div class="table-wrap">
      <table class="data">
        <caption class="sr-only">Shipping fees and estimated delivery times by destination</caption>
        <thead>
          <tr><th scope="col">Destination</th><th scope="col">Estimated delivery</th><th scope="col" class="num">Fee</th></tr>
        </thead>
        <tbody>
          <tr><td>Metro Manila</td><td>2–3 working days</td><td class="num"><?= e(money($fee)) ?></td></tr>
          <tr><td>Luzon (outside Metro Manila)</td><td>3–5 working days</td><td class="num"><?= e(money($fee)) ?></td></tr>
          <tr><td>Visayas and Mindanao</td><td>5–7 working days</td><td class="num"><?= e(money($fee)) ?></td></tr>
          <tr><td>Orders of <?= e(money($threshold)) ?> and above</td><td>as above</td><td class="num">Free</td></tr>
          <tr><td>Store pickup</td><td>Ready in 1 working day</td><td class="num">Free</td></tr>
        </tbody>
      </table>
    </div>
    <p>Working days are Monday to Saturday, excluding national holidays. Delivery times are estimates from our
      courier partners and can stretch during peak shopping periods and typhoon season. Orders placed before
      2:00 PM on a working day are usually packed the same day.</p>
    <p>If a courier cannot reach your address after two attempts, the parcel comes back to us. We will contact you
      using the number on the order to arrange redelivery or a refund of the item price.</p>

    <h2 id="payments">Payment options</h2>
    <ul>
      <li><strong>Cash on delivery (COD).</strong> Pay the courier in cash when the parcel arrives. Please prepare
        the exact amount — couriers do not always carry change.</li>
      <li><strong>Manual GCash transfer.</strong> Send the exact order total to the GCash number shown at checkout,
        then enter the reference number from your receipt. A bookseller checks the transfer by hand and marks it
        <em>verified</em> in your order history. This is a manual process and can take up to one working day.</li>
      <?php if (setting_bool('pickup_enabled', true)): ?>
        <li><strong>Pay and pick up in store.</strong> Choose pickup at checkout, wait for the confirmation message,
          then collect and pay at the counter.<?= setting('pickup_address') ? ' Collection point: ' . e(setting('pickup_address')) . '.' : '' ?></li>
      <?php endif; ?>
    </ul>
    <p class="notice notice-warning">
      <?= icon('shield', 'notice-icon', 18) ?>
      <span class="notice-body">We will never ask for your GCash PIN, one-time password, card number or online
        banking login. If anyone messages you claiming to be Aklatan Market and asks for those, it is not us.</span>
    </p>
    <p>No payment is taken automatically on this website. The store does not hold, store or process card data at
      all — every payment is either cash or a manual transfer that a person verifies.</p>

    <h2 id="returns">Returns, replacements and refunds</h2>
    <p>If an item arrives damaged, misprinted or different from what you ordered, message us within
      <strong>7 days</strong> of delivery through the <a href="<?= e(url('contact.php')) ?>">contact form</a> or from
      your <a href="<?= e(url('account/orders.php')) ?>">order history</a>. Include your order number and, if you can,
      a photo of the item and the packaging.</p>
    <ul>
      <li><strong>Our mistake</strong> (wrong title, defective print, damage in transit) — we replace the item or
        refund it in full, and we cover the return shipping.</li>
      <li><strong>Changed your mind</strong> — unopened items in original condition can be returned within 7 days.
        Return shipping is shouldered by the buyer, and the refund covers the item price only, not the original
        shipping fee.</li>
      <li><strong>Not returnable</strong> — opened music or film discs, magazines, and personalised or customised
        stationery, for hygiene and licensing reasons.</li>
      <li><strong>Refund timing</strong> — refunds for cash orders are issued by bank transfer or GCash within
        5 working days of us receiving the returned item. GCash refunds go back to the number you paid from where
        possible.</li>
    </ul>

    <h2>Order changes and cancellations</h2>
    <p>An order can be cancelled while it is still <em>pending</em> or <em>confirmed</em>. Use the cancel button in
      your order history, or message us. Once an order is <em>shipped</em> it is with the courier and can no longer
      be cancelled — but you may still refuse the parcel and we will handle it as a return.</p>

    <h2>Pickup</h2>
    <?php if (setting_bool('pickup_enabled', true)): ?>
      <p><?= e(setting('pickup_instructions', 'Choose pickup, place the order, wait for the confirmation message, then collect your books from the counter.')) ?></p>
      <?php if (setting('pickup_address')): ?>
        <p><strong>Collection point:</strong> <?= e(setting('pickup_address')) ?></p>
      <?php endif; ?>
    <?php else: ?>
      <p>Store pickup is currently switched off. All orders are delivered by courier.</p>
    <?php endif; ?>

    <h2>Questions</h2>
    <p>Still unsure about something? <a href="<?= e(url('contact.php')) ?>">Write to us</a> — we answer every message
      during store hours.</p>
  </div>
</div>
<?php render_footer(); ?>
