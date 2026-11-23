<?php
/**
 * Track a submission by reference number.
 *
 * Only what the holder of that reference number submitted is revealed - no
 * listing, no searching by name, no neighbouring records.
 */

/* Scanning a visitor pass with a phone camera lands here with the whole URL in
   hand, so take the reference number out of whatever arrived. */
$typed     = trim((string) ($_GET['ref'] ?? $_POST['ref'] ?? ''));
$reference = extract_ref_no($typed);
$record = null;
$kind = null;
$error = null;

if ($typed !== '') {
    if ($reference === '') {
        $error = 'That does not look like a reference number. A visitor pass reads VIS-'
               . date('md') . '-4817; a concern or feedback reads CON-2026-00123.';
    } else {
        $prefix = substr($reference, 0, 3);
        if ($prefix === 'CON') {
            $record = db_one('SELECT * FROM concerns WHERE ref_no = ?', [$reference]);
            $kind = 'concern';
        } elseif ($prefix === 'FBK') {
            $record = db_one('SELECT * FROM feedback WHERE ref_no = ?', [$reference]);
            $kind = 'feedback';
        } else {
            $record = db_one('SELECT ref_no, queue_no, visit_date, time_in, time_out, status FROM visitors WHERE ref_no = ?', [$reference]);
            $kind = 'visit';
        }
        if (!$record) {
            $error = 'No submission found with that reference number. Please check it and try again.';
        }
    }
}

require __DIR__ . '/../../includes/header.php';

