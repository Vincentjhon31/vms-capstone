<?php
/** Reports & Summary - printable figures for management and oversight agencies. */

require_once __DIR__ . '/../../includes/charts.php';

$from = trim((string) ($_GET['from'] ?? date('Y-m-01')));
$to   = trim((string) ($_GET['to'] ?? date('Y-m-d')));
if (strtotime($from) === false) $from = date('Y-m-01');
if (strtotime($to) === false)   $to = date('Y-m-d');
if (strtotime($from) > strtotime($to)) {
    [$from, $to] = [$to, $from];
}
$range = [$from, $to];

$totalVisitors = (int) db_val('SELECT COUNT(*) FROM visitors WHERE visit_date BETWEEN ? AND ?', $range, 0);
$byPurpose = array_column(
    db_all('SELECT purpose, COUNT(*) AS total FROM visitors WHERE visit_date BETWEEN ? AND ? GROUP BY purpose ORDER BY total DESC', $range),
    'total', 'purpose'
);
$bySex = ['Male' => 0, 'Female' => 0];
foreach (db_all('SELECT sex, COUNT(*) AS total FROM visitors WHERE visit_date BETWEEN ? AND ? GROUP BY sex', $range) as $row) {
    $bySex[$row['sex']] = (int) $row['total'];
}
$byAge = ['17 and below' => 0, '18-25' => 0, '26-40' => 0, '41-59' => 0, '60 and above' => 0];
foreach (db_all('SELECT age FROM visitors WHERE visit_date BETWEEN ? AND ?', $range) as $row) {
    $byAge[age_bracket((int) $row['age'])]++;
}
$byStatus = array_column(
    db_all('SELECT status, COUNT(*) AS total FROM visitors WHERE visit_date BETWEEN ? AND ? GROUP BY status', $range),
    'total', 'status'
);
$avgStay = db_val(
    'SELECT AVG(TIMESTAMPDIFF(MINUTE, time_in, time_out)) FROM visitors
     WHERE visit_date BETWEEN ? AND ? AND time_out IS NOT NULL',
    $range
);
$busiest = db_all(
    'SELECT visit_date, COUNT(*) AS total FROM visitors WHERE visit_date BETWEEN ? AND ?
     GROUP BY visit_date ORDER BY total DESC LIMIT 1',
    $range
);

$concernsReceived = (int) db_val('SELECT COUNT(*) FROM concerns WHERE DATE(date_received) BETWEEN ? AND ?', $range, 0);
$concernsResolved = (int) db_val('SELECT COUNT(*) FROM concerns WHERE DATE(date_resolved) BETWEEN ? AND ?', $range, 0);
$concernsOpen     = (int) db_val("SELECT COUNT(*) FROM concerns WHERE status NOT IN ('Resolved','Closed')", [], 0);
$concernAvgHours  = db_val(
    'SELECT AVG(TIMESTAMPDIFF(HOUR, date_received, date_resolved)) FROM concerns
     WHERE date_resolved IS NOT NULL AND DATE(date_resolved) BETWEEN ? AND ?',
    $range
);
$concernByType = array_column(
    db_all('SELECT concern_type, COUNT(*) AS total FROM concerns WHERE DATE(date_received) BETWEEN ? AND ? GROUP BY concern_type ORDER BY total DESC', $range),
    'total', 'concern_type'
);
$feedbackReceived = (int) db_val('SELECT COUNT(*) FROM feedback WHERE DATE(date_received) BETWEEN ? AND ?', $range, 0);

$csmTotal = (int) db_val('SELECT COUNT(*) FROM csm_responses WHERE response_date BETWEEN ? AND ?', $range, 0);
$csmOverall = $csmTotal
    ? db_val('SELECT (AVG(COALESCE(sqd1,0)+COALESCE(sqd2,0)+COALESCE(sqd3,0)+COALESCE(sqd4,0)+COALESCE(sqd5,0)
                          +COALESCE(sqd6,0)+COALESCE(sqd7,0)+COALESCE(sqd8,0))/8)
              FROM csm_responses WHERE response_date BETWEEN ? AND ?', $range)
    : null;

// Daily series, capped so a long range stays readable.
$dailyRows = db_all(
    'SELECT visit_date, COUNT(*) AS total FROM visitors WHERE visit_date BETWEEN ? AND ? GROUP BY visit_date ORDER BY visit_date',
    $range
);
$daily = [];
foreach (array_slice($dailyRows, -31) as $row) {
    $daily[date('M j', strtotime($row['visit_date']))] = (int) $row['total'];
}

