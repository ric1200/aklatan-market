<?php
/** Customer enquiries from the contact form. Stored locally — no SMTP needed. */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/admin-header.php';
require_admin();

if (is_post()) {
    csrf_guard();
    $message = fetch_one('SELECT * FROM contact_messages WHERE id = :id', ['id' => post_int('message_id')]);
    if (!$message) {
        flash_error('That enquiry could not be found.');
        redirect('admin/messages.php');
    }
    $action = post('action');
    $note = truncate(post_raw('reply_note'), 2000);
    $statusMap = ['mark_read' => 'read', 'mark_replied' => 'replied', 'archive' => 'archived', 'reopen' => 'new'];
    if (isset($statusMap[$action])) {
        db_update('contact_messages', [
            'status' => $statusMap[$action],
            'reply_note' => $note !== '' ? $note : $message['reply_note'],
        ], 'id', (int) $message['id']);
        audit_log('contact.message_' . $statusMap[$action], 'contact_message', (int) $message['id'],
            'Enquiry from ' . $message['email'] . ' marked as ' . $statusMap[$action]);
        flash_success('Enquiry updated.');
    } elseif ($action === 'delete') {
        q('DELETE FROM contact_messages WHERE id = :id', ['id' => (int) $message['id']]);
        audit_log('contact.message_deleted', 'contact_message', (int) $message['id'], 'Deleted enquiry from ' . $message['email']);
        flash_success('Enquiry deleted.');
    }
    redirect('admin/messages.php?' . http_build_query(array_filter(['status' => get_param('status')])));
}

$status = (string) get_param('status');
if (!in_array($status, ['new', 'read', 'replied', 'archived'], true)) { $status = ''; }
$keyword = truncate(get_param('q'), 80);
$page = max(1, get_int('page', 1));

$where = ['1=1'];
$params = [];
if ($status !== '') { $where[] = 'status = :status'; $params['status'] = $status; }
if ($keyword !== '') {
    $where[] = like_or_clause(['name', 'email', 'subject', 'message'], $keyword, $params, 'kw');
}
$whereSql = implode(' AND ', $where);

