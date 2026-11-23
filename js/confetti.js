/**
 * Drifting confetti behind the landing hero.
 *
 * The live system has a static SVG scatter; this is the same idea with slow
 * motion behind it. Deliberately calm - it sits under the page's own text, so
 * anything fast would fight the reading.
 *
 * It stops itself when: the user prefers reduced motion, the tab is hidden, or
 * the canvas scrolls out of view. A municipal PC should not spend a core on
 * decoration nobody is looking at.
 */
(function () {
  'use strict';

  var canvas = document.getElementById('confettiCanvas');
  if (!canvas || !canvas.getContext) return;

  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
  if (reduce.matches) return;

  var ctx = canvas.getContext('2d');
  var pieces = [];
  var frame = null;
  var running = false;
  var width = 0;
  var height = 0;
  var ratio = Math.min(window.devicePixelRatio || 1, 2);

  // Brand plus the seal's own red, yellow and blue.
  var COLORS = ['#3b82f6', '#1d4ed8', '#22c55e', '#fbbf24', '#f472b6',
                '#a78bfa', '#fb923c', '#d81f26', '#ffdd00', '#1a1f71'];

  function resize() {
    var rect = canvas.getBoundingClientRect();
    width = rect.width;
    height = rect.height;
    canvas.width = Math.round(width * ratio);
    canvas.height = Math.round(height * ratio);
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
  }

  function makePiece(seeded) {
    var size = 5 + Math.random() * 6;
    return {
      x: Math.random() * width,
      // Seeded pieces start mid-flight so the canvas is never empty on load.
      y: seeded ? Math.random() * height : -20 - Math.random() * 60,
      w: size,
      h: size * (0.35 + Math.random() * 0.5),
      color: COLORS[(Math.random() * COLORS.length) | 0],
      // px per second, not per frame - so speed is frame-rate independent.
      vy: 16 + Math.random() * 26,
      vx: -7 + Math.random() * 14,
      spin: (-40 + Math.random() * 80) * (Math.PI / 180),
      angle: Math.random() * Math.PI * 2,
      sway: 0.4 + Math.random() * 0.8,
      phase: Math.random() * Math.PI * 2,
      alpha: 0.5 + Math.random() * 0.45
    };
  }

  function seed() {
    // Density scales with area, capped so a wide monitor does not get 300 pieces.
    var count = Math.min(64, Math.max(18, Math.round(width / 22)));
    pieces = [];
    for (var i = 0; i < count; i++) pieces.push(makePiece(true));
  }

  var last = 0;

  function tick(now) {
    if (!running) return;
    var dt = last ? Math.min((now - last) / 1000, 0.05) : 0.016;
    last = now;

    ctx.clearRect(0, 0, width, height);

    for (var i = 0; i < pieces.length; i++) {
      var p = pieces[i];
      p.phase += dt * p.sway;
      p.y += p.vy * dt;
      p.x += (p.vx + Math.sin(p.phase) * 9) * dt;
      p.angle += p.spin * dt;

      if (p.y > height + 30) {
        pieces[i] = makePiece(false);
        continue;
      }

      ctx.save();
      ctx.globalAlpha = p.alpha;
      ctx.translate(p.x, p.y);
      ctx.rotate(p.angle);
      ctx.fillStyle = p.color;
      ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
      ctx.restore();
    }

    frame = requestAnimationFrame(tick);
  }

  function start() {
    if (running) return;
    running = true;
    last = 0;
    frame = requestAnimationFrame(tick);
  }

  function stop() {
    running = false;
    if (frame) cancelAnimationFrame(frame);
    frame = null;
  }

  resize();
  seed();
  start();

  var resizeTimer = null;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () {
      resize();
      seed();
    }, 180);
  });

  document.addEventListener('visibilitychange', function () {
    if (document.hidden) stop(); else start();
  });

  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (entries) {
      if (entries[0].isIntersecting) start(); else stop();
    }, { threshold: 0 }).observe(canvas);
  }

  // Honour the setting if it changes while the page is open.
  if (reduce.addEventListener) {
    reduce.addEventListener('change', function (e) {
      if (e.matches) { stop(); ctx.clearRect(0, 0, width, height); } else start();
    });
  }
})();
