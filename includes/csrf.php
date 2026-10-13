<?php
/** Cross-site request forgery protection for every state-changing form. */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_valid(?string $token = null): bool
{
    $token = $token ?? (string) ($_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '');
    $stored = $_SESSION['csrf_token'] ?? '';
    return is_string($stored) && $stored !== '' && $token !== '' && hash_equals($stored, $token);
}

/**
 * Verify the token and that the request came from this site.
 * Any failure is logged and ends the request with a friendly 403 page.
 */
function csrf_guard(): void
{
    if (!is_post()) {
        http_response_code(405);
        exit('Method not allowed.');
    }
    if (!csrf_valid()) {
        log_error('CSRF check failed on ' . ($_SERVER['REQUEST_URI'] ?? '?') . ' from ' . client_ip());
        aklatan_abort(403, 'Your session expired', 'For your safety we could not verify that form submission. Reload the page and try again.');
    }
    if (!same_origin_request()) {
        log_error('Cross-origin POST rejected on ' . ($_SERVER['REQUEST_URI'] ?? '?') . ' from ' . client_ip());
        aklatan_abort(403, 'Request blocked', 'That request came from an unexpected place and was blocked.');
    }
}

/** Light-weight origin/referer check for POST requests. */
function same_origin_request(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') { return true; }
    foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $key) {
        if (empty($_SERVER[$key])) { continue; }
        $candidate = parse_url((string) $_SERVER[$key], PHP_URL_HOST);
        if ($candidate !== null && strtolower($candidate) !== '' && strtolower($candidate) !== $host) {
            return false;
        }
    }
    return true;
}
