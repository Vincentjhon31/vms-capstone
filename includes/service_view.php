<?php
/**
 * Shared rendering for the Citizen's Charter. Used by the Services Directory
 * (every office) and the Mayor's Office Charter page (one office) so the two
 * can never drift apart.
 *
 * Laid out like the printed Handbook 2026: each office lists its External and
 * Internal services, and a service's sheet - requirements, steps, fees,
 * processing time, who is in charge - opens in a window over the list.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/icons.php';

/**
 * Offices with their active services grouped the way the Charter lists them:
 * program groups first (the Agriculture Office), then External, then Internal.
 * Pass an office code to load just that office.
 */
function load_charter(?string $only = null): array
{
    $offices = db_all(
        'SELECT * FROM charter_offices' . ($only !== null ? ' WHERE code = ?' : '') . ' ORDER BY sort_order, id',
        $only !== null ? [$only] : []
    );
    if (!$offices) {
        return [];
    }

    $rows = db_all(
        'SELECT s.id, s.service_no, s.name, s.office_code, s.service_type, s.group_name,
                (EXISTS (SELECT 1 FROM service_steps st WHERE st.service_id = s.id)
                 OR EXISTS (SELECT 1 FROM service_requirements r WHERE r.service_id = s.id)) AS has_sheet
         FROM services s
         WHERE s.active = 1 AND s.office_code IS NOT NULL
         ORDER BY s.sort_order, s.service_no, s.id'
    );
    $byOffice = [];
    foreach ($rows as $row) {
        $byOffice[$row['office_code']][] = $row;
    }

    foreach ($offices as &$office) {
        $groups = $external = $internal = [];
        foreach ($byOffice[$office['code']] ?? [] as $service) {
            if ($service['service_type'] === 'internal') {
                $internal[] = $service;
            } elseif ($service['group_name'] !== null && $service['group_name'] !== '') {
                $groups[$service['group_name']][] = $service;
            } else {
                $external[] = $service;
            }
        }
        $office['sections'] = [];
        foreach ($groups as $title => $items) {
            $office['sections'][] = ['title' => $title, 'type' => 'external', 'items' => $items];
        }
        if ($external) {
            $office['sections'][] = ['title' => 'External Services', 'type' => 'external', 'items' => $external];
        }
        if ($internal) {
            $office['sections'][] = ['title' => 'Internal Services', 'type' => 'internal', 'items' => $internal];
        }
        $office['n_external'] = count($external) + array_sum(array_map('count', $groups));
        $office['n_internal'] = count($internal);
    }
    unset($office);

    return $offices;
}

/** One active service with its requirements and process steps, or null. */
function load_service(int $id): ?array
{
    $service = db_one('SELECT * FROM services WHERE id = ? AND active = 1', [$id]);
    if (!$service) {
        return null;
    }
    $service['requirements'] = db_all('SELECT * FROM service_requirements WHERE service_id = ? ORDER BY sort_order', [$id]);
    $service['steps'] = db_all('SELECT * FROM service_steps WHERE service_id = ? ORDER BY sort_order', [$id]);
    return $service;
}

/** A text column that holds one entry per line (agency actions, persons responsible), as paragraphs. */
function charter_lines(string $text): string
{
    $out = '';
    foreach (preg_split('/\R/', trim($text)) as $line) {
        if (trim($line) !== '') {
            $out .= '<p>' . e(trim($line)) . '</p>';
        }
    }
    return $out;
}

