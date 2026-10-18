<?php
/**
 * Checkout.
 *
 * Server-side rules enforced here:
 *   - the customer must be signed in, so orders always belong to an account;
 *   - prices, totals, shipping and discounts are recalculated from the database
 *     and never taken from the submitted form;
 *   - stock is re-checked inside a database transaction and cannot be oversold;
 *   - the order, its items, its stock movement and its payment record are
 *     written together, or not at all.
 */
require_once __DIR__ . '/includes/bootstrap.php';

$user = require_login('Please sign in before checking out — that way only you can see your orders.');

$allItems = cart_items();
if (is_post() && post('action') === 'place_order') {
    csrf_guard();
    $postedIds = post_raw('checkout_ids', []);
    $postedQuantities = post_raw('checkout_quantities', []);
    $ids = [];
    if (is_array($postedIds)) {
        foreach ($postedIds as $id) {
            if (!is_scalar($id) || !ctype_digit((string) $id) || (int) $id <= 0) {
                flash_error('Your checkout selection is invalid. Please select your items again.');
                redirect('cart.php');
            }
            $ids[(int) $id] = true;
        }
    }
    $items = array_values(array_filter($allItems, function ($item) use ($ids) {
        return isset($ids[(int) $item['product']['id']]);
    }));
    if (count($items) !== count($ids) || !is_array($postedQuantities)) {
        flash_error('Your cart changed. Please review and select your items again.');
        redirect('cart.php');
    }
    foreach ($items as $item) {
        $pid = (int) $item['product']['id'];
        $postedQty = $postedQuantities[$pid] ?? null;
        if (!is_scalar($postedQty) || !ctype_digit((string) $postedQty)
            || (int) $postedQty !== (int) $item['quantity']) {
            flash_error('An item quantity changed. Please review your cart before ordering.');
            redirect('cart.php');
        }
    }
} else {
    $items = cart_selected_items($allItems);
}
if (!$items) {
    flash_info('Select at least one available item in your cart before checking out.');
    redirect('cart.php');
}
foreach ($items as $item) {
    if (!$item['available'] || (int) $item['quantity'] <= 0) {
        flash_error('A selected item is no longer in stock. Please review your cart.');
        redirect('cart.php');
    }
}

/* --------------------------------------------------------- payment options */
$paymentOptions = [];
if (setting_bool('cod_enabled', true)) {
    $paymentOptions['cod'] = [
        'label' => 'Cash on delivery (COD)',
        'desc' => 'Pay the courier in cash when your parcel arrives. Please prepare the exact amount.',
    ];
}
if (setting_bool('gcash_enabled', true)) {
    $paymentOptions['gcash'] = [
        'label' => 'GCash transfer (manual verification)',
        'desc' => 'Send the total to our GCash number, then enter the reference number from your receipt. A bookseller verifies it by hand.',
    ];
}
if (setting_bool('pickup_enabled', true)) {
    $paymentOptions['pickup'] = [
        'label' => 'Pay and pick up in store',
        'desc' => 'No shipping fee. Collect and pay at the counter once we confirm your order is ready.',
    ];
}
if (!$paymentOptions) {
    $paymentOptions['cod'] = ['label' => 'Cash on delivery (COD)', 'desc' => 'Pay the courier when your parcel arrives.'];
}

$addresses = fetch_all('SELECT * FROM user_addresses WHERE user_id = :uid ORDER BY is_default DESC, id DESC',
    ['uid' => $user['id']]);

$errors = [];
$form = [
    'address_id' => $addresses ? (string) $addresses[0]['id'] : 'new',
    'recipient_name' => trim(user_full_name()),
    'phone' => (string) ($user['phone'] ?? ''),
    'address_line1' => '',
    'address_line2' => '',
    'city' => '',
    'province' => '',
    'postal_code' => '',
    'delivery_notes' => '',
    'payment_method' => array_key_first($paymentOptions),
    'gcash_reference' => '',
    'payer_name' => '',
    'save_address' => '1',
];

