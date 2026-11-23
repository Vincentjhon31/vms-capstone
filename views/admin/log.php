<?php
/** Visitor log - the front desk's working screen. */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $visitor = db_one('SELECT * FROM visitors WHERE id = ?', [$id]);

    if ($visitor) {
        if ($action === 'attend') {
            visitor_mark_attended($id);
            audit('visitor_attended', 'visitor', $visitor['ref_no']);
            flash($visitor['name'] . ' marked as attended.');
        } elseif ($action === 'timeout') {
            db_run("UPDATE visitors SET time_out = NOW(), status = 'Completed' WHERE id = ?", [$id]);
            audit('visitor_timed_out', 'visitor', $visitor['ref_no']);
            flash('Time out recorded for ' . $visitor['name'] . '.');
        } elseif ($action === 'status' && in_array($_POST['status'] ?? '', visitor_statuses(), true)) {
            visitor_set_status($id, $_POST['status']);
            audit('visitor_status_changed', 'visitor', $visitor['ref_no'], 'to ' . $_POST['status']);
            flash('Status updated.');
        }
    }
    redirect('Log', array_filter([
        'from' => $_POST['from'] ?? '', 'to' => $_POST['to'] ?? '', 'purpose' => $_POST['purpose'] ?? '',
        'sex' => $_POST['sex'] ?? '', 'status' => $_POST['status_filter'] ?? '', 'q' => $_POST['q'] ?? '',
        'page' => $_POST['page'] ?? '',
    ]));
}

$filters = [
    'from'    => trim((string) ($_GET['from'] ?? '')),
    'to'      => trim((string) ($_GET['to'] ?? '')),
    'purpose' => trim((string) ($_GET['purpose'] ?? '')),
    'sex'     => trim((string) ($_GET['sex'] ?? '')),
    'status'  => trim((string) ($_GET['status'] ?? '')),
    'q'       => trim((string) ($_GET['q'] ?? '')),
];

