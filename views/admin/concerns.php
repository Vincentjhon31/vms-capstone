<?php
/** Concern management - assign an office, move the status, write the official response. */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $concern = db_one('SELECT * FROM concerns WHERE id = ?', [$id]);

    if ($concern) {
        $status   = in_array($_POST['status'] ?? '', concern_statuses(), true) ? $_POST['status'] : $concern['status'];
        $office   = trim((string) ($_POST['assigned_office'] ?? ''));
        $response = trim((string) ($_POST['official_response'] ?? ''));

        $resolvedAt = $concern['date_resolved'];
        if (in_array($status, ['Resolved', 'Closed'], true) && !$resolvedAt) {
            $resolvedAt = date('Y-m-d H:i:s');
        } elseif (!in_array($status, ['Resolved', 'Closed'], true)) {
            $resolvedAt = null;
        }

        db_run(
            'UPDATE concerns SET status = ?, assigned_office = ?, official_response = ?, date_resolved = ? WHERE id = ?',
            [$status, $office ?: null, $response ?: null, $resolvedAt, $id]
        );
        audit('concern_updated', 'concern', $concern['ref_no'],
              'status: ' . $status . ($office ? '; office: ' . $office : ''));
        flash('Concern ' . $concern['ref_no'] . ' updated.');
    }
    redirect('ConcernList', array_filter(['ref' => $_POST['ref'] ?? '']));
}

$filters = [
    'status' => trim((string) ($_GET['status'] ?? '')),
    'type'   => trim((string) ($_GET['type'] ?? '')),
    'q'      => trim((string) ($_GET['q'] ?? '')),
];
$openRef = trim((string) ($_GET['ref'] ?? ''));