$total = (int) fetch_value('SELECT COUNT(*) FROM contact_messages WHERE ' . $whereSql, $params, 0);
$p = paginate($total, 15, $page);
$messages = fetch_all('SELECT * FROM contact_messages WHERE ' . $whereSql . '
    ORDER BY (status = \'new\') DESC, created_at DESC LIMIT ' . (int) $p['perPage'] . ' OFFSET ' . (int) $p['offset'], $params);
$counts = [];
foreach (fetch_all('SELECT status, COUNT(*) AS c FROM contact_messages GROUP BY status') as $row) { $counts[$row['status']] = (int) $row['c']; }

render_admin_start([
    'current' => 'messages',
    'title' => 'Customer enquiries',
    'subtitle' => number_format($total) . ' matching · ' . (int) ($counts['new'] ?? 0) . ' never opened',
    'crumbs' => [['label' => 'Dashboard', 'url' => 'admin/index.php'], ['label' => 'Enquiries']],
]);
?>
<div class="notice notice-info">
  <?= icon('info', 'notice-icon', 18) ?>
  <div class="notice-body">
    <strong>This store does not depend on e-mail.</strong>
    Free hosting often cannot send mail, so every enquiry from the contact form is stored here in the database.
    Reply from your own e-mail client using the address on each message and mark the enquiry as replied.
  </div>
</div>

<form class="admin-toolbar admin-panel" method="get" action="<?= e(url('admin/messages.php')) ?>" style="padding:14px 16px">
  <div class="field">
    <label for="q">Name, e-mail, subject or text</label>
    <input id="q" type="search" name="q" value="<?= e($keyword) ?>">
  </div>
  <div class="field tight">
    <label for="status">State</label>
    <select id="status" name="status">
      <option value="">All enquiries</option>
      <option value="new" <?= $status === 'new' ? 'selected' : '' ?>>New (<?= (int) ($counts['new'] ?? 0) ?>)</option>
      <option value="read" <?= $status === 'read' ? 'selected' : '' ?>>Opened (<?= (int) ($counts['read'] ?? 0) ?>)</option>
      <option value="replied" <?= $status === 'replied' ? 'selected' : '' ?>>Replied (<?= (int) ($counts['replied'] ?? 0) ?>)</option>
      <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived (<?= (int) ($counts['archived'] ?? 0) ?>)</option>
    </select>
  </div>
  <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
  <a class="btn btn-quiet btn-sm" href="<?= e(url('admin/messages.php')) ?>">Reset</a>
</form>

<?php if (!$messages): ?>
  <div class="admin-panel"><div class="admin-panel-body">
    <?php render_empty_state('mail', 'No enquiries matched',
        'When a customer uses the contact form, the message lands here.',
        [['label' => 'Show all enquiries', 'url' => url('admin/messages.php')]]); ?>
  </div></div>
<?php else: ?>
  <?php foreach ($messages as $message): ?>
    <div class="admin-panel">
      <div class="admin-panel-head">
        <div>
          <h3 style="font-size:1rem;margin:0"><?= e($message['subject']) ?></h3>
          <p class="tiny muted">
            <?= e($message['name']) ?> · <a href="mailto:<?= e($message['email']) ?>?subject=<?= e(rawurlencode('Re: ' . $message['subject'])) ?>"><?= e($message['email']) ?></a>
            <?= $message['phone'] ? ' · ' . e($message['phone']) : '' ?>
            · <?= e(format_datetime($message['created_at'])) ?> (<?= e(time_ago($message['created_at'])) ?>)
          </p>
        </div>
        <span>
          <?php $tone = ['new' => 'warn', 'read' => 'muted', 'replied' => 'ok', 'archived' => 'muted'][$message['status']] ?? 'muted'; ?>
          <span class="pill pill-<?= e($tone) ?>"><?= e(ucfirst((string) $message['status'])) ?></span>
        </span>
      </div>
      <div class="admin-panel-body">
        <p><?= nl2br(e((string) $message['message'])) ?></p>
        <?php if (!empty($message['reply_note'])): ?>
          <p class="tiny muted mt-1">Internal note: <?= e((string) $message['reply_note']) ?></p>
        <?php endif; ?>
        <form method="post" action="<?= e(url('admin/messages.php')) ?>" class="inline-form mt-2">
          <?= csrf_field() ?>
          <input type="hidden" name="message_id" value="<?= (int) $message['id'] ?>">
          <div class="field">
            <label class="sr-only" for="reply-<?= (int) $message['id'] ?>">Internal note</label>
            <input id="reply-<?= (int) $message['id'] ?>" type="text" name="reply_note" maxlength="2000"
                   placeholder="What did you answer? (stored internally)">
          </div>
          <?php if ($message['status'] === 'new'): ?>
            <button type="submit" name="action" value="mark_read" class="btn btn-ghost btn-xs">Mark opened</button>
          <?php endif; ?>
          <button type="submit" name="action" value="mark_replied" class="btn btn-primary btn-xs"><?= icon('check', '', 14) ?> Mark replied</button>
          <?php if ($message['status'] === 'archived'): ?>
            <button type="submit" name="action" value="reopen" class="btn btn-ghost btn-xs">Reopen</button>
          <?php else: ?>
            <button type="submit" name="action" value="archive" class="btn btn-quiet btn-xs">Archive</button>
          <?php endif; ?>
          <button type="submit" name="action" value="delete" class="btn btn-quiet btn-xs" style="color:var(--danger)"
                  data-confirm="Delete this enquiry permanently?">Delete</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
<?= pagination_links($p, 'admin/messages.php', array_filter(['status' => $status, 'q' => $keyword], function ($v) { return $v !== ''; })) ?>
<?php render_admin_end(); ?>