$where = [];
$params = [];
if ($filters['from'] !== '')    { $where[] = 'visit_date >= ?';  $params[] = $filters['from']; }
if ($filters['to'] !== '')      { $where[] = 'visit_date <= ?';  $params[] = $filters['to']; }
if ($filters['purpose'] !== '') { $where[] = 'purpose = ?';      $params[] = $filters['purpose']; }
if ($filters['sex'] !== '')     { $where[] = 'sex = ?';          $params[] = $filters['sex']; }
if ($filters['status'] !== '')  { $where[] = 'status = ?';       $params[] = $filters['status']; }
if ($filters['q'] !== '') {
    $where[] = '(name LIKE ? OR ref_no LIKE ? OR address LIKE ? OR contact LIKE ?)';
    $like = '%' . $filters['q'] . '%';
    array_push($params, $like, $like, $like, $like);
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$perPage = 25;
$total   = (int) db_val('SELECT COUNT(*) FROM visitors' . $whereSql, $params, 0);
$pages   = max(1, (int) ceil($total / $perPage));
$page    = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
$offset  = ($page - 1) * $perPage;

$visitors = db_all(
    'SELECT * FROM visitors' . $whereSql . ' ORDER BY time_in DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
    $params
);

require __DIR__ . '/../../includes/header.php';
?>
<div class="wrap">
  <div class="page-head">
    <div>
      <h1>Visitor Log</h1>
      <p><?= number_format($total) ?> registration<?= $total === 1 ? '' : 's' ?> matching your filters.</p>
    </div>
    <div class="btn-row">
      <a class="btn btn--ghost btn--sm" href="<?= file_url('api/export.php', ['type' => 'visitors', 'format' => 'csv'] + $filters) ?>"><?= icon('download', 16) ?> CSV</a>
      <a class="btn btn--ghost btn--sm" href="<?= file_url('api/export.php', ['type' => 'visitors', 'format' => 'excel'] + $filters) ?>"><?= icon('download', 16) ?> Excel</a>
      <button class="btn btn--ghost btn--sm" type="button" data-print><?= icon('printer', 16) ?> Print</button>
    </div>
  </div>

  <div class="card">
    <div class="card__body">
      <form class="toolbar" method="get" action="<?= url('Log') ?>">
        <?php if (!PRETTY_URLS): ?><input type="hidden" name="r" value="Log"><?php endif; ?>
        <div class="field"><label for="from">From</label>
          <input class="input" type="date" id="from" name="from" value="<?= e($filters['from']) ?>"></div>
        <div class="field"><label for="to">To</label>
          <input class="input" type="date" id="to" name="to" value="<?= e($filters['to']) ?>"></div>
        <div class="field"><label for="purpose">Purpose</label>
          <select class="select" id="purpose" name="purpose">
            <option value="">All purposes</option>
            <?php foreach (array_keys(purposes()) as $purpose): ?>
              <option value="<?= e($purpose) ?>" <?= $filters['purpose'] === $purpose ? 'selected' : '' ?>><?= e($purpose) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="field"><label for="sex">Sex</label>
          <select class="select" id="sex" name="sex">
            <option value="">All</option>
            <option value="Male" <?= $filters['sex'] === 'Male' ? 'selected' : '' ?>>Male</option>
            <option value="Female" <?= $filters['sex'] === 'Female' ? 'selected' : '' ?>>Female</option>
          </select></div>
        <div class="field"><label for="status">Status</label>
          <select class="select" id="status" name="status">
            <option value="">All</option>
            <?php foreach (visitor_statuses() as $status): ?>
              <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="field" style="flex:1;min-width:190px"><label for="q">Search</label>
          <input class="input" type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Name, reference, address"></div>
        <button class="btn btn--primary btn--sm" type="submit"><?= icon('filter', 16) ?> Filter</button>
        <?php if (array_filter($filters)): ?>
          <a class="btn btn--ghost btn--sm" href="<?= url('Log') ?>">Clear</a>
        <?php endif; ?>
      </form>

      <?php if ($visitors): ?>
        <div class="table-scroll">
          <table class="data">
            <thead>
              <tr>
                <th style="width:130px">Reference</th><th style="width:230px">Visitor</th><th>Purpose</th><th>Time in</th><th>Time out</th>
                <th>Status</th><th>Signature</th><th class="nowrap">Desk actions</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($visitors as $visitor): ?>
              <tr>
                <td class="nowrap">
                  <strong><?= e($visitor['ref_no']) ?></strong><br>
                  <span class="small muted">Queue <?= (int) $visitor['queue_no'] ?> &middot; <?= e(fmt_date($visitor['visit_date'])) ?></span>
                </td>
                <td>
                  <strong><?= e($visitor['name']) ?></strong><br>
                  <span class="small muted"><?= e($visitor['sex']) ?>, <?= (int) $visitor['age'] ?>
                    <?= $visitor['contact'] ? ' &middot; ' . e($visitor['contact']) : '' ?></span><br>
                  <span class="small muted cell-clip" title="<?= e($visitor['address']) ?>"><?= e($visitor['address']) ?></span>
                </td>
                <td>
                  <?= e($visitor['purpose']) ?>
                  <?php if ($visitor['purpose_other']): ?>
                    <br><span class="small muted"><?= e($visitor['purpose_other']) ?></span>
                  <?php endif; ?>
                </td>
                <td class="num"><?= e(fmt_time($visitor['time_in'])) ?></td>
                <td class="num"><?= $visitor['time_out'] ? e(fmt_time($visitor['time_out'])) : '<span class="muted">-</span>' ?></td>
                <td><span class="status <?= e(status_class($visitor['status'])) ?>"><?= e($visitor['status']) ?></span></td>
                <td>
                  <?php if ($visitor['signature']): ?>
                    <button class="btn btn--ghost btn--sm" type="button"
                            data-signature="<?= e($visitor['signature']) ?>" data-name="<?= e($visitor['name']) ?>">
                      <?= icon('eye', 15) ?> View
                    </button>
                  <?php else: ?>
                    <span class="muted small">None</span>
                  <?php endif; ?>
                </td>
                <td class="nowrap">
                  <form method="post" action="<?= url('Log') ?>" style="display:inline-flex;gap:6px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $visitor['id'] ?>">
                    <?php foreach ($filters as $key => $value): ?>
                      <input type="hidden" name="<?= $key === 'status' ? 'status_filter' : e($key) ?>" value="<?= e($value) ?>">
                    <?php endforeach; ?>
                    <input type="hidden" name="page" value="<?= $page ?>">
                    <?php if ($visitor['status'] === 'Waiting'): ?>
                      <button class="btn btn--primary btn--sm" name="action" value="attend"><?= icon('check', 15) ?> Attended</button>
                    <?php endif; ?>
                    <?php if (!$visitor['time_out']): ?>
                      <button class="btn btn--ghost btn--sm" name="action" value="timeout"><?= icon('clock', 15) ?> Time out</button>
                    <?php endif; ?>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php if ($pages > 1): ?>
          <nav class="pagination" aria-label="Pagination">
            <?php
            $query = array_filter($filters);
            $window = range(max(1, $page - 2), min($pages, $page + 2));
            if ($page > 1): ?>
              <a href="<?= url('Log', $query + ['page' => $page - 1]) ?>">Previous</a>
            <?php endif; ?>
            <?php if (!in_array(1, $window, true)): ?>
              <a href="<?= url('Log', $query + ['page' => 1]) ?>">1</a><span>...</span>
            <?php endif; ?>
            <?php foreach ($window as $number): ?>
              <?php if ($number === $page): ?>
                <span class="is-current" aria-current="page"><?= $number ?></span>
              <?php else: ?>
                <a href="<?= url('Log', $query + ['page' => $number]) ?>"><?= $number ?></a>
              <?php endif; ?>
            <?php endforeach; ?>
            <?php if (!in_array($pages, $window, true)): ?>
              <span>...</span><a href="<?= url('Log', $query + ['page' => $pages]) ?>"><?= $pages ?></a>
            <?php endif; ?>
            <?php if ($page < $pages): ?>
              <a href="<?= url('Log', $query + ['page' => $page + 1]) ?>">Next</a>
            <?php endif; ?>
            <span class="pagination__info">Page <?= $page ?> of <?= $pages ?></span>
          </nav>
        <?php endif; ?>

      <?php else: ?>
        <div class="empty">
          <?= icon('users', 34) ?>
          <p>No visitor matches these filters.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="modal" id="signatureViewer" hidden role="dialog" aria-modal="true" aria-label="Visitor signature">
  <div class="modal__box">
    <div class="modal__head">
      <h2>Signature - <span data-signature-name></span></h2>
      <button class="modal__close" type="button" data-close-modal aria-label="Close"><?= icon('x', 22) ?></button>
    </div>
    <div class="modal__body center">
      <img data-signature-image src="" alt="Visitor signature" style="max-width:100%;border:1px solid var(--line);border-radius:10px">
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
