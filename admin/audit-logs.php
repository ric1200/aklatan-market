<?php
/** Audit log: who changed what, when and from where. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/admin-header.php';
require_admin();

if (get_param('export') === 'csv') {
    $rows = fetch_all('SELECT a.created_at, a.action, a.entity_type, a.entity_id, a.details, a.ip_address,
        u.first_name, u.last_name, u.email
        FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC LIMIT 5000');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="aklatan-audit-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['When', 'Action', 'Entity', 'Entity id', 'Details', 'IP address', 'Admin']);
    foreach ($rows as $row) {
        fputcsv($out, [$row['created_at'], $row['action'], $row['entity_type'], $row['entity_id'], $row['details'],
            $row['ip_address'], trim((string) $row['first_name'] . ' ' . (string) $row['last_name']) ?: 'system']);
    }
    fclose($out);
    exit;
}

$action = truncate(get_param('action'), 80);
$keyword = truncate(get_param('q'), 80);
$page = max(1, get_int('page', 1));

$where = ['1=1'];
$params = [];
if ($action !== '') { $where[] = 'a.action LIKE :action'; $params['action'] = $action . '%'; }
if ($keyword !== '') {
    $where[] = like_or_clause(['a.details', 'a.entity_id'], $keyword, $params, 'kw');
}
$whereSql = implode(' AND ', $where);

$total = (int) fetch_value('SELECT COUNT(*) FROM audit_logs a WHERE ' . $whereSql, $params, 0);
$p = paginate($total, 40, $page);
$logs = fetch_all('SELECT a.*, u.first_name, u.last_name, u.role FROM audit_logs a
    LEFT JOIN users u ON u.id = a.user_id
    WHERE ' . $whereSql . ' ORDER BY a.created_at DESC LIMIT ' . (int) $p['perPage'] . ' OFFSET ' . (int) $p['offset'], $params);

$actions = fetch_all('SELECT action, COUNT(*) AS c FROM audit_logs GROUP BY action ORDER BY c DESC LIMIT 30');

render_admin_start([
    'current' => 'audit',
    'title' => 'Audit log',
    'subtitle' => number_format($total) . ' recorded action(s). Newest first.',
    'crumbs' => [['label' => 'Dashboard', 'url' => 'admin/index.php'], ['label' => 'Audit log']],
    'actions' => '<a class="btn btn-ghost btn-sm" href="' . e(url('admin/audit-logs.php?export=csv')) . '">' . icon('download', '', 16) . ' Export CSV</a>',
]);
?>
<div class="notice notice-info">
  <?= icon('shield', 'notice-icon', 18) ?>
  <div class="notice-body">
    <strong>This log is append-only from the interface.</strong>
    It records product and category changes, order status moves, payment decisions, settings updates and sign-in
    events. It is deliberately not editable from the dashboard so the record stays trustworthy.
  </div>
</div>

<form class="admin-toolbar admin-panel" method="get" action="<?= e(url('admin/audit-logs.php')) ?>" style="padding:14px 16px">
  <div class="field">
    <label for="q">Search details or entity id</label>
    <input id="q" type="search" name="q" value="<?= e($keyword) ?>" placeholder="order number, product id, e-mail">
  </div>
  <div class="field tight">
    <label for="action">Action type</label>
    <select id="action" name="action">
      <option value="">Any action</option>
      <?php foreach ($actions as $row): ?>
        <option value="<?= e($row['action']) ?>" <?= $action === $row['action'] ? 'selected' : '' ?>>
          <?= e($row['action']) ?> (<?= (int) $row['c'] ?>)
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
  <a class="btn btn-quiet btn-sm" href="<?= e(url('admin/audit-logs.php')) ?>">Reset</a>
</form>

<div class="admin-panel">
  <div class="admin-panel-head"><h2>Activity</h2><p class="panel-note">Times are shown in <?= e(APP_TIMEZONE) ?>.</p></div>
  <div class="admin-panel-body flush">
    <?php if (!$logs): ?>
      <div style="padding:16px">
        <?php render_empty_state('clipboard', 'No activity matched',
            'Adjust the filters to see more of the log.', [['label' => 'Reset filters', 'url' => url('admin/audit-logs.php')]]); ?>
      </div>
    <?php else: ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <caption class="sr-only">Audit log entries</caption>
          <thead>
            <tr><th scope="col">When</th><th scope="col">Action</th><th scope="col">Entity</th>
              <th scope="col">Details</th><th scope="col">By</th><th scope="col">IP</th></tr>
          </thead>
          <tbody>
            <?php foreach ($logs as $log): ?>
              <tr>
                <td class="tiny"><?= e(format_datetime($log['created_at'])) ?><br>
                  <span class="muted"><?= e(time_ago($log['created_at'])) ?></span></td>
                <td><code class="tiny"><?= e($log['action']) ?></code></td>
                <td class="tiny"><?= e((string) ($log['entity_type'] ?? '—')) ?>
                  <?= $log['entity_id'] ? '<br><span class="muted">#' . e((string) $log['entity_id']) . '</span>' : '' ?></td>
                <td class="tiny"><?= e((string) ($log['details'] ?? '')) ?></td>
                <td class="tiny">
                  <?= $log['first_name'] ? e(trim($log['first_name'] . ' ' . $log['last_name'])) : 'system' ?>
                  <?= $log['role'] ? '<br><span class="muted">' . e((string) $log['role']) . '</span>' : '' ?>
                </td>
                <td class="tiny muted"><?= e((string) ($log['ip_address'] ?? '—')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?= pagination_links($p, 'admin/audit-logs.php', array_filter(['action' => $action, 'q' => $keyword], function ($v) { return $v !== ''; })) ?>
<?php render_admin_end(); ?>
