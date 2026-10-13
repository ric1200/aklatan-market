<?php
/** Shared formatting, request and validation helpers. */

/* ------------------------------------------------------------------ output */

/** Format a number as Philippine pesos: ₱1,234.50 */
function money($amount): string
{
    return CURRENCY_SYMBOL . number_format((float) $amount, 2);
}

/** Currency without the symbol, for machine-readable output. */
function money_plain($amount): string
{
    return number_format((float) $amount, 2, '.', '');
}

function format_date(?string $timestamp, string $format = 'M j, Y'): string
{
    if (!$timestamp) { return '—'; }
    $ts = strtotime($timestamp);
    return $ts === false ? '—' : date($format, $ts);
}

function format_datetime(?string $timestamp): string
{
    return format_date($timestamp, 'M j, Y g:i A');
}

function time_ago(?string $timestamp): string
{
    if (!$timestamp) { return '—'; }
    $ts = strtotime($timestamp);
    if ($ts === false) { return '—'; }
    $diff = time() - $ts;
    if ($diff < 60) { return 'just now'; }
    if ($diff < 3600) { $m = (int) floor($diff / 60); return $m . ' min ago'; }
    if ($diff < 86400) { $h = (int) floor($diff / 3600); return $h . ' hour' . ($h === 1 ? '' : 's') . ' ago'; }
    if ($diff < 2592000) { $d = (int) floor($diff / 86400); return $d . ' day' . ($d === 1 ? '' : 's') . ' ago'; }
    return format_date($timestamp);
}

function truncate(?string $text, int $length = 140): string
{
    $text = trim(preg_replace('/\s+/', ' ', (string) $text));
    if ($text === '') { return ''; }
    if (function_exists('mb_strlen') && mb_strlen($text) > $length) {
        return rtrim(mb_substr($text, 0, $length - 1)) . '…';
    }
    if (!function_exists('mb_strlen') && strlen($text) > $length) {
        return rtrim(substr($text, 0, $length - 1)) . '…';
    }
    return $text;
}

function slugify(string $text): string
{
    $text = trim($text);
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false) { $text = $converted; }
    }
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim((string) $text, '-');
    return $text === '' ? 'item' : substr($text, 0, 120);
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) { $out .= strtoupper(substr($p, 0, 1)); }
    return $out === '' ? '?' : $out;
}

/** Render 5 stars, with the numeric value exposed to screen readers. */
function stars(float $rating, string $class = ''): string
{
    $rounded = (int) round($rating);
    $html = '<span class="stars ' . e($class) . '" role="img" aria-label="' . e(number_format($rating, 1)) . ' out of 5 stars">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<span class="star' . ($i <= $rounded ? ' star-on' : '') . '" aria-hidden="true">' . icon('star', '', 15) . '</span>';
    }
    return $html . '</span>';
}

/* ----------------------------------------------------------------- request */

function is_post(): bool
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
}

/** Require POST, or stop with 405. */
function require_post(): void
{
    if (!is_post()) {
        http_response_code(405);
        header('Allow: POST');
        exit('This action must be submitted as a form (HTTP POST).');
    }
}

function post(string $key, $default = '')
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $value;
}

function post_raw(string $key, $default = '')
{
    return $_POST[$key] ?? $default;
}

function post_int(string $key, int $default = 0): int
{
    $value = $_POST[$key] ?? null;
    return is_numeric($value) ? (int) $value : $default;
}

/**
 * Read an HTML checkbox, radio pair or switch.
 *
 * A browser omits an unchecked box entirely, but sends "0" when it is the
 * value of a hidden companion field, and "on" for a plain checkbox. Only an
 * explicitly affirmative value counts as ticked, so a posted "0" can never
 * switch a feature on or wipe data.
 */
function checkbox_post(string $key): bool
{
    $value = $_POST[$key] ?? null;
    if (is_bool($value)) { return $value; }
    if (is_array($value)) { return false; }
    return in_array(strtolower(trim((string) $value)), ['1', 'on', 'true', 'yes', 'checked'], true);
}

function get_param(string $key, $default = '')
{
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $value;
}

function get_int(string $key, int $default = 0): int
{
    $value = $_GET[$key] ?? null;
    return is_numeric($value) ? (int) $value : $default;
}

