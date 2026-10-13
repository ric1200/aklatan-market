# Aklatan Market

**Stories, knowledge, and discoveries in one place.**

A complete online bookstore & media shop written in plain PHP 8 + MySQL, built and
tested for **InfinityFree's free hosting plan**. No Node, no npm, no Composer, no
build step, no external APIs, no cron jobs, no e-mail server required.

> **Deploying it?** Read [`INSTALLATION_GUIDE.txt`](INSTALLATION_GUIDE.txt) — a
> beginner-friendly, 15-section walkthrough of the whole deployment.
> The full file inventory and the acceptance-test checklist are in
> [`README.txt`](README.txt).

## What's inside

**Storefront** — home page with hero, announcement strip and database-driven
sections (Bestsellers, New arrivals, Staff picks, Books for students, Explore
media); full catalogue with filters, sorting and pagination; keyword search;
product pages with reviews and ratings; category browsing; wishlist; cart with
discount codes; checkout with server-side price and stock validation; order
confirmation and order history; manual GCash reference submission.

**Customer area** — profile, password change, delivery address book, order list,
order detail with cancellation and payment resubmission, own reviews.

**Admin dashboard** — sales/order/stock overview, product and category
management with validated image uploads, bulk inventory editor, order workflow
with internal notes, manual payment verification (approve/reject with reason),
customers, review moderation, contact enquiries, discount codes, reports with
CSV export, store settings, administrator profile and a full audit log.

**Payments** — Cash on delivery, manual GCash transfer verified by hand, or
pick up in store. No paid gateway, and no GCash PIN, OTP or card data is ever
collected or stored.

**Design** — editorial bookstore aesthetic: serif display headings, sans-serif
body, ink blue `#172B4D`, warm paper `#FAF7F0`, golden amber `#E5A93D`, sage
accents. Mobile-first CSS with breakpoints at 480/560/700/900/1100 px, keyboard
friendly, visible focus states, `prefers-reduced-motion` respected. All artwork
(including 47 product covers) is original SVG shipped with the project — no
hot-linked images.

## Quick start

1. Import `database/aklatan_market.sql` (17 tables, 47 starter titles) into a
   MySQL/MariaDB database.
2. Fill in `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` in `config/config.php`.
   On InfinityFree the host is **not** `localhost` — copy the value from
   Control Panel → MySQL Databases.
3. Upload every file and folder into `htdocs`, keeping the structure intact.
4. Open `setup_admin.php`, create your administrator, then **delete that file**.
5. Sign in and set your shipping fee, free-shipping threshold and GCash details
   under **Admin → Settings**.

## Security

Prepared statements for every query involving user input, bcrypt password
hashes, CSRF tokens on all state-changing forms, output escaping, server-side
validation, role and ownership checks on every protected page, throttled logins,
validated and randomised image uploads with execution-blocked upload folders,
and no technical error details exposed to visitors (`APP_DEBUG` off by default).

## Sample data

The catalogue, customers, orders and reviews that ship in the SQL file are
sample content for demonstration. The four demo customer accounts share the
password `AklatanDemo123!` and should be deleted before you trade for real —
`setup_admin.php` offers a one-click cleanup, or run:

```sql
DELETE FROM users WHERE email LIKE '%@example.com';
```

## Licence

Original artwork generated for this project; use and modify the package freely
in your own shop. Sample product names, authors and descriptions are invented
for demonstration and are not real listings.
