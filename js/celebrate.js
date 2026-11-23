/**
 * Confetti bursts (canvas-confetti).
 *
 * Two bursts fire from the left and right edges every 250ms for 3 seconds,
 * tapering off as the show ends. Two ways to use it:
 *
 *   - one shot: add [data-confetti-once] to the page, or call
 *     window.VMSCelebrate.burst() - this is the registration receipt
 *   - looping: add [data-confetti-loop] - this is the landing hero
 *
 * The burst config is the caller's own, kept verbatim. The loop, the guards and
 * the teardown are the parts added around it.
 *
 * It stops itself when the tab is hidden, when its host element scrolls out of
 * view, or when the user prefers reduced motion. A looping animation nobody is
 * looking at is a municipal PC spending a core on nothing.
 */
(function () {
  'use strict';

  if (typeof window.confetti !== 'function') return;

  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');

  /* ---- the show ------------------------------------------------------- */

  var duration = 3 * 1000;                  // one show lasts 3 seconds
  var defaults = { startVelocity: 30, spread: 360, ticks: 60, zIndex: 0 };

  /** Pause between shows when looping. Cycle = duration + GAP. */
  var GAP = 2 * 1000;

  function randomInRange(min, max) {
    return Math.random() * (max - min) + min;
  }

  /**
   * Run one 3-second show. Returns a handle so a caller can stop it early -
   * without that, a show started just before you switch tabs keeps firing.
   */
  function burst() {
    if (reduce.matches) return { stop: function () {} };

    var animationEnd = Date.now() + duration;

    var interval = setInterval(function () {
      var timeLeft = animationEnd - Date.now();

      if (timeLeft <= 0) {
        return clearInterval(interval);
      }

      var particleCount = 50 * (timeLeft / duration);

      // Particles fall, so start a little above the top edge.
      confetti(Object.assign({}, defaults, {
        particleCount: particleCount,
        origin: { x: randomInRange(0.1, 0.3), y: Math.random() - 0.2 }
      }));
      confetti(Object.assign({}, defaults, {
        particleCount: particleCount,
        origin: { x: randomInRange(0.7, 0.9), y: Math.random() - 0.2 }
      }));
    }, 250);

    return {
      stop: function () { clearInterval(interval); }
    };
  }

  window.VMSCelebrate = { burst: burst };

  /* ---- one shot ------------------------------------------------------- */

  if (document.querySelector('[data-confetti-once]')) {
    burst();
  }

  /* ---- looping -------------------------------------------------------- */

  var host = document.querySelector('[data-confetti-loop]');
  if (!host) return;

  var timer = null;
  var current = null;
  var running = false;
  var onScreen = true;

  function cycle() {
    if (!running) return;
    current = burst();
    // Wait for the show to finish, then hold for GAP before going again.
    timer = setTimeout(cycle, duration + GAP);
  }

  function start() {
    if (running || reduce.matches || !onScreen || document.hidden) return;
    running = true;
    cycle();
  }

  function stop() {
    running = false;
    if (timer) { clearTimeout(timer); timer = null; }
    if (current) { current.stop(); current = null; }
    // Clear anything still on the canvas so a hidden tab is not left mid-show.
    if (typeof confetti.reset === 'function') confetti.reset();
  }

  document.addEventListener('visibilitychange', function () {
    if (document.hidden) stop(); else start();
  });

  if (reduce.addEventListener) {
    reduce.addEventListener('change', function (event) {
      if (event.matches) stop(); else start();
    });
  }

  // The observer's first callback fires as soon as it is wired up, so it - not
  // a speculative start() - decides whether the host is actually on screen.
  // Starting eagerly meant an off-screen host fired one wasted show first.
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (entries) {
      onScreen = entries[0].isIntersecting;
      if (onScreen) start(); else stop();
    }, { threshold: 0 }).observe(host);
  } else {
    start();
  }
})();
