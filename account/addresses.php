<?php
/** Saved delivery addresses: add, edit, delete and set a default. */
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_login('Please sign in to manage your delivery addresses.');
$userId = (int) $user['id'];

$errors = [];
$editing = null;
$values = ['label' => 'Home', 'recipient_name' => trim(user_full_name()), 'phone' => (string) ($user['phone'] ?? ''),
           'address_line1' => '', 'address_line2' => '', 'city' => '', 'province' => '', 'postal_code' => '',
           'delivery_notes' => '', 'is_default' => '0'];

if (is_post()) {
    csrf_guard();
    $action = post('action');

    if ($action === 'save') {
        $addressId = post_int('address_id');
        foreach (['label' => 60, 'recipient_name' => 120, 'phone' => 30, 'address_line1' => 180, 'address_line2' => 180,
                  'city' => 100, 'province' => 100, 'postal_code' => 12, 'delivery_notes' => 255] as $key => $max) {
            $values[$key] = truncate(post_raw($key), $max);
        }
        $values['is_default'] = checkbox_post('is_default') ? '1' : '0';

        if ($values['recipient_name'] === '') { $errors[] = 'Please enter the recipient name.'; }
        if (!is_valid_phone($values['phone'])) { $errors[] = 'Please enter a valid mobile number.'; }
        if ($values['address_line1'] === '') { $errors[] = 'Please enter the street address.'; }
        if ($values['city'] === '') { $errors[] = 'Please enter the city or municipality.'; }
        if ($values['province'] === '') { $errors[] = 'Please enter the province.'; }
        if ($values['label'] === '') { $values['label'] = 'Address'; }

        $existing = $addressId > 0
            ? fetch_one('SELECT * FROM user_addresses WHERE id = :id AND user_id = :uid', ['id' => $addressId, 'uid' => $userId])
            : null;
        if ($addressId > 0 && !$existing) { $errors[] = 'That address does not belong to your account.'; }

        if (!$errors) {
            $data = [
                'label' => $values['label'],
                'recipient_name' => $values['recipient_name'],
                'phone' => $values['phone'],
                'address_line1' => $values['address_line1'],
                'address_line2' => $values['address_line2'] !== '' ? $values['address_line2'] : null,
                'city' => $values['city'],
                'province' => $values['province'],
                'postal_code' => $values['postal_code'] !== '' ? $values['postal_code'] : null,
                'delivery_notes' => $values['delivery_notes'] !== '' ? $values['delivery_notes'] : null,
                'is_default' => $values['is_default'] === '1' ? 1 : 0,
            ];
            if ($existing) {
                db_update('user_addresses', $data, 'id', (int) $existing['id']);
                $savedId = (int) $existing['id'];
            } else {
                $data['user_id'] = $userId;
                $savedId = db_insert('user_addresses', $data);
            }
            if ($data['is_default'] === 1) {
                q('UPDATE user_addresses SET is_default = 0 WHERE user_id = :uid AND id <> :id', ['uid' => $userId, 'id' => $savedId]);
            }
            audit_log('customer.address_saved', 'user_address', $savedId);
            flash_success($existing ? 'Address updated.' : 'Address saved.');
            redirect('account/addresses.php');
        }
        $editing = $addressId > 0 ? ['id' => $addressId] : ['id' => 0];
    }

    if ($action === 'delete') {
        $addressId = post_int('address_id');
        $existing = fetch_one('SELECT * FROM user_addresses WHERE id = :id AND user_id = :uid', ['id' => $addressId, 'uid' => $userId]);
        if ($existing) {
            q('DELETE FROM user_addresses WHERE id = :id AND user_id = :uid', ['id' => $addressId, 'uid' => $userId]);
            if (!empty($existing['is_default'])) {
                $next = fetch_one('SELECT id FROM user_addresses WHERE user_id = :uid ORDER BY id ASC LIMIT 1', ['uid' => $userId]);
                if ($next) { db_update('user_addresses', ['is_default' => 1], 'id', (int) $next['id']); }
            }
            audit_log('customer.address_deleted', 'user_address', $addressId);
            flash_success('Address removed.');
        } else {
            flash_error('That address could not be found.');
        }
        redirect('account/addresses.php');
    }

    if ($action === 'make_default') {
        $addressId = post_int('address_id');
        $existing = fetch_one('SELECT * FROM user_addresses WHERE id = :id AND user_id = :uid', ['id' => $addressId, 'uid' => $userId]);
        if ($existing) {
            q('UPDATE user_addresses SET is_default = 0 WHERE user_id = :uid', ['uid' => $userId]);
            db_update('user_addresses', ['is_default' => 1], 'id', $addressId);
            flash_success('Default delivery address updated.');
        }
        redirect('account/addresses.php');
    }
}

