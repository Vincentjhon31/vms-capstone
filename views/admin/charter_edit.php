<?php
/** Edit the Citizen's Charter foreword, vision, mission and contact details. */

$charter = db_one('SELECT * FROM charter_content ORDER BY id LIMIT 1');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $fields = [
        'foreword'         => trim((string) ($_POST['foreword'] ?? '')),
        'signatory_title'  => trim((string) ($_POST['signatory_title'] ?? '')),
        'signatory_office' => trim((string) ($_POST['signatory_office'] ?? '')),
        'signatory_place'  => trim((string) ($_POST['signatory_place'] ?? '')),
        'signatory_year'   => trim((string) ($_POST['signatory_year'] ?? '')),
        'vision'           => trim((string) ($_POST['vision'] ?? '')),
        'mission'          => trim((string) ($_POST['mission'] ?? '')),
        'address'          => trim((string) ($_POST['address'] ?? '')),
        'contact_number'   => trim((string) ($_POST['contact_number'] ?? '')),
        'email'            => trim((string) ($_POST['email'] ?? '')),
        'office_hours'     => trim((string) ($_POST['office_hours'] ?? '')),
    ];

    if ($charter) {
        db_run(
            'UPDATE charter_content SET foreword = ?, signatory_title = ?, signatory_office = ?, signatory_place = ?,
                                        signatory_year = ?, vision = ?, mission = ?, address = ?, contact_number = ?,
                                        email = ?, office_hours = ? WHERE id = ?',
            [...array_values($fields), $charter['id']]
        );
    } else {
        db_run(
            'INSERT INTO charter_content (foreword, signatory_title, signatory_office, signatory_place, signatory_year,
                                          vision, mission, address, contact_number, email, office_hours)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array_values($fields)
        );
    }
    audit('charter_updated', 'charter', $charter['id'] ?? null);
    flash('Charter content updated. The public page shows the new text immediately.');
    redirect('AdminCharter');
}

require __DIR__ . '/../../includes/header.php';
?>
<div class="wrap wrap--narrow">
  <div class="page-head">
    <div>
      <h1>Charter Content</h1>
      <p>Foreword, vision, mission and contact details for
        <a href="<?= url('MayorsOfficeCitizensCharter') ?>">the published Charter</a>.</p>
    </div>

  </div>

  <div class="card">
    <div class="card__body">
      <form method="post" action="<?= url('AdminCharter') ?>">
        <?= csrf_field() ?>

        <div class="field">
          <label class="label" for="foreword">Foreword</label>
          <textarea class="textarea" id="foreword" name="foreword" rows="12"><?= e($charter['foreword'] ?? '') ?></textarea>
          <span class="field-hint">Required by RA 11032 to be kept current. Review it whenever the Charter edition changes.</span>
        </div>

        <div class="grid-2">
          <div class="field">
            <label class="label" for="signatory_title">Signature block - title</label>
            <input class="input" type="text" id="signatory_title" name="signatory_title" value="<?= e($charter['signatory_title'] ?? '') ?>">
          </div>
          <div class="field">
            <label class="label" for="signatory_office">Signature block - office</label>
            <input class="input" type="text" id="signatory_office" name="signatory_office" value="<?= e($charter['signatory_office'] ?? '') ?>">
          </div>
          <div class="field">
            <label class="label" for="signatory_place">Signature block - place</label>
            <input class="input" type="text" id="signatory_place" name="signatory_place" value="<?= e($charter['signatory_place'] ?? '') ?>">
          </div>
          <div class="field">
            <label class="label" for="signatory_year">Signature block - year</label>
            <input class="input" type="text" id="signatory_year" name="signatory_year" value="<?= e($charter['signatory_year'] ?? '') ?>">
          </div>
        </div>

        <div class="field">
          <label class="label" for="vision">Vision</label>
          <textarea class="textarea" id="vision" name="vision"><?= e($charter['vision'] ?? '') ?></textarea>
        </div>
        <div class="field">
          <label class="label" for="mission">Mission</label>
          <textarea class="textarea" id="mission" name="mission"><?= e($charter['mission'] ?? '') ?></textarea>
        </div>

        <div class="field">
          <label class="label" for="address">Address</label>
          <input class="input" type="text" id="address" name="address" value="<?= e($charter['address'] ?? '') ?>">
        </div>
        <div class="grid-3">
          <div class="field">
            <label class="label" for="contact_number">Contact number</label>
            <input class="input" type="text" id="contact_number" name="contact_number" value="<?= e($charter['contact_number'] ?? '') ?>">
          </div>
          <div class="field">
            <label class="label" for="email">Email address</label>
            <input class="input" type="email" id="email" name="email" value="<?= e($charter['email'] ?? '') ?>">
          </div>
          <div class="field">
            <label class="label" for="office_hours">Office hours</label>
            <input class="input" type="text" id="office_hours" name="office_hours" value="<?= e($charter['office_hours'] ?? '') ?>">
          </div>
        </div>

        <button class="btn btn--primary" type="submit"><?= icon('save') ?> Save charter content</button>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
