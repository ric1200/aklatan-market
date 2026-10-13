<?php
/**
 * Aklatan Market — storefront home page.
 * Every shelf on this page is driven by the products table, not by hardcoded cards.
 */
require_once __DIR__ . '/includes/bootstrap.php';

$featured  = catalog_shelf('is_featured', 3);
$bestsellers = catalog_shelf('is_bestseller', 4);
$newArrivals = catalog_shelf('is_new_arrival', 4);
$staffPicks  = catalog_shelf('is_staff_pick', 4);
$forStudents = catalog_products_for_students(4);
$media       = catalog_media(4);
$categories  = catalog_categories();
$lowStockThreshold = setting_int('low_stock_threshold', 5);

$catalogCount = (int) fetch_value('SELECT COUNT(*) FROM products WHERE is_active = 1', [], 0);

render_header([
    'title' => '',
    'description' => setting('meta_description', 'Aklatan Market — books, manga, music and movies delivered across the Philippines.'),
    'active' => 'home',
]);
?>

<section class="hero">
  <div class="container hero-inner">
    <div class="hero-copy">
      <p class="eyebrow">Independent bookshop since day one</p>
      <h1>Stories, knowledge and discoveries — <em>in one place</em>.</h1>
      <p class="lede">
        Aklatan Market gathers Filipino literature, academic titles, manga, music and film into one
        friendly shop. Browse <?= number_format($catalogCount) ?> hand-picked titles, pay by cash on
        delivery or GCash, and we will pack them for you.
      </p>
      <div class="hero-actions">
        <a class="btn btn-primary btn-lg" href="<?= e(url('products.php')) ?>">
          <?= icon('book-open', '', 18) ?> Explore books
        </a>
        <a class="btn btn-ghost btn-lg" href="<?= e(url('products.php?category=music-cds-vinyl')) ?>">
          <?= icon('music', '', 18) ?> Browse media
        </a>
      </div>
      <div class="hero-proof">
        <div><strong><?= number_format($catalogCount) ?></strong><span>titles in stock</span></div>
        <div><strong><?= e(money(setting_float('free_shipping_threshold', 1500))) ?></strong><span>and up ships free</span></div>
        <div><strong>3–7 days</strong><span>nationwide delivery</span></div>
      </div>
    </div>

    <?php if ($featured): ?>
      <div class="hero-collage" aria-label="Featured titles">
        <?php foreach (array_slice($featured, 0, 3) as $hero): ?>
          <a href="<?= e(product_url($hero)) ?>">
            <img src="<?= e(product_image_url($hero)) ?>" alt="<?= e($hero['name']) ?> cover" width="400" height="600">
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="trust-strip" aria-label="Why shop with us">
  <div class="container trust-grid">
    <div class="trust-item">
      <?= icon('truck', '', 22) ?>
      <div><strong>Free shipping over <?= e(money(setting_float('free_shipping_threshold', 1500))) ?></strong>
        <span class="muted">Flat <?= e(money(setting_float('shipping_fee', 99))) ?> below that, anywhere in the Philippines.</span></div>
    </div>
    <div class="trust-item">
      <?= icon('shield', '', 22) ?>
      <div><strong>Cash on delivery or GCash</strong>
        <span class="muted">No card details needed. Manual GCash payments are checked by a real person.</span></div>
    </div>
    <div class="trust-item">
      <?= icon('package', '', 22) ?>
      <div><strong>Packed like books should be</strong>
        <span class="muted">Corner-protected and bubble-wrapped, with a printed receipt inside.</span></div>
    </div>
  </div>
</section>

<section class="section" aria-labelledby="browse-categories">
  <div class="container">
    <div class="section-title">
      <div>
        <p class="eyebrow">Shelves</p>
        <h2 id="browse-categories">Browse by category</h2>
        <p>Ten shelves, from Filipino classics to vinyl records.</p>
      </div>
      <a class="link-arrow" href="<?= e(url('products.php')) ?>">See everything <?= icon('arrow-right', '', 15) ?></a>
    </div>
    <div class="cat-grid">
      <?php foreach ($categories as $cat): ?>
        <a class="cat-card" data-accent="<?= e($cat['accent']) ?>" href="<?= e(category_url($cat)) ?>">
          <span class="cat-icon"><?= icon($cat['icon'] ?: 'book', '', 22) ?></span>
          <span class="cat-name"><?= e($cat['name']) ?></span>
          <span class="cat-meta"><?= (int) $cat['product_count'] ?> <?= (int) $cat['product_count'] === 1 ? 'title' : 'titles' ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($bestsellers): ?>
