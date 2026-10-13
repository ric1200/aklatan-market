<?php
/** Manual payment queue: verify or reject with an audit record. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/admin-header.php';
$admin = require_admin();

if (is_post()) {
    csrf_guard();
    $action = post('action');
    if ($action === 'verify' || $action === 'reject') {
        $payment = fetch_one('SELECT p.*, o.order_number FROM payments p JOIN orders o ON o.id = p.order_id WHERE p.id = :id',
            ['id' => post_int('payment_id')]);
        if (!$payment) {
            flash_error('That payment record could not be found.');
            redirect('admin/payments.php');
        }
        $note = truncate(post_raw('admin_note'), 500);
        $status = $action === 'verify' ? 'verified' : 'rejected';
        if ($status === 'rejected' && $note === '') {
            flash_error('Please add a short reason before rejecting a payment.');
            redirect('admin/payments.php?' . http_build_query(array_filter([
                'status' => get_param('status'), 'q' => get_param('q')])));
        }
        db_update('payments', [
            'status' => $status,
            'admin_note' => $note !== '' ? $note : 'Verified against the transaction list.',
            'verified_by' => (int) $admin['id'],
            'verified_at' => date('Y-m-d H:i:s'),
        ], 'id', (int) $payment['id']);
        db_update('orders', ['payment_status' => $status], 'id', (int) $payment['order_id']);
        audit_log('order.payment_' . $status, 'payment', (int) $payment['id'],
            'Order ' . $payment['order_number'] . ' — ' . payment_method_label((string) $payment['method'])
            . ($payment['reference_number'] ? ', ref ' . $payment['reference_number'] : '') . ($note !== '' ? ' — ' . $note : ''));
        flash_success('Payment for ' . $payment['order_number'] . ' was marked ' . $status . '.');
        redirect('admin/payments.php?' . http_build_query(array_filter(['status' => get_param('status'), 'q' => get_param('q')])));
    }
}

$status = (string) get_param('status');
if (!array_key_exists($status, PAYMENT_STATUSES)) { $status = ''; }
$method = (string) get_param('method');
if (!in_array($method, ['cod', 'gcash', 'pickup', 'bank'], true)) { $method = ''; }
$keyword = truncate(get_param('q'), 80);
$page = max(1, get_int('page', 1));

$where = ['1=1'];
$params = [];
if ($status !== '') { $where[] = 'p.status = :status'; $params['status'] = $status; }
if ($method !== '') { $where[] = 'p.method = :method'; $params['method'] = $method; }
if ($keyword !== '') {
    $where[] = like_or_clause(['o.order_number', 'p.reference_number', 'p.payer_name'], $keyword, $params, 'kw');
}
$whereSql = implode(' AND ', $where);

$total = (int) fetch_value('SELECT COUNT(*) FROM payments p JOIN orders o ON o.id = p.order_id WHERE ' . $whereSql, $params, 0);
$p = paginate($total, 20, $page);
$payments = fetch_all('SELECT p.*, o.order_number, o.total AS order_total, o.status AS order_status
    FROM payments p JOIN orders o ON o.id = p.order_id
    WHERE ' . $whereSql . ' ORDER BY (p.status = \'pending_verification\') DESC, p.created_at DESC
    LIMIT ' . (int) $p['perPage'] . ' OFFSET ' . (int) $p['offset'], $params);

$summary = [
    'pending' => (int) fetch_value("SELECT COUNT(*) FROM payments WHERE status = 'pending_verification'", [], 0),
    'verified' => (int) fetch_value("SELECT COUNT(*) FROM payments WHERE status = 'verified'", [], 0),
    'rejected' => (int) fetch_value("SELECT COUNT(*) FROM payments WHERE status = 'rejected'", [], 0),
    'verified_value' => (float) fetch_value("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'verified'", [], 0),
];

render_admin_start([
    'current' => 'payments',
    'title' => 'Payments',
    'subtitle' => $summary['pending'] . ' waiting for manual verification · '
        . money($summary['verified_value']) . ' verified to date',
    'crumbs' => [['label' => 'Dashboard', 'url' => 'admin/index.php'], ['label' => 'Payments']],
    'actions' => '<a class="btn btn-ghost btn-sm" href="' . e(url('admin/payments.php?status=pending_verification')) . '">'
        . icon('clock', '', 16) . ' Only pending</a>',
]);
?>
<div class="kpi-grid">
  <div class="kpi <?= $summary['pending'] ? 'kpi-warn' : '' ?>"><p class="kpi-label">Pending verification</p><p class="kpi-value"><?= number_format($summary['pending']) ?></p><p class="kpi-note">Needs a decision</p></div>
  <div class="kpi kpi-ok"><p class="kpi-label">Verified</p><p class="kpi-value"><?= number_format($summary['verified']) ?></p><p class="kpi-note"><?= e(money($summary['verified_value'])) ?> confirmed</p></div>
  <div class="kpi"><p class="kpi-label">Rejected</p><p class="kpi-value"><?= number_format($summary['rejected']) ?></p><p class="kpi-note">Customer told to re-check</p></div>
</div>

<form class="admin-toolbar admin-panel" method="get" action="<?= e(url('admin/payments.php')) ?>" style="padding:14px 16px">
  <div class="field">
    <label for="q">Order number, reference or payer</label>
    <input id="q" type="search" name="q" value="<?= e($keyword) ?>">
  </div>
  <div class="field tight">
    <label for="status">Payment state</label>
    <select id="status" name="status">
      <option value="">Any state</option>
      <?php foreach (PAYMENT_STATUSES as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field tight">
    <label for="method">Method</label>
    <select id="method" name="method">
      <option value="">Any method</option>
      <?php foreach (['cod' => 'Cash on delivery', 'gcash' => 'GCash', 'pickup' => 'Pickup', 'bank' => 'Bank transfer'] as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= $method === $key ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
  <a class="btn btn-quiet btn-sm" href="<?= e(url('admin/payments.php')) ?>">Reset</a>
</form>

<div class="admin-panel">
  <div class="admin-panel-head">
    <h2>Payment records</h2>
    <p class="panel-note">Rejecting always requires a note so the customer knows what went wrong.</p>
  </div>
  <div class="admin-panel-body flush">
    <?php if (!$payments): ?>
      <div style="padding:16px">
        <?php render_empty_state('shield', 'No payment records matched',
            'Try a different filter, or check that customers have submitted their references.',
            [['label' => 'Show all payments', 'url' => url('admin/payments.php')]]); ?>
      </div>
    <?php else: ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <caption class="sr-only">Manual payment submissions</caption>
          <thead>
            <tr><th scope="col">Order</th><th scope="col">Method</th><th scope="col">Reference</th>
              <th scope="col" class="num">Amount</th><th scope="col">Submitted</th><th scope="col">State</th>
              <th scope="col">Proof</th><th scope="col" class="actions">Decision</th></tr>
          </thead>
          <tbody>
            <?php foreach ($payments as $payment): ?>
              <tr>
                <td>
                  <a href="<?= e(url('admin/order-view.php?order=' . urlencode((string) $payment['order_number']))) ?>"><?= e($payment['order_number']) ?></a><br>
                  <span class="tiny muted">Order <?= e(order_status_label((string) $payment['order_status'])) ?></span>
                </td>
                <td class="tiny"><?= e(payment_method_label((string) $payment['method'])) ?></td>
                <td class="tiny">
                  <?= e((string) ($payment['reference_number'] ?: '—')) ?><br>
                  <span class="muted"><?= e((string) ($payment['payer_name'] ?: '')) ?></span>
                </td>
                <td class="num"><?= e(money($payment['amount'])) ?></td>
                <td class="tiny"><?= e(format_datetime($payment['created_at'])) ?><br>
                  <span class="muted"><?= e(time_ago($payment['created_at'])) ?></span></td>
                <td><?= payment_status_badge((string) $payment['status']) ?></td>
                <td>
                  <?php if (!empty($payment['proof_image'])): ?>
                    <button type="button" class="btn btn-quiet btn-xs" data-proof="<?= e(url('admin/payment-proof.php?payment=' . (int) $payment['id'])) ?>">
                      <?= icon('image', '', 14) ?> View
                    </button>
                  <?php else: ?>
                    <span class="tiny muted">none</span>
                  <?php endif; ?>
                </td>
                <td class="actions">
                  <?php if ($payment['status'] !== 'verified'): ?>
                    <form method="post" action="<?= e(url('admin/payments.php')) ?>" class="inline-form">
                      <?= csrf_field() ?>
                      <input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>">
                      <input type="hidden" name="status" value="<?= e($status) ?>">
                      <input type="hidden" name="q" value="<?= e($keyword) ?>">
                      <input type="text" name="admin_note" maxlength="500" placeholder="Optional note" style="min-height:34px;padding:6px 8px;width:150px">
                      <button type="submit" name="action" value="verify" class="btn btn-primary btn-xs">Verify</button>
                      <button type="submit" name="action" value="reject" class="btn btn-danger btn-xs"
                              data-confirm="Reject this payment? Add a note first so the customer knows why.">Reject</button>
                    </form>
                  <?php else: ?>
                    <span class="tiny muted">Verified <?= e(format_datetime($payment['verified_at'])) ?><br>
                      admin #<?= (int) $payment['verified_by'] ?></span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?= pagination_links($p, 'admin/payments.php', array_filter(['status' => $status, 'method' => $method, 'q' => $keyword], function ($v) { return $v !== ''; })) ?>
<?php render_admin_end(); ?>
