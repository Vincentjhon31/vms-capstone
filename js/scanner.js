/**
 * Camera scanning on the check-in desk.
 *
 * Two decoders, chosen in this order:
 *
 *   1. BarcodeDetector, the browser's own. Free, fast, and hardware-accelerated
 *      where it exists - which is Android, ChromeOS and macOS, and not Windows.
 *   2. jsQR, vendored at js/vendor/jsqr.min.js. 130KB, so it is fetched only
 *      when someone presses Start camera on a machine without the first, and
 *      never on the page load of a desk that only ever types numbers.
 *
 * The Windows front-desk PC is precisely the case (1) misses, so shipping only
 * the native path would have meant a scan button that never worked on the one
 * machine it was built for.
 *
 * Neither is required. Typing the reference number is the supported path and
 * always present; a USB barcode scanner - the kind that behaves as a keyboard -
 * types into that same field and needs none of this code. A scan does not act
 * on its own either: it fills the field and submits the form a person would
 * have submitted, so there is one route into the lookup and one place to debug.
 */
(function () {
  'use strict';

  /* After a lookup the visit is rendered at the top of the page, so nothing
     needs scrolling - but the caret does need handing back, or the desk has to
     reach for the mouse between visitors. A USB barcode scanner types into
     whatever has focus, so the field is emptied rather than selected: a scanner
     would otherwise append its next code to the one already sitting there.

     preventScroll, because focusing a field below the visit would push it back
     off the top of the screen - which is the whole thing this avoids. */
  var refField = document.querySelector('[data-scan-target]');
  if (refField && document.querySelector('.visit-card')) {
    refField.value = '';
    refField.focus({ preventScroll: true });
  }

  var scanner = document.querySelector('[data-scanner]');
  if (!scanner) return;

  var video  = document.getElementById('scannerVideo');
  var state  = document.getElementById('scannerState');
  var start  = scanner.querySelector('[data-scan-start]');
  var stop   = scanner.querySelector('[data-scan-stop]');
  var form   = document.querySelector('[data-scan-form]');
  var target = document.querySelector('[data-scan-target]');

  var stream = null;
  var timer = null;
  var decode = null;          // set once a decoder is ready
  var handled = false;        // one submit per camera session
  var frame = null;           // reused canvas for the jsQR path

  function say(message) {
    if (state) state.textContent = message;
  }

  var hasCamera = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
  var hasNative = 'BarcodeDetector' in window;

  if (!hasCamera) {
    scanner.classList.add('is-unavailable');
    if (start) start.hidden = true;
    if (stop) stop.hidden = true;
    say('No camera is available to this browser. Type the reference number instead - '
      + 'a USB barcode scanner types into that field too.');
    return;
  }

  /* ---- decoders -------------------------------------------------------- */

  function nativeDecoder() {
    var detector = new window.BarcodeDetector({ formats: ['qr_code'] });
    return function () {
      return detector.detect(video).then(function (codes) {
        return codes && codes.length ? codes[0].rawValue : null;
      });
    };
  }

  /** Read the frame through a canvas, which is the only input jsQR takes. */
  function jsqrDecoder() {
    frame = document.createElement('canvas');
    var ctx = frame.getContext('2d', { willReadFrequently: true });

    return function () {
      var width = video.videoWidth;
      var height = video.videoHeight;
      if (!width || !height) return Promise.resolve(null);

      // Decoding scales with pixel count, and a 1080p frame is far more than a
      // QR needs. Cap the long edge so a slow desk PC still keeps up.
      var scale = Math.min(1, 640 / Math.max(width, height));
      frame.width = Math.round(width * scale);
      frame.height = Math.round(height * scale);
      ctx.drawImage(video, 0, 0, frame.width, frame.height);

      var pixels = ctx.getImageData(0, 0, frame.width, frame.height);
      var found = window.jsQR(pixels.data, pixels.width, pixels.height, {
        inversionAttempts: 'dontInvert'
      });
      return Promise.resolve(found ? found.data : null);
    };
  }

  /** Fetch the vendored decoder. Resolves false if it will not load. */
  function loadJsQr() {
    if (window.jsQR) return Promise.resolve(true);

    return new Promise(function (resolve) {
      // Resolved against the manifest, so this works from a subfolder without
      // the script needing to know the install path.
      var manifest = document.querySelector('link[rel="manifest"]');
      var base = manifest ? manifest.href.replace(/assets\/manifest\.webmanifest.*$/, '') : '';

      var script = document.createElement('script');
      script.src = base + 'js/vendor/jsqr.min.js';
      script.onload = function () { resolve(!!window.jsQR); };
      script.onerror = function () { resolve(false); };
      document.head.appendChild(script);
    });
  }

  function prepareDecoder() {
    if (decode) return Promise.resolve(true);

    if (hasNative) {
      try {
        decode = nativeDecoder();
        return Promise.resolve(true);
      } catch (err) {
        // Present but refusing the format - fall through to jsQR.
      }
    }

    say('Loading the code reader...');
    return loadJsQr().then(function (ok) {
      if (ok) decode = jsqrDecoder();
      return ok;
    });
  }

  /* ---- camera ---------------------------------------------------------- */

  function release() {
    if (timer) { clearInterval(timer); timer = null; }
    if (stream) {
      stream.getTracks().forEach(function (track) { track.stop(); });
      stream = null;
    }
    if (video) video.srcObject = null;
    scanner.classList.remove('is-live');
    if (start) start.hidden = false;
    if (stop) stop.hidden = true;
  }

  /** A scan yields the pass URL; the server takes the number back out of it. */
  function accept(value) {
    if (handled || !value) return;
    handled = true;
    release();
    say('Found it - looking up the visit.');
    if (target) target.value = value;
    if (form) form.submit();
  }

  function read() {
    if (!decode || !video || video.readyState < 2) return;
    decode()
      .then(accept)
      .catch(function () { /* a dropped frame is not worth reporting */ });
  }

  function begin() {
    handled = false;
    say('Starting the camera...');

    prepareDecoder().then(function (ready) {
      if (!ready) {
        say('The code reader could not be loaded. Type the reference number instead.');
        return;
      }

      // facingMode is a request, not a guarantee: a desk PC with one webcam
      // simply gets that webcam, which is what is wanted anyway.
      return navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'environment', width: { ideal: 1280 } },
        audio: false
      })
        .then(function (granted) {
          stream = granted;
          video.srcObject = stream;
          return video.play();
        })
        .then(function () {
          scanner.classList.add('is-live');
          if (start) start.hidden = true;
          if (stop) stop.hidden = false;
          say('Hold the visitor’s code inside the frame.');
          timer = setInterval(read, hasNative ? 250 : 400);
        });
    })
      .catch(function (err) {
        release();
        if (err && (err.name === 'NotAllowedError' || err.name === 'SecurityError')) {
          say('Camera permission was refused. Allow it from the browser’s address bar, or type the number instead.');
        } else if (err && (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError')) {
          say('No camera on this machine. Type the reference number instead.');
        } else if (err && err.name === 'NotReadableError') {
          say('The camera is in use by another program. Close it, or type the number instead.');
        } else {
          say('The camera could not be started. Type the reference number instead.');
        }
      });
  }

  if (start) start.addEventListener('click', begin);
  if (stop) stop.addEventListener('click', function () {
    release();
    say('Camera is off.');
  });

  // Leaving the page or switching tabs must not leave the camera light on.
  window.addEventListener('pagehide', release);
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden || !stream) return;
    release();
    say('Camera stopped - the tab went into the background.');
  });
})();
