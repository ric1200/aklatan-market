<?php
/** Friendly "not allowed" state. */
require_once __DIR__ . '/includes/bootstrap.php';
aklatan_abort(403, 'You do not have access to this page',
    'This area belongs to another account. If you believe you should be able to open it, sign in with the correct account or contact the store.');
