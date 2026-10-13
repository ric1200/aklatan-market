<?php
/** Central error handling: log technical detail, never leak it to visitors. */

function aklatan_log_dir(): string
{
    $dir = APP_ROOT . '/storage';
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    return $dir;
}

function log_error(string $message): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . str_replace(["\r", "\n"], ' ', $message) . PHP_EOL;
    $file = aklatan_log_dir() . '/error.log';
    if (is_dir(dirname($file)) && (is_file($file) ? is_writable($file) : is_writable(dirname($file)))) {
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}

function aklatan_register_error_handlers(): void
{
    error_reporting(E_ALL);
    ini_set('display_errors', APP_DEBUG ? '1' : '0');
    ini_set('log_errors', '0');

    set_exception_handler(function ($ex) {
        log_error('Uncaught ' . get_class($ex) . ': ' . $ex->getMessage() . ' in ' . $ex->getFile() . ':' . $ex->getLine());
        aklatan_fatal_page();
    });

    register_shutdown_function(function () {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            log_error('Fatal: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
        }
    });

    set_error_handler(function ($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) { return false; }
        log_error('PHP notice/warning: ' . $message . ' in ' . $file . ':' . $line);
        return true;
    });
}

function aklatan_fatal_page(): void
{
    if (PHP_SAPI === 'cli') { return; }
    if (!headers_sent()) { http_response_code(500); }
    $safeHeadline = 'Something went wrong on our side';
    include APP_ROOT . '/includes/error-page.php';
    exit;
}