require __DIR__ . '/../../includes/header.php';
?>
<div class="wrap">
  <div class="page-head">
    <div>
      <h1>Reports &amp; Summary</h1>
      <p><?= e(fmt_date($from)) ?> to <?= e(fmt_date($to)) ?></p>
    </div>
    <div class="btn-row">
      <button class="btn btn--primary btn--sm" type="button" data-print><?= icon('printer', 16) ?> Print / Save as PDF</button>
      <a class="btn btn--ghost btn--sm" href="<?= file_url('api/export.php', ['type' => 'summary', 'format' => 'excel', 'from' => $from, 'to' => $to]) ?>"><?= icon('download', 16) ?> Excel</a>
      <a class="btn btn--ghost btn--sm" href="<?= file_url('api/export.php', ['type' => 'visitors', 'format' => 'csv', 'from' => $from, 'to' => $to]) ?>"><?= icon('download', 16) ?> Raw CSV</a>
    </div>
  </div>

  <div class="card">
    <div class="card__body">
      <form class="toolbar mb-0" method="get" action="<?= url('Summary') ?>">
        <?php if (!PRETTY_URLS): ?><input type="hidden" name="r" value="Summary"><?php endif; ?>
        <div class="field"><label for="from">From</label>
          <input class="input" type="date" id="from" name="from" value="<?= e($from) ?>"></div>
        <div class="field"><label for="to">To</label>
          <input class="input" type="date" id="to" name="to" value="<?= e($to) ?>"></div>
        <button class="btn btn--primary btn--sm" type="submit"><?= icon('chart', 16) ?> Generate</button>
        <a class="btn btn--ghost btn--sm" href="<?= url('Summary', ['from' => date('Y-m-01', strtotime('-1 month')), 'to' => date('Y-m-t', strtotime('-1 month'))]) ?>">Last month</a>
        <a class="btn btn--ghost btn--sm" href="<?= url('Summary', ['from' => date('Y-01-01'), 'to' => date('Y-m-d')]) ?>">Year to date</a>
      </form>
    </div>
  </div>

  <div class="print-only" style="text-align:center;margin:16px 0">
    <img src="<?= asset('assets/seal.png') ?>" alt="" width="70" height="70">
    <h2 style="margin:6px 0 0"><?= e(APP_LGU) ?></h2>
    <p style="margin:0"><?= e(APP_NAME) ?> - Summary Report</p>
    <p style="margin:0"><?= e(fmt_date($from)) ?> to <?= e(fmt_date($to)) ?></p>
  </div>

  <div class="kpi-grid">
    <div class="kpi">
      <p class="kpi__label"><?= icon('users', 16) ?> Total visitors</p>
      <p class="kpi__value"><?= number_format($totalVisitors) ?></p>
      <p class="kpi__note"><?= $busiest ? 'Busiest: ' . fmt_date($busiest[0]['visit_date']) . ' (' . $busiest[0]['total'] . ')' : 'No visits recorded' ?></p>
    </div>
    <div class="kpi">
      <p class="kpi__label"><?= icon('clock', 16) ?> Average stay</p>
      <p class="kpi__value"><?= $avgStay !== null ? round((float) $avgStay) . '<span style="font-size:1rem;color:#94a3b8"> min</span>' : '&mdash;' ?></p>
      <p class="kpi__note">Time in to time out</p>
    </div>
    <div class="kpi">
      <p class="kpi__label"><?= icon('alert', 16) ?> Concerns received</p>
      <p class="kpi__value"><?= number_format($concernsReceived) ?></p>
      <p class="kpi__note"><?= number_format($concernsResolved) ?> resolved in period</p>
    </div>
    <div class="kpi">
      <p class="kpi__label"><?= icon('clock', 16) ?> Avg. resolution</p>
      <p class="kpi__value"><?= $concernAvgHours !== null ? number_format((float) $concernAvgHours / 24, 1) . '<span style="font-size:1rem;color:#94a3b8"> days</span>' : '&mdash;' ?></p>
      <p class="kpi__note"><?= $concernsOpen ?> still open today</p>
    </div>
    <div class="kpi">
      <p class="kpi__label"><?= icon('message', 16) ?> Feedback received</p>
      <p class="kpi__value"><?= number_format($feedbackReceived) ?></p>
      <p class="kpi__note">In the selected period</p>
    </div>
    <div class="kpi <?= $csmOverall ? 'kpi--good' : '' ?>">
      <p class="kpi__label"><?= icon('star', 16) ?> Satisfaction</p>
      <p class="kpi__value"><?= $csmOverall ? number_format((float) $csmOverall, 2) . '<span style="font-size:1rem;color:#94a3b8">/5</span>' : '&mdash;' ?></p>
      <p class="kpi__note"><?= $csmTotal ? $csmTotal . ' CSM responses' : 'Import the CSM sheet' ?></p>
    </div>
  </div>

  <div class="card">
    <div class="section-bar"><h2>Visitor statistics</h2><p><?= e(fmt_date($from)) ?> to <?= e(fmt_date($to)) ?></p></div>
    <div class="card__body">
      <?php if ($daily): ?>
        <?= svg_bar_chart($daily, ['label' => 'Visitors per day in the selected period']) ?>
      <?php endif; ?>

      <div class="panel-grid mt-24">
        <div>
          <h3 class="subhead"><?= icon('target', 18) ?> By purpose of visit</h3>
          <div class="table-scroll">
            <table class="data">
              <thead><tr><th>Purpose</th><th class="num">Visitors</th><th class="num">Share</th></tr></thead>
              <tbody>
              <?php foreach (array_keys(purposes()) as $purpose): $count = (int) ($byPurpose[$purpose] ?? 0); ?>
                <tr>
                  <td><?= e($purpose) ?></td>
                  <td class="num"><?= $count ?></td>
                  <td class="num"><?= $totalVisitors ? round($count / $totalVisitors * 100) . '%' : '-' ?></td>
                </tr>
              <?php endforeach; ?>
              <tr class="row-total"><td>TOTAL</td><td class="num"><?= $totalVisitors ?></td><td class="num">100%</td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <div>
          <h3 class="subhead"><?= icon('users', 18) ?> By sex and age</h3>
          <div class="table-scroll">
            <table class="data">
              <thead><tr><th>Group</th><th class="num">Visitors</th><th class="num">Share</th></tr></thead>
              <tbody>
              <?php foreach ($bySex as $label => $count): ?>
                <tr><td><?= e($label) ?></td><td class="num"><?= $count ?></td>
                    <td class="num"><?= $totalVisitors ? round($count / $totalVisitors * 100) . '%' : '-' ?></td></tr>
              <?php endforeach; ?>
              <?php foreach ($byAge as $label => $count): ?>
                <tr><td><?= e($label) ?></td><td class="num"><?= $count ?></td>
                    <td class="num"><?= $totalVisitors ? round($count / $totalVisitors * 100) . '%' : '-' ?></td></tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <h3 class="subhead"><?= icon('list', 18) ?> By status</h3>
          <?= bar_rows(array_map('intval', array_merge(array_fill_keys(visitor_statuses(), 0), $byStatus)), '#7c3aed') ?>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="section-bar"><h2>Concerns and feedback</h2><p>Resolution performance for the period</p></div>
    <div class="card__body">
      <div class="table-scroll">
        <table class="data">
          <tbody>
            <tr><th>Concerns received</th><td class="num"><?= number_format($concernsReceived) ?></td></tr>
            <tr><th>Concerns resolved</th><td class="num"><?= number_format($concernsResolved) ?></td></tr>
            <tr><th>Open concerns today</th><td class="num"><?= number_format($concernsOpen) ?></td></tr>
            <tr><th>Average resolution time</th>
                <td class="num"><?= $concernAvgHours !== null ? number_format((float) $concernAvgHours / 24, 1) . ' days' : '-' ?></td></tr>
            <tr><th>Service standard</th><td class="num"><?= (int) CONCERN_SLA_DAYS ?> working days</td></tr>
            <tr><th>Feedback received</th><td class="num"><?= number_format($feedbackReceived) ?></td></tr>
          </tbody>
        </table>
      </div>

      <?php if ($concernByType): ?>
        <h3 class="subhead mt-24"><?= icon('alert', 18) ?> Concerns by type</h3>
        <?= bar_rows(array_map('intval', $concernByType), '#c2410c') ?>
      <?php endif; ?>
    </div>
  </div>

  <p class="small muted center">
    Generated <?= e(date('F j, Y g:i A')) ?> by <?= e($admin['full_name']) ?> &middot; <?= e(APP_LGU) ?>
  </p>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
