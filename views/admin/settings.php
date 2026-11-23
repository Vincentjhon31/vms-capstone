<?php
/** Settings - password, the server clock, retention housekeeping, and the audit trail. */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        if (strlen($new) < 10) {
            flash('Choose a password of at least 10 characters.', 'error');
        } elseif ($new !== $confirm) {
            flash('The two new passwords do not match.', 'error');
        } elseif (!change_admin_password((int) $admin['id'], $current, $new)) {
            flash('Your current password is not correct.', 'error');
        } else {
            flash('Password changed.');
        }
    }

    // One-time repair for rows written before the MySQL session time zone was
    // pinned (see includes/db.php). Those rows hold the database server's wall
    // clock, not Manila's, and no amount of formatting fixes a wrong stored
    // value - the numbers themselves have to move.
    if ($action === 'shift_times') {
        $hours   = (int) ($_POST['hours'] ?? 0);
        $already = (int) db_val("SELECT COUNT(*) FROM audit_log WHERE action = 'timezone_shift'", [], 0);

        if ($hours === 0 || $hours < -14 || $hours > 14) {
            flash('Choose how many hours the old records are out by.', 'error');
        } elseif ($already && ($_POST['again'] ?? '') !== 'yes') {
            flash('These timestamps have already been corrected once. Tick "apply anyway" only if the first run used the wrong number of hours.', 'error');
        } else {
            $cutoff = strtotime((string) ($_POST['cutoff'] ?? ''));
            $cutoff = date('Y-m-d H:i:s', $cutoff === false ? time() : $cutoff);

            // visit_date is derived from time_in rather than shifted: a visitor
            // registered at 20:30 UTC belongs to the next day in Manila, and
            // adding hours to the date column alone would not say that. It has to
            // be assigned first - MySQL evaluates SET left to right and later
            // expressions see the already-updated time_in, which would shift twice.
            $moved = db_run(
                'UPDATE visitors
                    SET visit_date  = DATE(time_in + INTERVAL ? HOUR),
                        time_in     = time_in + INTERVAL ? HOUR,
                        time_out    = CASE WHEN time_out IS NULL THEN NULL ELSE time_out + INTERVAL ? HOUR END,
                        attended_at = CASE WHEN attended_at IS NULL THEN NULL ELSE attended_at + INTERVAL ? HOUR END
                  WHERE time_in < ?',
                [$hours, $hours, $hours, $hours, $cutoff]
            )->rowCount();

            audit('timezone_shift', 'visitor', null,
                  $moved . ' record(s) before ' . $cutoff . ' shifted by ' . sprintf('%+d', $hours) . ' hour(s)');
            flash($moved . ' visitor record(s) were shifted by ' . sprintf('%+d', $hours) . ' hour(s).');
        }
    }

    if ($action === 'purge') {
        $cutoff = date('Y-m-d', strtotime('-' . RETENTION_DAYS . ' days'));
        $visitors = (int) db_val('SELECT COUNT(*) FROM visitors WHERE visit_date < ?', [$cutoff], 0);
        db_run('DELETE FROM visitors WHERE visit_date < ?', [$cutoff]);
        audit('retention_purge', 'visitor', null, $visitors . ' records older than ' . $cutoff);
        flash($visitors . ' visitor record(s) older than ' . fmt_date($cutoff) . ' were disposed of.');
    }

    redirect('AdminSettings');
}

$cutoff = date('Y-m-d', strtotime('-' . RETENTION_DAYS . ' days'));
$dueForPurge = (int) db_val('SELECT COUNT(*) FROM visitors WHERE visit_date < ?', [$cutoff], 0);
$auditRows = db_all('SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 40');

// The clock check. If these two disagree, time_in is being written on the wrong
// clock and every recorded time is out by the difference.
$phpNow    = date('Y-m-d H:i:s');
$dbNow     = (string) db_val('SELECT NOW()', [], '');
$dbDrift   = $dbNow === '' ? null : (int) round((strtotime($dbNow) - strtotime($phpNow)) / 60);
$clockOk   = $dbDrift !== null && abs($dbDrift) <= 2;
$dbZone    = (string) db_val('SELECT @@session.time_zone', [], '');