// Edit mode. ?edit=0 opens the blank "add a new address" form.
if (array_key_exists('edit', $_GET)) {
    $editId = get_int('edit');
    if ($editId > 0) {
        $record = fetch_one('SELECT * FROM user_addresses WHERE id = :id AND user_id = :uid', ['id' => $editId, 'uid' => $userId]);
        if ($record) {
            $editing = $record;
            foreach (array_keys($values) as $key) {
                if (isset($record[$key])) { $values[$key] = (string) ($record[$key] ?? ''); }
            }
            $values['is_default'] = !empty($record['is_default']) ? '1' : '0';
        }
    } else {
        $editing = ['id' => 0];
    }
}

$addresses = fetch_all('SELECT * FROM user_addresses WHERE user_id = :uid ORDER BY is_default DESC, id DESC', ['uid' => $userId]);
// Open the blank form automatically when the customer has nothing saved yet.
if (!$editing && !$addresses) { $editing = ['id' => 0]; }

render_account_start([
    'current' => 'addresses',
    'title' => 'Delivery addresses',
    'eyebrow' => 'Where we send your books',
    'description' => 'Save the places you order to most often. At checkout you can pick one with a single tap.',
    'breadcrumb' => [['label' => 'Home', 'url' => 'index.php'], ['label' => 'My account', 'url' => 'account/index.php'], ['label' => 'Addresses']],
]);
?>
<div class="panel">
  <div class="panel-head">
    <h2><?= $editing ? ($editing['id'] > 0 ? 'Edit address' : 'Add a new address') : 'Your saved addresses' ?></h2>
    <?php if ($editing): ?>
      <a class="btn btn-quiet btn-sm" href="<?= e(url('account/addresses.php')) ?>">Cancel edit</a>
    <?php endif; ?>
  </div>

  <?php if ($errors): ?>
    <div class="notice notice-error" role="alert">
      <?= icon('alert', 'notice-icon', 18) ?>
      <div class="notice-body"><strong>Please check the form</strong>
        <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    </div>
  <?php endif; ?>

  <?php if ($editing): ?>
    <form method="post" action="<?= e(url('account/addresses.php')) ?>" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="address_id" value="<?= (int) ($editing['id'] ?? 0) ?>">
      <div class="field-row">
        <div class="field">
          <label for="label">Label</label>
          <input id="label" type="text" name="label" maxlength="60" value="<?= e($values['label']) ?>" placeholder="Home, Office, Dorm">
        </div>
        <div class="field">
          <label for="recipient_name">Recipient name</label>
          <input id="recipient_name" type="text" name="recipient_name" required maxlength="120" value="<?= e($values['recipient_name']) ?>">
        </div>
      </div>
      <div class="field-row">
        <div class="field">
          <label for="phone">Mobile number</label>
          <input id="phone" type="tel" name="phone" required maxlength="30" value="<?= e($values['phone']) ?>" placeholder="09171234567">
        </div>
        <div class="field">
          <label for="postal_code">Postal code <span class="hint">(optional)</span></label>
          <input id="postal_code" type="text" name="postal_code" maxlength="12" value="<?= e($values['postal_code']) ?>" inputmode="numeric">
        </div>
      </div>
      <div class="field">
        <label for="address_line1">Street address, house or unit number</label>
        <input id="address_line1" type="text" name="address_line1" required maxlength="180" value="<?= e($values['address_line1']) ?>">
      </div>
      <div class="field">
        <label for="address_line2">Barangay, building or landmark <span class="hint">(optional)</span></label>
        <input id="address_line2" type="text" name="address_line2" maxlength="180" value="<?= e($values['address_line2']) ?>">
      </div>
      <div class="field-row">
        <div class="field">
          <label for="city">City or municipality</label>
          <input id="city" type="text" name="city" required maxlength="100" value="<?= e($values['city']) ?>">
        </div>
        <div class="field">
          <label for="province">Province</label>
          <input id="province" type="text" name="province" required maxlength="100" value="<?= e($values['province']) ?>">
        </div>
      </div>
      <div class="field">
        <label for="delivery_notes">Standing delivery note <span class="hint">(optional)</span></label>
        <input id="delivery_notes" type="text" name="delivery_notes" maxlength="255" value="<?= e($values['delivery_notes']) ?>"
               placeholder="For example: leave with the guard, or call on arrival">
      </div>
      <div class="check">
        <input id="is_default" type="checkbox" name="is_default" value="1" <?= $values['is_default'] === '1' ? 'checked' : '' ?>>
        <label for="is_default">Use this as my default delivery address.</label>
      </div>
      <button type="submit" class="btn btn-primary"><?= (int) ($editing['id'] ?? 0) > 0 ? 'Save address' : 'Add address' ?></button>
    </form>
  <?php else: ?>
    <p class="muted">No address selected. Choose “Edit” on a saved address below, or add a new one.</p>
    <p><a class="btn btn-primary" href="<?= e(url('account/addresses.php?edit=0')) ?>"><?= icon('plus', '', 17) ?> Add a new address</a></p>
  <?php endif; ?>
