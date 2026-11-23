<?php
/**
 * Floating "SCAN QR TO REGISTER" panel. The QR is generated in the browser
 * from the site's own address, so the printed code is correct both on
 * localhost and on the live domain.
 */
?>
<aside class="qr-panel" aria-label="Scan to register as a visitor">
  <p class="qr-panel__label"><span aria-hidden="true">&#8862;</span> SCAN QR TO REGISTER!</p>
  <div class="qr-panel__code" id="qrPanel" role="img" aria-label="QR code linking to the visitor registration form"></div>
  <a class="qr-panel__link" href="<?= url('Register') ?>">or tap to register</a>
  <button class="qr-panel__print" type="button" data-print-qr>Print poster</button>
</aside>
