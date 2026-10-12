<?php
/**
 * Streams a payment receipt image to an administrator only.
 * The uploads/payments folder is blocked by .htaccess, so the file is never
 * reachable directly — this gate checks the admin session first.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$paymentId = get_int('payment');
$payment = $paymentId > 0
    ? fetch_one('SELECT p.*, o.order_number, o.user_id FROM payments p JOIN orders o ON o.id = p.order_id WHERE p.id = :id',
        ['id' => $paymentId])
    : null;

if (!$payment || empty($payment['proof_image'])) {
    http_response_code(404);
    exit('No receipt image on that payment.');
}

$relative = (string) $payment['proof_image'];
if (strpos($relative, '..') !== false || strpos(ltrim($relative, '/'), 'uploads/payments/') !== 0) {
    log_error('Blocked suspicious payment proof path: ' . $relative);
    http_response_code(403);
    exit('That file is not available.');
}

$full = APP_ROOT . '/' . ltrim($relative, '/');
if (!is_file($full)) {
    http_response_code(404);
    exit('The receipt image is missing from the server.');
}

$info = @getimagesize($full);
if ($info === false || empty($info['mime'])) {
    http_response_code(415);
    exit('That file is not a readable image.');
}

// Only allow known image mime types to be served.
$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($info['mime'], $allowed, true)) {
    http_response_code(415);
    exit('That file type cannot be displayed.');
}

header('Content-Type: ' . $info['mime']);
header('Content-Length: ' . filesize($full));
header('Content-Disposition: inline; filename="receipt-' . (int) $payment['id'] . '.' . image_type_to_extension($info[2], false) . '"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($full);
exit;