// What this server would have recorded without the pin in includes/db.php. On a
// host that already runs on Philippine time it matches; on Hostinger it is eight
// hours out, which is the whole reason the pin exists.
$rawNow    = db_unpinned_now();
$rawDrift  = $rawNow === null ? null : (int) round((strtotime($rawNow) - strtotime($phpNow)) / 60);
$pinMatters = $rawDrift !== null && abs($rawDrift) > 2;
$shiftDone = (int) db_val("SELECT COUNT(*) FROM audit_log WHERE action = 'timezone_shift'", [], 0);
$oldest    = (string) db_val('SELECT MIN(time_in) FROM visitors', [], '');
$counts = [
    'Visitors'      => (int) db_val('SELECT COUNT(*) FROM visitors', [], 0),
    'Concerns'      => (int) db_val('SELECT COUNT(*) FROM concerns', [], 0),
    'Feedback'      => (int) db_val('SELECT COUNT(*) FROM feedback', [], 0),
    'CSM responses' => (int) db_val('SELECT COUNT(*) FROM csm_responses', [], 0),
    'Services'      => (int) db_val('SELECT COUNT(*) FROM services', [], 0),
    'FAQ entries'   => (int) db_val('SELECT COUNT(*) FROM faq_entries', [], 0),
];

require __DIR__ . '/../../includes/header.php';
?>
<div class="wrap">
  <div class="page-head">
    <div>
      <h1>Settings</h1>
      <p>Signed in as <?= e($admin['full_name']) ?> (<?= e($admin['username']) ?>).</p>
    </div>
    <a class="btn btn--ghost btn--sm" href="<?= url('AdminLogout') ?>"><?= icon('logout', 16) ?> Sign out</a>
  </div>

  <div class="panel-grid">
    <div class="panel">
      <div class="panel__head"><h2>Change password</h2></div>
      <div class="panel__body">
        <form method="post" action="<?= url('AdminSettings') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="password">
          <div class="field">
            <label class="label" for="current_password">Current password</label>
            <input class="input" type="password" id="current_password" name="current_password" required autocomplete="current-password">
          </div>
          <div class="field">
            <label class="label" for="new_password">New password</label>
            <input class="input" type="password" id="new_password" name="new_password" required minlength="10" autocomplete="new-password">
            <span class="field-hint">At least 10 characters. Change the seeded password before going live.</span>
          </div>
          <div class="field">
            <label class="label" for="confirm_password">Repeat new password</label>
            <input class="input" type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
          </div>
          <button class="btn btn--primary" type="submit"><?= icon('lock') ?> Change password</button>
        </form>
      </div>
    </div>

    <div class="panel">
      <div class="panel__head">
        <h2>Server clock</h2>
        <span class="chip"><?= e(APP_TIMEZONE) ?></span>
      </div>
      <div class="panel__body">
        <div class="table-scroll">
          <table class="data" style="min-width:0">
            <tbody>
              <tr><th>Application (PHP)</th><td class="num nowrap"><?= e($phpNow) ?></td></tr>
              <tr><th>Database (MySQL)</th><td class="num nowrap"><?= $dbNow === '' ? '-' : e($dbNow) ?></td></tr>
              <tr><th>Difference</th>
                  <td class="num nowrap"><?= $dbDrift === null ? '-' : e(sprintf('%+d min', $dbDrift)) ?></td></tr>
              <tr><th>Session zone</th><td class="num nowrap"><?= $dbZone === '' ? '-' : e($dbZone) ?></td></tr>
              <tr><th class="muted">This server unpinned</th>
                  <td class="num nowrap muted"><?= $rawNow === null ? '-' : e($rawNow) ?></td></tr>
            </tbody>
          </table>
        </div>

        <?php if ($clockOk): ?>
          <p class="small"><?= icon('check', 15) ?> Both clocks read <?= e(APP_TIMEZONE) ?>.
            Time in and time out are being recorded correctly.</p>
          <?php if ($pinMatters): ?>
            <p class="small"><?= icon('alert', 15) ?> Left to itself this database would have
              recorded <strong><?= e(sprintf('%+d', (int) round($rawDrift / 60))) ?> hours</strong>
              off. The session zone above is what corrects it, and it is set on every connection -
              so this stays right only while <code>includes/db.php</code> is deployed.</p>
          <?php endif; ?>
        <?php else: ?>
          <p class="small"><?= icon('alert', 15) ?> <strong>The two clocks disagree.</strong>
            Times recorded right now are out by this much. Deploy the current
            <code>includes/db.php</code> to this server, then reload this page.</p>
        <?php endif; ?>

        <?php if ($oldest !== ''): ?>
        <details>
          <summary class="small">Correct records saved before the fix</summary>
          <p class="small">Entries written while the clocks disagreed hold the wrong time and stay
            wrong until they are moved. The earliest recorded time in is
            <strong><?= e(fmt_datetime($oldest)) ?></strong> - check that against what the desk
            actually saw, then shift the older records by the difference.</p>
          <?php if ($shiftDone): ?>
            <p class="small"><?= icon('alert', 15) ?> This correction has already been run once -
              see the audit trail below. Running it again moves the same records a second time.</p>
          <?php endif; ?>
          <form method="post" action="<?= url('AdminSettings') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="shift_times">
            <div class="field">
              <label class="label" for="hours">Shift older records by</label>
              <select class="input" id="hours" name="hours">
                <option value="0">Select...</option>
                <?php foreach ([8, 7, -7, -8] as $h): ?>
                  <option value="<?= $h ?>"><?= sprintf('%+d', $h) ?> hours</option>
                <?php endforeach; ?>
              </select>
              <span class="field-hint">Philippine time is 8 hours ahead of UTC, so records written
                on a UTC database need <strong>+8</strong>.</span>
            </div>
            <div class="field">
              <label class="label" for="cutoff">Only records with a time in before</label>
              <input class="input" type="datetime-local" id="cutoff" name="cutoff"
                     value="<?= e(date('Y-m-d\\TH:i')) ?>">
              <span class="field-hint">Leave this at the current moment to correct everything
                recorded so far. Anything saved after the fix is already right and must not move.</span>
            </div>
            <?php if ($shiftDone): ?>
              <label class="small" style="display:block;margin-bottom:12px">
                <input type="checkbox" name="again" value="yes"> Apply anyway - the first run used the wrong number of hours
              </label>
            <?php endif; ?>
            <button class="btn btn--danger" type="submit"
                    data-confirm="Shift the stored time in, time out and visit date of every visitor record before the cutoff? This rewrites saved data.">
              <?= icon('clock') ?> Shift stored times
            </button>
          </form>
        </details>
        <?php endif; ?>
      </div>
    </div>

    <div class="panel">
      <div class="panel__head"><h2>Data retention</h2><span class="chip"><?= (int) RETENTION_DAYS ?> days</span></div>
      <div class="panel__body">
        <p class="small">The privacy notice tells citizens their visitor records are kept for
          <strong><?= (int) RETENTION_DAYS ?> days</strong>. Records before
          <strong><?= e(fmt_date($cutoff)) ?></strong> are past that period.</p>

        <div class="table-scroll">
          <table class="data" style="min-width:0">
            <tbody>
            <?php foreach ($counts as $label => $count): ?>
              <tr><th><?= e($label) ?></th><td class="num"><?= number_format($count) ?></td></tr>
            <?php endforeach; ?>
            <tr><th>Past retention</th><td class="num"><?= number_format($dueForPurge) ?></td></tr>
            </tbody>
          </table>
        </div>

        <form method="post" action="<?= url('AdminSettings') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="purge">
          <button class="btn btn--danger" type="submit" <?= $dueForPurge ? '' : 'disabled' ?>
                  data-confirm="Permanently delete <?= $dueForPurge ?> visitor record(s) older than <?= e(fmt_date($cutoff)) ?>?">
            <?= icon('trash') ?> Dispose of records past retention
          </button>
        </form>
        <p class="field-hint">The retention period is set in <code>includes/config.php</code> (RETENTION_DAYS).</p>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="section-bar"><h2>Audit trail</h2><p>Last 40 administrative actions</p></div>
    <div class="card__body">
      <div class="table-scroll">
        <table class="data">
          <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Entity</th><th>Detail</th><th>IP</th></tr></thead>
          <tbody>
          <?php foreach ($auditRows as $row): ?>
            <tr>
              <td class="nowrap small"><?= e(fmt_datetime($row['created_at'])) ?></td>
              <td class="small"><?= e($row['actor']) ?></td>
              <td class="small"><?= e(str_replace('_', ' ', $row['action'])) ?></td>
              <td class="small"><?= e($row['entity']) ?> <?= $row['entity_id'] ? '<span class="muted">' . e($row['entity_id']) . '</span>' : '' ?></td>
              <td class="small"><?= e($row['details']) ?></td>
              <td class="small muted"><?= e($row['ip_address']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
