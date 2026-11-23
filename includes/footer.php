<?php
/**
 * Page shell - closing half. Mirrors the two layouts opened in header.php.
 */
$showQrPanel = $showQrPanel ?? false;
$useSidebar  = $useSidebar ?? false;
?>
</main>

<?php if ($useSidebar): ?>

  <footer class="admin-footer">
    <span>&copy; <?= e(APP_YEAR) ?> <?= e(APP_LGU) ?> &middot; <?= e(APP_NAME) ?></span>
    <span class="admin-footer__links">
      <a href="<?= url('') ?>" target="_blank" rel="noopener">Public site</a>
      <a href="<?= url('Privacy') ?>" target="_blank" rel="noopener">Data Privacy Notice</a>
    </span>
  </footer>

    </div><!-- /.admin-main -->
  </div><!-- /.admin-shell -->

<?php else: ?>

  <?php /* The queue strip belongs to the whole public site, not to one page, so
           it lives in the shell. Fixed to the bottom of the viewport - see
           includes/queue_bar.php for why it is not in the flow. */ ?>
  <?php if (!empty($showQueueStrip)): ?>
    <?php require __DIR__ . '/queue_bar.php'; ?>
  <?php endif; ?>

  <footer class="site-footer">
    <div class="wrap site-footer__inner">
      <img class="site-footer__seal" src="<?= asset('assets/seal.png') ?>" alt="Seal of the Municipality of Bongabong" width="68" height="68">
      <p class="site-footer__line">&copy; <?= e(APP_YEAR) ?> <?= e(APP_LGU) ?> - <?= e(APP_NAME) ?></p>
      <p class="site-footer__sub"><?= e(APP_PROVINCE) ?></p>
      <nav class="site-footer__links" aria-label="Footer">
        <a href="<?= url('ServicesDirectory') ?>">Services Directory</a>
        <a href="<?= url('OrganizationalChart') ?>">Organizational Chart</a>
        <a href="<?= url('TrackSubmission') ?>">Track a Submission</a>
        <a href="<?= url('Privacy') ?>">Data Privacy Notice</a>
        <a href="<?= url(is_admin() ? 'AdminDashboard' : 'Admin') ?>">Administrator</a>
      </nav>
    </div>
  </footer>

<?php endif; ?>

<?php /* Deferred scripts run in document order, so Motion must come before ui.js. */ ?>
<?php if (!empty($useMotion)): ?>
<script src="<?= asset_url('js/vendor/motion.min.js') ?>" defer></script>
<?php endif; ?>
<?php if (!empty($useConfetti)): ?>
<script src="<?= asset_url('js/vendor/confetti.browser.js') ?>" defer></script>
<script src="<?= asset_url('js/celebrate.js') ?>" defer></script>
<?php endif; ?>
<script src="<?= asset_url('js/app.js') ?>" defer></script>
<script src="<?= asset_url('js/ui.js') ?>" defer></script>
<script src="<?= asset_url('js/validate.js') ?>" defer></script>
<?php if ($showQrPanel): ?>
<script src="<?= asset_url('js/qr.js') ?>" defer></script>
<script>window.VMS_REGISTER_URL = <?= json_encode(site_url('Register'), JSON_UNESCAPED_SLASHES) ?>;</script>
<?php endif; ?>
<?php if (!empty($showQueueStrip)): ?>
<script src="<?= asset_url('js/queue.js') ?>" defer></script>
<?php endif; ?>
<?php foreach (($pageScripts ?? []) as $script): ?>
<script src="<?= asset_url($script) ?>" defer></script>
<?php endforeach; ?>

<?php /* Last resort against a wedged service worker.

   A worker from before vms-v8 matched cached assets with { ignoreSearch: true },
   so it answers 'style.css?v=<anything>' with the copy it froze on the day it
   installed. The page is then current HTML wearing last week's stylesheet, and
   nothing served from the network can talk it out of that - including any fix
   shipped in a .js file, which the same worker would serve from the same cache.

   So the page checks itself. .hero-full carries a solid background in every
   current stylesheet; if the computed value comes back transparent, the CSS in
   force is old, and the only way out is to tear out the worker and its caches
   and load again. Once per tab, so a genuine network failure cannot loop it. */ ?>
<script>
(function () {
  if (sessionStorage.getItem('vms-cache-flushed')) return;

  var probe = document.createElement('div');
  probe.className = 'hero-full';
  probe.style.cssText = 'position:absolute;left:-9999px;top:0;width:1px;height:1px';
  document.body.appendChild(probe);
  var background = getComputedStyle(probe).backgroundColor;
  probe.remove();

  if (background && background !== 'transparent' && background !== 'rgba(0, 0, 0, 0)') return;

  sessionStorage.setItem('vms-cache-flushed', '1');

  var workers = navigator.serviceWorker
    ? navigator.serviceWorker.getRegistrations()
        .then(function (registrations) {
          return Promise.all(registrations.map(function (r) { return r.unregister(); }));
        })
        .catch(function () {})
    : Promise.resolve();

  var caches_ = window.caches
    ? caches.keys()
        .then(function (keys) { return Promise.all(keys.map(function (k) { return caches.delete(k); })); })
        .catch(function () {})
    : Promise.resolve();

  Promise.all([workers, caches_]).then(function () { location.reload(); });
})();
</script>

<?php /* Unattended screens reload themselves when the deployed code changes. */ ?>
<?php if (empty($isAdminArea)): ?>
<script>window.VMS_BUILD = <?= json_encode(build_stamp()) ?>;</script>
<script src="<?= asset_url('js/autoreload.js') ?>" defer></script>
<?php endif; ?>
<?php /* Two details here are load-bearing, both learned the hard way.

   The script URL carries the ?v=<mtime> stamp. Registering the bare 'sw.js'
   meant the browser could satisfy it from its own HTTP cache - and the old
   .htaccess held .js for seven days - so unregistering a stale worker and
   reloading simply re-installed the same stale worker from cache. A screen
   could sit on vms-v4 indefinitely and every attempt to clear it looped.

   updateViaCache: 'none' says the worker script is never to be read from the
   HTTP cache, on this registration or any later update check. Scope is what
   identifies a registration, not the script URL, so a changed stamp updates the
   existing worker rather than adding a second one. */ ?>
<script>
if ('serviceWorker' in navigator && location.protocol.startsWith('http')) {
  window.addEventListener('load', function () {
    navigator.serviceWorker
      .register('<?= asset_url('sw.js') ?>', { updateViaCache: 'none' })
      .catch(function () {});
  });
}
</script>
</body>
</html>
