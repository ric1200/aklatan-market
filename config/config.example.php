<?php
/**
 * =============================================================================
 *  AKLATAN MARKET - CONFIGURATION TEMPLATE
 * =============================================================================
 *
 *  This is the ONLY file you must edit before your store goes live.
 *
 *  HOW TO USE IT
 *  -------------
 *  1. Copy this file to  config/config.php   (a blank one already ships with
 *     the package, so you can simply edit  config/config.php  directly).
 *  2. Fill in the five database values from your InfinityFree control panel
 *     (Control Panel -> MySQL Databases). InfinityFree shows them like this:
 *
 *         MySQL Host Name ... example:  sql300.infinityfree.com   -> DB_HOST
 *         MySQL Database .... example:  if0_38612345_aklatan      -> DB_NAME
 *         MySQL User Name ... example:  if0_38612345              -> DB_USER
 *         MySQL Password .... the password you typed when creating
 *                             the database (use the "Show / Change"
 *                             link if you forgot it)              -> DB_PASS
 *
 *  IMPORTANT: On free hosting the database host is NOT "localhost".
 *  Always copy the exact host name that the control panel displays.
 *
 *  Do not use "<?php" inside the values. Keep the quotes.
 * =============================================================================
 */

/* ---------------------------------------------------------------------------
 * 1. DATABASE CONNECTION
 * ------------------------------------------------------------------------ */

/** Database server / host name, exactly as shown in your control panel. */
define('DB_HOST', '');          // e.g. 'sql300.infinityfree.com'

/** Database port. Free hosting almost always uses 3306. */
define('DB_PORT', 3306);

/** Database name. */
define('DB_NAME', '');          // e.g. 'if0_38612345_aklatan'

/** Database user name. */
define('DB_USER', '');          // e.g. 'if0_38612345'

/** Database password (the one you set in the control panel). */
define('DB_PASS', '');          // e.g. 'MySecretDbPass123'

/** Connection character set. utf8mb4 handles Filipino accents and emoji. */
define('DB_CHARSET', 'utf8mb4');

/* ---------------------------------------------------------------------------
 * 2. SITE ADDRESS
 * ------------------------------------------------------------------------ */

/**
 * SITE_BASE_URL
 *
 * Leave it EMPTY ('') and Aklatan Market works out its own address
 * automatically. That is the correct setting for almost everybody, whether
 * you upload to the root of your subdomain, to a sub-folder, or to a custom
 * domain.
 *
 * Only fill this in if auto-detection does not match your hosting layout,
 * for example:
 *      define('SITE_BASE_URL', 'https://aklatan.infinityfreeapp.com');
 * or   define('SITE_BASE_URL', 'https://example.com/shop');
 * Do NOT add a trailing slash.
 */
define('SITE_BASE_URL', '');

/** Store timezone. Philippines Standard Time. */
define('APP_TIMEZONE', 'Asia/Manila');

/* ---------------------------------------------------------------------------
 * 3. OPPORTUNISTIC SETTINGS
 * ------------------------------------------------------------------------ */

/**
 * APP_DEBUG - keep this FALSE on your live website.
 * When TRUE the store prints technical error details on screen. Only turn it
 * on temporarily while troubleshooting, then set it back to FALSE.
 */
define('APP_DEBUG', false);

/**
 * FORCE_HTTPS - set to TRUE once you have enabled the free SSL certificate in
 * the InfinityFree control panel ("SSL Certificates"). While it is FALSE the
 * store accepts both http:// and https://.
 */
define('FORCE_HTTPS', false);

/** Maximum size (bytes) accepted for an uploaded product image / payment proof. */
define('UPLOAD_MAX_BYTES', 2 * 1024 * 1024);   // 2 MB

/** Currency symbol shown to shoppers. The Philippine peso sign. */
define('CURRENCY_SYMBOL', "\u{20B1}");   // ₱

/** Session cookie name. Change it only if it clashes with another app. */
define('SESSION_COOKIE_NAME', 'aklatan_session');

/** Failed logins allowed per 15 minutes, per e-mail address and per IP. */
define('LOGIN_MAX_ATTEMPTS', 8);
define('LOGIN_LOCKOUT_MINUTES', 15);
