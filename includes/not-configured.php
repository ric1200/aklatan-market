<?php
/** Shown while config/config.php still holds blank values. Never leaks secrets. */
if (!headers_sent()) { http_response_code(503); header('Retry-After: 3600'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Aklatan Market &middot; finish the installation</title>
<style>
  :root { color-scheme: light; }
  * { box-sizing: border-box; }
  body { margin: 0; background: #FAF7F0; color: #172B4D;
         font: 16px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
  .wrap { max-width: 780px; margin: 0 auto; padding: 40px 20px 64px; }
  .card { background: #fff; border: 1px solid rgba(23,43,77,.12); border-radius: 16px; padding: 28px; box-shadow: 0 10px 30px rgba(23,43,77,.07); }
  h1 { font: 700 28px/1.25 Georgia, "Times New Roman", serif; margin: 0 0 6px; }
  h2 { font-size: 17px; margin: 28px 0 8px; }
  p { margin: 0 0 12px; }
  code, pre { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
  pre { background: #172B4D; color: #F5EBD6; padding: 14px 16px; border-radius: 10px; overflow-x: auto; font-size: 13.5px; }
  ol { padding-left: 20px; } li { margin-bottom: 8px; }
  .badge { display: inline-block; background: #FBEBCB; color: #7A5310; border-radius: 999px; padding: 4px 12px; font-size: 13px; font-weight: 600; }
  a { color: #2F6F63; }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <p class="badge">Installation step remaining</p>
    <h1>Aklatan Market is installed, but not connected yet</h1>
    <p>The storefront cannot open until the database details are filled in.
       This is expected on a fresh upload &mdash; it takes about two minutes to finish.</p>

    <h2>1. Open <code>config/config.php</code></h2>
    <p>Use the File Manager in your hosting control panel and edit that one file. Fill in the four values
       shown in <strong>Control Panel &rarr; MySQL Databases</strong>:</p>
    <pre>define('DB_HOST', 'sql300.infinityfree.com');   // MySQL Host Name
define('DB_NAME', 'if0_38612345_aklatan');      // MySQL Database
define('DB_USER', 'if0_38612345');              // MySQL User Name
define('DB_PASS', 'your-database-password');    // MySQL Password</pre>

    <h2>2. Import the database</h2>
    <p>In phpMyAdmin open your database and use <strong>Import</strong> to upload
       <code>database/aklatan_market.sql</code> from this package. It creates every table and the sample catalog.</p>

    <h2>3. Create your administrator</h2>
    <p>Visit <code><?= htmlspecialchars(($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'your-site'), ENT_QUOTES) ?>/setup_admin.php</code>,
       create the first admin account, then <strong>delete that file</strong>.</p>

    <h2>Need the full walkthrough?</h2>
    <ol>
      <li>Open <code>INSTALLATION_GUIDE.txt</code> in the package for step-by-step screenshots-level detail.</li>
      <li>Reload this page once the four values above are saved.</li>
    </ol>
    <p class="muted">Nothing is wrong with the upload itself &mdash; this notice simply means
      <code>config/config.php</code> still contains empty placeholder values.</p>
  </div>
</div>
</body>
</html>
