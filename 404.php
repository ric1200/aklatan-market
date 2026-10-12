<?php
/** Friendly "page not found" state. */
require_once __DIR__ . '/includes/bootstrap.php';
aklatan_abort(404, 'We could not find that page',
    'The link may be broken or the page may have been renamed. Try searching the catalogue, or start again from the storefront.');
