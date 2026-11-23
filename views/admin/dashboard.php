<?php
/** Admin dashboard - the daily picture of the front desk. */

require_once __DIR__ . '/../../includes/charts.php';

$today  = (int) db_val('SELECT COUNT(*) FROM visitors WHERE visit_date = CURDATE()', [], 0);
$week   = (int) db_val('SELECT COUNT(*) FROM visitors WHERE YEARWEEK(visit_date, 1) = YEARWEEK(CURDATE(), 1)', [], 0);
$month  = (int) db_val('SELECT COUNT(*) FROM visitors WHERE YEAR(visit_date) = YEAR(CURDATE()) AND MONTH(visit_date) = MONTH(CURDATE())', [], 0);
$waiting = (int) db_val("SELECT COUNT(*) FROM visitors WHERE visit_date = CURDATE() AND status = 'Waiting'", [], 0);

$openConcerns = (int) db_val("SELECT COUNT(*) FROM concerns WHERE status NOT IN ('Resolved','Closed')", [], 0);
$newFeedback  = (int) db_val("SELECT COUNT(*) FROM feedback WHERE status = 'Received'", [], 0);

$avgResolution = db_val(
    "SELECT AVG(TIMESTAMPDIFF(HOUR, date_received, date_resolved)) FROM concerns WHERE date_resolved IS NOT NULL"
);
$csmCount = (int) db_val('SELECT COUNT(*) FROM csm_responses', [], 0);
$csmAverage = $csmCount
    ? db_val('SELECT (AVG(COALESCE(sqd1,0)+COALESCE(sqd2,0)+COALESCE(sqd3,0)+COALESCE(sqd4,0)+COALESCE(sqd5,0)
                          +COALESCE(sqd6,0)+COALESCE(sqd7,0)+COALESCE(sqd8,0))/8) FROM csm_responses')
    : null;

// Visitors per day for the past 14 days.
$perDay = [];
for ($offset = 13; $offset >= 0; $offset--) {
    $date = date('Y-m-d', strtotime("-{$offset} days"));
    $perDay[date('M j', strtotime($date))] = (int) db_val('SELECT COUNT(*) FROM visitors WHERE visit_date = ?', [$date], 0);
}

$purposeRows = db_all(
    'SELECT purpose, COUNT(*) AS total FROM visitors
     WHERE visit_date >= (CURDATE() - INTERVAL 30 DAY) GROUP BY purpose ORDER BY total DESC'
);
$purposeData = array_column($purposeRows, 'total', 'purpose');

$sexRows = db_all('SELECT sex, COUNT(*) AS total FROM visitors WHERE visit_date >= (CURDATE() - INTERVAL 30 DAY) GROUP BY sex');
$sexData = ['Male' => 0, 'Female' => 0];
foreach ($sexRows as $row) {
    $sexData[$row['sex']] = (int) $row['total'];
}

$ageData = ['17 and below' => 0, '18-25' => 0, '26-40' => 0, '41-59' => 0, '60 and above' => 0];
foreach (db_all('SELECT age FROM visitors WHERE visit_date >= (CURDATE() - INTERVAL 30 DAY)') as $row) {
    $ageData[age_bracket((int) $row['age'])]++;
}

$recentVisitors = db_all('SELECT ref_no, name, purpose, time_in, status FROM visitors ORDER BY id DESC LIMIT 6');
$dueConcerns = db_all(
    "SELECT * FROM concerns WHERE status NOT IN ('Resolved','Closed')
     ORDER BY date_received ASC LIMIT 6"
);

