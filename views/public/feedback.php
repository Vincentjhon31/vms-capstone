<?php
/** Feedback form. */

$errors = [];
$old = ['name' => '', 'email' => '', 'contact' => '', 'feedback_type' => '', 'message' => ''];
$done = null;

if (!empty($_SESSION['feedback_done'])) {
    $done = $_SESSION['feedback_done'];
    unset($_SESSION['feedback_done']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($old as $key => $_) {
        $old[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    if ($old['name'] === '')                                        $errors['name'] = 'Please enter your full name.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))          $errors['email'] = 'Please enter a valid email address.';
    if ($old['contact'] !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $old['contact'])) {
        $errors['contact'] = 'Please enter a valid contact number.';
    }
    if (!in_array($old['feedback_type'], feedback_types(), true))    $errors['feedback_type'] = 'Please select a feedback type.';
    if (mb_strlen($old['message']) < 5)                              $errors['message'] = 'Please tell us a little more.';
    if (!empty($_POST['website']))                                   $errors['form'] = 'Submission blocked.'; // honeypot
    if (!$errors && !rate_limit_ok('feedback')) {
        $errors['form'] = 'You have submitted several times in the past hour. Please try again later.';
    }

    if (!$errors) {
        $refNo = next_ref_no('FBK', 'feedback');
        db_run(
            'INSERT INTO feedback (ref_no, name, email, contact, feedback_type, message, status, date_received)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
            [$refNo, $old['name'], $old['email'], $old['contact'] ?: null, $old['feedback_type'], $old['message'], 'Received']
        );
        audit('feedback_submitted', 'feedback', $refNo, $old['feedback_type']);
        $_SESSION['feedback_done'] = ['ref_no' => $refNo, 'name' => $old['name']];
        redirect('FeedbackForm');
    }
}

require __DIR__ . '/../../includes/header.php';
?>
<div class="wrap">
  <div class="card" style="max-width:470px;margin-inline:auto">
    <div class="card__header">
      <img src="<?= asset('assets/seal.png') ?>" alt="">
      <div>
  <h1>Feedback Form</h1>
        <p>Share your suggestions and comments</p>
      </div>
    </div>
    <div class="card__body">

      <?php if ($done): ?>
        <div class="center">
          <div class="success-mark"><?= icon('check', 38) ?></div>
          <h2>Thank you, <?= e($done['name']) ?>!</h2>
          <p class="muted">Your feedback has been received by the Municipality of Bongabong.</p>
          <div class="ref-box">
            <small>Your reference number</small>
            <strong><?= e($done['ref_no']) ?></strong>
          </div>
          <p class="small muted">Keep this number. You can check the status of your feedback any time on the
            <a href="<?= url('TrackSubmission') ?>">Track a Submission</a> page.</p>
          <div class="btn-row" style="justify-content:center">
            <button class="btn btn--ghost" type="button" data-print><?= icon('printer') ?> Print</button>
            <a class="btn btn--primary" href="<?= url('') ?>">Back to home</a>
          </div>
        </div>
      <?php else: ?>

        <?php if (!empty($errors)): ?>
          <div class="flash flash--error" role="alert">
            <?= icon('alert', 20) ?><span><?= e($errors['form'] ?? 'Please check the highlighted fields below.') ?></span>
          </div>
        <?php endif; ?>

        <form method="post" action="<?= url('FeedbackForm') ?>" data-guard novalidate>
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
            <label class="label" for="feedback_type"><?= icon('list', 17) ?>Feedback Type<span class="req">*</span></label>
            <select class="select <?= isset($errors['feedback_type']) ? 'is-invalid' : '' ?>" id="feedback_type" name="feedback_type" required>
              <option value="">Select feedback type</option>
              <?php foreach (feedback_types() as $type): ?>
                <option value="<?= e($type) ?>" <?= $old['feedback_type'] === $type ? 'selected' : '' ?>><?= e($type) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['feedback_type'])): ?><span class="field-error"><?= e($errors['feedback_type']) ?></span><?php endif; ?>
          </div>

          <div class="field">
            <label class="label" for="message"><?= icon('message', 17) ?>Message<span class="req">*</span></label>
            <textarea class="textarea <?= isset($errors['message']) ? 'is-invalid' : '' ?>" id="message" name="message"
                      placeholder="Share your feedback..." required data-validate="required"><?= e($old['message']) ?></textarea>
            <?php if (isset($errors['message'])): ?><span class="field-error"><?= e($errors['message']) ?></span><?php endif; ?>
          </div>

          <button class="btn btn--primary btn--block" type="submit"><?= icon('message') ?> Submit Feedback</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
