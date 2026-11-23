<?php
/**
 * Customer Satisfaction Survey.
 *
 * The live ARTA-harmonised Client Satisfaction Measurement instrument runs to
 * 29 pages with a separate question path per service availed. It is embedded
 * rather than rebuilt: retyping it risks changing the prescribed wording and
 * numbering, which would make the results non-reportable.
 */
require __DIR__ . '/../../includes/header.php';
?>
<div class="wrap">
  <div class="page-title">
    <img src="<?= asset('assets/seal.png') ?>" alt="" width="62" height="62">
    <div>
      <h1>Customer Satisfaction Survey</h1>
      <p>Help us improve our services</p>
    </div>
  </div>

  <div class="card">
    <div class="card__body">
      <div class="tinted" style="margin-bottom:18px">
        <p><strong>TULUNGAN MO KAMI MAS MAPABUTI ANG AMING MGA PROSESO AT SERBISYO!</strong></p>
        <p class="small mb-0">
          Ang Client Satisfaction Measurement (CSM) ay naglalayong masubaybayan ang karanasan ng taumbayan hinggil sa
          kanilang pakikitransaksyon sa mga tanggapan ng gobyerno. Makatutulong ang inyong kasagutan ukol sa inyong
          naging karanasan sa kakatapos lamang na transaksyon, upang mas mapabuti at lalong mapahusay ang aming
          serbisyo publiko. Ang personal na impormasyon na iyong ibabahagi ay mananatiling kumpidensyal. Maaari ring
          piliin na hindi sagutan ang sarbey na ito.
        </p>
      </div>

      <div class="note" style="margin-bottom:18px">
        <?= icon('help', 20) ?>
        <span>
          Having trouble with the form below, especially on a small phone?
          <a href="<?= e(GOOGLE_FORM_URL) ?>" target="_blank" rel="noopener">Open the survey in a new tab</a>
          - it is easier to scroll there.
        </span>
      </div>

      <div class="iframe-wrap">
        <iframe src="<?= e(GOOGLE_FORM_EMBED_URL) ?>"
                width="100%" height="1400" frameborder="0" marginheight="0" marginwidth="0"
                title="Client Satisfaction Measurement survey form"
                loading="lazy">Loading the survey...</iframe>
      </div>

      <p class="small muted mt-24">
        Responses go directly to the Municipality's Client Satisfaction Measurement records.
        Answering the survey is voluntary and your answers stay confidential.
      </p>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
