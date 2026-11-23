<?php
/** Other Concern form. */

$errors = [];
$old = ['name' => '', 'email' => '', 'contact' => '', 'concern_type' => '', 'subject' => '', 'description' => ''];
$done = null;

if (!empty($_SESSION['concern_done'])) {
    $done = $_SESSION['concern_done'];
    unset($_SESSION['concern_done']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($old as $key => $_) {
        $old[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    if ($old['name'] === '')                                     $errors['name'] = 'Please enter your full name.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))       $errors['email'] = 'Please enter a valid email address.';
    if ($old['contact'] !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $old['contact'])) {
        $errors['contact'] = 'Please enter a valid contact number.';
    }
    if (!in_array($old['concern_type'], concern_types(), true))   $errors['concern_type'] = 'Please select a concern type.';
    if ($old['subject'] === '')                                   $errors['subject'] = 'Please give your concern a short subject.';
    if (mb_strlen($old['description']) < 10)                      $errors['description'] = 'Please describe the concern in a little more detail.';
    if (!empty($_POST['website']))                                $errors['form'] = 'Submission blocked.'; // honeypot
    if (!$errors && !rate_limit_ok('concern')) {
        $errors['form'] = 'You have submitted several times in the past hour. Please try again later.';
    }

    if (!$errors) {
        $refNo = next_ref_no('CON', 'concerns');
        db_run(
            'INSERT INTO concerns (ref_no, name, email, contact, concern_type, subject, description, status, date_received)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [$refNo, $old['name'], $old['email'], $old['contact'] ?: null,
             $old['concern_type'], $old['subject'], $old['description'], 'Received']
        );
        audit('concern_submitted', 'concern', $refNo, $old['concern_type']);
        $_SESSION['concern_done'] = ['ref_no' => $refNo, 'name' => $old['name']];
        redirect('ConcernForm');
    }
}

require __DIR__ . '/../../includes/header.php';
?>
<div class="wrap">
  <div class="card" style="max-width:470px;margin-inline:auto">
    <div class="card__header card__header--orange">
      <img src="<?= asset('assets/seal.png') ?>" alt="">
      <div>
  <h1>Other Concern Form</h1>
        <p>Report issues or concerns that need attention</p>
      </div>
    </div>
    <div class="card__body">

      <?php if ($done): ?>
        <div class="center">
          <div class="success-mark"><?= icon('check', 38) ?></div>
          <h2>Your concern has been received</h2>
          <p class="muted">Thank you, <?= e($done['name']) ?>. Your concern has been logged and endorsed to the responsible office.</p>
          <div class="ref-box">
            <small>Your reference number</small>
            <strong><?= e($done['ref_no']) ?></strong>
          </div>
          <p class="small muted">
            <strong>Write this number down.</strong> Enter it on the
            <a href="<?= url('TrackSubmission') ?>">Track a Submission</a> page any time to see the status of your
            concern and the official response. Concerns are normally answered within
            <?= (int) CONCERN_SLA_DAYS ?> working days.
          </p>
          <div class="btn-row" style="justify-content:center">
            <button class="btn btn--ghost" type="button" data-print><?= icon('printer') ?> Print</button>
            <a class="btn btn--orange" href="<?= url('TrackSubmission', ['ref' => $done['ref_no']]) ?>">Track this concern</a>
          </div>
        </div>
      <?php else: ?>

        <?php if (!empty($errors)): ?>
          <div class="flash flash--error" role="alert">
            <?= icon('alert', 20) ?><span><?= e($errors['form'] ?? 'Please check the highlighted fields below.') ?></span>
          </div>
        <?php endif; ?>

        <form method="post" action="<?= url('ConcernForm') ?>" data-guard novalidate>
          <?= csrf_field() ?>
          <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">

          <div class="field">
            <label class="label" for="name"><?= icon('user', 17) ?>Name<span class="req">*</span></label>
            <input class="input <?= isset($errors['name']) ? 'is-invalid' : '' ?>" type="text" id="name" name="name"
                   value="<?= e($old['name']) ?>" placeholder="Enter your full name" required autocomplete="name" data-validate="name">
            <?php if (isset($errors['name'])): ?><span class="field-error"><?= e($errors['name']) ?></span><?php endif; ?>
          </div>

          <div class="field">
            <label class="label" for="email"><?= icon('mail', 17) ?>Email<span class="req">*</span></label>
            <input class="input <?= isset($errors['email']) ? 'is-invalid' : '' ?>" type="email" id="email" name="email"
                   value="<?= e($old['email']) ?>" placeholder="Enter your email" required autocomplete="email" data-validate="email">
            <?php if (isset($errors['email'])): ?><span class="field-error"><?= e($errors['email']) ?></span><?php endif; ?>
          </div>

          <div class="field">
            <label class="label" for="contact"><?= icon('phone', 17) ?>Contact Number</label>
            <input class="input <?= isset($errors['contact']) ? 'is-invalid' : '' ?>" type="tel" id="contact" name="contact"
                   value="<?= e($old['contact']) ?>" placeholder="Enter your contact number" autocomplete="tel">
            <?php if (isset($errors['contact'])): ?><span class="field-error"><?= e($errors['contact']) ?></span><?php endif; ?>
          </div>

          <div class="field">
            <label class="label" for="concern_type"><?= icon('alert', 17) ?>Concern Type<span class="req">*</span></label>
            <select class="select <?= isset($errors['concern_type']) ? 'is-invalid' : '' ?>" id="concern_type" name="concern_type" required>
              <option value="">Select concern type</option>
              <?php foreach (concern_types() as $type): ?>
                <option value="<?= e($type) ?>" <?= $old['concern_type'] === $type ? 'selected' : '' ?>><?= e($type) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['concern_type'])): ?><span class="field-error"><?= e($errors['concern_type']) ?></span><?php endif; ?>
          </div>

          <div class="field">
            <label class="label" for="subject"><?= icon('edit', 17) ?>Subject<span class="req">*</span></label>
            <input class="input <?= isset($errors['subject']) ? 'is-invalid' : '' ?>" type="text" id="subject" name="subject"
                   value="<?= e($old['subject']) ?>" placeholder="Brief subject of your concern" required data-validate="required">
            <?php if (isset($errors['subject'])): ?><span class="field-error"><?= e($errors['subject']) ?></span><?php endif; ?>
          </div>

          <div class="field">
            <label class="label" for="description"><?= icon('list', 17) ?>Description<span class="req">*</span></label>
            <textarea class="textarea <?= isset($errors['description']) ? 'is-invalid' : '' ?>" id="description" name="description"
                      placeholder="Provide detailed description of your concern..." required rows="5" data-validate="required"><?= e($old['description']) ?></textarea>
            <?php if (isset($errors['description'])): ?><span class="field-error"><?= e($errors['description']) ?></span><?php endif; ?>
          </div>

          <button class="btn btn--orange btn--block" type="submit"><?= icon('alert') ?> Submit Concern</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
