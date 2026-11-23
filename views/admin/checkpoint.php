<?php
/**
 * Front-desk check-in: find a visit by scanning its pass or typing its number.
 *
 * The visitor log is the right screen for reviewing the day. It is the wrong
 * screen for the thirty seconds when somebody is standing at the counter
 * holding up a phone - that wants one visit, large, with the two buttons that
 * matter and nothing else on the page to mis-tap.
 *
 * Scanning and typing land in the same place on purpose. The camera is a
 * shortcut, never a requirement: it needs HTTPS, a camera, and a decoder, and
 * on the day one of those is missing the desk must still be able to work. So
 * the reference field is always there, always focused, and a scan simply fills
 * it in and submits it.
 *
 * The found visit is rendered above the two tools rather than below them. It
 * was below at first, which on a desk monitor put it under the fold: the
 * operator scanned, saw nothing move, and scanned again. Scrolling to it in
 * script is the fiddlier answer and depends on the browser co-operating; a
 * layout that never needed scrolling does not.
 */

$reference = extract_ref_no((string) ($_POST['ref'] ?? $_GET['ref'] ?? ''));
$typed     = trim((string) ($_POST['ref'] ?? $_GET['ref'] ?? ''));
$visitor   = null;
$error     = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $action = $_POST['action'] ?? 'find';

    if ($action === 'attend' || $action === 'timeout') {
        $id      = (int) ($_POST['id'] ?? 0);
        $visitor = db_one('SELECT * FROM visitors WHERE id = ?', [$id]);

        if (!$visitor) {
            $error = 'That visit no longer exists.';
        } elseif ($action === 'attend') {
            visitor_mark_attended($id);
            audit('visitor_attended', 'visitor', $visitor['ref_no'], 'from the check-in desk');
            flash($visitor['name'] . ' is now being served.');
            redirect('Checkpoint', ['ref' => $visitor['ref_no']]);
        } else {
            db_run("UPDATE visitors SET time_out = NOW(), status = 'Completed' WHERE id = ?", [$id]);
            audit('visitor_timed_out', 'visitor', $visitor['ref_no'], 'from the check-in desk');
            flash('Time out recorded for ' . $visitor['name'] . '.');
            redirect('Checkpoint', ['ref' => $visitor['ref_no']]);
        }
    }
}

if (!$error && $typed !== '') {
    if ($reference === '') {
        $error = 'That is not a reference number. A visitor pass reads VIS-'
               . date('md') . '-0000, and older ones read VIS-2026-00123.';
    } elseif (!str_starts_with($reference, 'VIS')) {
        $error = $reference . ' is a ' . (str_starts_with($reference, 'CON') ? 'concern' : 'feedback')
               . ' reference, not a visitor pass. Open it under '
               . (str_starts_with($reference, 'CON') ? 'Concerns' : 'Feedback') . '.';
    } else {
        $visitor = db_one('SELECT * FROM visitors WHERE ref_no = ?', [$reference]);
        if (!$visitor) {
            $error = 'No visit found for ' . $reference . '. Check the number, or search the visitor log.';
        }
    }
}

/* No spring on the found visit. The overshoot on the registration receipt is
   earned - it happens once, and it is a small celebration. This screen shows a
   card per visitor all morning, and a bounce on each one is noise. */
$pageScripts = ['js/scanner.js'];

