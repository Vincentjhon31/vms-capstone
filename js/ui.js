/**
 * Shared interface behaviour: scroll reveal, sticky header state, and the
 * spring-driven touches that use Motion.
 *
 * Motion (motion.dev - the vanilla build of what used to be called Framer
 * Motion) is vendored at js/vendor/motion.min.js and loaded only on the pages
 * that need a spring. Everything that a CSS transition can do is left to CSS:
 * transitions keep running while the main thread is busy, which matters far
 * more on a municipal PC than on a developer's laptop.
 */
(function () {
  'use strict';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---- header height --------------------------------------------------
     The full-height hero sizes itself with calc(100svh - var(--header-h)), and
     the header is not one fixed height: on the kiosk the nav wraps onto a
     second row, and on a phone it collapses to a toggle. A hard-coded token
     would leave a gap under the hero on one of them and a scrollbar on another,
     so measure the real thing and let CSS use it. */

  var siteHeader = document.querySelector('.site-header');
  if (siteHeader) {
    var syncHeaderHeight = function () {
      var height = Math.round(siteHeader.getBoundingClientRect().height);
      if (height > 0) {
        document.documentElement.style.setProperty('--header-h', height + 'px');
      }
    };

    syncHeaderHeight();

    if ('ResizeObserver' in window) {
      new ResizeObserver(syncHeaderHeight).observe(siteHeader);
    } else {
      window.addEventListener('resize', syncHeaderHeight);
    }
    // Web fonts land after first paint and can change the brand's height.
    if (document.fonts && document.fonts.ready) {
      document.fonts.ready.then(syncHeaderHeight).catch(function () {});
    }
  }

  /* ---- scroll reveal, AOS-style ---------------------------------------
     Fires once per element. Re-animating on every scroll-by is an interface
     fighting its reader. */

  var revealables = document.querySelectorAll('[data-reveal]');
  if (revealables.length) {
    if (reduceMotion || !('IntersectionObserver' in window)) {
      revealables.forEach(function (el) { el.classList.add('is-visible'); });
    } else {
      var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          var el = entry.target;
          // A group staggers its children rather than arriving all at once.
          var delay = parseInt(el.dataset.revealDelay || '0', 10);
          setTimeout(function () { el.classList.add('is-visible'); }, delay);
          observer.unobserve(el);
        });
      }, { threshold: 0.12, rootMargin: '0px 0px -60px 0px' });

      revealables.forEach(function (el) { observer.observe(el); });
    }
  }

  /* ---- admin sidebar drawer (narrow screens only) ---------------------
     Above 1024px the sidebar is always in view and none of this runs. */

  var sidebar = document.getElementById('adminSidebar');
  if (sidebar) {
    var openButton = document.querySelector('[data-open-sidebar]');
    var scrim = document.querySelector('[data-close-sidebar]');
    var lastFocus = null;

    function openSidebar() {
      lastFocus = document.activeElement;
      document.body.classList.add('sidebar-open');
      if (openButton) openButton.setAttribute('aria-expanded', 'true');
      if (scrim) scrim.hidden = false;
      var first = sidebar.querySelector('a, button');
      if (first) first.focus();
    }

    function closeSidebar() {
      document.body.classList.remove('sidebar-open');
      if (openButton) openButton.setAttribute('aria-expanded', 'false');
      if (scrim) scrim.hidden = true;
      if (lastFocus && lastFocus.focus) lastFocus.focus();
    }

    if (openButton) openButton.addEventListener('click', openSidebar);
    if (scrim) scrim.addEventListener('click', closeSidebar);

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && document.body.classList.contains('sidebar-open')) closeSidebar();
    });

    // Following a link should leave the drawer closed behind you.
    sidebar.addEventListener('click', function (event) {
      if (event.target.closest('a')) closeSidebar();
    });

    // Resizing past the breakpoint must not leave the body in the open state.
    var wide = window.matchMedia('(min-width: 1024px)');
    var onWide = function (e) { if (e.matches) closeSidebar(); };
    if (wide.addEventListener) wide.addEventListener('change', onWide);
  }

  /* ---- header gains a shadow once the page scrolls -------------------- */

  var header = document.querySelector('.site-header');
  if (header) {
    var ticking = false;
    var setStuck = function () {
      header.classList.toggle('is-stuck', window.scrollY > 4);
      ticking = false;
    };
    window.addEventListener('scroll', function () {
      if (!ticking) { ticking = true; requestAnimationFrame(setStuck); }
    }, { passive: true });
    setStuck();
  }

  /* ---- count up the dashboard KPI figures -----------------------------
     State indication: the number arriving tells you the panel is live. Once
     per page load, and only for figures already on screen. */

  var counters = document.querySelectorAll('[data-count-to]');
  if (counters.length && !reduceMotion) {
    counters.forEach(function (el) {
      var target = parseFloat(el.dataset.countTo);
      if (!isFinite(target) || target === 0) return;
      var decimals = parseInt(el.dataset.countDecimals || '0', 10);
      var started = false;

      function run() {
        if (started) return;
        started = true;
        var duration = 620;
        var start = performance.now();
        function step(now) {
          var t = Math.min((now - start) / duration, 1);
          // ease-out cubic - fast first, settles at the end
          var eased = 1 - Math.pow(1 - t, 3);
          el.textContent = (target * eased).toFixed(decimals);
          if (t < 1) requestAnimationFrame(step);
          else el.textContent = target.toFixed(decimals);
        }
        requestAnimationFrame(step);
      }

      if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
          if (entries[0].isIntersecting) { run(); io.disconnect(); }
        }, { threshold: 0.4 });
        io.observe(el);
      } else {
        run();
      }
    });
  }

  /* ---- Motion: the two places a spring is actually earned --------------
     1. the queue number on the registration receipt - rare, celebratory
     2. the success mark - same
     Both are first-time / rare tier, which is where the delight budget lives. */

  if (window.Motion && !reduceMotion) {
    var animate = window.Motion.animate;

    var queue = document.querySelector('[data-spring-in]');
    if (queue) {
      animate(
        queue,
        { transform: ['scale(0.82)', 'scale(1)'], opacity: [0, 1] },
        { type: 'spring', duration: 0.62, bounce: 0.28, delay: 0.12 }
      );
    }

    // Reference number arrives just after, so the eye follows one then the other.
    var ref = document.querySelector('[data-spring-in-2]');
    if (ref) {
      animate(
        ref,
        { transform: ['translateY(10px)', 'translateY(0px)'], opacity: [0, 1] },
        { type: 'spring', duration: 0.55, bounce: 0.16, delay: 0.28 }
      );
    }
  }
})();
