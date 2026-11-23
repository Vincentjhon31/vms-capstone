<?php
/**
 * Frequently Asked Questions - accordion grouped by category, admin-editable.
 *
 * Categories appear in the order the entries are numbered, not alphabetically,
 * so the published order matches the live site.
 */

$entries = db_all('SELECT * FROM faq_entries WHERE active = 1 ORDER BY sort_order, id');
$charter = db_one('SELECT * FROM charter_content ORDER BY id LIMIT 1') ?? [];

$grouped = [];
foreach ($entries as $entry) {
    $grouped[$entry['category']][] = $entry;
}

require __DIR__ . '/../../includes/header.php';
?>
<div class="wrap wrap--narrow">
  <div class="page-title">
    <img src="<?= asset('assets/seal.png') ?>" alt="" width="62" height="62">
    <div>
      <h1>Frequently Asked Questions</h1>
      <p>Find answers to common questions about municipal services and procedures</p>
    </div>
  </div>

  <div class="card">
    <div class="card__body">
      <div class="field">
        <label class="label" for="faqSearch"><?= icon('search', 18) ?>Search the FAQ
          <span class="label__ta">Maghanap ng katanungan</span></label>
        <input class="input" type="search" id="faqSearch" placeholder="Search questions or keywords..."
               data-filter-input data-filter-target="[data-faq]" data-filter-count="faqCount"
               data-filter-empty="faqEmpty" data-filter-group="[data-faq-group]">
        <span class="field-hint"><span id="faqCount"><?= count($entries) ?></span> of <?= count($entries) ?> question(s) shown</span>
      </div>

      <?php foreach ($grouped as $category => $items): ?>
        <section data-faq-group>
          <h2 class="faq-category"><?= e($category) ?></h2>
          <?php foreach ($items as $entry): ?>
            <details class="accordion-faq" data-faq
                     data-search="<?= e(strtolower($entry['question'] . ' ' . $entry['answer'] . ' ' . $entry['category'])) ?>">
              <summary><?= e($entry['question']) ?></summary>
              <p><?= nl2br(e($entry['answer'])) ?></p>
            </details>
          <?php endforeach; ?>
        </section>
      <?php endforeach; ?>

      <div class="empty" id="faqEmpty" hidden>
        <?= icon('search', 34) ?>
        <p>No question matches that keyword. Try a different word, or contact the Mayor's Office below.</p>
      </div>

      <?php if (!$entries): ?>
        <div class="empty">
          <?= icon('help', 34) ?>
          <p>No FAQ entries yet. An administrator can add them under Content &rarr; Manage FAQ.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card__body center">
      <h2>Still have questions?</h2>
      <p class="muted">Feel free to contact us directly or visit the Mayor's Office during office hours.</p>
      <ul class="contact-list">
        <li><?= icon('phone', 18) ?><a href="tel:<?= e(preg_replace('/\D/', '', $charter['contact_number'] ?? '')) ?>"><?= e($charter['contact_number'] ?? '') ?></a></li>
        <li><?= icon('mail', 18) ?><a href="mailto:<?= e($charter['email'] ?? '') ?>"><?= e($charter['email'] ?? '') ?></a></li>
        <li><?= icon('clock', 18) ?><span><?= e($charter['office_hours'] ?? '') ?></span></li>
      </ul>
      <div class="btn-row" style="justify-content:center">
        <a class="btn btn--primary" href="<?= url('FeedbackForm') ?>"><?= icon('message') ?> Send feedback</a>
        <a class="btn btn--orange" href="<?= url('ConcernForm') ?>"><?= icon('alert') ?> Raise a concern</a>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
