<?php
/** Friendly server error state. */
require_once __DIR__ . '/includes/bootstrap.php';
http_response_code(500);
aklatan_abort(500, 'Something went wrong on our side',
    'The page could not be completed. This is usually temporary. Please try again in a moment.');