require __DIR__ . '/../../includes/header.php';
?>
<div class="wrap">
  <div class="page-head">
    <div>
      <h1>Dashboard</h1>
      <p>Signed in as <?= e($admin['full_name']) ?> &middot; <?= e(date('l, F j, Y')) ?></p>
    </div>
    <div class="btn-row">
      <a class="btn btn--ghost btn--sm" href="<?= file_url('api/export.php', ['type' => 'visitors', 'format' => 'csv']) ?>"><?= icon('download', 16) ?> Export today</a>
      <a class="btn btn--primary btn--sm" href="<?= url('Register') ?>" target="_blank" rel="noopener"><?= icon('user-plus', 16) ?> Register a visitor</a>
    </div>
  </div>

  <div class="kpi-grid">
    <div class="kpi">
      <p class="kpi__label"><?= icon('users', 16) ?> Visitors today</p>
      <p class="kpi__value"><span data-count-to="<?= $today ?>"><?= $today ?></span></p>
      <p class="kpi__note"><?= $waiting ?> still waiting</p>
    </div>
    <div class="kpi">
      <p class="kpi__label"><?= icon('calendar', 16) ?> This week</p>
      <p class="kpi__value"><span data-count-to="<?= $week ?>"><?= $week ?></span></p>
      <p class="kpi__note">Monday to today</p>
    </div>
    <div class="kpi">
      <p class="kpi__label"><?= icon('calendar', 16) ?> This month</p>
      <p class="kpi__value"><span data-count-to="<?= $month ?>"><?= $month ?></span></p>
      <p class="kpi__note"><?= e(date('F Y')) ?></p>
    </div>
    <div class="kpi <?= $openConcerns ? 'kpi--warn' : '' ?>">
      <p class="kpi__label"><?= icon('alert', 16) ?> Open concerns</p>
      <p class="kpi__value"><span data-count-to="<?= $openConcerns ?>"><?= $openConcerns ?></span></p>
      <p class="kpi__note"><?= $newFeedback ?> new feedback</p>
    </div>
    <div class="kpi <?= $csmAverage ? 'kpi--good' : '' ?>">
      <p class="kpi__label"><?= icon('star', 16) ?> Avg. satisfaction</p>
      <p class="kpi__value"><?= $csmAverage ? number_format((float) $csmAverage, 2) . '<span style="font-size:1rem;color:#94a3b8">/5</span>' : '&mdash;' ?></p>
      <p class="kpi__note"><?= $csmCount ? $csmCount . ' CSM responses imported' : 'Import the CSM sheet to see this' ?></p>
    </div>
    <div class="kpi">
      <p class="kpi__label"><?= icon('clock', 16) ?> Avg. resolution</p>
      <p class="kpi__value"><?= $avgResolution !== null ? number_format((float) $avgResolution / 24, 1) . '<span style="font-size:1rem;color:#94a3b8"> days</span>' : '&mdash;' ?></p>
      <p class="kpi__note">Concern received to resolved</p>
    </div>
  </div>

  <div class="panel-grid" style="margin-bottom:20px">
    <div class="panel" style="grid-column:1 / -1">
      <div class="panel__head">
        <h2>Visitors per day</h2>
        <span class="chip">Last 14 days</span>
      </div>
      <div class="panel__body"><?= svg_bar_chart($perDay, ['label' => 'Visitors per day for the last 14 days']) ?></div>
    </div>
  </div>

  <div class="panel-grid" style="margin-bottom:20px">
    <div class="panel">
      <div class="panel__head"><h2>Purpose of visit</h2><span class="chip">Last 30 days</span></div>
      <div class="panel__body"><?= svg_donut($purposeData, ['label' => 'Purpose of visit breakdown']) ?></div>
    </div>
    <div class="panel">
      <div class="panel__head"><h2>Sex and age</h2><span class="chip">Last 30 days</span></div>
      <div class="panel__body">
        <p class="cat-head" style="margin-top:0">Sex</p>
        <?= bar_rows($sexData) ?>
        <p class="cat-head">Age group</p>
        <?= bar_rows($ageData, '#16a34a') ?>
      </div>
    </div>
  </div>

  <div class="panel-grid">
    <div class="panel">
      <div class="panel__head">
        <h2>Recent activity</h2>
        <a class="small" href="<?= url('Log') ?>">View all</a>
      </div>
      <div class="panel__body" style="padding:0">
        <?php if ($recentVisitors): ?>
          <div class="table-scroll" style="border:0;margin:0">
            <table class="data">
              <tbody>
              <?php foreach ($recentVisitors as $visitor): ?>
                <tr>
                  <td>
                    <strong><?= e($visitor['name']) ?></strong><br>
                    <span class="small muted"><?= e($visitor['purpose']) ?> &middot; <?= e($visitor['ref_no']) ?></span>
                  </td>
                  <td class="num small"><?= e(fmt_time($visitor['time_in'])) ?></td>
                  <td><span class="status <?= e(status_class($visitor['status'])) ?>"><?= e($visitor['status']) ?></span></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="empty"><?= icon('users', 30) ?><p>No visitors registered yet.</p></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="panel">
      <div class="panel__head">
        <h2>Concerns needing attention</h2>
        <a class="small" href="<?= url('ConcernList') ?>">View all</a>
      </div>
      <div class="panel__body" style="padding:0">
        <?php if ($dueConcerns): ?>
          <div class="table-scroll" style="border:0;margin:0">
            <table class="data">
              <tbody>
              <?php foreach ($dueConcerns as $concern): ?>
                <tr>
                  <td>
                    <strong><?= e($concern['subject']) ?></strong><br>
                    <span class="small muted"><?= e($concern['ref_no']) ?> &middot; <?= e(fmt_date($concern['date_received'])) ?></span>
                  </td>
                  <td>
                    <?php if (is_overdue($concern)): ?>
                      <span class="status status--overdue">Overdue</span>
                    <?php else: ?>
                      <span class="status <?= e(status_class($concern['status'])) ?>"><?= e($concern['status']) ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="num"><a class="btn btn--ghost btn--sm" href="<?= url('ConcernList', ['ref' => $concern['ref_no']]) ?>">Open</a></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="empty"><?= icon('check-circle', 30) ?><p>Nothing outstanding. Every concern is resolved or closed.</p></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
