<?php
/** Feedback management - read what citizens sent and post a response. */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $entry = db_one('SELECT * FROM feedback WHERE id = ?', [$id]);

    if ($entry) {
        $status   = in_array($_POST['status'] ?? '', concern_statuses(), true) ? $_POST['status'] : $entry['status'];
        $response = trim((string) ($_POST['official_response'] ?? ''));
        $responded = $response !== '' ? ($entry['date_responded'] ?? date('Y-m-d H:i:s')) : null;

        db_run(
            'UPDATE feedback SET status = ?, official_response = ?, date_responded = ? WHERE id = ?',
            [$status, $response ?: null, $responded, $id]
        );
        audit('feedback_updated', 'feedback', $entry['ref_no'], 'status: ' . $status);
        flash('Feedback ' . $entry['ref_no'] . ' updated.');
    }
    redirect('FeedbackList');
}

$filters = [
    'type'   => trim((string) ($_GET['type'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'q'      => trim((string) ($_GET['q'] ?? '')),
];

$where = [];
$params = [];
if ($filters['type'] !== '')   { $where[] = 'feedback_type = ?'; $params[] = $filters['type']; }
if ($filters['status'] !== '') { $where[] = 'status = ?';        $params[] = $filters['status']; }
if ($filters['q'] !== '') {
    $where[] = '(ref_no LIKE ? OR name LIKE ? OR message LIKE ?)';
    $like = '%' . $filters['q'] . '%';
    array_push($params, $like, $like, $like);
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$perPage = 20;
$total   = (int) db_val('SELECT COUNT(*) FROM feedback' . $whereSql, $params, 0);
$pages   = max(1, (int) ceil($total / $perPage));
$page    = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
$entries = db_all(
    'SELECT * FROM feedback' . $whereSql . ' ORDER BY date_received DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
    $params
);

require __DIR__ . '/../../includes/header.php';
?>
<div class="wrap">
  <div class="page-head">
    <div>
      <h1>Feedback Management</h1>
      <p><?= number_format($total) ?> submission<?= $total === 1 ? '' : 's' ?> matching your filters.</p>
    </div>
    <div class="btn-row">
      <a class="btn btn--ghost btn--sm" href="<?= file_url('api/export.php', ['type' => 'feedback', 'format' => 'csv'] + $filters) ?>"><?= icon('download', 16) ?> CSV</a>
      <a class="btn btn--ghost btn--sm" href="<?= file_url('api/export.php', ['type' => 'feedback', 'format' => 'excel'] + $filters) ?>"><?= icon('download', 16) ?> Excel</a>
    </div>
  </div>

  <div class="card">
    <div class="card__body">
      <form class="toolbar" method="get" action="<?= url('FeedbackList') ?>">
        <?php if (!PRETTY_URLS): ?><input type="hidden" name="r" value="FeedbackList"><?php endif; ?>
        <div class="field"><label for="type">Feedback type</label>
          <select class="select" id="type" name="type" data-auto-submit>
            <option value="">All types</option>
            <?php foreach (feedback_types() as $type): ?>
              <option value="<?= e($type) ?>" <?= $filters['type'] === $type ? 'selected' : '' ?>><?= e($type) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="field"><label for="status">Status</label>
          <select class="select" id="status" name="status" data-auto-submit>
            <option value="">All statuses</option>
            <?php foreach (concern_statuses() as $status): ?>
              <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="field" style="flex:1;min-width:200px"><label for="q">Search</label>
          <input class="input" type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Reference, name or message"></div>
        <button class="btn btn--primary btn--sm" type="submit"><?= icon('filter', 16) ?> Filter</button>
        <?php if (array_filter($filters)): ?>
          <a class="btn btn--ghost btn--sm" href="<?= url('FeedbackList') ?>">Clear</a>
        <?php endif; ?>
      </form>

      <?php if (!$entries): ?>
        <div class="empty"><?= icon('message', 34) ?><p>No feedback matches these filters.</p></div>
      <?php endif; ?>

      <?php foreach ($entries as $entry): ?>
        <details class="service">
          <summary class="service__toggle">
            <span class="service__no"><?= icon('message', 15) ?></span>
            <span class="service__name">
              <?= e($entry['feedback_type']) ?> from <?= e($entry['name']) ?>
              <span class="small muted" style="display:block;font-weight:500">
                <?= e($entry['ref_no']) ?> &middot; <?= e(fmt_date($entry['date_received'])) ?>
              </span>
            </span>
            <span class="status <?= e(status_class($entry['status'])) ?>"><?= e($entry['status']) ?></span>
            <span class="service__chev"><?= icon('chevron', 20) ?></span>
          </summary>

          <div class="service__panel">
            <div class="service__box">
              <p class="service__desc"><?= nl2br(e($entry['message'])) ?></p>
              <div class="meta-grid">
                <p class="meta-cell"><strong>Email:</strong> <span><?= e($entry['email']) ?></span></p>
                <p class="meta-cell"><strong>Contact:</strong> <span><?= e($entry['contact'] ?: 'Not given') ?></span></p>
              </div>
            </div>

            <form method="post" action="<?= url('FeedbackList') ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $entry['id'] ?>">
              <div class="field" style="max-width:280px">
                <label class="label" for="fstatus-<?= (int) $entry['id'] ?>">Status</label>
                <select class="select" id="fstatus-<?= (int) $entry['id'] ?>" name="status">
                  <?php foreach (concern_statuses() as $status): ?>
                    <option value="<?= e($status) ?>" <?= $entry['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="field">
                <label class="label" for="fresponse-<?= (int) $entry['id'] ?>">Response</label>
                <textarea class="textarea" id="fresponse-<?= (int) $entry['id'] ?>" name="official_response"
                          placeholder="Shown to the sender on the Track a Submission page."><?= e($entry['official_response']) ?></textarea>
              </div>
              <button class="btn btn--primary" type="submit"><?= icon('save') ?> Save changes</button>
            </form>
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
              <a href="<?= url('FeedbackList', $query + ['page' => $number]) ?>"><?= $number ?></a>
            <?php endif; ?>
          <?php endfor; ?>
          <span class="pagination__info">Page <?= $page ?> of <?= $pages ?></span>
        </nav>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