function redirect(string $path, int $status = 302): void
{
    $target = preg_match('#^(https?:)?//#', $path) ? $path : url($path);
    if (!headers_sent()) { header('Location: ' . $target, true, $status); }
    else { echo '<script>location.replace(' . json_encode($target) . ');</script>'; }
    exit;
}

/**
 * Only allow redirect targets inside this installation, so a crafted
 * ?next= parameter can never bounce a customer to another website.
 */
function safe_next_url(string $candidate, string $fallback = 'index.php'): string
{
    $candidate = trim($candidate);
    if ($candidate === '' || strpos($candidate, '//') === 0 || preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*:#', $candidate)) {
        return url($fallback);
    }
    if (strpos($candidate, '/') === 0 && strpos($candidate, url('/')) !== 0 && base_path() !== '') {
        return url($fallback); // absolute path that ignores our sub-folder
    }
    return $candidate;
}

/**
 * Build a safe "(col LIKE :kw1 OR col2 LIKE :kw2 …)" clause.
 *
 * MySQL native prepared statements (PDO with emulated prepares turned off)
 * refuse to reuse the same named placeholder twice, so every column gets its
 * own placeholder name. The values are added to $params by reference.
 */
function like_or_clause(array $columns, string $needle, array &$params, string $prefix = 'kw'): string
{
    $parts = [];
    $index = 0;
    foreach ($columns as $column) {
        $index++;
        $key = $prefix . $index;
        $parts[] = $column . ' LIKE :' . $key;
        $params[$key] = '%' . $needle . '%';
    }
    return '(' . implode(' OR ', $parts) . ')';
}

/* -------------------------------------------------------------- validation */

function is_valid_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

/** Strip everything but digits, leading + and the local 0 trunk prefix. */
function normalize_phone(string $phone): string
{
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    $phone = preg_replace('/(?!^)\+/', '', (string) $phone);
    return (string) $phone;
}

function is_valid_phone(string $phone): bool
{
    $digits = preg_replace('/\D/', '', $phone);
    return strlen((string) $digits) >= 7 && strlen((string) $digits) <= 15;
}

/** Bytes to a human readable string. */
function human_bytes(int $bytes): string
{
    if ($bytes >= 1048576) { return number_format($bytes / 1048576, 1) . ' MB'; }
    if ($bytes >= 1024) { return number_format($bytes / 1024, 0) . ' KB'; }
    return $bytes . ' bytes';
}

/* -------------------------------------------------------------- pagination */

/**
 * Build pagination metadata.
 *
 * @return array{page:int,perPage:int,total:int,pages:int,offset:int,from:int,to:int}
 */
function paginate(int $total, int $perPage, int $page): array
{
    $perPage = max(1, $perPage);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, $page), $pages);
    $offset = ($page - 1) * $perPage;
    return [
        'page' => $page,
        'perPage' => $perPage,
        'total' => $total,
        'pages' => $pages,
        'offset' => $offset,
        'from' => $total === 0 ? 0 : $offset + 1,
        'to' => min($offset + $perPage, $total),
    ];
}

/** Render pagination links that preserve the current query string. */
function pagination_links(array $p, string $basePath, array $query = []): string
{
    if ($p['pages'] <= 1) { return ''; }
    $link = function (int $page) use ($basePath, $query) {
        $query['page'] = $page;
        return url($basePath) . '?' . http_build_query($query);
    };
    $start = max(1, $p['page'] - 2);
    $end = min($p['pages'], $p['page'] + 2);

    $html = '<nav class="pagination" aria-label="Pagination"><ul>';
    $html .= $p['page'] > 1
        ? '<li><a class="page-link" rel="prev" href="' . e($link($p['page'] - 1)) . '" aria-label="Previous page">' . icon('chevron-left', '', 16) . '<span>Prev</span></a></li>'
        : '<li><span class="page-link is-disabled" aria-disabled="true">' . icon('chevron-left', '', 16) . '<span>Prev</span></span></li>';

    if ($start > 1) {
        $html .= '<li><a class="page-link" href="' . e($link(1)) . '">1</a></li>';
        if ($start > 2) { $html .= '<li><span class="page-ellipsis" aria-hidden="true">…</span></li>'; }
    }
    for ($i = $start; $i <= $end; $i++) {
        $current = $i === $p['page'];
        $html .= '<li>' . ($current
            ? '<span class="page-link is-current" aria-current="page">' . $i . '</span>'
            : '<a class="page-link" href="' . e($link($i)) . '">' . $i . '</a>') . '</li>';
    }
    if ($end < $p['pages']) {
        if ($end < $p['pages'] - 1) { $html .= '<li><span class="page-ellipsis" aria-hidden="true">…</span></li>'; }
        $html .= '<li><a class="page-link" href="' . e($link($p['pages'])) . '">' . $p['pages'] . '</a></li>';
    }
    $html .= $p['page'] < $p['pages']
        ? '<li><a class="page-link" rel="next" href="' . e($link($p['page'] + 1)) . '" aria-label="Next page"><span>Next</span>' . icon('chevron-right', '', 16) . '</a></li>'
        : '<li><span class="page-link is-disabled" aria-disabled="true"><span>Next</span>' . icon('chevron-right', '', 16) . '</span></li>';
    return $html . '</ul></nav>';
}

