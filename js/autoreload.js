/**
 * Keep an unattended screen on the current build.
 *
 * The lobby kiosk is a touch screen with no keyboard, so nobody standing in
 * front of it can press Ctrl+Shift+R. Left alone it would keep rendering
 * whatever it loaded the morning it was switched on. This polls the build stamp
 * and reloads when the deployed code has actually changed.
 *
 * Two rules keep it from being rude:
 *
 *   - it never interrupts someone mid-form. A reload while a visitor is halfway
 *     through registering would throw their details away, so an update waits
 *     until the form is untouched or the screen has been idle long enough that
 *     nobody is standing there.
 *   - it only ever reloads when the stamp is genuinely different from the one
 *     the page was rendered with. A failed poll changes nothing.
 *
 * The stamp is printed into the page as window.VMS_BUILD by footer.php.
 */
(function () {
  'use strict';

  var current = window.VMS_BUILD;
  if (!current || !window.fetch) return;

  var POLL_MS = 60 * 1000;        // how often to ask
  var IDLE_MS = 4 * 60 * 1000;    // "nobody is standing here" after this long
  var ENDPOINT = 'api/version.php';

  var pending = false;            // an update is waiting for a safe moment
  var lastTouch = Date.now();

  ['pointerdown', 'keydown', 'input', 'scroll'].forEach(function (type) {
    window.addEventListener(type, function () { lastTouch = Date.now(); }, { passive: true });
  });

  /** True while a visitor has typed, ticked or drawn something not yet saved. */
  function inUse() {
    var dirty = false;

    document.querySelectorAll('form').forEach(function (form) {
      form.querySelectorAll('input, select, textarea').forEach(function (field) {
        if (dirty || field.disabled) return;

        if (field.type === 'checkbox' || field.type === 'radio') {
          if (field.checked !== field.defaultChecked) dirty = true;
          return;
        }
        if (field.type === 'hidden') {
          // The signature lives in a hidden input; a drawn one counts as in use.
          if (field.value) dirty = true;
          return;
        }
        if (field.value !== field.defaultValue) dirty = true;
      });
    });

    return dirty;
  }

  function reload() {
    // Drop the service worker's caches too, so the new build is fetched rather
    // than re-served from whatever the previous worker kept.
    var done = window.caches
      ? window.caches.keys()
          .then(function (keys) { return Promise.all(keys.map(function (k) { return caches.delete(k); })); })
          .catch(function () {})
      : Promise.resolve();

    done.then(function () { location.reload(); });
  }

  function apply() {
    if (!pending) return;
    if (inUse() && Date.now() - lastTouch < IDLE_MS) return;
    reload();
  }

  function poll() {
    // The URL is relative to the app root, which is where every page is served
    // from, so this works in a subfolder without knowing the base path.
    var base = document.querySelector('link[rel="manifest"]');
    var url = base ? base.href.replace(/assets\/manifest\.webmanifest.*$/, ENDPOINT) : ENDPOINT;

    fetch(url + '?t=' + Date.now(), { cache: 'no-store', credentials: 'same-origin' })
      .then(function (response) { return response.ok ? response.json() : null; })
      .then(function (data) {
        if (!data || !data.build) return;
        if (data.build !== current) pending = true;
        apply();
      })
      .catch(function () { /* offline, or the server is restarting - try later */ });
  }

  /* A worker swap is the other way this page can be out of date, and the one
     that used to need a hard refresh on every single page. When a new service
     worker takes over, whatever this page was assembled from came through the
     old one - so reload once, immediately, and let the new worker serve it.
     Guarded by a flag: controllerchange can fire more than once, and a reload
     loop on an unattended screen would be worse than a stale one. */
  var swapped = false;
  if (navigator.serviceWorker) {
    navigator.serviceWorker.addEventListener('controllerchange', function () {
      if (swapped) return;
      swapped = true;
      location.reload();
    });
    navigator.serviceWorker.addEventListener('message', function (event) {
      if (!event.data || event.data.type !== 'vms-worker-updated' || swapped) return;
      swapped = true;
      location.reload();
    });
  }

  setInterval(poll, POLL_MS);
  setInterval(apply, 15 * 1000);   // a waiting update lands as soon as it may

  // Coming back to a screen that was left alone is the cheapest moment to check.
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) poll();
  });
})();
