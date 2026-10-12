<?php
/** Shared storefront footer + closing markup. */
function render_footer(): void
{
    $storeName = setting('store_name', 'Aklatan Market');
    $categories = function_exists('catalog_categories') ? array_slice(catalog_categories(), 0, 6) : [];
    ?>
</main>

<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-brand">
      <a class="brand brand-footer" href="<?= e(url('index.php')) ?>">
        <?= brand_mark(32) ?>
        <span class="brand-text"><span class="brand-name"><?= e($storeName) ?></span></span>
      </a>
      <p class="footer-blurb"><?= e(setting('tagline', 'Stories, knowledge, and discoveries in one place.')) ?></p>
      <p class="footer-blurb"><?= e(setting('footer_note', 'A small independent bookshop for readers, students and collectors across the Philippines.')) ?></p>
    </div>

    <nav class="footer-col" aria-label="Shop">
      <h2>Shop</h2>
      <ul>
        <li><a href="<?= e(url('products.php')) ?>">All products</a></li>
        <?php foreach ($categories as $cat): ?>
          <li><a href="<?= e(url('category.php?slug=' . urlencode($cat['slug']))) ?>"><?= e($cat['name']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <nav class="footer-col" aria-label="Your account">
      <h2>Your account</h2>
      <ul>
        <li><a href="<?= e(url('account/index.php')) ?>">Account overview</a></li>
        <li><a href="<?= e(url('account/orders.php')) ?>">Order history</a></li>
        <li><a href="<?= e(url('wishlist.php')) ?>">Wishlist</a></li>
        <li><a href="<?= e(url('cart.php')) ?>">Shopping cart</a></li>
        <li><a href="<?= e(url('login.php')) ?>">Sign in</a></li>
        <li><a href="<?= e(url('register.php')) ?>">Create an account</a></li>
      </ul>
    </nav>

    <nav class="footer-col" aria-label="Help and policies">
      <h2>Help &amp; policies</h2>
      <ul>
        <li><a href="<?= e(url('shipping-returns.php')) ?>">Shipping &amp; returns</a></li>
        <li><a href="<?= e(url('contact.php')) ?>">Contact us</a></li>
        <li><a href="<?= e(url('about.php')) ?>">About the store</a></li>
        <li><a href="<?= e(url('privacy.php')) ?>">Privacy notice</a></li>
        <li><a href="<?= e(url('shipping-returns.php#payments')) ?>">Payment options</a></li>
      </ul>
    </nav>

    <div class="footer-col footer-contact">
      <h2>Visit &amp; contact</h2>
      <ul class="footer-contact-list">
        <?php if (setting('store_email', '') !== ''): ?>
          <li><?= icon('mail', '', 16) ?><a href="mailto:<?= e(setting('store_email')) ?>"><?= e(setting('store_email')) ?></a></li>
        <?php endif; ?>
        <?php if (setting('store_phone', '') !== ''): ?>
          <li><?= icon('phone', '', 16) ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', setting('store_phone'))) ?>"><?= e(setting('store_phone')) ?></a></li>
        <?php endif; ?>
        <?php if (setting('store_address', '') !== ''): ?>
          <li><?= icon('pin', '', 16) ?><span><?= e(setting('store_address')) ?></span></li>
        <?php endif; ?>
        <?php if (setting('store_hours', '') !== ''): ?>
          <li><?= icon('clock', '', 16) ?><span><?= e(setting('store_hours')) ?></span></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>

  <div class="container footer-bottom">
    <p>&copy; <?= date('Y') ?> <?= e($storeName) ?>. All rights reserved.</p>
    <p class="footer-fine">
      Prices are in Philippine pesos (PHP) and include applicable taxes unless stated otherwise.
      Sample catalogue data ships with this store as a demonstration — confirm your own inventory before selling.
    </p>
    <p class="footer-fine">
      <a href="<?= e(url('privacy.php')) ?>">Privacy notice</a> ·
      <a href="<?= e(url('shipping-returns.php')) ?>">Shipping &amp; returns</a> ·
      <a href="<?= e(url('contact.php')) ?>">Contact</a>
    </p>
  </div>
</footer>
<script src="<?= e(url('assets/js/main.js')) ?>" defer></script>
</body>
</html>
<?php
}