require __DIR__ . '/../../includes/header.php';
?>
<div class="wrap">
  <div class="page-head">
    <div>
      <h1>Check-in Desk</h1>
      <p>Scan a visitor's pass, or type the reference number they read out.</p>
    </div>
    <a class="btn btn--ghost btn--sm" href="<?= url('Log') ?>"><?= icon('list', 16) ?> Full visitor log</a>
  </div>

  <?php if ($visitor): ?>
    <?php
    $isToday   = $visitor['visit_date'] === date('Y-m-d');
    $ahead     = $visitor['status'] === 'Waiting' && $isToday
        ? (int) db_val("SELECT COUNT(*) FROM visitors
                         WHERE visit_date = CURDATE() AND status = 'Waiting' AND queue_no < ?",
                       [$visitor['queue_no']], 0)
        : null;
    ?>
    <div class="card visit-card">
      <div class="card__header">
        <img src="<?= asset('assets/seal.png') ?>" alt="">
        <div>
          <h1><?= e($visitor['name']) ?></h1>
          <p><?= e($visitor['ref_no']) ?> &middot; Queue <?= (int) $visitor['queue_no'] ?></p>
        </div>
        <span class="status status--lg <?= e(status_class($visitor['status'])) ?>"><?= e($visitor['status']) ?></span>
      </div>
      <div class="card__body">

        <?php if (!$isToday): ?>
          <div class="flash flash--error" role="alert">
            <?= icon('alert', 20) ?>
            <span>This pass is from <?= e(fmt_date($visitor['visit_date'])) ?>, not today.
              Register the visitor again rather than reusing it.</span>
          </div>
        <?php elseif ($ahead !== null && $ahead > 0): ?>
          <div class="note">
            <?= icon('users', 20) ?>
            <span><?= $ahead ?> visitor<?= $ahead === 1 ? '' : 's' ?> registered before this one
              <?= $ahead === 1 ? 'is' : 'are' ?> still waiting.</span>
          </div>
        <?php endif; ?>

        <div class="info-grid">
          <div class="info-cell"><strong>Purpose</strong>
            <span><?= e($visitor['purpose']) ?><?= $visitor['purpose_other'] ? ' - ' . e($visitor['purpose_other']) : '' ?></span></div>
          <div class="info-cell"><strong>Sex and age</strong>
            <span><?= e($visitor['sex']) ?>, <?= (int) $visitor['age'] ?></span></div>
          <div class="info-cell"><strong>Contact</strong>
            <span><?= $visitor['contact'] ? e($visitor['contact']) : '<span class="muted">Not given</span>' ?></span></div>
          <div class="info-cell"><strong>Address</strong>
            <span><?= e($visitor['address']) ?></span></div>
          <div class="info-cell"><strong>Time in</strong>
            <span><?= e(fmt_time($visitor['time_in'])) ?></span></div>
          <div class="info-cell"><strong>Time out</strong>
            <span><?= $visitor['time_out'] ? e(fmt_time($visitor['time_out'])) : '<span class="muted">Not yet recorded</span>' ?></span></div>
        </div>

        <div class="btn-row mt-24">
          <?php if ($visitor['status'] === 'Waiting'): ?>
            <form method="post" action="<?= url('Checkpoint') ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $visitor['id'] ?>">
              <button class="btn btn--primary btn--lg" name="action" value="attend">
                <?= icon('check', 20) ?> Call this visitor
              </button>
            </form>
          <?php endif; ?>

          <?php if (!$visitor['time_out']): ?>
            <form method="post" action="<?= url('Checkpoint') ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $visitor['id'] ?>">
              <button class="btn <?= $visitor['status'] === 'Waiting' ? 'btn--ghost' : 'btn--primary' ?> btn--lg"
                      name="action" value="timeout">
                <?= icon('clock', 20) ?> Record time out
              </button>
            </form>
          <?php else: ?>
            <p class="muted mb-0"><?= icon('check-circle', 18) ?>
              Timed out at <?= e(fmt_time($visitor['time_out'])) ?>. Nothing further to do.</p>
          <?php endif; ?>

          <?php if ($visitor['signature']): ?>
            <button class="btn btn--ghost btn--lg" type="button"
                    data-signature="<?= e($visitor['signature']) ?>" data-name="<?= e($visitor['name']) ?>">
              <?= icon('eye', 18) ?> Signature
            </button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="checkpoint">

    <!-- Scanner ------------------------------------------------------- -->
    <div class="card">
      <div class="card__body">
        <h2 class="subhead"><?= icon('scan', 18) ?> Scan the pass</h2>

        <div class="scanner" id="scanner" data-scanner>
          <div class="scanner__stage">
            <?php /* muted + playsinline: an unmuted autoplaying video is blocked
                     outright on mobile, and without playsinline iOS takes the
                     camera fullscreen and the desk loses the rest of the page. */ ?>
            <video class="scanner__video" id="scannerVideo" muted playsinline></video>
            <span class="scanner__frame" aria-hidden="true"></span>
            <p class="scanner__state" id="scannerState" role="status">Camera is off.</p>
          </div>

          <div class="btn-row">
            <button class="btn btn--primary" type="button" data-scan-start>
              <?= icon('camera', 18) ?> Start camera
            </button>
            <button class="btn btn--ghost" type="button" data-scan-stop hidden>
              <?= icon('x', 18) ?> Stop
            </button>
          </div>

          <p class="field-hint" data-scan-help>
            The camera needs permission the first time, and the site must be on HTTPS
            (or localhost) for the browser to offer it at all. A USB barcode scanner
            needs none of this - it types into the reference field on its own.
          </p>
        </div>
      </div>
    </div>

    <!-- Manual entry --------------------------------------------------- -->
    <div class="card">
      <div class="card__body">
        <h2 class="subhead"><?= icon('edit', 18) ?> Or type the reference number</h2>

        <form method="post" action="<?= url('Checkpoint') ?>" data-scan-form>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="find">
          <div class="field">
            <label class="label" for="ref">Reference number</label>
            <div class="btn-row" style="flex-wrap:nowrap">
              <input class="input input--ref" type="text" id="ref" name="ref" value="<?= e($typed) ?>"
                     placeholder="VIS-<?= date('md') ?>-0000" autocapitalize="characters"
                     autocomplete="off" spellcheck="false" style="flex:1"
                     <?= $visitor ? '' : 'autofocus' ?> data-scan-target>
              <button class="btn btn--primary" type="submit"><?= icon('search', 17) ?> Find</button>
            </div>
            <span class="field-hint">
              A camera scan or a USB scanner fills this in by itself. Pasting the whole
              link from a visitor's phone works too - the number is read out of it.
            </span>
          </div>
        </form>

        <?php if ($error): ?>
          <div class="flash flash--error" role="alert"><?= icon('alert', 20) ?><span><?= e($error) ?></span></div>
        <?php endif; ?>
      </div>
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
      <img data-signature-image src="" alt="Visitor signature"
           style="max-width:100%;border:1px solid var(--line);border-radius:10px">
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
