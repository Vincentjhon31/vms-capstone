/**
 * Service worker.
 *
 * Its only job is to keep the app installable and to say something useful with
 * no signal. It is deliberately not a performance cache: HTTP caching already
 * does that job correctly, because every stylesheet, script and the hero image
 * is requested with a ?v=<mtime> stamp (asset_url() in includes/helpers.php).
 *
 * That restraint is the lesson from the previous versions. They precached bare
 * paths - 'css/style.css', 'js/app.js' - and then matched requests with
 * { ignoreSearch: true }, so 'css/style.css?v=1788317532' was answered with
 * whatever copy had been frozen weeks earlier. The query string existed
 * precisely to prevent that and was being thrown away before the lookup. The
 * symptom was maddening and hard to attribute: a hard refresh bypasses the
 * worker and shows the new page, but never updates the worker's cache, so the
 * very next ordinary navigation went stale again, for ever.
 *
 * So: nothing versioned is precached, and nothing is matched loosely. Fonts are
 * the single exception - they are immutable and carry no stamp.
 */

const VERSION = 'vms-v8';

/* Only what an offline visitor genuinely needs. No CSS, no JS: those are
   versioned, and a precached copy under the bare path would shadow every later
   version of it. */
const SHELL = [
  'offline.html',
  'assets/fonts.css',
  'assets/fonts/inter-latin.woff2',
  'assets/fonts/manrope-latin.woff2',
  'assets/seal.png',
  'assets/icon-192.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(VERSION)
      .then((cache) => cache.addAll(SHELL.map((path) => new URL(path, self.registration.scope).toString())))
      .then(() => self.skipWaiting())
      .catch(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((key) => key !== VERSION).map((key) => caches.delete(key))))
      .then(() => self.clients.claim())
      .then(() => self.clients.matchAll({ type: 'window' }))
      .then((clients) => {
        // Whatever these pages are showing was assembled by the worker being
        // replaced, so it may be stale in ways they cannot detect themselves.
        // autoreload.js listens for this and reloads once.
        clients.forEach((client) => client.postMessage({ type: 'vms-worker-updated', version: VERSION }));
      })
  );
});

/** Cache a copy without making the caller wait for it. */
function remember(request, response) {
  const copy = response.clone();
  caches.open(VERSION).then((cache) => cache.put(request, copy)).catch(() => {});
  return response;
}

self.addEventListener('fetch', (event) => {
  const request = event.request;

  if (request.method !== 'GET' || new URL(request.url).origin !== self.location.origin) {
    return;
  }

  const path = new URL(request.url).pathname;

  // Fonts are the one safe cache-first case: they change only by getting a new
  // filename, so a hit can never be the wrong version.
  if (/\.woff2$/.test(path)) {
    event.respondWith(
      caches.match(request).then((cached) =>
        cached || fetch(request).then((response) => remember(request, response))
      )
    );
    return;
  }

  // Everything else - pages, styles, scripts, images - comes from the network,
  // and is matched EXACTLY when falling back, so a stamped URL can never be
  // answered by a differently stamped copy. Visitor records and concern
  // statuses must never be stale either, which is the same rule.
  event.respondWith(
    fetch(request)
      .then((response) => remember(request, response))
      .catch(() =>
        caches.match(request).then((cached) => {
          if (cached) return cached;
          if (request.mode === 'navigate' || request.destination === 'document') {
            return caches.match(new URL('offline.html', self.registration.scope).toString());
          }
          return new Response('Offline', { status: 503, headers: { 'Content-Type': 'text/plain' } });
        })
      )
  );
});
