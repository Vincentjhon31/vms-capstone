/**
 * Saving the visitor pass off the receipt screen.
 *
 * The pass is only useful if it survives the visitor closing the tab, and the
 * three ways that happens are not equally available to everyone standing in
 * the lobby:
 *
 *   - the kiosk has no camera roll and no downloads folder anybody can reach,
 *     so its visitors photograph the screen. Nothing here helps them, which is
 *     why the screen says so in plain words and the code is large enough to
 *     photograph from arm's length.
 *   - an Android phone downloads the PNG.
 *   - an iPhone in Safari ignores the download attribute, so the image is
 *     opened in a tab where it can be long-pressed and saved to Photos.
 *
 * The saved image carries the reference number underneath the code, drawn into
 * the picture itself. A QR alone is worthless the moment a camera will not
 * focus on it, and at that point the visitor needs something they can read out.
 */
(function () {
  'use strict';

  var button = document.querySelector('[data-save-pass]');
  if (!button || !window.VMSQR) return;

  var hint = document.querySelector('[data-save-hint]');

  function say(message) {
    if (hint) hint.textContent = message;
  }

  /** iOS Safari: <a download> is ignored, so open the image instead. */
  function canDownload() {
    var probe = document.createElement('a');
    return 'download' in probe && !/iP(hone|ad|od)/.test(navigator.userAgent);
  }

  button.addEventListener('click', function () {
    var payload = button.dataset.payload;
    var ref = button.dataset.ref || '';
    var queue = button.dataset.queue || '';

    var canvas;
    try {
      canvas = window.VMSQR.toCanvas(payload, {
        scale: 10,
        caption: ref,
        subcaption: queue ? 'Queue ' + queue : ''
      });
    } catch (err) {
      say('The code could not be saved on this device. Please take a screenshot of this screen instead.');
      return;
    }

    canvas.toBlob(function (blob) {
      if (!blob) {
        say('The code could not be saved on this device. Please take a screenshot of this screen instead.');
        return;
      }
      var url = URL.createObjectURL(blob);

      if (canDownload()) {
        var link = document.createElement('a');
        link.href = url;
        link.download = ref + '.png';
        document.body.appendChild(link);
        link.click();
        link.remove();
        say('Saved as ' + ref + '.png. Keep it until you have finished at the counter.');
      } else {
        // The tab may be blocked by a pop-up setting; the screen is still there.
        var opened = window.open(url, '_blank');
        say(opened
          ? 'Press and hold the image, then choose "Add to Photos" or "Save Image".'
          : 'Please take a screenshot of this screen - your browser would not open the image.');
      }

      // Long enough for the download or the new tab to have taken the data.
      setTimeout(function () { URL.revokeObjectURL(url); }, 60000);
    }, 'image/png');
  });
})();
