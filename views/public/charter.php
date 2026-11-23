<?php
/** Mayor's Office Citizen's Charter - the full published handbook page. */

require_once __DIR__ . '/../../includes/service_view.php';

$found    = charter_requested_service();
$charter  = db_one('SELECT * FROM charter_content ORDER BY id LIMIT 1') ?? [];
$mayor    = load_charter('mayor')[0] ?? null;
// The Office of the Municipal Administrator shares the building and the front desk.
$oma      = load_charter('administrator')[0] ?? null;

if ($found) {
    $pageTitle = $found['name'] . " | Citizen's Charter";
}
require __DIR__ . '/../../includes/header.php';
?>
<div class="wrap">

  <div class="charter-hero">
    <img src="<?= asset('assets/seal-256.png') ?>" alt="Seal of the Municipality of Bongabong">
    <p class="charter-hero__office">Mayor's Office</p>
    <p class="charter-hero__title">Citizen's Charter</p>
    <p class="charter-hero__sub"><?= e(APP_LGU) ?> - Office of the Mayor Services and Guidelines</p>
    <div class="btn-row" style="justify-content:center">
      <a class="btn btn--primary" href="#services"><?= icon('book') ?> View Services Directory</a>
      <button class="btn btn--ghost" type="button" data-print><?= icon('printer') ?> Download PDF</button>
    </div>
  </div>

  <div class="card">
    <div class="section-bar">
      <h2>Foreword</h2>
      <p>A message from the Municipal Government of Bongabong</p>
    </div>
    <div class="card__body foreword">
      <img src="<?= asset('assets/seal.png') ?>" alt="">
      <p><?= nl2br(e($charter['foreword'] ?? '')) ?></p>
      <div class="signature-block">
        <strong><?= e($charter['signatory_title'] ?? 'THE MUNICIPAL MAYOR') ?></strong>
        <span><?= e($charter['signatory_office'] ?? '') ?></span>
        <span><?= e($charter['signatory_place'] ?? '') ?></span>
        <span><?= e($charter['signatory_year'] ?? '') ?></span>
      </div>
    </div>
  </div>

  <div class="card" id="services" data-cc-dir>
    <div class="section-bar">
      <h2>Available Services</h2>
      <?php if ($mayor): ?>
        <p><?= (int) $mayor['n_external'] ?> external and <?= (int) $mayor['n_internal'] ?> internal services of the <?= e($mayor['name']) ?><?php if ($oma): ?>, and <?= (int) $oma['n_external'] ?> external and <?= (int) $oma['n_internal'] ?> internal services of the <?= e($oma['name']) ?> in the same building<?php endif; ?>. Press <strong>Details</strong> for the requirements, steps, fees and processing time.</p>
      <?php endif; ?>
    </div>
    <div class="card__body">
      <?php if ($mayor): ?>
        <?php if ($oma): ?><h3 class="heading-blue" id="office-mayor"><?= e($mayor['name']) ?></h3><?php endif; ?>
        <?php render_charter_sections($mayor, 'MayorsOfficeCitizensCharter'); ?>
        <?php if ($oma): ?>
          <h3 class="heading-blue cc-office-head" id="office-administrator"><?= e($oma['name']) ?></h3>
          <?php render_charter_sections($oma, 'MayorsOfficeCitizensCharter'); ?>
        <?php endif; ?>
      <?php else: ?>
        <div class="empty">
          <?= icon('book', 34) ?>
          <p>No services have been published yet. Run <code>install.php</code> to load the Charter.</p>
        </div>
      <?php endif; ?>
      <div class="cc-more">
        <p class="mb-0"><strong>Looking for another office?</strong> The Services Directory lists the services of all offices and departments of the Municipality.</p>
        <a class="btn btn--primary" href="<?= url('ServicesDirectory') ?>"><?= icon('book') ?> Services Directory</a>
      </div>
    </div>
    <?php render_charter_dialog($found, 'MayorsOfficeCitizensCharter'); ?>
  </div>

  <div class="card">
    <div class="section-bar">
      <h2><span aria-hidden="true">&#128205;</span> Office Information</h2>
    </div>
    <div class="card__body">
      <h3 class="heading-blue">Vision</h3>
      <div class="tinted" style="margin-bottom:22px"><p class="mb-0"><?= e($charter['vision'] ?? '') ?></p></div>

      <h3 class="heading-blue">Mission</h3>
      <div class="tinted" style="margin-bottom:26px"><p class="mb-0"><?= e($charter['mission'] ?? '') ?></p></div>

      <h3 class="heading-blue">Organizational Chart</h3>
      <div class="cc-more" style="margin:0 0 26px">
        <p class="mb-0">Who is who in the Office of the Municipal Mayor, from the Mayor to each unit.</p>
        <a class="btn btn--ghost" href="<?= url('OrganizationalChart') ?>"><?= icon('users') ?> View the Organizational Chart</a>
      </div>

      <h3 class="heading-blue">Contact Information</h3>
      <div class="contact-grid">
        <div class="contact-card">
          <?= icon('pin', 20) ?>
          <div><strong>Address</strong><span><?= e($charter['address'] ?? '') ?></span></div>
        </div>
        <div class="contact-card">
          <?= icon('phone', 20) ?>
          <div><strong>Contact Number</strong><span><?= e($charter['contact_number'] ?? '') ?></span></div>
        </div>
        <div class="contact-card">
          <?= icon('mail', 20) ?>
          <div><strong>Email Address</strong><span><?= e($charter['email'] ?? '') ?></span></div>
        </div>
        <div class="contact-card">
          <?= icon('clock', 20) ?>
          <div><strong>Office Hours</strong><span><?= e($charter['office_hours'] ?? '') ?></span></div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