</div>

<div class="panel">
  <div class="panel-head"><h2>Saved addresses (<?= count($addresses) ?>)</h2></div>
  <?php if (!$addresses): ?>
    <?php render_empty_state('pin', 'No addresses saved yet',
        'Add the address you order to most often and checkout becomes a two-tap job.',
        [['label' => 'Add my first address', 'url' => url('account/addresses.php?edit=0')]]); ?>
  <?php else: ?>
    <div class="address-grid">
      <?php foreach ($addresses as $address): ?>
        <div class="address-card <?= !empty($address['is_default']) ? 'is-default' : '' ?>">
          <p class="addr-name"><?= e($address['label']) ?><?= !empty($address['is_default']) ? ' <span class="pill pill-ok">Default</span>' : '' ?></p>
          <p class="addr-line"><strong><?= e($address['recipient_name']) ?></strong><br>
            <?= e($address['address_line1']) ?><?= $address['address_line2'] ? ', ' . e($address['address_line2']) : '' ?><br>
            <?= e($address['city']) ?>, <?= e($address['province']) ?><?= $address['postal_code'] ? ' ' . e($address['postal_code']) : '' ?><br>
            <?= e($address['phone']) ?></p>
          <?php if (!empty($address['delivery_notes'])): ?>
            <p class="tiny muted">Note: <?= e($address['delivery_notes']) ?></p>
          <?php endif; ?>
          <div class="cluster mt-1">
            <a class="btn btn-quiet btn-sm" href="<?= e(url('account/addresses.php?edit=' . (int) $address['id'])) ?>"><?= icon('edit', '', 15) ?> Edit</a>
            <?php if (empty($address['is_default'])): ?>
              <form method="post" action="<?= e(url('account/addresses.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="make_default">
                <input type="hidden" name="address_id" value="<?= (int) $address['id'] ?>">
                <button type="submit" class="btn btn-quiet btn-sm"><?= icon('check', '', 15) ?> Make default</button>
              </form>
            <?php endif; ?>
            <form method="post" action="<?= e(url('account/addresses.php')) ?>" data-confirm="Remove this address?">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="address_id" value="<?= (int) $address['id'] ?>">
              <button type="submit" class="btn btn-quiet btn-sm" style="color:var(--danger)"><?= icon('trash', '', 15) ?> Remove</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php render_account_end(); ?>