/** A service's sheet, in the layout of the printed Charter. */
function charter_sheet(array $service): void
{
    $steps = $service['steps'];
    ?>
<article class="cc-doc">
  <h2 class="cc-title" id="cc-doc-title"><?= (int) $service['service_no'] ?>. <?= e(mb_strtoupper($service['name'])) ?></h2>
  <?php if (trim($service['description']) !== ''): ?><p class="cc-desc"><?= e($service['description']) ?></p><?php endif; ?>

  <table class="cc-table cc-info">
    <tbody>
      <tr><th scope="row">Office/Division:</th><td><?= e($service['office']) ?></td></tr>
      <tr><th scope="row">Classification:</th><td><?= e($service['classification']) ?></td></tr>
      <tr><th scope="row">Type of Transaction:</th><td><?= e($service['transaction_type']) ?></td></tr>
      <tr><th scope="row">Who may avail:</th><td><?= e($service['who_may_avail']) ?></td></tr>
    </tbody>
  </table>

  <?php if ($service['requirements']): ?>
    <table class="cc-table cc-reqs">
      <thead><tr><th scope="col">Checklist of Requirements</th><th scope="col">Where to Secure</th></tr></thead>
      <tbody>
        <?php foreach ($service['requirements'] as $requirement): ?>
          <tr><td><?= nl2br(e($requirement['requirement'])) ?></td><td><?= nl2br(e($requirement['where_to_secure'])) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if ($steps): ?>
    <table class="cc-table cc-steps">
      <thead><tr><th scope="col">Client Steps</th><th scope="col">Agency Action</th><th scope="col">Fees</th><th scope="col">Processing Time</th><th scope="col">Person Responsible</th></tr></thead>
      <tbody>
        <?php foreach ($steps as $n => $step): ?>
          <tr>
            <td><strong><?= $n + 1 ?>.</strong> <?= e($step['client_step']) ?></td>
            <td class="cc-lines"><?= charter_lines($step['agency_action']) ?></td>
            <td class="cc-mid"><?= e($step['fees']) ?></td>
            <td class="cc-mid"><?= e($step['processing_time']) ?></td>
            <td class="cc-lines cc-who"><?= charter_lines($step['person_responsible']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr><th scope="row" colspan="2">Total</th><td class="cc-mid"><?= e($service['total_fee']) ?></td><td class="cc-mid"><?= e($service['total_time']) ?></td><td></td></tr>
      </tfoot>
    </table>

    <div class="cc-cards">
      <p class="cc-cards-h">Steps</p>
      <?php foreach ($steps as $n => $step): ?>
        <section class="cc-card">
          <h3>Step <?= $n + 1 ?>: <?= e($step['client_step']) ?></h3>
          <div class="cc-lines"><span class="cc-k">Agency action</span><?= charter_lines($step['agency_action']) ?></div>
          <div class="cc-pair">
            <div><span class="cc-k">Fee</span><?= e($step['fees'] !== '' ? $step['fees'] : '-') ?></div>
            <div><span class="cc-k">Time</span><?= e($step['processing_time'] !== '' ? $step['processing_time'] : '-') ?></div>
          </div>
          <div class="cc-lines cc-who"><span class="cc-k">Person responsible</span><?= charter_lines($step['person_responsible']) ?></div>
        </section>
      <?php endforeach; ?>
      <p class="cc-card-total"><span>Total</span><span><?= e(implode(' | ', array_filter([$service['total_time'], $service['total_fee']]))) ?></span></p>
    </div>
  <?php endif; ?>

  <footer class="cc-foot">
    <img src="<?= asset('assets/seal.png') ?>" alt="" width="32" height="32">
    <span>Municipal Government of Bongabong</span>
  </footer>
</article>
    <?php
}

/**
 * The service a page was asked to show (?service=ID), if any. Two requests are
 * answered here and end the page: ?partial=1 is the sheet the page loads into
 * its window, ?print=1 is the sheet on its own, ready for paper.
 */
function charter_requested_service(): ?array
{
    $service = isset($_GET['service']) ? load_service((int) $_GET['service']) : null;
    if ($service && !$service['steps'] && !$service['requirements']) {
        $service = null;
    }
    if (!isset($_GET['partial']) && !isset($_GET['print'])) {
        return $service;
    }

    header('X-Robots-Tag: noindex');
    if (!$service) {
        http_response_code(404);
        exit("This service is not in the Citizen's Charter.");
    }
    if (isset($_GET['partial'])) {
        charter_sheet($service);
        exit;
    }
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($service['name']) ?> | Citizen's Charter 2026</title>
<link rel="stylesheet" href="<?= asset_url('css/style.css') ?>">
</head>
<body class="cc-print-page">
<?php charter_sheet($service); ?>
<script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>
<?php
    exit;
}

/** Address of a Charter page; empty query values are left out. */
function charter_link(string $route, array $query = []): string
{
    return url($route, array_filter($query, fn ($v) => $v !== null && $v !== ''));
}

/**
 * An office's service lists. $match decides which entries show (the page also
 * filters live in the browser); returns whether anything showed. $keepOffice
 * puts the office in the Details links, so a shared link opens on that office.
 */
function render_charter_sections(array $office, string $route, ?callable $match = null, bool $keepOffice = false): bool
{
    $any = false;
    $missing = false;
    foreach ($office['sections'] as $section):
        ob_start();
        $sectionHit = false; ?>
        <ol class="cc-list">
          <?php foreach ($section['items'] as $i => $service):
            $hit = $match === null || $match($service['name']);
            $sectionHit = $sectionHit || $hit;
            $missing = $missing || !$service['has_sheet']; ?>
            <li data-search="<?= e($service['name']) ?>"<?= $hit ? '' : ' hidden' ?>>
              <span class="cc-num"><?= $i + 1 ?></span>
              <span class="cc-name"><?= e($service['name']) ?></span>
              <?php if ($service['has_sheet']): ?>
                <a class="btn btn--ghost btn--sm" href="<?= e(charter_link($route, ['office' => $keepOffice ? $office['code'] : null, 'service' => $service['id']])) ?>" data-cc-open><?= icon('eye', 16) ?> Details</a>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
        <?php $list = ob_get_clean();
        $any = $any || $sectionHit; ?>
        <div class="cc-group"<?= $sectionHit ? '' : ' hidden' ?>>
          <h3><?= e($section['title']) ?><?php if ($section['type'] === 'internal'): ?> <span>For LGU offices and employees</span><?php endif; ?></h3>
          <?= $list ?>
        </div>
    <?php endforeach;

    if ($missing): ?>
      <p class="cc-note"><?= icon('alert', 16) ?> <span>Services without a <strong>Details</strong> button have no write-up yet. Ask the office for their requirements.</span></p>
    <?php endif;
    if ($office['address'] !== '' || $office['contact'] !== ''): ?>
      <p class="cc-contact"><?= icon('pin', 16) ?> <span><?= e(implode(' - ', array_filter([$office['address'], $office['contact']]))) ?></span></p>
    <?php endif;

    return $any;
}

/** One office as an accordion row of the Services Directory; returns whether it shows. */
function render_charter_office(array $office, string $route, callable $match, bool $open): bool
{
    ob_start();
    $hit = render_charter_sections($office, $route, $match, true);
    $body = ob_get_clean();
    ?>
    <details class="service cc-office" id="office-<?= e($office['code']) ?>" data-office="<?= e($office['code']) ?>"
             data-search="<?= e($office['name'] . ' ' . $office['short_name']) ?>"<?= $hit ? '' : ' hidden' ?><?= $hit && $open ? ' open' : '' ?>>
      <summary class="service__toggle">
        <span class="cc-ico"><?= icon('building', 22) ?></span>
        <span class="cc-head">
          <span class="cc-oname"><?= e($office['name']) ?></span>
          <span class="cc-pills">
            <?php if ($office['n_external']): ?><span class="cc-pill cc-ext"><?= (int) $office['n_external'] ?> External</span><?php endif; ?>
            <?php if ($office['n_internal']): ?><span class="cc-pill cc-int"><?= (int) $office['n_internal'] ?> Internal</span><?php endif; ?>
            <?php if ($office['page']): ?><span class="cc-pill">Page <?= (int) $office['page'] ?></span><?php endif; ?>
          </span>
        </span>
        <span class="service__chev"><?= icon('chevron', 20) ?></span>
      </summary>
      <div class="service__panel"><?= $body ?></div>
    </details>
    <?php
    return $hit;
}

/** The window a service's sheet opens in. Rendered open when the page was asked for one. */
function render_charter_dialog(?array $service, string $route, ?string $officeCode = null): void
{
    $keep = ['office' => $officeCode];
    ?>
    <dialog class="cc-dialog" id="cc-dialog" tabindex="-1" aria-labelledby="cc-doc-title"<?= $service ? ' open' : '' ?>>
      <div class="cc-bar">
        <a class="cc-btn" href="<?= $service ? e(charter_link($route, $keep + ['service' => $service['id'], 'print' => 1])) : '#' ?>" target="_blank" rel="noopener" data-cc-print aria-label="Print this service"><?= icon('printer', 17) ?></a>
        <a class="cc-btn" href="<?= e(charter_link($route, $keep)) ?>#services" data-cc-close aria-label="Close"><?= icon('x', 17) ?></a>
      </div>
      <div class="cc-body"><?php if ($service) charter_sheet($service); ?></div>
    </dialog>
    <?php
}
