<?php
/**
 * Visitor registration.
 *
 * Presented as four short steps rather than one long scroll - the field list is
 * unchanged, but a phone screen now shows a step at a time with its progress
 * visible. It still POSTs once, and the server re-validates everything below,
 * so with JavaScript off every step is simply shown at once and it works.
 */

$errors = [];
$old = ['name' => '', 'sex' => '', 'age' => '', 'contact' => '', 'address' => '', 'purpose' => '', 'purpose_other' => ''];
$done = null;

if (!empty($_SESSION['registered'])) {
    $done = $_SESSION['registered'];
    unset($_SESSION['registered']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    foreach ($old as $key => $_) {
        $old[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $signature = (string) ($_POST['signature'] ?? '');
    $consent   = !empty($_POST['consent']);

    if (!$consent) {
        $errors['consent'] = 'Your consent is required before we can record your visit.';
    }
    if ($old['name'] === '' || mb_strlen($old['name']) < 2) {
        $errors['name'] = 'Please enter your full name.';
    }
    if (!in_array($old['sex'], ['Male', 'Female'], true)) {
        $errors['sex'] = 'Please select your sex.';
    }
    if (!ctype_digit($old['age']) || (int) $old['age'] < 1 || (int) $old['age'] > 120) {
        $errors['age'] = 'Please enter a valid age.';
    }
    if ($old['contact'] !== '') {
        $old['contact'] = preg_replace('/\D/', '', $old['contact']);
        if (strlen($old['contact']) !== 11 || !str_starts_with($old['contact'], '0')) {
            $errors['contact'] = 'The contact number must be exactly 11 digits, e.g. 09XXXXXXXXX.';
        }
    }
    if ($old['address'] === '') {
        $errors['address'] = 'Please enter your complete address.';
    }
    if (!array_key_exists($old['purpose'], purposes())) {
        $errors['purpose'] = 'Please choose the purpose of your visit.';
    }
    if ($old['purpose'] === 'Others' && $old['purpose_other'] === '') {
        $errors['purpose_other'] = 'Please tell us the purpose of your visit.';
    }
    if (!str_starts_with($signature, 'data:image/png;base64,') || strlen($signature) > 1500000) {
        $errors['signature'] = 'Please sign in the box above.';
    }
    if (!$errors && !rate_limit_ok('register')) {
        $errors['form'] = 'Too many registrations from this device in the past hour. Please ask the front desk for help.';
    }

    if (!$errors) {
        $refNo   = short_ref_no('VIS', 'visitors');
        $queueNo = next_queue_no();
        db_run(
            'INSERT INTO visitors (ref_no, queue_no, name, sex, age, contact, address, purpose, purpose_other,
                                   signature, consent, visit_date, time_in, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, CURDATE(), NOW(), ?)',
            [
                $refNo, $queueNo, $old['name'], $old['sex'], (int) $old['age'],
                $old['contact'] !== '' ? $old['contact'] : null,
                $old['address'], $old['purpose'],
                $old['purpose'] === 'Others' ? $old['purpose_other'] : null,
                $signature, 'Waiting',
            ]
        );
        audit('visitor_registered', 'visitor', $refNo, $old['purpose']);
        $_SESSION['registered'] = [
            'ref_no'   => $refNo,
            'queue_no' => $queueNo,
            'name'     => $old['name'],
            'purpose'  => $old['purpose'] === 'Others' ? $old['purpose_other'] : $old['purpose'],
            'time_in'  => date('c'),
        ];
        redirect('Register');
    }
}

/** An icon for each purpose, so the choice reads at a glance. */
$purposeIcons = [
    'See the Mayor'              => 'building',
    'See the Municipal Administrator' => 'user',
    'Avail of Services'          => 'clipboard',
    'Courtesy Visit/Appointment' => 'calendar',
    'Inquiry'                    => 'help',
    'Attend Meeting'             => 'users',
    'Document Request'           => 'book',
    'Others'                     => 'grid',
];

$steps = [
    ['label' => 'Consent',   'title' => 'Data privacy consent',   'sub' => 'We need your permission before recording anything.'],
    ['label' => 'About you', 'title' => 'About you',              'sub' => 'Tell us who is visiting today.'],
    ['label' => 'Purpose',   'title' => 'Purpose of your visit',  'sub' => 'Who or what are you here for?'],
    ['label' => 'Sign',      'title' => 'Review and sign',        'sub' => 'Check your details, then sign to finish.'],
];

/* The wall panel invites people to register; on the receipt that invitation is
   already spent, and the screen has its own, far more important code to show. */
$showQrPanel = !$done;
$useMotion   = true;                       // the queue number springs in on success
$useConfetti = (bool) $done;               // one burst on the receipt, nowhere else
$pageScripts = $done ? ['js/qr.js', 'js/pass.js'] : ['js/stepper.js'];

/* What the pass encodes: the visitor's own tracking page, not the bare number.
   A phone camera pointed at it opens that page and shows where they are in the
   queue; the desk's scanner reads the number back out of the URL. One code,
   both jobs. */
$passUrl = $done ? site_url('TrackSubmission', ['ref' => $done['ref_no']]) : '';
require __DIR__ . '/../../includes/header.php';
?>
<div class="wrap">
  <?php /* The wall panel invites people to register. On the receipt they already
           have, so it would only compete with the code that is actually theirs. */ ?>
  <?php if ($showQrPanel): ?>
    <?php require __DIR__ . '/../../includes/qr_panel.php'; ?>
  <?php endif; ?>

  <?php if ($done): ?>
    <div class="card pass-card" data-confetti-once>
      <div class="card__header card__header--green">
        <img src="<?= asset('assets/seal.png') ?>" alt="">
        <div>
          <h1>Registration Complete</h1>
          <p>Tapos na ang pagpaparehistro</p>
        </div>
      </div>
      <div class="card__body center">
        <div class="success-mark"><?= icon('check', 38) ?></div>

        <div class="queue-box" data-spring-in>
          <small>Your queue number / Numero ninyo</small>
          <strong><?= (int) $done['queue_no'] ?></strong>
        </div>

        <?php /* The pass. Everything the desk needs to find this visit is inside
                 this one bordered block, so a screenshot of it is complete: the
                 code, the number under it in plain type in case a camera will
                 not read the code, and what to do with it. */ ?>
        <div class="pass" data-spring-in-2>
          <p class="pass__title"><?= icon('qr', 18) ?> Your visitor pass</p>

          <div class="pass__code" id="visitorPassCode"
               data-qr="<?= e($passUrl) ?>"
               role="img"
               aria-label="QR code for reference number <?= e($done['ref_no']) ?>">
            <span class="pass__code-fallback">Loading code&hellip;</span>
          </div>

          <p class="pass__ref-label">Reference number</p>
          <p class="pass__ref"><?= e($done['ref_no']) ?></p>
          <p class="pass__scan">Show this at the counter, or scan it to see your place in the queue.</p>
        </div>

        <?php /* Said before the buttons, because the screenshot is the action
                 that actually matters and a visitor who reads no further has
                 still read this. */ ?>
        <div class="pass-notice" role="status">
          <span class="pass-notice__icon"><?= icon('camera', 22) ?></span>
          <span>
            <strong>Save or screenshot this code before you leave this screen.</strong>
            It is how the counter finds your registration and records your time out.
            <em>I-screenshot o i-save ang QR code na ito bago umalis sa screen. Ito ang gagamitin
              sa counter para hanapin ang inyong rehistro at itala ang oras ng paglabas.</em>
          </span>
        </div>

        <ul class="review-list" style="text-align:left">
          <li><span class="review-list__key">Name</span><span class="review-list__val"><?= e($done['name']) ?></span></li>
          <li><span class="review-list__key">Purpose</span><span class="review-list__val"><?= e($done['purpose']) ?></span></li>
          <li><span class="review-list__key">Time in</span><span class="review-list__val"><?= e(date('M j, Y g:i A', strtotime($done['time_in']))) ?></span></li>
        </ul>

        <div class="btn-row btn-row--center">
          <button class="btn btn--primary btn--shine" type="button" data-save-pass
                  data-ref="<?= e($done['ref_no']) ?>"
                  data-queue="<?= (int) $done['queue_no'] ?>"
                  data-payload="<?= e($passUrl) ?>">
            <?= icon('download') ?> Save QR code
          </button>
          <button class="btn btn--ghost" type="button" data-print><?= icon('printer') ?> Print</button>
        </div>
        <p class="small muted pass-hint" data-save-hint>
          Saves a picture of the code with your reference number to this device.
          On a kiosk, take a photo of the screen instead.
        </p>

        <div class="btn-row btn-row--center mt-24">
          <a class="btn btn--ghost btn--sm" href="<?= url('TrackSubmission', ['ref' => $done['ref_no']]) ?>">
            <?= icon('search', 16) ?> Check my place in the queue
          </a>
          <a class="btn btn--ghost btn--sm" href="<?= url('Register') ?>">
            <?= icon('user-plus', 16) ?> Register another visitor
          </a>
        </div>

        <?php /* Lets the queue strip on every public page recognise this visit
                 and say "you are next" rather than only "now serving 12". Kept
                 on the device, never sent anywhere. */ ?>
        <script>
          try {
            localStorage.setItem('vms-my-visit', JSON.stringify({
              ref: <?= json_encode($done['ref_no']) ?>,
              queue: <?= (int) $done['queue_no'] ?>,
              date: <?= json_encode(date('Y-m-d')) ?>
            }));
          } catch (e) {}
        </script>
      </div>
    </div>

  <?php else: ?>

  <div class="card" style="max-width:560px;margin-inline:auto">
    <div class="card__header">
      <img src="<?= asset('assets/seal.png') ?>" alt="">
      <div>
        <h1>Visitor Registration</h1>
        <p><?= e(APP_NAME) ?></p>
      </div>
    </div>
    <div class="card__body">
      <?php if (!empty($errors)): ?>
        <div class="flash flash--error" role="alert">
          <?= icon('alert', 20) ?>
          <span><?= e($errors['form'] ?? 'Please check the highlighted fields below.') ?></span>
        </div>
      <?php endif; ?>

      <form method="post" action="<?= url('Register') ?>" data-stepper data-guard novalidate>
        <?= csrf_field() ?>

        <ol class="stepper">
          <?php foreach ($steps as $index => $step): ?>
            <li class="stepper__item<?= $index === 0 ? ' is-current' : '' ?>" tabindex="0">
              <span class="stepper__dot"><?= $index + 1 ?></span>
              <span class="stepper__label"><?= e($step['label']) ?></span>
            </li>
          <?php endforeach; ?>
        </ol>

        <div class="progress" role="progressbar" aria-label="Registration progress"
             aria-valuemin="1" aria-valuemax="<?= count($steps) ?>" aria-valuenow="1">
          <span class="progress__bar" style="transform:scaleX(<?= round(1 / count($steps), 4) ?>)"></span>
        </div>
        <p class="progress-meta">
          <span>Step <strong data-step-now>1</strong> of <?= count($steps) ?></span>
          <span data-step-label><?= e($steps[0]['label']) ?></span>
        </p>

        <!-- Step 1: consent ------------------------------------------------ -->
        <section class="step-panel is-active" data-step-panel="1">
          <div class="step-head">
            <h2><?= e($steps[0]['title']) ?></h2>
            <p><?= e($steps[0]['sub']) ?></p>
          </div>

          <div class="field">
            <div class="consent">
              <input type="checkbox" id="consent" name="consent" value="1"
                     data-validate-checked="Please tick the box to give your consent."
                     <?= $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['consent']) ? 'checked' : '' ?>>
              <label for="consent">
                <strong>Data Privacy Consent / Pahintulot sa Pagkapribado ng Data</strong><br>
                I consent to the collection and processing of my personal information for visitor management purposes.
                <em>Pumapayag ako sa pagkolekta at pagsasaayos ng aking personal na impormasyon para sa layunin ng pamamahala ng bisita.</em>
              </label>
            </div>
            <?php if (isset($errors['consent'])): ?><span class="field-error"><?= icon('alert', 14) ?><?= e($errors['consent']) ?></span><?php endif; ?>
            <a class="field-hint" href="<?= url('Privacy') ?>">Read the full data privacy notice</a>
          </div>

          <div class="note">
            <?= icon('shield', 20) ?>
            <span>Your details are kept for <?= (int) RETENTION_DAYS ?> days and used only to manage visits to the
              Municipal Hall. They are never sold or published.</span>
          </div>

          <div class="step-nav">
            <button class="btn btn--primary btn--block" type="button" data-step-next>
              Continue <?= icon('arrow-right', 18) ?>
            </button>
          </div>
        </section>

        <!-- Step 2: about you --------------------------------------------- -->
        <section class="step-panel" data-step-panel="2">
          <div class="step-head">
            <h2><?= e($steps[1]['title']) ?></h2>
            <p><?= e($steps[1]['sub']) ?></p>
          </div>

          <div class="field">
            <label class="label" for="name">
              <?= icon('user', 17) ?>Name<span class="req">*</span>
              <span class="label__ta">Pangalan</span>
            </label>
            <input class="input <?= isset($errors['name']) ? 'is-invalid' : '' ?>" type="text" id="name" name="name"
                   value="<?= e($old['name']) ?>" placeholder="Enter your full name" autocomplete="name"
                   data-validate="name">
            <?php if (isset($errors['name'])): ?><span class="field-error"><?= icon('alert', 14) ?><?= e($errors['name']) ?></span><?php endif; ?>
          </div>

          <div class="grid-2">
            <div class="field" data-validate-group="sex" data-validate-message="Please select your sex.">
              <label class="label">Sex<span class="req">*</span><span class="label__ta">Kasarian</span></label>
              <div class="radio-stack">
                <label>
                  <input type="radio" name="sex" value="Male" <?= $old['sex'] === 'Male' ? 'checked' : '' ?>>
                  <span>Male (Lalaki)</span>
                </label>
                <label>
                  <input type="radio" name="sex" value="Female" <?= $old['sex'] === 'Female' ? 'checked' : '' ?>>
                  <span>Female (Babae)</span>
                </label>
              </div>
              <?php if (isset($errors['sex'])): ?><span class="field-error"><?= icon('alert', 14) ?><?= e($errors['sex']) ?></span><?php endif; ?>
            </div>

            <div class="field">
              <label class="label" for="age">Age<span class="req">*</span><span class="label__ta">Edad</span></label>
              <input class="input <?= isset($errors['age']) ? 'is-invalid' : '' ?>" type="number" id="age" name="age"
                     value="<?= e($old['age']) ?>" min="1" max="120" placeholder="Age" inputmode="numeric"
                     data-validate="age">
              <?php if (isset($errors['age'])): ?><span class="field-error"><?= icon('alert', 14) ?><?= e($errors['age']) ?></span><?php endif; ?>
            </div>
          </div>

          <div class="field">
            <label class="label" for="contact">
              <?= icon('phone', 17) ?>Contact Number (11 digits)
              <button class="hint-dot" type="button" tabindex="0"
                      data-tip="Optional. If you give a number we can reach you about your transaction. It must start with 0 and be 11 digits, e.g. 09171234567.">?</button>
              <span class="label__ta">Numero ng Telepono (Optional)</span>
            </label>
            <input class="input <?= isset($errors['contact']) ? 'is-invalid' : '' ?>" type="tel" id="contact" name="contact"
                   value="<?= e($old['contact']) ?>" placeholder="09XXXXXXXXX" inputmode="numeric"
                   data-digit-counter="contactCount" data-max-digits="11" autocomplete="tel"
                   data-validate="contact">
            <span class="field-hint" id="contactCount">0/11 digits</span>
            <?php if (isset($errors['contact'])): ?><span class="field-error"><?= icon('alert', 14) ?><?= e($errors['contact']) ?></span><?php endif; ?>
          </div>

          <div class="field">
            <label class="label" for="address">
              <?= icon('pin', 17) ?>Address<span class="req">*</span><span class="label__ta">Tirahan</span>
            </label>
            <textarea class="textarea <?= isset($errors['address']) ? 'is-invalid' : '' ?>" id="address" name="address"
                      placeholder="Enter your complete address" data-validate="address"><?= e($old['address']) ?></textarea>
            <?php if (isset($errors['address'])): ?><span class="field-error"><?= icon('alert', 14) ?><?= e($errors['address']) ?></span><?php endif; ?>
          </div>

          <div class="step-nav">
            <button class="btn btn--ghost" type="button" data-step-back><?= icon('chevron', 18) ?> Back</button>
            <button class="btn btn--primary" type="button" data-step-next>Continue <?= icon('arrow-right', 18) ?></button>
          </div>
        </section>

        <!-- Step 3: purpose ------------------------------------------------ -->
        <section class="step-panel" data-step-panel="3">
          <div class="step-head">
            <h2><?= e($steps[2]['title']) ?></h2>
            <p><?= e($steps[2]['sub']) ?></p>
          </div>

          <div class="field" data-validate-group="purpose" data-validate-message="Please choose the purpose of your visit.">
            <label class="label">
              <?= icon('target', 17) ?>Purpose of Visit<span class="req">*</span>
              <span class="label__ta">Layunin ng Pagbisita</span>
            </label>
            <div class="purpose-box"><div class="purpose-list">
              <?php foreach (purposes() as $label => $tagalog): ?>
                <label class="purpose-card">
                  <input type="radio" name="purpose" value="<?= e($label) ?>" <?= $old['purpose'] === $label ? 'checked' : '' ?>>
                  <span class="purpose-card__icon"><?= icon($purposeIcons[$label] ?? 'grid', 19) ?></span>
                  <span class="purpose-card__text">
                    <strong><?= e($label) ?></strong>
                    <em><?= e($tagalog) ?></em>
                  </span>
                </label>
              <?php endforeach; ?>
            </div></div>
            <?php if (isset($errors['purpose'])): ?><span class="field-error"><?= icon('alert', 14) ?><?= e($errors['purpose']) ?></span><?php endif; ?>
          </div>

          <div id="purposeOtherWrap" class="field" <?= $old['purpose'] === 'Others' ? '' : 'hidden' ?>>
            <label class="label" for="purpose_other">Please specify<span class="req">*</span><span class="label__ta">Pakisaad</span></label>
            <input class="input <?= isset($errors['purpose_other']) ? 'is-invalid' : '' ?>" type="text"
                   id="purpose_other" name="purpose_other" value="<?= e($old['purpose_other']) ?>"
                   placeholder="Tell us the purpose of your visit">
            <?php if (isset($errors['purpose_other'])): ?><span class="field-error"><?= icon('alert', 14) ?><?= e($errors['purpose_other']) ?></span><?php endif; ?>
          </div>

          <div class="step-nav">
            <button class="btn btn--ghost" type="button" data-step-back><?= icon('chevron', 18) ?> Back</button>
            <button class="btn btn--primary" type="button" data-step-next>Continue <?= icon('arrow-right', 18) ?></button>
          </div>
        </section>

        <!-- Step 4: review and sign ---------------------------------------- -->
        <section class="step-panel" data-step-panel="4">
          <div class="step-head">
            <h2><?= e($steps[3]['title']) ?></h2>
            <p><?= e($steps[3]['sub']) ?></p>
          </div>

          <ul class="review-list">
            <li><span class="review-list__key">Name</span><span class="review-list__val" data-review-for="name"></span></li>
            <li><span class="review-list__key">Sex</span><span class="review-list__val" data-review-for="sex"></span></li>
            <li><span class="review-list__key">Age</span><span class="review-list__val" data-review-for="age"></span></li>
            <li><span class="review-list__key">Contact</span><span class="review-list__val" data-review-for="contact"></span></li>
            <li><span class="review-list__key">Address</span><span class="review-list__val" data-review-for="address"></span></li>
            <li><span class="review-list__key">Purpose</span><span class="review-list__val" data-review-for="purpose"></span></li>
          </ul>

          <div class="field mt-24">
            <p class="label" id="signatureLabel">
              Signature<span class="req">*</span>
              <button class="hint-dot" type="button" tabindex="0"
                      data-tip="Your signature confirms you appeared in person and that the details above are correct. It replaces the paper logbook.">?</button>
              <span class="label__ta">Lagda</span>
            </p>

            <?php /* The pad is drawn on directly - there is nothing to open first. */ ?>
            <div class="sign-pad <?= isset($errors['signature']) ? 'is-invalid' : '' ?>" id="signaturePadWrap">
              <div class="sign-pad__head">
                <div>
                  <p class="sign-pad__title">Sign here / Lumagda dito</p>
                  <p class="sign-pad__help" id="signatureHelp">Draw your signature using your finger, stylus, or mouse.</p>
                </div>
                <span class="sign-pad__state" id="signatureState" role="status">Not signed yet</span>
              </div>

              <?php /* The paper look lives on the surface, not on the canvas, so the
                        saved PNG carries only the ink. */ ?>
              <div class="sign-pad__surface">
                <canvas id="signaturePad" aria-labelledby="signatureLabel" aria-describedby="signatureHelp"></canvas>
                <span class="sign-pad__rule" aria-hidden="true"></span>
                <span class="sign-pad__mark" aria-hidden="true">&times;</span>
              </div>

              <div class="sign-pad__actions">
                <button class="btn btn--ghost btn--sm" type="button" id="signatureClear"><?= icon('refresh', 18) ?> Clear</button>
                <button class="btn btn--primary btn--sm" type="button" id="signatureSave"><?= icon('check', 18) ?> Save Signature</button>
              </div>
            </div>

            <input type="hidden" id="signature" name="signature" value="" data-validate-signature>
            <?php if (isset($errors['signature'])): ?><span class="field-error"><?= icon('alert', 14) ?><?= e($errors['signature']) ?></span><?php endif; ?>
          </div>

          <div class="step-nav">
            <button class="btn btn--ghost" type="button" data-step-back><?= icon('chevron', 18) ?> Back</button>
            <button class="btn btn--primary btn--shine" type="submit" data-step-submit>
              <?= icon('user-plus') ?> Complete Registration
            </button>
          </div>
        </section>
      </form>
    </div>
  </div>

  <?php endif; ?>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
