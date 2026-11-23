/**
 * Keeps the public queue strip current.
 *
 * Polls api/queue.php and rewrites the three numbers in place. Two things it
 * deliberately does not do:
 *
 *   - it never reloads the page. The strip changing under a visitor who is
 *     halfway through the registration form must cost them nothing.
 *   - it stops polling while the tab is hidden. A phone left in a pocket in
 *     the lobby all afternoon should not be asking the municipal server for
 *     the queue every twenty seconds.
 *
 * The visitor's own reference number is read from localStorage, where the
 * receipt put it, and sent back so the strip can say how many are ahead of
 * them. It is their own number and nobody else's; the endpoint reports on no
 * other visit.
 */
(function () {
  'use strict';

  var strip = document.querySelector('[data-queue-strip]');
  if (!strip || !window.fetch) return;

  var POLL_MS = 20 * 1000;

  var servingEl = strip.querySelector('[data-queue-serving]');
  var nextEl    = strip.querySelector('[data-queue-next]');
  var waitEl    = strip.querySelector('[data-queue-waiting]');
  var wordEl    = strip.querySelector('[data-queue-waiting-word]');
  var youEl     = strip.querySelector('[data-queue-you]');

  /** This device's own visit, if it registered today. */
  function myVisit() {
    var raw;
    try {
      raw = localStorage.getItem('vms-my-visit');
    } catch (err) {
      return null;
    }
    if (!raw) return null;

    var saved;
    try {
      saved = JSON.parse(raw);
    } catch (err) {
      return null;
    }
    if (!saved || !saved.ref) return null;

    // Yesterday's pass is not this queue. Drop it rather than mislead.
    var today = new Date();
    var stamp = today.getFullYear() + '-' +
                String(today.getMonth() + 1).padStart(2, '0') + '-' +
                String(today.getDate()).padStart(2, '0');
    if (saved.date !== stamp) {
      try { localStorage.removeItem('vms-my-visit'); } catch (err) {}
      return null;
    }
    return saved;
  }

  /** api/queue.php, resolved against wherever the app is installed. */
  function endpoint() {
    var manifest = document.querySelector('link[rel="manifest"]');
    var base = manifest
      ? manifest.href.replace(/assets\/manifest\.webmanifest.*$/, '')
      : '';
    return base + 'api/queue.php';
  }

  /** Only touch the DOM when the value has actually changed. */
  function set(element, value) {
    if (!element) return;
    var text = String(value);
    if (element.textContent === text) return;
    element.textContent = text;
    element.classList.remove('is-changed');
    void element.offsetWidth;         // restart the flash
    element.classList.add('is-changed');
  }

  function renderYou(you) {
    if (!youEl) return;

    if (!you) {
      youEl.hidden = true;
      return;
    }

    if (you.status !== 'Waiting') {
      youEl.textContent = you.status === 'Completed'
        ? 'Your visit is finished - thank you.'
        : 'You are being served now (number ' + you.queue + ').';
      youEl.className = 'queue-strip__you is-called';
    } else if (you.ahead === 0) {
      youEl.textContent = 'You are next (number ' + you.queue + ').';
      youEl.className = 'queue-strip__you is-called';
    } else {
      youEl.textContent = 'You are number ' + you.queue + ' - ' + you.ahead +
                          (you.ahead === 1 ? ' person' : ' people') + ' ahead of you.';
      youEl.className = 'queue-strip__you';
    }
    youEl.hidden = false;
  }

  function poll(force) {
    // A tab that is not on screen has nobody reading it. The exception is the
    // very first poll: a page opened in the background - or restored by a
    // session restart - would otherwise show the figures it was rendered with
    // until somebody happened to switch to it.
    if (document.hidden && !force) return;

    var mine = myVisit();
    var url = endpoint() + '?t=' + Date.now() + (mine ? '&ref=' + encodeURIComponent(mine.ref) : '');

    fetch(url, { cache: 'no-store', credentials: 'same-origin' })
      .then(function (response) { return response.ok ? response.json() : null; })
      .then(function (data) {
        if (!data) return;
        set(servingEl, data.serving == null ? '--' : data.serving);
        set(nextEl, data.next == null ? '--' : data.next);
        set(waitEl, data.waiting);
        if (wordEl) wordEl.textContent = data.waiting === 1 ? 'person' : 'people';
        renderYou(data.you || null);
      })
      .catch(function () { /* offline or restarting - the strip keeps its last figures */ });
  }

  poll(true);
  setInterval(poll, POLL_MS);
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) poll();
  });
})();