<section class="shelf shelf-alt" aria-labelledby="shelf-bestsellers">
  <div class="container">
    <div class="section-title">
      <div>
        <p class="eyebrow">Reader favourites</p>
        <h2 id="shelf-bestsellers">Bestsellers</h2>
        <p>The titles our customers keep coming back for.</p>
      </div>
      <a class="link-arrow" href="<?= e(url('products.php?sort=popular')) ?>">All bestsellers <?= icon('arrow-right', '', 15) ?></a>
    </div>
    <div class="product-grid product-grid--wide">
      <?php foreach ($bestsellers as $product) { render_product_card($product); } ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($newArrivals): ?>
<section class="shelf" aria-labelledby="shelf-new">
  <div class="container">
    <div class="section-title">
      <div>
        <p class="eyebrow">Fresh off the delivery van</p>
        <h2 id="shelf-new">New arrivals</h2>
        <p>Just unpacked and shelved this month.</p>
      </div>
      <a class="link-arrow" href="<?= e(url('products.php?sort=newest')) ?>">Newest first <?= icon('arrow-right', '', 15) ?></a>
    </div>
    <div class="product-grid product-grid--wide">
      <?php foreach ($newArrivals as $product) { render_product_card($product); } ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($staffPicks): ?>
<section class="shelf shelf-alt" aria-labelledby="shelf-staff">
  <div class="container">
    <div class="section-title">
      <div>
        <p class="eyebrow">From behind the counter</p>
        <h2 id="shelf-staff">Staff picks</h2>
        <p>Titles the Aklatan Market team argues about the most.</p>
      </div>
      <a class="link-arrow" href="<?= e(url('search.php?featured=staff')) ?>">More staff picks <?= icon('arrow-right', '', 15) ?></a>
    </div>
    <div class="product-grid product-grid--wide">
      <?php foreach ($staffPicks as $product) { render_product_card($product); } ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($forStudents): ?>
<section class="shelf" aria-labelledby="shelf-students">
  <div class="container">
    <div class="section-title">
      <div>
        <p class="eyebrow">Back to school</p>
        <h2 id="shelf-students">Books for students</h2>
        <p>Textbooks, reviewers and readings for senior high and college.</p>
      </div>
      <a class="link-arrow" href="<?= e(url('category.php?slug=academic-reference')) ?>">Academic shelf <?= icon('arrow-right', '', 15) ?></a>
    </div>
    <div class="product-grid product-grid--wide">
      <?php foreach ($forStudents as $product) { render_product_card($product); } ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($media): ?>
<section class="shelf shelf-alt" aria-labelledby="shelf-media">
  <div class="container">
    <div class="section-title">
      <div>
        <p class="eyebrow">Listen and watch</p>
        <h2 id="shelf-media">Explore media</h2>
        <p>Vinyl, CDs, magazines and restored Filipino films on DVD.</p>
      </div>
      <a class="link-arrow" href="<?= e(url('category.php?slug=movies-dvds')) ?>">Movies &amp; DVDs <?= icon('arrow-right', '', 15) ?></a>
    </div>
    <div class="product-grid product-grid--wide">
      <?php foreach ($media as $product) { render_product_card($product); } ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="container">
    <div class="info-grid">
      <div class="info-card">
        <div class="info-icon"><?= icon('truck', '', 20) ?></div>
        <h3>Delivery across the Philippines</h3>
        <p class="muted">Metro Manila orders usually arrive in 2–3 working days; provincial addresses in 4–7.
          Free shipping applies automatically once your basket reaches <?= e(money(setting_float('free_shipping_threshold', 1500))) ?>.</p>
        <p class="mt-1"><a class="link-arrow" href="<?= e(url('shipping-returns.php')) ?>">Shipping and returns <?= icon('arrow-right', '', 14) ?></a></p>
      </div>
      <div class="info-card">
        <div class="info-icon"><?= icon('tag', '', 20) ?></div>
        <h3>Pay the way that suits you</h3>
        <p class="muted">Choose cash on delivery, a manual GCash transfer, or pay at the counter if you
          selected store pickup. Nothing is charged automatically.</p>
        <p class="mt-1"><a class="link-arrow" href="<?= e(url('shipping-returns.php#payments')) ?>">Payment options <?= icon('arrow-right', '', 14) ?></a></p>
      </div>
      <div class="info-card">
        <div class="info-icon"><?= icon('message', '', 20) ?></div>
        <h3>Ask a bookseller</h3>
        <p class="muted">Looking for a title we do not list, or ordering for a whole class? Send us a note
          and a human being will reply.</p>
        <p class="mt-1"><a class="link-arrow" href="<?= e(url('contact.php')) ?>">Contact the store <?= icon('arrow-right', '', 14) ?></a></p>
      </div>
    </div>
  </div>
</section>

<?php render_footer(); ?>
