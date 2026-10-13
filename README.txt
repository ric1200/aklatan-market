================================================================================
 AKLATAN MARKET
 Stories, knowledge, and discoveries in one place.
 A complete online bookstore & media shop in plain PHP + MySQL
================================================================================

 Version     1.0
 Platform    Shared PHP hosting, built and tested for InfinityFree's FREE plan
 Stack       PHP 8.x, MySQL/MariaDB via PDO, HTML5, CSS3, vanilla JavaScript
 Currency    Philippine peso (PHP / the peso sign)
 Timezone    Asia/Manila
 Footprint   143 files, about 2 MB. No Node, no npm, no Composer, no build step,
             no external images, no external API, no cron job, no e-mail server.

 START HERE:  INSTALLATION_GUIDE.txt  walks a beginner through the whole
              deployment in about 45 minutes. This README explains what is
              inside the package and what was tested.


================================================================================
 1. WHAT THIS IS
================================================================================

An online shop for physical books and media aimed at Filipino readers: fiction
and novels, academic and reference books, Filipino books and literature, manga
and comics, children's books, magazines, music on CD and vinyl, movies on DVD,
and book-related stationery and collectibles.

It is a real shop, not a mock-up: a customer can register, search and filter the
catalogue, keep a wishlist, fill a cart, check out with server-side price and
stock validation, pay by cash on delivery, by manual GCash transfer or by
picking the order up in store, leave a review after a completed purchase, and
follow the status of every order. On the other side, the administrator manages
products, categories, stock, orders, manual payments, customers, reviews,
enquiries, discount codes, reports and store settings - with every important
action written to an audit log.

