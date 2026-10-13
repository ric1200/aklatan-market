<?php
/**
 * Aklatan Market - application bootstrap.
 * Every public page and every admin page loads this file first.
 */
if (defined('AKLATAN_BOOTSTRAPPED')) { return; }
define('AKLATAN_BOOTSTRAPPED', true);

if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    http_response_code(500);
    exit('Aklatan Market needs PHP 7.4 or newer. This server runs PHP ' . PHP_VERSION . '.');
}

define('APP_ROOT', dirname(__DIR__));
define('AKLATAN_VERSION', '1.0.0');

/* ------------------------------------------------------------------ config */
$aklatanConfig = APP_ROOT . '/config/config.php';
if (!is_file($aklatanConfig)) {
    require APP_ROOT . '/includes/not-configured.php';
    exit;
}
require $aklatanConfig;

foreach ([
    'DB_HOST' => '', 'DB_PORT' => 3306, 'DB_NAME' => '', 'DB_USER' => '',
    'DB_PASS' => '', 'DB_CHARSET' => 'utf8mb4', 'SITE_BASE_URL' => '',
    'APP_TIMEZONE' => 'Asia/Manila', 'CURRENCY_SYMBOL' => "\u{20B1}", 'APP_DEBUG' => false, 'FORCE_HTTPS' => false,
    'UPLOAD_MAX_BYTES' => 2097152, 'SESSION_COOKIE_NAME' => 'aklatan_session',
    'LOGIN_MAX_ATTEMPTS' => 8, 'LOGIN_LOCKOUT_MINUTES' => 15,
] as $aklatanConst => $aklatanDefault) {
    if (!defined($aklatanConst)) { define($aklatanConst, $aklatanDefault); }
}
unset($aklatanConfig, $aklatanConst, $aklatanDefault);

if (DB_HOST === '' || DB_NAME === '' || DB_USER === '') {
    require APP_ROOT . '/includes/not-configured.php';
    exit;
}

date_default_timezone_set(APP_TIMEZONE);
if (function_exists('mb_internal_encoding')) { mb_internal_encoding('UTF-8'); }

/* -------------------------------------------------------------- environment */
function aklatan_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') { return true; }
    if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) { return true; }
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') { return true; }
    if (isset($_SERVER['HTTP_CF_VISITOR']) && strpos((string) $_SERVER['HTTP_CF_VISITOR'], 'https') !== false) { return true; }
    return false;
}
define('AKLATAN_HTTPS', aklatan_is_https());

if (FORCE_HTTPS && !AKLATAN_HTTPS && PHP_SAPI !== 'cli' && !headers_sent()) {
    $host = isset($_SERVER['HTTP_HOST']) ? preg_replace('/[^A-Za-z0-9\.\-:\[\]]/', '', (string) $_SERVER['HTTP_HOST']) : '';
    if ($host !== '') {
        header('Location: https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
        exit;
    }
}

/**
 * Base path of the installation, '' when installed at the site root.
 * Auto-detected from DOCUMENT_ROOT so the store keeps working when it lives in
 * a sub-folder such as /shop - no rewriting rules are ever required.
 */
function base_path(): string
{
    static $base = null;
    if ($base !== null) { return $base; }

    if (defined('SITE_BASE_URL') && SITE_BASE_URL !== '') {
        $path = parse_url(SITE_BASE_URL, PHP_URL_PATH);
        $base = is_string($path) ? rtrim($path, '/') : '';
        return $base;
    }

    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : false;
    $appRoot = realpath(APP_ROOT);
    if ($docRoot !== false && $appRoot !== false) {
        $docRoot = str_replace('\\', '/', $docRoot);
        $appRoot = str_replace('\\', '/', $appRoot);
        if ($appRoot === $docRoot) { $base = ''; return $base; }
        if (strpos($appRoot, $docRoot . '/') === 0) {
            $base = rtrim(substr($appRoot, strlen($docRoot)), '/');
            return $base;
        }
    }
    $base = '';
    return $base;
}

/** Site-relative link for a path inside the installation. */
function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return $path === '' ? (base_path() === '' ? '/' : base_path() . '/') : base_path() . '/' . $path;
}

/** Fully qualified URL, useful for canonical tags and sharing links. */
function absolute_url(string $path = ''): string
{
    $scheme = AKLATAN_HTTPS ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? preg_replace('/[^A-Za-z0-9\.\-:\[\]]/', '', (string) $_SERVER['HTTP_HOST']) : 'localhost';
    return $scheme . '://' . $host . url($path);
}

/** Escape any value for safe HTML output. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Current request path (used to compare with session-stored return URLs). */
function current_relative_url(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? url('index.php'));
    return $uri === '' ? url('index.php') : $uri;
}

/* ----------------------------------------------------------------- session */
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    $cookiePath = base_path() === '' ? '/' : base_path() . '/';
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookiePath,
            'domain' => '',
            'secure' => AKLATAN_HTTPS,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, $cookiePath, '', AKLATAN_HTTPS, true);
    }
    session_name(SESSION_COOKIE_NAME);
    session_start();
}

/* ------------------------------------------------------------- error policy */
require_once APP_ROOT . '/includes/errors.php';
aklatan_register_error_handlers();

/* ------------------------------------------------------------- application */
require_once APP_ROOT . '/includes/abort.php';
require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/icons.php';
require_once APP_ROOT . '/includes/csrf.php';
require_once APP_ROOT . '/includes/flash.php';
require_once APP_ROOT . '/includes/settings.php';
require_once APP_ROOT . '/includes/auth.php';
require_once APP_ROOT . '/includes/catalog.php';
require_once APP_ROOT . '/includes/discounts.php';
require_once APP_ROOT . '/includes/cart.php';
require_once APP_ROOT . '/includes/uploads.php';
require_once APP_ROOT . '/includes/account-layout.php';
require_once APP_ROOT . '/includes/orders.php';
require_once APP_ROOT . '/includes/partials.php';
require_once APP_ROOT . '/includes/header.php';
require_once APP_ROOT . '/includes/footer.php';
