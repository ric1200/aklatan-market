<?php
/** Discount codes: create, edit, pause and delete. Validation happens at checkout. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/admin-header.php';
require_admin();

$errors = [];
$editing = null;
$form = ['code' => '', 'description' => '', 'type' => 'percent', 'value' => '', 'min_subtotal' => '0',
         'max_uses' => '', 'per_user_limit' => '1', 'starts_at' => '', 'expires_at' => '', 'is_active' => '1'];

if (is_post()) {
    csrf_guard();
    $action = post('action');

    if ($action === 'save') {
        $codeId = post_int('code_id');
        $form['code'] = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', (string) post_raw('code')));
        $form['description'] = truncate(post_raw('description'), 200);
        $form['type'] = post('type') === 'fixed' ? 'fixed' : 'percent';
        $form['value'] = post('value');
        $form['min_subtotal'] = post('min_subtotal') !== '' ? post('min_subtotal') : '0';
        $form['max_uses'] = post('max_uses') !== '' ? (string) max(1, post_int('max_uses')) : '';
        $form['per_user_limit'] = post('per_user_limit') !== '' ? (string) max(1, post_int('per_user_limit')) : '';
        $form['starts_at'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) post('starts_at')) ? (string) post('starts_at') : '';
        $form['expires_at'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) post('expires_at')) ? (string) post('expires_at') : '';
        $form['is_active'] = checkbox_post('is_active') ? '1' : '0';

        if ($form['code'] === '') { $errors[] = 'Please enter a code, for example WELCOME10.'; }
        if (!is_numeric($form['value']) || (float) $form['value'] <= 0) { $errors[] = 'Please enter a discount value greater than zero.'; }
        if ($form['type'] === 'percent' && (float) $form['value'] > 90) { $errors[] = 'A percentage discount above 90% is almost certainly a mistake.'; }
        if (!is_numeric($form['min_subtotal'])) { $form['min_subtotal'] = '0'; }
        if ($form['starts_at'] !== '' && $form['expires_at'] !== '' && strtotime($form['starts_at']) > strtotime($form['expires_at'])) {
            $errors[] = 'The expiry date is before the start date.';
        }
        $duplicate = fetch_value('SELECT id FROM discount_codes WHERE code = :code' . ($codeId ? ' AND id <> :id' : ''),
            $codeId ? ['code' => $form['code'], 'id' => $codeId] : ['code' => $form['code']]);
        if ($duplicate) { $errors[] = 'A discount code with that spelling already exists.'; }
        $existing = $codeId > 0 ? fetch_one('SELECT * FROM discount_codes WHERE id = :id', ['id' => $codeId]) : null;
        if ($codeId > 0 && !$existing) { $errors[] = 'That discount code could not be found.'; }

        if (!$errors) {
            $data = [
                'code' => $form['code'],
                'description' => $form['description'] !== '' ? $form['description'] : null,
                'type' => $form['type'],
                'value' => round((float) $form['value'], 2),
                'min_subtotal' => round((float) $form['min_subtotal'], 2),
                'max_uses' => $form['max_uses'] !== '' ? (int) $form['max_uses'] : null,
                'per_user_limit' => $form['per_user_limit'] !== '' ? (int) $form['per_user_limit'] : null,
                'starts_at' => $form['starts_at'] !== '' ? $form['starts_at'] . ' 00:00:00' : null,
                'expires_at' => $form['expires_at'] !== '' ? $form['expires_at'] . ' 23:59:59' : null,
                'is_active' => (int) $form['is_active'],
            ];
            if ($existing) {
                db_update('discount_codes', $data, 'id', $codeId);
                audit_log('discount.updated', 'discount_code', $codeId, 'Updated ' . $data['code'] . ' (' . discount_label($data) . ')');
                flash_success($data['code'] . ' was updated.');
            } else {
                $newId = db_insert('discount_codes', $data);
                audit_log('discount.created', 'discount_code', $newId, 'Created ' . $data['code'] . ' (' . discount_label($data) . ')');
                flash_success($data['code'] . ' is ready to use.');
            }
            redirect('admin/discount-codes.php');
        }
        $editing = ['id' => $codeId];
    }

    if ($action === 'toggle' || $action === 'delete') {
        $record = fetch_one('SELECT * FROM discount_codes WHERE id = :id', ['id' => post_int('code_id')]);
        if (!$record) {
            flash_error('That discount code could not be found.');
            redirect('admin/discount-codes.php');
        }
        if ($action === 'toggle') {
            $state = (int) $record['is_active'] === 1 ? 0 : 1;
            db_update('discount_codes', ['is_active' => $state], 'id', (int) $record['id']);
            audit_log('discount.' . ($state ? 'activated' : 'paused'), 'discount_code', (int) $record['id'], $record['code']);
            flash_success($record['code'] . ' is now ' . ($state ? 'active.' : 'paused.'));
        } else {
            $used = (int) $record['used_count'];
            q('DELETE FROM discount_codes WHERE id = :id', ['id' => (int) $record['id']]);
            audit_log('discount.deleted', 'discount_code', (int) $record['id'], $record['code'] . ' deleted after ' . $used . ' use(s)');
            flash_success($record['code'] . ' was deleted. Past orders keep the code they used.');
        }
        redirect('admin/discount-codes.php');
    }
}

if (array_key_exists('edit', $_GET)) {
    $editId = get_int('edit');
    if ($editId > 0) {
        $record = fetch_one('SELECT * FROM discount_codes WHERE id = :id', ['id' => $editId]);
        if ($record) {
            $editing = $record;
            foreach (array_keys($form) as $key) {
                if (isset($record[$key])) { $form[$key] = (string) ($record[$key] ?? ''); }
            }
            $form['starts_at'] = substr((string) $record['starts_at'], 0, 10);
            $form['expires_at'] = substr((string) $record['expires_at'], 0, 10);
            $form['is_active'] = !empty($record['is_active']) ? '1' : '0';
        }
    } else {
        $editing = ['id' => 0];
    }
}

$codes = fetch_all('SELECT * FROM discount_codes ORDER BY is_active DESC, created_at DESC');
$redemptionStats = [];
foreach (fetch_all('SELECT code_id, COUNT(*) AS c, COALESCE(SUM(amount),0) AS t FROM discount_redemptions GROUP BY code_id') as $row) {
    $redemptionStats[(int) $row['code_id']] = ['uses' => (int) $row['c'], 'value' => (float) $row['t']];
}

render_admin_start([
    'current' => 'discounts',
    'title' => 'Discount codes',
    'subtitle' => count($codes) . ' code(s). Every code is re-validated on the server at checkout — expiry, usage and minimum basket included.',
    'crumbs' => [['label' => 'Dashboard', 'url' => 'admin/index.php'], ['label' => 'Discount codes']],
    'actions' => '<a class="btn btn-primary btn-sm" href="' . e(url('admin/discount-codes.php?edit=0')) . '">' . icon('plus', '', 16) . ' New code</a>',
]);
?>
<?php if ($errors): ?>
  <div class="notice notice-error" role="alert">
    <?= icon('alert', 'notice-icon', 18) ?>
    <div class="notice-body"><strong>Please fix the following</strong>
      <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
  </div>
<?php endif; ?>

<?php if ($editing): ?>
  <div class="admin-panel">
    <div class="admin-panel-head">
      <h2><?= (int) ($editing['id'] ?? 0) > 0 ? 'Edit code' : 'New discount code' ?></h2>
      <a class="btn btn-quiet btn-xs" href="<?= e(url('admin/discount-codes.php')) ?>">Close form</a>
    </div>
    <div class="admin-panel-body">
      <form method="post" action="<?= e(url('admin/discount-codes.php')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="code_id" value="<?= (int) ($editing['id'] ?? 0) ?>">
        <div class="field-row">
          <div class="field">
            <label for="code">Code</label>
            <input id="code" type="text" name="code" required maxlength="40" value="<?= e($form['code']) ?>" placeholder="WELCOME10">
            <span class="hint">Letters, numbers and dashes only. Customers type it in the cart.</span>
          </div>
          <div class="field">
            <label for="description">Description <span class="hint">(internal)</span></label>
            <input id="description" type="text" name="description" maxlength="200" value="<?= e($form['description']) ?>">
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="type">Type</label>
            <select id="type" name="type">
              <option value="percent" <?= $form['type'] === 'percent' ? 'selected' : '' ?>>Percentage off</option>
              <option value="fixed" <?= $form['type'] === 'fixed' ? 'selected' : '' ?>>Fixed amount off (₱)</option>
            </select>
          </div>
          <div class="field">
            <label for="value">Value</label>
            <input id="value" type="number" name="value" step="0.01" min="0" required value="<?= e($form['value']) ?>">
            <span class="hint">10 means 10% for a percentage code, or ₱10 for a fixed code.</span>
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="min_subtotal">Minimum basket (₱)</label>
            <input id="min_subtotal" type="number" name="min_subtotal" step="0.01" min="0" value="<?= e($form['min_subtotal']) ?>">
          </div>
          <div class="field">
            <label for="max_uses">Maximum total uses <span class="hint">(blank = unlimited)</span></label>
            <input id="max_uses" type="number" name="max_uses" min="1" value="<?= e($form['max_uses']) ?>">
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="per_user_limit">Uses per customer</label>
            <input id="per_user_limit" type="number" name="per_user_limit" min="1" value="<?= e($form['per_user_limit']) ?>">
          </div>
          <div class="field">
            <label for="starts_at">Starts on <span class="hint">(optional)</span></label>
            <input id="starts_at" type="date" name="starts_at" value="<?= e($form['starts_at']) ?>">
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="expires_at">Expires on <span class="hint">(optional)</span></label>
            <input id="expires_at" type="date" name="expires_at" value="<?= e($form['expires_at']) ?>">
          </div>
          <div class="field" style="justify-content:flex-end">
            <div class="check"><input id="is_active" type="checkbox" name="is_active" value="1" <?= $form['is_active'] === '1' ? 'checked' : '' ?>>
              <label for="is_active">Active</label></div>
          </div>
        </div>
        <div class="cluster">
          <button type="submit" class="btn btn-primary"><?= icon('check', '', 17) ?> Save code</button>
          <a class="btn btn-ghost" href="<?= e(url('admin/discount-codes.php')) ?>">Cancel</a>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<div class="admin-panel">
  <div class="admin-panel-head"><h2>All codes</h2></div>
  <div class="admin-panel-body flush">
    <?php if (!$codes): ?>
      <div style="padding:16px">
        <?php render_empty_state('tag', 'No discount codes yet',
            'Create one to run a launch offer or a student promotion.',
            [['label' => 'Create the first code', 'url' => url('admin/discount-codes.php?edit=0')]]); ?>
      </div>
    <?php else: ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <caption class="sr-only">Discount codes</caption>
          <thead>
            <tr><th scope="col">Code</th><th scope="col">Discount</th><th scope="col" class="num">Min basket</th>
              <th scope="col">Validity</th><th scope="col" class="num">Used</th><th scope="col">State</th>
              <th scope="col" class="actions">Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($codes as $code): $stats = $redemptionStats[(int) $code['id']] ?? ['uses' => 0, 'value' => 0]; ?>
              <tr>
                <td>
                  <strong><?= e($code['code']) ?></strong><br>
                  <span class="tiny muted"><?= e(truncate((string) $code['description'], 60)) ?></span>
                </td>
                <td><?= e(discount_label($code)) ?><br><span class="tiny muted"><?= (int) $code['per_user_limit'] ?: '∞' ?> per customer</span></td>
                <td class="num"><?= e(money($code['min_subtotal'])) ?></td>
                <td class="tiny">
                  <?= $code['starts_at'] ? 'from ' . e(format_date($code['starts_at'])) : 'no start date' ?><br>
                  <span class="muted"><?= $code['expires_at'] ? 'until ' . e(format_date($code['expires_at'])) : 'no expiry' ?></span>
                </td>
                <td class="num"><?= (int) $code['used_count'] ?><?= $code['max_uses'] ? ' / ' . (int) $code['max_uses'] : '' ?><br>
                  <span class="tiny muted"><?= e(money($stats['value'])) ?> given</span></td>
                <td><?= (int) $code['is_active'] === 1 ? '<span class="pill pill-ok">Active</span>' : '<span class="pill pill-muted">Paused</span>' ?></td>
                <td class="actions">
                  <span class="admin-actions">
                    <a class="btn btn-ghost btn-xs" href="<?= e(url('admin/discount-codes.php?edit=' . (int) $code['id'])) ?>">Edit</a>
                    <form method="post" action="<?= e(url('admin/discount-codes.php')) ?>">
                      <?= csrf_field() ?>
                      <input type="hidden" name="code_id" value="<?= (int) $code['id'] ?>">
                      <button type="submit" name="action" value="toggle" class="btn btn-quiet btn-xs">
                        <?= (int) $code['is_active'] === 1 ? 'Pause' : 'Activate' ?>
                      </button>
                    </form>
                    <form method="post" action="<?= e(url('admin/discount-codes.php')) ?>"
                          data-confirm="Delete code <?= e($code['code']) ?>? Past orders keep the discount they already received.">
                      <?= csrf_field() ?>
                      <input type="hidden" name="code_id" value="<?= (int) $code['id'] ?>">
                      <button type="submit" name="action" value="delete" class="btn btn-quiet btn-xs" style="color:var(--danger)">Delete</button>
                    </form>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php render_admin_end(); ?>