Design: an editorial bookstore aesthetic with a serif display face for headings
and a clean sans-serif body, deep ink blue (#172B4D), warm paper (#FAF7F0),
golden amber (#E5A93D) and muted teal accents, refined borders, soft shadows and
restrained motion that respects prefers-reduced-motion. Every image is an
original SVG drawn for this project - nothing is hot-linked from another site.


================================================================================
 2. REQUIREMENTS
================================================================================

  * PHP 8.0 or newer with the standard PDO MySQL extension (pdo_mysql). PHP 8.4
    is fine.
  * One MySQL or MariaDB database (MySQL 5.7+/8.0 or MariaDB 10.4+).
  * Apache with .htaccess support is recommended, not required. The shop works
    without any rewrite rules.
  * No JavaScript build tools, no database server of your own, no paid service.

  Optional: internet access for Google Fonts. If the fonts cannot be loaded the
  shop falls back to system serif and sans-serif stacks and still works.


================================================================================
 3. QUICK START (5 STEPS)
================================================================================

  1. Create a database, then import  database/aklatan_market.sql  (17 tables,
     47 starter titles, 10 categories, sample customers and orders).
  2. Open  config/config.php  and fill in DB_HOST, DB_NAME, DB_USER, DB_PASS
     (InfinityFree: copy the values from Control Panel -> MySQL Databases; the
     host is NOT "localhost").
  3. Upload every file and folder into the htdocs folder of your site, keeping
     the folder structure exactly as it is here.
  4. Visit  https://your-site/setup_admin.php  and create your administrator
     account (10+ character password). Decide whether to keep the sample data.
  5. Delete  setup_admin.php  from the server, then sign in at login.php and
     open  Admin -> Settings  to set your shipping fee, free-shipping threshold
     and GCash details.

  Full details, screenshots-in-words and troubleshooting: INSTALLATION_GUIDE.txt


================================================================================
 4. FILE AND FOLDER INVENTORY
================================================================================

ROOT - CUSTOMER-FACING PAGES
  index.php               Homepage: hero, announcement strip, category browser,
                          Bestsellers / New arrivals / Staff picks / Books for
                          students / Explore media - all database driven.
  products.php            Full catalogue with filters, sorting and pagination.
  product.php             Product detail: cover, price, discount badge, stock
                          status, add to cart, wishlist, reviews and ratings.
  category.php            One category, with its own filters and sorting.
  search.php              Keyword search plus category, price, availability
                          filters, sorting and pagination.
  cart.php                Cart: update quantity, remove, discount code, shipping
                          estimate, running totals.
  checkout.php            Checkout: server-validated delivery details, payment
                          choice (COD / GCash / pickup), order creation.
  order-success.php       Order confirmation, GCash instructions, reference
                          submission, "what happens next" timeline.
  wishlist.php            Saved titles (signed-in customers).
  login.php register.php logout.php
                          Sign in, create account, sign out (POST + CSRF).
  about.php contact.php shipping-returns.php privacy.php
                          Store information; the contact form stores enquiries
                          in the database so no mail server is needed.
  403.php 404.php 500.php Friendly error pages.
  setup_admin.php         ONE-TIME first administrator creator. Locked as soon
                          as an admin exists. Delete it after use.

account/  - THE CUSTOMER AREA
  index.php               Overview: open orders, recent activity, shortcuts.
  orders.php              Order history with statuses and totals.
  order-details.php       One order: items, addresses, payment state, GCash
                          reference submission/replacement, cancellation.
  profile.php             Name, phone, e-mail and password change.
  addresses.php           Delivery address book (add, edit, set default, delete).
  reviews.php             Reviews the customer has written.

admin/  - THE ADMINISTRATOR AREA (server-checked on every page)
  index.php               Dashboard: orders, pending orders, qualifying sales,
                          active products, low-stock list, recent orders.
  products.php            Product list with search, filters, activate/deactivate
                          and delete (delete keeps historical order lines).
  product-edit.php        Create/edit a product, including validated image
                          upload with a live preview.
  categories.php          Category create/edit/hide/reorder.
  inventory.php           Bulk stock editor and "running low" view.
  orders.php order-view.php
                          Order search/filter, itemised view, status transitions,
                          internal notes, customer and delivery details.
  payments.php payment-proof.php
                          Manual payment queue: verify or reject with a reason;
                          receipt images are served through an ownership check.
  customers.php customer-view.php
                          Customer list and detail (never shows hashes).
  reviews.php             Approve, hide or delete reviews.
  messages.php            Contact-form enquiries with read/answered states.
  discount-codes.php      Discount codes with expiry, usage caps and per-user
                          limits (optional feature, fully working).
  reports.php             Sales by date range with totals and a safe CSV export.
  settings.php            Store name, tagline, announcement, contact details,
                          shipping fee, free-shipping threshold, page sizes,
                          payment methods and manual-payment instructions.
  profile.php             The administrator's own account and password.
  audit-logs.php          Who changed what, when, from which address.
  includes/admin-header.php  Admin chrome, sidebar and access guard.

includes/  - SHARED CORE (never reachable from a browser)
  bootstrap.php           Loads config, database, session, helpers; sets
                          timezone and security headers.
  config loading + not-configured.php / db-error.php
                          Friendly pages while the shop is unconfigured or the
                          database is unreachable - never a stack trace.
  db.php                  PDO connection, q(), fetch_one(), fetch_all(),
                          fetch_value(), db_insert(), db_update(), transactions.
  functions.php           Escaping, formatting, money, dates, slugs, pagination,
                          input readers, checkbox reader.
  auth.php                Registration, login, throttling, roles, ownership
                          checks, password changes.
  csrf.php                Per-session tokens and csrf_guard().
  cart.php                Session and database carts, totals, shipping rules,
                          guest-cart merge on sign-in.
  discounts.php           Discount-code validation and redemption.
  orders.php              Order creation in a transaction, stock handling,
                          status transitions, timeline, payment submission.
  catalog.php catalog-view.php partials.php
                          Product queries, filters, sorting, product cards,
                          empty states.
  settings.php            Store settings with sane defaults.
  uploads.php             Image upload validation (real MIME check, size, random
                          file name, folder whitelist).
  header.php footer.php account-layout.php icons.php flash.php flash messages,
                          icons, layout chrome.
  errors.php abort.php error-page.php
                          Central error handling and the friendly 403/404/500
                          presentations.
  index.php .htaccess     Directory guards.

assets/
  css/style.css           The storefront stylesheet (mobile-first, ~1,000 lines,
                          CSS custom properties, print styles, reduced-motion
                          and forced-colors support).
  css/admin.css           The dashboard stylesheet.
  js/main.js              Menu, filters, quantity steppers, live cart counts,
                          image previews, copy buttons, confirmations - vanilla
                          JavaScript, no dependencies.
  js/admin.js             Dashboard interactions (bulk stock, confirmations).
  images/                 6 brand and UI SVGs: wordmark/hero mark, favicon,
                          placeholder cover, paper texture, reading-nook art.
  images/covers/          47 original SVG book, CD, vinyl and DVD covers, one
                          per starter product.

database/
  aklatan_market.sql      Complete schema + starter data (about 58 KB):
                          users, user_addresses, categories, products, carts,
                          cart_items, wishlists, orders, order_items, payments,
                          reviews, contact_messages, store_settings,
                          discount_codes, discount_redemptions, audit_logs,
                          login_attempts - with indexes, foreign keys and
                          realistic sample content.
  .htaccess index.php     Keep the dump private.

config/
  config.php              THE ONLY FILE YOU EDIT. Ships blank.
  config.example.php      Identical template, in case you overwrite it.
  .htaccess index.php     Keep credentials private.

storage/                  Error log location (writable, never public).
uploads/products/         Product cover images uploaded from the dashboard.
uploads/payments/         Payment receipt images submitted by customers.
                          Both folders ship with an .htaccess that refuses to
                          execute or list anything inside them.
.htaccess                 Optional hardening: no directory listings, blocks
                          config/, includes/, database/, storage/, tools/,
                          cache headers, compression and security headers.
INSTALLATION_GUIDE.txt    Beginner deployment guide (15 sections).
README.txt                This file.


================================================================================
 5. SAMPLE DATA, AND HOW TO REMOVE IT
================================================================================

The starter catalogue (47 titles, 10 categories) is invented sample content for
demonstration. Treat the stock numbers as examples to edit, not as real
inventory, and replace the descriptions with your own copy before trading.

Four sample customer accounts are included so the admin dashboard, order lists
and reports are not empty on day one. They all share one documented password:

    maria.santos@example.com      Juan Dela Cruz   juan.delacruz@example.com
    ana.reyes@example.com         paolo.garcia@example.com
    password for all four:  AklatanDemo123!

  >>> These are DEMO accounts. Delete them before you trade for real. <<<

Three ways to do that:
  * When running setup_admin.php, tick "Delete the sample customers, orders and
    reviews from my shop" - everything belonging to any @example.com account is
    removed (orders, order items, payments, reviews, wishlists, addresses and
    the two sample enquiries), while your products, categories and settings are
    kept.
  * Or sign in as admin, open Admin -> Customers and delete them one by one.
  * Or run this in phpMyAdmin:
        DELETE FROM users WHERE email LIKE '%@example.com';

A few sample orders, reviews and audit-log rows in the SQL file carry realistic
statuses and dates so you can see every screen working. They disappear with the
sample customers.


================================================================================
 6. ACCEPTANCE TEST CHECKLIST
================================================================================

The list below maps to the 14 flows the shop had to satisfy. "Verified" means I
ran it locally against a freshly imported database and it passed; the exact
command is described in section 7. "You should confirm" is a check that needs a
real browser or your own hosting account.

  1. First admin setup, then the setup script locked/removed .......... VERIFIED
     setup_admin.php creates the administrator, hashes the password, refuses a
     weak one, refuses a second administrator, and reports itself locked.
  2. Customer registration, login, logout, invalid-login messages ...... VERIFIED
     Including duplicate e-mail, mismatched passwords, a password shorter than
     8 characters, and a wrong password that reveals nothing about which part
     was wrong.
  3. Search, category filter, sorting, pagination, product detail ..... VERIFIED
     Keyword search, category/price/availability filters, four sort orders,
     pagination, unknown product and unknown category both return 404.
  4. Add to cart, update quantity, remove, correct totals ............. VERIFIED
     Guest (session) cart, merge into the account on sign-in, quantity updates,
     removal, subtotal and shipping recalculated server-side.
  5. Checkout with server-side price/stock validation ................. VERIFIED
     Prices, shipping and totals are re-read from the database; an order for
     more units than exist is refused; missing delivery fields are reported.
  6. Order creation and visible customer order history ................ VERIFIED
     Transactional order + order_items, human-readable AKL-nnnnnnnn-XXXXX
     number, stock reduced, matching payment row, confirmation page visible to
     the owner only.
  7. Admin product/category create, edit, deactivate, delete .......... VERIFIED
     Including slug and SKU generation, discount badge data, hiding a product
     (404 in the shop), deleting a product without breaking old orders, and
     category hide/show.
  8. Admin order status update and customer-side display .............. VERIFIED
     Pending -> Confirmed -> Processing -> Shipped -> Delivered, invalid jumps
     refused, cancellation returns stock, internal notes stay internal.
  9. Manual GCash submission and admin verification/rejection ......... VERIFIED
     Reference captured at checkout or later from the order page, an optional
     receipt upload, pending state, approval, rejection with a required reason
     that the customer can see and answer, and an audit entry for each decision.
 10. Customer cannot reach another customer's order or the admin ...... VERIFIED
     Every protected page and every admin action refused for a customer and for
     a signed-out visitor (302/403), across all 14 admin screens.
 11. Upload validation rejects invalid files ......................... VERIFIED
     A PHP script, a text file renamed .png and random binary data are all
     refused; a genuine PNG is accepted, renamed randomly, stored outside the
     web root's executable paths, and rendered on the product page.
 12. Responsive layout at phone, tablet and desktop .................. PARTLY
     The CSS is mobile-first with breakpoints at 480, 560, 700, 900 and 1100 px
     plus a small-screen rule below 360 px, wide tables scroll inside their own
     wrapper, and the layout was checked by reading the CSS and markup. It has
     NOT been viewed in a real browser yet - please open the shop on a phone
     (or in your browser's device toolbar at 320, 375, 768, 1024 and 1440 px)
     and confirm you see no horizontal scrolling.
 13. No decorative or non-functional buttons or forms ................ VERIFIED
     Static audit: no href="#", no empty form action, every form has a method,
     every image has alt text, and every form posts to a handler that was
     exercised by the tests above.
 14. A fresh installation works after importing the SQL .............. VERIFIED
     Database dropped, the shipped SQL imported into an empty database, config
     completed, then the whole flow above re-run from zero: 418 automatic
     checks, 0 failures.


================================================================================
 7. WHAT WAS TESTED LOCALLY, AND HOW
================================================================================

Environment: PHP 8.1.34 CLI (the shop also parses cleanly under PHP 8.4),
MariaDB 11.4, PHP's built-in web server. No PHP syntax errors across all 75 PHP
files (php -l).

  * 418 automatic checks across 42 sections, all passing, run against a database
    built by importing the shipped database/aklatan_market.sql into an empty
    schema. A real HTTP client logged in, added items to carts, checked out,
    uploaded images, approved payments and clicked every admin screen.
  * The three entry states a real shop passes through were tested separately,
    each from a pristine database: (a) no administrator yet, (b) an
    administrator who keeps the sample data, (c) an administrator who chooses
    the one-click cleanup.
  * Upload abuse, cross-account access, CSRF-less posts, wrong passwords,
    repeated failed logins, invalid order-status jumps, negative stock, expired
    and per-user-limited discount codes, and direct access to files under
    config/, includes/, database/ and uploads/ were all attempted and rejected.

What was NOT tested, and why:
  * Real InfinityFree hosting. I cannot create an account there, so the upload,
    the phpMyAdmin import, the free SSL certificate and the host's own limits
    have to be done by you (INSTALLATION_GUIDE.txt covers each step). The code
    deliberately avoids everything free hosting blocks: no cron, no SMTP, no
    background daemons, no external APIs, no rewrites.
  * Visual appearance in a real browser. The layout, contrast and motion are
    designed and reviewed in code, but the environment I worked in has no
    browser, so please eyeball the home page, a product page, the cart, the
    checkout and the dashboard once on a phone and once on a desktop.

  If you find anything that behaves differently on your hosting, the fastest
  diagnosis is: temporarily set APP_DEBUG to true in config/config.php, reload
  the failing page, then set it back to false.


================================================================================
 8. CUSTOMISING THE LOOK
================================================================================

  * Colours, spacing, corner radii and shadows: the CSS custom properties at the
    top of  assets/css/style.css  (--ink, --paper, --amber, --sage, --radius,
    --shadow...). Change them in one place and the whole shop follows.
  * Logo:  assets/images/hero-mark.svg  and the wordmark drawn in
    includes/header.php and assets/images/favicon.svg.
  * Fonts: the font stack is defined once in style.css; the shop already falls
    back to system fonts if the web fonts do not load.
  * Product covers: replace the SVGs in assets/images/covers/ or upload a real
    image per product in Admin -> Products. Products without an image always use
    assets/images/placeholder-cover.svg.
  * Home page sections: the Bestsellers / New arrivals / Staff picks / Books for
    students / Explore media rows are driven by the flags in Admin -> Products,
    not by hard-coded HTML.


================================================================================
 9. SECURITY AT A GLANCE
================================================================================

  Prepared statements everywhere user input is involved; password_hash() and
  password_verify() (bcrypt) with no plaintext or default password anywhere;
  session ID regenerated on sign-in and a full clear on sign-out; CSRF tokens on
  every state-changing form including all admin actions; htmlspecialchars()
  escaping on output; server-side validation and normalization; role and
  ownership checks on every protected page and action; upload type, size and
  name checks with randomised file names and execution-blocked upload folders;
  login throttling per e-mail and per IP with a correct password always allowed;
  prices, stock, shipping, discounts, payment state and order status always
  recalculated or re-read from the database, never trusted from the browser;
  database errors and stack traces never shown to visitors, with APP_DEBUG off
  by default; no GCash PINs, OTPs or card data collected or stored; private
  folders blocked by .htaccess and by their own guard files.

  Before going live, read section 13 of INSTALLATION_GUIDE.txt (the pre-launch
  security checklist) and delete setup_admin.php.


================================================================================
 10. CREDITS AND LICENSING
================================================================================

  All artwork in this package (wordmark, hero mark, favicon, placeholder cover,
  paper texture, reading-nook illustration and the 47 covers) was generated for
  this project as SVG and ships with it. No image is loaded from or copied from
  another website, so you may use and modify the package freely in your own
  shop. Sample product names, authors and descriptions are invented for
  demonstration and are not real listings.

================================================================================
 End of README - open INSTALLATION_GUIDE.txt to deploy.
================================================================================
