AKLATAN MARKET - COMPACT RESPONSIVE ADMIN HEADER

Back up your existing files first.
Upload and replace ONLY these two files, keeping the folder paths:

1. admin/includes/admin-header.php
   Hosting path: htdocs/admin/includes/admin-header.php
2. assets/css/admin.css
   Hosting path: htdocs/assets/css/admin.css

Do not replace products.php, style.css, main.js, admin.js or the database.
This package preserves your existing authentication, role checks, navigation,
logout POST form and CSRF field. It does not modify product records.

Changes:
- Compact mobile header: menu + brand, then Store / Account / Sign out.
- Desktop header stays on one row.
- Skip-to-content link is hidden until keyboard focus, not removed.
- Mobile menu retains the existing JavaScript behavior.
- Header adds an admin.css version query to refresh cached styles.

After uploading BOTH files, reload the admin page. If needed, refresh with
Ctrl+F5 on desktop, or close and reopen the admin tab on your phone.
Keep your previously updated global style.css (including the shipping fix).

Validation: actual supplied admin-header.php and admin Products template
rendered locally with demo data at 14 viewport widths from 320 to 1920 px.
Checked menu/Escape, keyboard skip link, no-JavaScript fallback, overflow,
and sign-out form protections. This was not a live-hosting test.
