<?php
/** Friendly page when the database credentials are wrong or the server is down. */
if (!headers_sent()) { http_response_code(503); }
$storeName = function_exists('setting') ? setting('store_name', 'Aklatan Market') : 'Aklatan Market';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Database unavailable &middot; <?= htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8') ?></title>
<style>
  body { margin: 0; background: #FAF7F0; color: #172B4D;
         font: 16px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
  .wrap { max-width: 720px; margin: 0 auto; padding: 48px 20px; }
  .card { background: #fff; border: 1px solid rgba(23,43,77,.12); border-radius: 16px; padding: 28px; }
  h1 { font: 700 26px/1.25 Georgia, serif; margin: 0 0 10px; }
  code { background: #F2EDE2; padding: 2px 6px; border-radius: 6px; font-size: 14px; }
  ul { padding-left: 20px; } li { margin-bottom: 6px; }
</style>
</head>
<body>
<div class="wrap"><div class="card">
  <h1>The store cannot reach its database right now</h1>
  <p>This is a site-owner issue, not something you did. If you are the owner, check:</p>
  <ul>
    <li><code>DB_HOST</code> &mdash; on InfinityFree this is <strong>not</strong> <code>localhost</code>. Copy the exact host shown in
        Control Panel &rarr; MySQL Databases.</li>
    <li><code>DB_NAME</code>, <code>DB_USER</code> and <code>DB_PASS</code> spelling and case.</li>
    <li>That <code>database/aklatan_market.sql</code> was imported through phpMyAdmin.</li>
    <li>That the database is still active in the control panel (free databases are suspended if unused for a long time).</li>
  </ul>
  <p>Technical details were written to <code>storage/error.log</code>.</p>
</div></div>
</body>
</html>