/* ------------------------------------------------------------------ orders */

const ORDER_STATUSES = [
    'pending'    => 'Pending',
    'confirmed'  => 'Confirmed',
    'processing' => 'Processing',
    'shipped'    => 'Shipped',
    'delivered'  => 'Delivered',
    'cancelled'  => 'Cancelled',
];

/** Statuses an order may move to from its current state (server-side rule). */
const ORDER_STATUS_FLOW = [
    'pending'    => ['confirmed', 'cancelled'],
    'confirmed'  => ['processing', 'cancelled'],
    'processing' => ['shipped', 'cancelled'],
    'shipped'    => ['delivered'],
    'delivered'  => [],
    'cancelled'  => [],
];

function allowed_next_statuses(string $current): array
{
    return ORDER_STATUS_FLOW[$current] ?? [];
}

/** Which statuses release reserved stock back to inventory. */
function status_releases_stock(string $status): bool
{
    return $status === 'cancelled';
}

function order_status_label(string $status): string
{
    return ORDER_STATUSES[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function order_status_badge(string $status): string
{
    $label = order_status_label($status);
    $iconName = [
        'pending' => 'clock', 'confirmed' => 'check-circle', 'processing' => 'box',
        'shipped' => 'truck', 'delivered' => 'check-circle', 'cancelled' => 'x-circle',
    ][$status] ?? 'info';
    return '<span class="status status-' . e($status) . '">' . icon($iconName, '', 14) . '<span>' . e($label) . '</span></span>';
}

const PAYMENT_STATUSES = [
    'unpaid'               => 'Unpaid',
    'pending_verification' => 'Pending verification',
    'verified'             => 'Verified',
    'rejected'             => 'Rejected',
];

function payment_status_label(string $status): string
{
    return PAYMENT_STATUSES[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function payment_status_badge(string $status): string
{
    $tone = [
        'unpaid' => 'muted', 'pending_verification' => 'warn',
        'verified' => 'ok', 'rejected' => 'bad',
    ][$status] ?? 'muted';
    return '<span class="pill pill-' . e($tone) . '">' . e(payment_status_label($status)) . '</span>';
}

function payment_method_label(string $method): string
{
    return [
        'cod' => 'Cash on Delivery',
        'gcash' => 'GCash (manual transfer)',
        'pickup' => 'Pay & pick up in store',
        'bank' => 'Bank transfer (manual)',
    ][$method] ?? ucfirst($method);
}

/** Human-readable, unique order number: AKL-20261012-8F3K2 */
function generate_order_number(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $suffix = '';
    for ($i = 0; $i < 5; $i++) { $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)]; }
    return 'AKL-' . date('Ymd') . '-' . $suffix;
}

/* -------------------------------------------------------------- audit logs */

function audit_log(string $action, string $entityType = '', $entityId = null, string $details = ''): void
{
    try {
        db_insert('audit_logs', [
            'user_id' => function_exists('current_user_id') ? current_user_id() : null,
            'action' => $action,
            'entity_type' => $entityType !== '' ? $entityType : null,
            'entity_id' => $entityId !== null ? (string) $entityId : null,
            'details' => $details !== '' ? $details : null,
            'ip_address' => client_ip(),
        ]);
    } catch (Throwable $ex) {
        log_error('audit_log failed: ' . $ex->getMessage());
    }
}

function client_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return substr($ip, 0, 45);
}

/** Never trust X-Forwarded-For from the browser; REMOTE_ADDR is what we store. */
function client_user_agent(): string
{
    return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}
