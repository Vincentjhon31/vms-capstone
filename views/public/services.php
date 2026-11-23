<?php
/**
 * Services Directory - every office and department of the Citizen's Charter
 * Handbook 2026, each with its External and Internal services. Details opens a
 * service's sheet over the list.
 */

require_once __DIR__ . '/../../includes/service_view.php';

$found   = charter_requested_service();
$offices = load_charter();
$search  = trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 80));
$pick    = trim((string) ($_GET['office'] ?? ''));
if (!in_array($pick, array_column($offices, 'code'), true)) {
    $pick = '';
}

// The page filters live in the browser too; this is for links and for no JavaScript.
$words = array_values(array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($search)), fn ($w) => mb_strlen($w) > 1));
$total = 0;
foreach ($offices as $office) {
    $total += $office['n_external'] + $office['n_internal'];
}

if ($found) {
    $pageTitle = $found['name'] . " | Citizen's Charter";
}
require __DIR__ . '/../../includes/header.php';
?>
<div class="wrap" data-cc-dir>
  <div class="page-head">
    <div>
      <h1>Services Directory</h1>
      <p>Complete list of frontline services of all offices and departments - Citizen's Charter Handbook 2026.</p>
    </div>
    <a class="btn btn--ghost" href="<?= url('MayorsOfficeCitizensCharter') ?>"><?= icon('book') ?> Mayor's Office Charter</a>
  </div>

  <div class="cc-stats">
    <div class="cc-stat"><b><?= count($offices) ?></b><span>Offices / Departments</span></div>
    <div class="cc-stat"><b><?= $total ?></b><span>Total Services</span></div>
    <div class="cc-stat"><b>2026</b><span>1st Edition</span></div>
  </div>

  <div class="card" id="services">
    <div class="card__body">
      <form class="cc-tools" role="search" method="get" action="<?= url('ServicesDirectory') ?>" data-cc-form>
        <?php if (!PRETTY_URLS): ?><input type="hidden" name="r" value="ServicesDirectory"><?php endif; ?>
        <label class="cc-q">
          <span class="sr-only">Search services or offices</span>
          <?= icon('search', 18) ?>
          <input class="input" type="search" name="q" value="<?= e($search) ?>" placeholder="Search services or offices..." autocomplete="off" data-cc-q>
        </label>
        <label class="cc-pick">
          <span class="sr-only">Office</span>
          <?= icon('filter', 18) ?>
          <select class="select" name="office" data-cc-office>
            <option value="">All Offices</option>
            <?php foreach ($offices as $office): ?>
              <option value="<?= e($office['code']) ?>" <?= $pick === $office['code'] ? 'selected' : '' ?>><?= e($office['short_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button class="btn btn--primary" type="submit"><?= icon('search', 18) ?> Search</button>
      </form>

      <?php ob_start();
      $shown = 0;
      foreach ($offices as $office) {
          $officeText = $office['name'] . ' ' . $office['short_name'];
          $mine = $pick === '' || $pick === $office['code'];
          $match = function (string $name) use ($mine, $officeText, $words) {
              if (!$mine) {
                  return false;
              }
              $hay = mb_strtolower($officeText . ' ' . $name);
              foreach ($words as $word) {
                  if (!str_contains($hay, $word)) {
                      return false;
                  }
              }
              return true;
          };
          $shown += render_charter_office($office, 'ServicesDirectory', $match, $search !== '' || $pick !== '') ? 1 : 0;
      }
      $list = ob_get_clean(); ?>

      <p class="cc-showing small muted" aria-live="polite">Showing <strong data-cc-shown><?= $shown ?></strong> of <?= count($offices) ?> offices - click an office to expand its services</p>

      <div class="cc-offices"><?= $list ?></div>

      <div class="empty" data-cc-none<?= $shown ? ' hidden' : '' ?>>
        <?= icon('search', 34) ?>
        <?php if ($offices): ?>
          <p>No results found. Try a different search term, such as "birth" or "permit".</p>
        <?php else: ?>
          <p>The Citizen's Charter has not been loaded yet. Run <code>install.php</code> to load it.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php render_charter_dialog($found, 'ServicesDirectory', $found['office_code'] ?? null); ?>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