$where = [];
$params = [];
if ($filters['status'] !== '') { $where[] = 'status = ?';       $params[] = $filters['status']; }
if ($filters['type'] !== '')   { $where[] = 'concern_type = ?'; $params[] = $filters['type']; }
if ($filters['q'] !== '') {
    $where[] = '(ref_no LIKE ? OR name LIKE ? OR subject LIKE ? OR description LIKE ?)';
    $like = '%' . $filters['q'] . '%';
    array_push($params, $like, $like, $like, $like);
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$perPage = 20;
$total   = (int) db_val('SELECT COUNT(*) FROM concerns' . $whereSql, $params, 0);
$pages   = max(1, (int) ceil($total / $perPage));
$page    = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
$concerns = db_all(
    'SELECT * FROM concerns' . $whereSql . ' ORDER BY FIELD(status, "Received","Under Review","In Progress","Resolved","Closed"), date_received ASC'
    . ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
    $params
);

require __DIR__ . '/../../includes/header.php';
?>
<div class="wrap">
  <div class="page-head">
    <div>
      <h1>Concern Management</h1>
      <p><?= number_format($total) ?> concern<?= $total === 1 ? '' : 's' ?> matching your filters.
        Overdue means unresolved after <?= (int) CONCERN_SLA_DAYS ?> days.</p>
    </div>
    <div class="btn-row">
      <a class="btn btn--ghost btn--sm" href="<?= file_url('api/export.php', ['type' => 'concerns', 'format' => 'csv'] + $filters) ?>"><?= icon('download', 16) ?> CSV</a>
      <a class="btn btn--ghost btn--sm" href="<?= file_url('api/export.php', ['type' => 'concerns', 'format' => 'excel'] + $filters) ?>"><?= icon('download', 16) ?> Excel</a>
    </div>
  </div>

  <div class="card">
    <div class="card__body">
      <form class="toolbar" method="get" action="<?= url('ConcernList') ?>">
        <?php if (!PRETTY_URLS): ?><input type="hidden" name="r" value="ConcernList"><?php endif; ?>
        <div class="field"><label for="status">Status</label>
          <select class="select" id="status" name="status" data-auto-submit>
            <option value="">All statuses</option>
            <?php foreach (concern_statuses() as $status): ?>
              <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="field"><label for="type">Type</label>
          <select class="select" id="type" name="type" data-auto-submit>
            <option value="">All types</option>
            <?php foreach (concern_types() as $type): ?>
              <option value="<?= e($type) ?>" <?= $filters['type'] === $type ? 'selected' : '' ?>><?= e($type) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="field" style="flex:1;min-width:200px"><label for="q">Search</label>
          <input class="input" type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Reference, name or subject"></div>
        <button class="btn btn--primary btn--sm" type="submit"><?= icon('filter', 16) ?> Filter</button>
        <?php if (array_filter($filters)): ?>
          <a class="btn btn--ghost btn--sm" href="<?= url('ConcernList') ?>">Clear</a>
        <?php endif; ?>
      </form>

      <?php if (!$concerns): ?>
        <div class="empty"><?= icon('alert', 34) ?><p>No concern matches these filters.</p></div>
      <?php endif; ?>

      <?php foreach ($concerns as $concern): ?>
        <?php $overdue = is_overdue($concern); ?>
        <details class="service" <?= $openRef === $concern['ref_no'] ? 'open' : '' ?>>
          <summary class="service__toggle">
            <span class="service__no" style="<?= $overdue ? 'background:#b91c1c' : '' ?>"><?= icon('alert', 15) ?></span>
            <span class="service__name">
              <?= e($concern['subject']) ?>
              <span class="small muted" style="display:block;font-weight:500">
                <?= e($concern['ref_no']) ?> &middot; <?= e($concern['name']) ?> &middot; <?= e(fmt_date($concern['date_received'])) ?>
                &middot; <?= e($concern['concern_type']) ?>
              </span>
            </span>
            <?php if ($overdue): ?><span class="status status--overdue">Overdue</span><?php endif; ?>
            <span class="status <?= e(status_class($concern['status'])) ?>"><?= e($concern['status']) ?></span>
            <span class="service__chev"><?= icon('chevron', 20) ?></span>
          </summary>

          <div class="service__panel">
            <div class="service__box">
              <p class="service__desc"><?= nl2br(e($concern['description'])) ?></p>
              <div class="meta-grid">
                <p class="meta-cell"><strong>From:</strong> <span><?= e($concern['name']) ?></span></p>
                <p class="meta-cell"><strong>Email:</strong> <span><?= e($concern['email']) ?></span></p>
                <p class="meta-cell"><strong>Contact:</strong> <span><?= e($concern['contact'] ?: 'Not given') ?></span></p>
                <p class="meta-cell"><strong>Received:</strong> <span><?= e(fmt_datetime($concern['date_received'])) ?></span></p>
              </div>
            </div>

            <form method="post" action="<?= url('ConcernList') ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $concern['id'] ?>">
              <input type="hidden" name="ref" value="<?= e($concern['ref_no']) ?>">

              <div class="grid-2">
                <div class="field">
                  <label class="label" for="status-<?= (int) $concern['id'] ?>">Status</label>
                  <select class="select" id="status-<?= (int) $concern['id'] ?>" name="status">
                    <?php foreach (concern_statuses() as $status): ?>
                      <option value="<?= e($status) ?>" <?= $concern['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="field">
                  <label class="label" for="office-<?= (int) $concern['id'] ?>">Assigned office</label>
                  <select class="select" id="office-<?= (int) $concern['id'] ?>" name="assigned_office">
                    <option value="">Not assigned</option>
                    <?php foreach (offices() as $office): ?>
                      <option value="<?= e($office) ?>" <?= $concern['assigned_office'] === $office ? 'selected' : '' ?>><?= e($office) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <div class="field">
                <label class="label" for="response-<?= (int) $concern['id'] ?>">Official response</label>
                <textarea class="textarea" id="response-<?= (int) $concern['id'] ?>" name="official_response"
                          placeholder="This is shown to the citizen on the Track a Submission page."><?= e($concern['official_response']) ?></textarea>
                <span class="field-hint">Visible to anyone holding reference <?= e($concern['ref_no']) ?>.</span>
              </div>

              <button class="btn btn--primary" type="submit"><?= icon('save') ?> Save changes</button>
            </form>

            <?php
            $history = db_all(
                'SELECT * FROM audit_log WHERE entity = ? AND entity_id = ? ORDER BY created_at DESC LIMIT 10',
                ['concern', $concern['ref_no']]
            );
            ?>
            <?php if ($history): ?>
              <h3 class="subhead" style="margin-top:22px"><?= icon('clock', 18) ?> Action history</h3>
              <div class="table-scroll">
                <table class="data">
                  <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Detail</th></tr></thead>
                  <tbody>
                  <?php foreach ($history as $row): ?>
                    <tr>
                      <td class="nowrap small"><?= e(fmt_datetime($row['created_at'])) ?></td>
                      <td class="small"><?= e($row['actor']) ?></td>
                      <td class="small"><?= e(str_replace('_', ' ', $row['action'])) ?></td>
                      <td class="small"><?= e($row['details']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </details>
      <?php endforeach; ?>

      <?php if ($pages > 1): ?>
        <nav class="pagination" aria-label="Pagination">
          <?php $query = array_filter($filters); ?>
          <?php for ($number = 1; $number <= $pages; $number++): ?>
            <?php if ($number === $page): ?>
              <span class="is-current" aria-current="page"><?= $number ?></span>
            <?php else: ?>
              <a href="<?= url('ConcernList', $query + ['page' => $number]) ?>"><?= $number ?></a>
            <?php endif; ?>
          <?php endfor; ?>
          <span class="pagination__info">Page <?= $page ?> of <?= $pages ?></span>
        </nav>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
