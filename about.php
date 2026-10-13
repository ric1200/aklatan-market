<?php
require_once __DIR__ . '/includes/bootstrap.php';
render_header([
    'title' => 'About the store',
    'description' => 'Who we are, how we choose titles, and how Aklatan Market packs and ships your books.',
]);
render_breadcrumb([['label' => 'Home', 'url' => 'index.php'], ['label' => 'About']]);
?>
<section class="page-hero">
  <div class="container">
    <p class="eyebrow">Our story</p>
    <h1>A small shop for people who read</h1>
    <p class="lede"><?= e(setting('about_intro', 'Aklatan Market began as a stall of second-hand paperbacks.')) ?></p>
  </div>
</section>

<div class="container content-layout">
  <div class="prose">
    <h2>How we got here</h2>
    <p>Aklatan Market started with two shelves of second-hand paperbacks in a rented stall, a hand-written
      inventory notebook, and a folding table for the rainy season. Readers kept asking for titles we did not have,
      so we started ordering them — first from local distributors, then from small presses, then directly from
      authors printing their own runs.</p>
    <p>Today the shop is online, but the idea has not changed. We would rather carry forty titles we can recommend
      honestly than forty thousand we cannot describe.</p>

    <h2>What we sell</h2>
    <ul>
      <li><strong>Fiction and novels</strong> — Filipino and translated literary fiction, short story collections and popular reads.</li>
      <li><strong>Academic and reference</strong> — textbooks, reviewers and worktexts for senior high school, college and licensure exams.</li>
      <li><strong>Filipino books and literature</strong> — classics, poetry and contemporary writing in Filipino and other Philippine languages.</li>
      <li><strong>Manga and comics</strong> — local komiks, graphic novel anthologies and translated manga volumes.</li>
      <li><strong>Children's books</strong> — picture books and early readers in English and Filipino.</li>
      <li><strong>Magazines</strong> — food, travel, design and hobby titles, including back numbers.</li>
      <li><strong>Music and film</strong> — CDs, vinyl records, restored Filipino classics and short film collections on DVD.</li>
      <li><strong>Stationery and collectibles</strong> — notebooks, bookmarks, bookends and reading accessories.</li>
    </ul>

    <h2>How we choose what to stock</h2>
    <p>Every title on the shelf is read or used by someone on the team before we list it. For academic titles we
      check that the edition still matches current course outlines. For children's books we read them aloud once —
      if it does not survive a read-aloud, it does not go on the shelf.</p>

    <h2>Packing and delivery</h2>
    <p>Books travel in a rigid mailer with corner protection, wrapped in a waterproof sleeve for the rainy season.
      Orders leave the shop on the next working day and reach Metro Manila addresses in 2–3 working days and
      provincial addresses in 4–7 working days. Free shipping applies once your basket reaches
      <?= e(money(setting_float('free_shipping_threshold', 1500))) ?>.</p>

    <h2>The fine print</h2>
    <p><?= e(setting('company_legal_name', 'Aklatan Market Books & Media')) ?> trades from
      <?= e(setting('business_address', 'Quezon City, Metro Manila')) ?>. Operational questions go to
      <a href="mailto:<?= e(setting('store_email', 'hello@example.com')) ?>"><?= e(setting('store_email', 'hello@example.com')) ?></a>,
      and privacy requests to <a href="mailto:<?= e(setting('dpo_contact', 'privacy@example.com')) ?>"><?= e(setting('dpo_contact', 'privacy@example.com')) ?></a>.
      See the full <a href="<?= e(url('privacy.php')) ?>">privacy notice</a>.</p>

    <p class="notice notice-warning">
      <?= icon('info', 'notice-icon', 18) ?>
      <span class="notice-body">This storefront ships with sample catalogue data for demonstration. Replace the
        titles, prices, stock and store details with your own before selling to the public.</span>
    </p>
  </div>

  <div>
    <img src="<?= e(url('assets/images/reading-nook.svg')) ?>" alt="An open book, a cup of coffee and a stack of books on a table" width="520" height="400" style="border-radius:var(--radius-lg);border:1px solid var(--line);background:#fff">
    <div class="info-grid mt-2">
      <div class="info-card">
        <div class="info-icon"><?= icon('book-open', '', 20) ?></div>
        <h3>Curated, not dumped</h3>
        <p class="small muted">Every shelf is reviewed by a person, not an algorithm.</p>
      </div>
      <div class="info-card">
        <div class="info-icon"><?= icon('shield', '', 20) ?></div>
        <h3>Straightforward payment</h3>
        <p class="small muted">Cash on delivery, manual GCash, or pickup. No stored card data.</p>
      </div>
    </div>
  </div>
</div>
<?php render_footer(); ?>
