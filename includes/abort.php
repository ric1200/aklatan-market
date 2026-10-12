<?php
/**
 * Friendly HTTP error pages (403 / 404 / 500).
 * Output is safe at any point: if headers were already sent by a partial page
 * we print a compact inline block instead of a full document.
 */

function aklatan_abort(int $code, string $headline = '', string $message = '', bool $includeChrome = true): void
{
    if (PHP_SAPI === 'cli') { throw new RuntimeException('HTTP ' . $code . ': ' . $headline); }

    $titles = [
        400 => 'That request could not be understood',
        403 => 'You do not have access to this page',
        404 => 'We could not find that page',
        405 => 'That action is not allowed here',
        419 => 'Your session expired',
        429 => 'Please slow down',
        500 => 'Something went wrong on our side',
        503 => 'The store is temporarily unavailable',
    ];
    $headline = $headline !== '' ? $headline : ($titles[$code] ?? 'Something went wrong');
    $message = $message !== '' ? $message : 'Please use the links below to get back to the storefront.';

    http_response_code($code);

    $GLOBALS['aklatan_error'] = ['code' => $code, 'headline' => $headline, 'message' => $message];

    if ($includeChrome && !headers_sent() && function_exists('render_header')) {
        render_header(['title' => $headline, 'robots' => 'noindex, nofollow', 'body_class' => 'page-error']);
        render_error_body($code, $headline, $message);
        render_footer();
        exit;
    }

    if (!headers_sent()) {
        $storeName = function_exists('setting') ? setting('store_name', 'Aklatan Market') : 'Aklatan Market';
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
           . '<meta name="viewport" content="width=device-width, initial-scale=1">'
           . '<title>' . e($headline) . ' &middot; ' . e($storeName) . '</title>'
           . '<link rel="stylesheet" href="' . e(url('assets/css/style.css')) . '"></head>'
           . '<body class="page-error"><main id="main">';
        render_error_body($code, $headline, $message);
        echo '</main></body></html>';
        exit;
    }

    render_error_body($code, $headline, $message);
    exit;
}

function render_error_body(int $code, string $headline, string $message): void
{
    ?>
    <div class="error-shell">
      <div class="error-card">
        <p class="error-code" aria-hidden="true"><?= (int) $code ?></p>
        <h1><?= e($headline) ?></h1>
        <p class="lede"><?= e($message) ?></p>
        <p class="error-actions">
          <a class="btn btn-primary" href="<?= e(url('index.php')) ?>">Back to the storefront</a>
          <a class="btn btn-ghost" href="<?= e(url('products.php')) ?>">Browse all products</a>
        </p>
        <p class="error-help">Looking for something specific?
          <a href="<?= e(url('search.php')) ?>">Search the catalogue</a> or
          <a href="<?= e(url('contact.php')) ?>">contact the store</a>.</p>
      </div>
    </div>
    <?php
}