$stages = ['Received', 'Under Review', 'In Progress', 'Resolved'];
?>
<div class="wrap wrap--narrow">
  <div class="card">
    <div class="card__header">
      <img src="<?= asset('assets/seal.png') ?>" alt="">
      <div>
  <h1>Track a Submission</h1>
        <p>Subaybayan ang inyong isinumite</p>
      </div>
    </div>
    <div class="card__body">

      <form method="get" action="<?= url('TrackSubmission') ?>" class="field">
        <?php if (!PRETTY_URLS): ?><input type="hidden" name="r" value="TrackSubmission"><?php endif; ?>
        <label class="label" for="ref">Reference Number / Numero ng Sanggunian</label>
        <div class="btn-row" style="flex-wrap:nowrap">
          <input class="input" type="text" id="ref" name="ref" value="<?= e($typed) ?>"
                 placeholder="VIS-<?= date('md') ?>-4817" autocapitalize="characters"
                 autocomplete="off" spellcheck="false" style="flex:1">
          <button class="btn btn--primary" type="submit"><?= icon('search') ?> Track</button>
        </div>
        <span class="field-hint">Enter the reference number on your visitor pass, or the one shown when you submitted feedback or a concern. Scanning the QR code on your pass brings you straight here.</span>
      </form>

      <?php if ($error): ?>
        <div class="flash flash--error" role="alert"><?= icon('alert', 20) ?><span><?= e($error) ?></span></div>
      <?php endif; ?>

      <?php if ($record && $kind === 'visit'): ?>
        <?php
        /* The question a visitor actually opened this page to ask is "am I
           next?", not "what is my status enum". Answer that first, in words,
           above the table of facts. */
        $position = queue_position($record['ref_no']);
        $queue    = queue_state();
        ?>
        <div class="ref-box">
          <small>Visitor registration</small>
          <strong><?= e($record['ref_no']) ?></strong>
        </div>

        <?php if ($position): ?>
          <div class="queue-now">
            <div class="queue-now__pair">
              <span class="queue-now__cell">
                <small>Now serving</small>
                <strong><?= $queue['serving'] ?? '--' ?></strong>
              </span>
              <span class="queue-now__cell queue-now__cell--you">
                <small>You are</small>
                <strong><?= (int) $position['queue'] ?></strong>
              </span>
            </div>
            <p class="queue-now__line">
              <?php if ($position['status'] !== 'Waiting'): ?>
                <?= $position['status'] === 'Completed'
                      ? 'Your visit is finished. Thank you for coming.'
                      : 'You are being served now - please go to the counter.' ?>
              <?php elseif ($position['ahead'] === 0): ?>
                <strong>You are next.</strong> Please stay near the counter.
              <?php else: ?>
                <strong><?= (int) $position['ahead'] ?></strong>
                <?= $position['ahead'] === 1 ? 'person is' : 'people are' ?> ahead of you.
              <?php endif; ?>
            </p>
            <p class="queue-now__meta">This page updates when you reload it.</p>
          </div>
        <?php endif; ?>

        <table class="data" style="min-width:0">
          <tr><th>Queue number</th><td><?= (int) $record['queue_no'] ?></td></tr>
          <tr><th>Date of visit</th><td><?= e(fmt_date($record['visit_date'])) ?></td></tr>
          <tr><th>Time in</th><td><?= e(fmt_time($record['time_in'])) ?></td></tr>
          <tr><th>Time out</th><td><?= $record['time_out'] ? e(fmt_time($record['time_out'])) : '<span class="muted">Not yet recorded</span>' ?></td></tr>
          <tr><th>Status</th><td><span class="status <?= e(status_class($record['status'])) ?>"><?= e($record['status']) ?></span></td></tr>
        </table>

      <?php elseif ($record): ?>
        <div class="ref-box">
          <small><?= $kind === 'concern' ? 'Concern' : 'Feedback' ?></small>
          <strong><?= e($record['ref_no']) ?></strong>
        </div>

        <div class="info-grid">
          <div class="info-cell">
            <strong>Submitted</strong>
            <span><?= e(fmt_date($record['date_received'])) ?></span>
          </div>
          <div class="info-cell">
            <strong>Type</strong>
            <span><?= e($record[$kind === 'concern' ? 'concern_type' : 'feedback_type']) ?></span>
          </div>
          <?php if ($kind === 'concern'): ?>
            <div class="info-cell">
              <strong>Subject</strong>
              <span><?= e($record['subject']) ?></span>
            </div>
            <div class="info-cell">
              <strong>Handled by</strong>
              <span><?= $record['assigned_office'] ? e($record['assigned_office']) : 'Being assigned' ?></span>
            </div>
          <?php endif; ?>
        </div>

        <h2 class="subhead"><?= icon('clock', 18) ?> Status</h2>
        <?php
        $currentIndex = array_search($record['status'], $stages, true);
        if ($record['status'] === 'Closed') {
            $currentIndex = count($stages) - 1;
        }
        ?>
        <ul class="timeline">
          <?php foreach ($stages as $index => $stage): ?>
            <?php
            $class = '';
            if ($currentIndex !== false && $index < $currentIndex)      $class = 'is-done';
            elseif ($currentIndex !== false && $index === $currentIndex) $class = 'is-current';
            ?>
            <li class="<?= $class ?>">
              <strong><?= e($stage) ?></strong>
              <?php if ($index === 0): ?>
                <span><?= e(fmt_datetime($record['date_received'])) ?></span>
              <?php elseif ($stage === 'Resolved' && !empty($record['date_resolved'])): ?>
                <span><?= e(fmt_datetime($record['date_resolved'])) ?></span>
              <?php elseif ($class === 'is-current'): ?>
                <span>In this stage now</span>
              <?php else: ?>
                <span class="muted">Pending</span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>

        <?php if (!empty($record['official_response'])): ?>
          <h2 class="subhead" style="margin-top:20px"><?= icon('message', 18) ?> Official response</h2>
          <div class="tinted"><p class="mb-0"><?= nl2br(e($record['official_response'])) ?></p></div>
        <?php else: ?>
          <div class="note mt-24">
            <?= icon('clock', 20) ?>
            <span>No official response has been posted yet. Concerns are normally answered within
              <?= (int) CONCERN_SLA_DAYS ?> working days of being received.</span>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