if (is_post() && post('action') === 'place_order') {
    csrf_guard();

    foreach (['recipient_name' => 120, 'phone' => 30, 'address_line1' => 180, 'address_line2' => 180,
              'city' => 100, 'province' => 100, 'postal_code' => 12, 'delivery_notes' => 500,
              'gcash_reference' => 80, 'payer_name' => 160] as $key => $max) {
        $form[$key] = truncate(post_raw($key), $max);
    }
    $form['address_id'] = post('address_id', 'new');
    $form['payment_method'] = post('payment_method', 'cod');
    $form['save_address'] = checkbox_post('save_address') ? '1' : '';

    if (!array_key_exists($form['payment_method'], $paymentOptions)) {
        $errors[] = 'Please choose one of the available payment methods.';
        $form['payment_method'] = array_key_first($paymentOptions);
    }

    // Resolve the delivery address: a saved one, or the typed-in one.
    $useSaved = $form['address_id'] !== 'new' && ctype_digit((string) $form['address_id']);
    $saved = null;
    if ($useSaved) {
        $saved = fetch_one('SELECT * FROM user_addresses WHERE id = :id AND user_id = :uid',
            ['id' => (int) $form['address_id'], 'uid' => $user['id']]);
        if (!$saved) {
            $errors[] = 'That saved address could not be found. Please enter the delivery address again.';
            $useSaved = false;
        }
    }

    if ($useSaved && $form['payment_method'] !== 'pickup') {
        $shipping = [
            'recipient_name' => (string) $saved['recipient_name'],
            'phone' => (string) $saved['phone'],
            'address_line1' => (string) $saved['address_line1'],
            'address_line2' => (string) ($saved['address_line2'] ?? ''),
            'city' => (string) $saved['city'],
            'province' => (string) $saved['province'],
            'postal_code' => (string) ($saved['postal_code'] ?? ''),
        ];
    } else {
        if ($form['recipient_name'] === '') { $errors[] = 'Please enter the name of the person receiving the parcel.'; }
        if (!is_valid_phone($form['phone'])) { $errors[] = 'Please enter a valid mobile number, for example 09171234567.'; }
        if ($form['payment_method'] !== 'pickup') {
            if ($form['address_line1'] === '') { $errors[] = 'Please enter the street address, house or unit number.'; }
            if ($form['city'] === '') { $errors[] = 'Please enter the city or municipality.'; }
            if ($form['province'] === '') { $errors[] = 'Please enter the province.'; }
        }
        $shipping = [
            'recipient_name' => $form['recipient_name'],
            'phone' => $form['phone'],
            'address_line1' => $form['address_line1'] !== '' ? $form['address_line1'] : 'Store pickup',
            'address_line2' => $form['address_line2'],
            'city' => $form['city'] !== '' ? $form['city'] : (string) setting('pickup_address', 'Store pickup'),
            'province' => $form['province'] !== '' ? $form['province'] : 'Pickup',
            'postal_code' => $form['postal_code'],
        ];
    }

    $referenceDigits = preg_replace('/\s+/', '', $form['gcash_reference']);
    if ($form['payment_method'] === 'gcash' && $referenceDigits !== '' && strlen((string) $referenceDigits) < 6) {
        $errors[] = 'That GCash reference looks too short. Copy the 13-digit reference from your GCash receipt, or leave it blank and add it later from your order history.';
    }

    if (!$errors) {
        $pdo = db();
        $orderId = 0;
        $orderNumber = '';
        try {
            $pdo->beginTransaction();

            $ids = [];
            foreach ($items as $item) { $ids[] = (int) $item['product']['id']; }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $locked = fetch_all('SELECT * FROM products WHERE id IN (' . $placeholders . ') AND is_active = 1 FOR UPDATE', $ids);
            $byId = [];
            foreach ($locked as $row) { $byId[(int) $row['id']] = $row; }

            $subtotal = 0.0;
            $orderLines = [];
            foreach ($items as $item) {
                $pid = (int) $item['product']['id'];
                if (!isset($byId[$pid])) {
                    throw new RuntimeException(e($item['product']['name']) . ' is no longer available. Please remove it from your cart.');
                }
                $dbProduct = $byId[$pid];
                $qty = (int) $item['quantity'];
                if ((int) $dbProduct['stock_quantity'] < $qty) {
                    throw new RuntimeException('Only ' . (int) $dbProduct['stock_quantity'] . ' of ' . e($dbProduct['name'])
                        . ' remain in stock. Please adjust your cart and try again.');
                }
                $unit = (float) $dbProduct['price'];
                $line = round($unit * $qty, 2);
                $subtotal += $line;
                $orderLines[] = ['product' => $dbProduct, 'quantity' => $qty, 'unit_price' => $unit, 'line_total' => $line];
            }
            $subtotal = round($subtotal, 2);

            // The discount is validated a second time against the live database.
            $discountAmount = 0.0;
            $discountCode = null;
            $discountRow = null;
            $applied = applied_discount();
            if ($applied) {
                $check = discount_validate($applied['code'], $subtotal);
                if ($check['ok']) {
                    $discountAmount = (float) $check['discount'];
                    $discountCode = (string) $check['code']['code'];
                    $discountRow = $check['code'];
                } else {
                    throw new RuntimeException('Discount code problem: ' . e($check['message']) . ' Remove it from your cart and try again.');
                }
            }

            $shippingFee = shipping_fee_for($subtotal, $form['payment_method']);
            $total = max(0.0, round($subtotal - $discountAmount + $shippingFee, 2));

            $orderNumber = generate_order_number();
            $attempts = 0;
            while (fetch_value('SELECT id FROM orders WHERE order_number = :n', ['n' => $orderNumber]) && $attempts < 5) {
                $orderNumber = generate_order_number();
                $attempts++;
            }

            $hasReference = $form['payment_method'] === 'gcash' && $form['gcash_reference'] !== '';
            $initialPaymentStatus = $form['payment_method'] === 'gcash'
                ? ($hasReference ? 'pending_verification' : 'unpaid')
                : 'unpaid';

            $orderId = db_insert('orders', [
                'order_number' => $orderNumber,
                'user_id' => (int) $user['id'],
                'status' => 'pending',
                'payment_method' => $form['payment_method'],
                'payment_status' => $initialPaymentStatus,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'discount_code' => $discountCode,
                'shipping_fee' => $shippingFee,
                'total' => $total,
                'customer_name' => $shipping['recipient_name'],
                'email' => (string) $user['email'],
                'phone' => $shipping['phone'],
                'address_line1' => $shipping['address_line1'],
                'address_line2' => $shipping['address_line2'] !== '' ? $shipping['address_line2'] : null,
                'city' => $shipping['city'],
                'province' => $shipping['province'],
                'postal_code' => $shipping['postal_code'] !== '' ? $shipping['postal_code'] : null,
                'delivery_notes' => $form['delivery_notes'] !== '' ? $form['delivery_notes'] : null,
                'pickup_location' => $form['payment_method'] === 'pickup' ? (string) setting('pickup_address', 'Store counter') : null,
            ]);

            foreach ($orderLines as $line) {
                $product = $line['product'];
                db_insert('order_items', [
                    'order_id' => $orderId,
                    'product_id' => (int) $product['id'],
                    'product_name' => (string) $product['name'],
                    'product_sku' => (string) $product['sku'],
                    'product_image' => (string) ($product['image'] ?? ''),
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'line_total' => $line['line_total'],
                ]);

                // Conditional update: stock can never fall below zero, even when
                // two customers check out at the very same moment.
                $affected = q('UPDATE products SET stock_quantity = stock_quantity - :qty
                               WHERE id = :id AND stock_quantity >= :needed',
                    ['qty' => $line['quantity'], 'id' => (int) $product['id'], 'needed' => $line['quantity']])->rowCount();
                if ($affected !== 1) {
                    throw new RuntimeException('Stock changed while you were checking out. Please review your cart and try again.');
                }
            }

            $proofPath = null;
            if (!empty($_FILES['payment_proof']['name'])) {
                [$uploadOk, , $uploadedPath] = save_uploaded_image($_FILES['payment_proof'], 'payments');
                if ($uploadOk) { $proofPath = $uploadedPath; }
            }

            db_insert('payments', [
                'order_id' => $orderId,
                'method' => $form['payment_method'],
                'amount' => $total,
                'reference_number' => $hasReference ? $form['gcash_reference'] : null,
                'payer_name' => $form['payment_method'] === 'gcash' && $form['payer_name'] !== '' ? $form['payer_name'] : null,
                'proof_image' => $proofPath,
                'status' => $initialPaymentStatus,
                'customer_note' => $form['payment_method'] === 'gcash' ? 'Payment reference submitted at checkout.' : null,
            ]);

            if ($discountRow) {
                q('UPDATE discount_codes SET used_count = used_count + 1 WHERE id = :id', ['id' => $discountRow['id']]);
                db_insert('discount_redemptions', [
                    'code_id' => (int) $discountRow['id'],
                    'user_id' => (int) $user['id'],
                    'order_id' => $orderId,
                    'amount' => $discountAmount,
                ]);
            }

            if ($form['save_address'] === '1' && !$useSaved && $form['payment_method'] !== 'pickup') {
                db_insert('user_addresses', [
                    'user_id' => (int) $user['id'],
                    'label' => 'Saved at checkout',
                    'recipient_name' => $shipping['recipient_name'],
                    'phone' => $shipping['phone'],
                    'address_line1' => $shipping['address_line1'],
                    'address_line2' => $shipping['address_line2'] !== '' ? $shipping['address_line2'] : null,
                    'city' => $shipping['city'],
                    'province' => $shipping['province'],
                    'postal_code' => $shipping['postal_code'] !== '' ? $shipping['postal_code'] : null,
                    'is_default' => $addresses ? 0 : 1,
                ]);
            }

            // Remove only the products in this order, in the same transaction.
            foreach ($orderLines as $line) {
                cart_remove((int) $line['product']['id']);
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            if ($ex instanceof RuntimeException) {
                $errors[] = $ex->getMessage();
            } else {
                log_error('checkout failed: ' . $ex->getMessage() . ' in ' . $ex->getFile() . ':' . $ex->getLine());
                $errors[] = 'We could not complete your order just now. Nothing was charged — please try again in a moment.';
            }
        }

        if (!$errors) {
            unset($_SESSION['checkout_selections'][cart_selection_key()]);
            discount_clear();
            audit_log('order.created', 'order', $orderId, 'Order ' . $orderNumber . ' placed for ' . money($total));
            flash_success('Order ' . $orderNumber . ' has been received. Thank you!');
            redirect('order-success.php?order=' . urlencode($orderNumber));
        }
    }
}

$totals = cart_totals($form['payment_method'], $items);
$threshold = setting_float('free_shipping_threshold', 1500);
$remaining = max(0, $threshold - (float) $totals['subtotal']);
$applied = applied_discount();

render_header([
    'title' => 'Checkout',
    'description' => 'Confirm your delivery details and payment method.',
    'robots' => 'noindex, nofollow',
]);
render_breadcrumb([['label' => 'Home', 'url' => 'index.php'], ['label' => 'Cart', 'url' => 'cart.php'], ['label' => 'Checkout']]);
?>
<div class="container">
  <header class="page-head">
    <p class="eyebrow">Step 2 of 2</p>
    <h1>Checkout</h1>
    <p class="lede">Confirm where the parcel goes and how you would like to pay. Prices and stock are verified again
      the moment you place the order.</p>
  </header>

  <?php if ($errors): ?>
    <div class="notice notice-error" role="alert">
      <?= icon('alert', 'notice-icon', 18) ?>
      <div class="notice-body">
        <strong>We could not place your order yet</strong>
        <ul><?php foreach ($errors as $err): ?><li><?= $err ?></li><?php endforeach; ?></ul>
      </div>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= e(url('checkout.php')) ?>" enctype="multipart/form-data" class="checkout-layout">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="place_order">
    <?php foreach ($items as $checkoutItem): ?>
      <input type="hidden" name="checkout_ids[]" value="<?= (int) $checkoutItem['product']['id'] ?>">
      <input type="hidden" name="checkout_quantities[<?= (int) $checkoutItem['product']['id'] ?>]" value="<?= (int) $checkoutItem['quantity'] ?>">
    <?php endforeach; ?>

    <div>
      <div class="checkout-steps" aria-hidden="true">
        <span class="is-on">1. Delivery details</span>
        <span>2. Payment</span>
        <span>3. Confirm</span>
      </div>

      <div class="panel">
        <div class="panel-head"><h2>Where should we deliver?</h2></div>

        <?php if ($addresses): ?>
          <div class="address-choices">
            <?php foreach ($addresses as $address): ?>
              <label class="address-choice">
                <input type="radio" name="address_id" value="<?= (int) $address['id'] ?>"
                       <?= $form['address_id'] === (string) $address['id'] ? 'checked' : '' ?>>
                <span>
                  <span class="addr-name"><?= e($address['recipient_name']) ?><?= !empty($address['is_default']) ? ' · default' : '' ?></span>
                  <span class="addr-line"><?= e($address['address_line1']) ?><?= $address['address_line2'] ? ', ' . e($address['address_line2']) : '' ?><br>
                    <?= e($address['city']) ?>, <?= e($address['province']) ?><?= $address['postal_code'] ? ' ' . e($address['postal_code']) : '' ?><br>
                    <?= e($address['phone']) ?></span>
                </span>
              </label>
            <?php endforeach; ?>
            <label class="address-choice">
              <input type="radio" name="address_id" value="new" <?= $form['address_id'] === 'new' ? 'checked' : '' ?>>
              <span><span class="addr-name">Send to a new address</span>
                <span class="addr-line">Fill in the delivery details below.</span></span>
            </label>
          </div>
        <?php endif; ?>

        <div id="new-address-fields" class="mt-2">
          <h3>Delivery details</h3>
          <div class="form-grid">
            <div class="field-row">
              <div class="field">
                <label for="recipient_name">Recipient name</label>
                <input id="recipient_name" type="text" name="recipient_name" maxlength="120" value="<?= e($form['recipient_name']) ?>" autocomplete="name">
              </div>
              <div class="field">
                <label for="phone">Mobile number</label>
                <input id="phone" type="tel" name="phone" maxlength="30" value="<?= e($form['phone']) ?>" placeholder="09171234567" autocomplete="tel">
                <span class="hint">The courier will call or text this number.</span>
              </div>
            </div>
            <div class="field">
              <label for="address_line1">Street address, house or unit number</label>
              <input id="address_line1" type="text" name="address_line1" maxlength="180" value="<?= e($form['address_line1']) ?>" autocomplete="address-line1">
            </div>
            <div class="field">
              <label for="address_line2">Barangay, building or landmark <span class="hint">(optional)</span></label>
              <input id="address_line2" type="text" name="address_line2" maxlength="180" value="<?= e($form['address_line2']) ?>" autocomplete="address-line2">
            </div>
            <div class="field-row">
              <div class="field">
                <label for="city">City or municipality</label>
                <input id="city" type="text" name="city" maxlength="100" value="<?= e($form['city']) ?>" autocomplete="address-level2">
              </div>
              <div class="field">
                <label for="province">Province</label>
                <input id="province" type="text" name="province" maxlength="100" value="<?= e($form['province']) ?>" autocomplete="address-level1">
              </div>
            </div>
            <div class="field-row">
              <div class="field">
                <label for="postal_code">Postal code <span class="hint">(optional)</span></label>
                <input id="postal_code" type="text" name="postal_code" maxlength="12" value="<?= e($form['postal_code']) ?>" autocomplete="postal-code" inputmode="numeric">
              </div>
              <div class="field">
                <label for="delivery_notes">Delivery notes <span class="hint">(optional)</span></label>
                <input id="delivery_notes" type="text" name="delivery_notes" maxlength="500" value="<?= e($form['delivery_notes']) ?>"
                       placeholder="Gate code, preferred time, nearby landmark">
              </div>
            </div>
            <div class="check">
              <input id="save_address" type="checkbox" name="save_address" value="1" <?= $form['save_address'] === '1' ? 'checked' : '' ?>>
              <label for="save_address">Save this address to my account for next time.</label>
            </div>
          </div>
        </div>
      </div>

      <div class="panel">
        <div class="panel-head"><h2>How would you like to pay?</h2></div>
        <?php foreach ($paymentOptions as $value => $option): ?>
          <label class="pay-option">
            <input type="radio" name="payment_method" value="<?= e($value) ?>" <?= $form['payment_method'] === $value ? 'checked' : '' ?>>
            <span>
              <strong><?= e($option['label']) ?></strong>
              <span class="pay-desc"><?= e($option['desc']) ?></span>
            </span>
          </label>

          <?php if ($value === 'gcash'): ?>
            <div class="pay-detail" data-pay-detail="gcash">
              <div class="pay-instructions">
                <p><strong>Send the order total to:</strong></p>
                <dl>
                  <dt>GCash account name</dt><dd><?= e(setting('gcash_account_name', 'Aklatan Market')) ?></dd>
                  <dt>GCash number</dt><dd><?= e(setting('gcash_account_number', '—')) ?>
                    <?php if (setting('gcash_account_number')): ?>
                      <button type="button" class="btn btn-quiet btn-sm" data-copy="<?= e(setting('gcash_account_number')) ?>">Copy</button>
                    <?php endif; ?>
                  </dd>
                </dl>
                <p class="small mt-1"><?= e(setting('gcash_instructions', '')) ?></p>
                <p class="small mt-1"><strong><?= e(setting('payment_note', 'We never ask for your GCash PIN or OTP.')) ?></strong></p>
              </div>
              <div class="form-grid mt-2">
                <div class="field-row">
                  <div class="field">
                    <label for="gcash_reference">GCash reference number <span class="hint">(you may add this later)</span></label>
                    <input id="gcash_reference" type="text" name="gcash_reference" maxlength="80"
                           value="<?= e($form['gcash_reference']) ?>" placeholder="e.g. 0031 550 118 442" autocomplete="off">
                  </div>
                  <div class="field">
                    <label for="payer_name">Name on the GCash account <span class="hint">(optional)</span></label>
                    <input id="payer_name" type="text" name="payer_name" maxlength="160" value="<?= e($form['payer_name']) ?>" autocomplete="off">
                  </div>
                </div>
                <div class="field">
                  <label for="payment_proof">Screenshot of your receipt <span class="hint">(optional, JPG/PNG up to <?= e(human_bytes((int) UPLOAD_MAX_BYTES)) ?>)</span></label>
                  <input id="payment_proof" type="file" name="payment_proof" accept="image/jpeg,image/png,image/gif,image/webp" data-preview="proof-preview">
                  <img id="proof-preview" alt="Preview of the receipt you selected" hidden style="max-width:220px;margin-top:8px;border-radius:10px">
                </div>
                <p class="notice notice-info">
                  <?= icon('info', 'notice-icon', 18) ?>
                  <span class="notice-body">Manual payments are checked by a person, usually within one working day.
                    Your order stays <em>pending verification</em> until a bookseller confirms the transfer.</span>
                </p>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($value === 'pickup'): ?>
            <div class="pay-detail" data-pay-detail="pickup">
              <div class="pay-instructions">
                <p><strong>Collection point</strong></p>
                <p><?= e(setting('pickup_address', '')) ?></p>
                <p class="small mt-1"><?= e(setting('pickup_instructions', '')) ?></p>
              </div>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>

    <aside>
      <div class="summary-card sticky-summary">
        <h2>Your selected items</h2>
        <p class="summary-note">Only these items will be ordered. Other items stay in your cart.</p>
        <?php foreach ($items as $item): ?>
          <div class="order-item-mini">
            <img src="<?= e(product_image_url($item['product'])) ?>" alt="" width="400" height="600" loading="lazy">
            <div class="oi-body">
              <p class="oi-name"><?= e($item['product']['name']) ?></p>
              <p class="oi-meta"><?= (int) $item['quantity'] ?> × <?= e(money($item['product']['price'])) ?></p>
            </div>
            <span class="oi-price"><?= e(money($item['line_total'])) ?></span>
          </div>
        <?php endforeach; ?>

        <div class="summary-lines mt-2">
          <div><span>Subtotal</span><span><?= e(money($totals['subtotal'])) ?></span></div>
          <?php if ($totals['discount'] > 0): ?>
            <div><span>Discount<?= $applied ? ' (' . e($applied['code']) . ')' : '' ?></span>
              <span class="stock-ok">−<?= e(money($totals['discount'])) ?></span></div>
          <?php endif; ?>
          <div><span>Shipping</span><span><?= $totals['shipping'] > 0 ? e(money($totals['shipping'])) : 'Free' ?></span></div>
        </div>
        <div class="summary-lines summary-total">
          <span>Total</span><span class="price"><?= e(money($totals['total'])) ?></span>
        </div>

        <?php if ($remaining > 0 && $threshold > 0): ?>
          <p class="summary-note">Spend <?= e(money($remaining)) ?> more to unlock free shipping.</p>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary btn-block btn-lg mt-2">
          <span id="place-order-label">Place order</span>
        </button>
        <p class="summary-note">
          By placing this order you agree to the <a href="<?= e(url('privacy.php')) ?>">privacy notice</a> and the
          <a href="<?= e(url('shipping-returns.php')) ?>">shipping &amp; returns</a> terms. The final total is
          recalculated on the server before the order is saved.
        </p>
        <p class="mt-1"><a class="back-link" href="<?= e(url('cart.php')) ?>"><?= icon('chevron-left', '', 15) ?> Back to cart</a></p>
      </div>
    </aside>
  </form>
</div>
<?php render_footer(); ?>
