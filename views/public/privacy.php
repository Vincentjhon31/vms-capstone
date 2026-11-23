<?php
/** Data privacy notice - required alongside the consent checkbox (RA 10173). */
require __DIR__ . '/../../includes/header.php';
$charter = db_one('SELECT * FROM charter_content ORDER BY id LIMIT 1');
?>
<div class="wrap wrap--narrow">
  <div class="card">
    <div class="card__header">
      <img src="<?= asset('assets/seal.png') ?>" alt="">
      <div>
  <h1>Data Privacy Notice</h1>
        <p>Paunawa sa Pagkapribado ng Datos</p>
      </div>
    </div>
    <div class="card__body">
      <p>The Municipality of Bongabong processes personal information in accordance with Republic Act No. 10173,
        the <strong>Data Privacy Act of 2012</strong>, its implementing rules, and the issuances of the National
        Privacy Commission.</p>

      <h2 class="heading-blue">What we collect</h2>
      <ul>
        <li><strong>Visitor registration:</strong> your name, sex, age, address, purpose of visit, signature, and
          the time you entered and left the Municipal Hall. Your contact number is optional.</li>
        <li><strong>Feedback and concerns:</strong> your name, email address, optional contact number, and the
          content of what you submitted.</li>
        <li><strong>Client Satisfaction Measurement survey:</strong> answered on a Google Form. Answering is
          voluntary and responses are held in the Municipality's CSM records.</li>
      </ul>

      <h2 class="heading-blue">Why we collect it</h2>
      <ul>
        <li>To record and manage visits to the Municipal Hall, for security and for public health contact tracing.</li>
        <li>To route your concern to the responsible office and give you an official response.</li>
        <li>To produce statistics on visitor volume, demographics, and service quality for management and for
          oversight agencies. Statistics are reported in aggregate and never identify individuals.</li>
      </ul>

      <h2 class="heading-blue">How long we keep it</h2>
      <p>Visitor records and submissions are retained for <strong><?= (int) RETENTION_DAYS ?> days</strong>
        from the date of the visit or submission, after which they are disposed of securely, unless a longer
        period is required by law, by an ongoing investigation, or by an audit.</p>

      <h2 class="heading-blue">Who can see it</h2>
      <p>Only authorised personnel of the Municipal Government of Bongabong. Records are not sold, published, or
        shared with third parties except where required by law or lawful order. Every administrative action on a
        record is logged.</p>

      <h2 class="heading-blue">Your rights</h2>
      <p>Under the Data Privacy Act you have the right to be informed, to access, to correct, to object, to erasure
        or blocking, to damages, and to data portability. To exercise any of these, or to withdraw consent, contact
        the Municipal Hall:</p>

      <div class="tinted">
        <p class="mb-0">
          <strong><?= e(APP_LGU) ?></strong><br>
          <?= e($charter['address'] ?? 'Municipal Hall, Poblacion, Bongabong, Oriental Mindoro') ?><br>
          <?= e($charter['contact_number'] ?? '') ?> &middot; <?= e($charter['email'] ?? '') ?><br>
          <?= e($charter['office_hours'] ?? '') ?>
        </p>
      </div>

      <p class="small muted mt-24">Giving consent on the registration form is voluntary, but the Municipal Hall
        cannot record your visit without it. If you prefer not to consent, please ask the front desk for assistance.</p>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
