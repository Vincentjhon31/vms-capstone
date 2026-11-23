<?php
/**
 * Organizational Chart of the Office of the Municipal Mayor.
 *
 * The chart is the office's own 13 x 8.5 in artwork. The page shows a WebP copy
 * sized to the screen (140 KB, or 265 KB on wide and high-density screens);
 * the 4.5 MB original is only fetched by Download. On a phone the names are too
 * small to read at fit-to-width, so a tap zooms in and the chart pans inside
 * its frame.
 */

$chart = [
    'png'    => 'assets/org-chart/mo-organizational-chart.png',
    'small'  => 'assets/org-chart/mo-organizational-chart-1600.webp',
    'large'  => 'assets/org-chart/mo-organizational-chart-2800.webp',
    'width'  => 3999,
    'height' => 2615,
];

$bodyClass = 'org-chart-page';
require __DIR__ . '/../../includes/header.php';
?>
<div class="wrap">
  <div class="page-head">
    <div>
      <h1>Organizational Chart</h1>
      <p>Office of the Municipal Mayor - from the Municipal Mayor to the people in each unit.</p>
    </div>
    <div class="btn-row">
      <a class="btn btn--ghost" href="<?= asset($chart['png']) ?>" download="Office of the Municipal Mayor - Organizational Chart.png"><?= icon('download') ?> Download</a>
      <button class="btn btn--ghost" type="button" data-print><?= icon('printer') ?> Print</button>
    </div>
  </div>

  <figure class="card org-chart">
    <div class="org-chart__frame" data-org-chart tabindex="0" aria-label="Organizational chart. Press Enter to zoom in.">
      <img src="<?= asset($chart['small']) ?>"
           srcset="<?= asset($chart['small']) ?> 1600w, <?= asset($chart['large']) ?> 2800w"
           sizes="(min-width: 1180px) 1140px, 100vw"
           width="<?= $chart['width'] ?>" height="<?= $chart['height'] ?>"
           data-zoom-src="<?= asset($chart['large']) ?>"
           alt="Organizational chart of the Office of the Municipal Mayor of Bongabong. The Citizens of Bongabong are at the top. Below them are the Municipal Mayor, John Michael K. Malaluan, with the Local Special Bodies beside him; then the Municipal Administrator, Atty. Maynard Gabayno, Jr.; then the Executive Secretary, Christon Jervil M. Ligon, with External/Political Affairs beside him, and two Executive Assistants. Under them are five groups of units with their staff: Public Engagement, Special Concerns, Community Welfare and Empowerment, Administrative, and Mayor's Affairs.">
    </div>
    <figcaption class="org-chart__bar">
      <span class="small muted" data-org-chart-hint>Tap or click the chart to zoom in, then drag to move around.</span>
      <button class="btn btn--ghost btn--sm" type="button" data-org-chart-toggle aria-pressed="false"><?= icon('search', 16) ?> <span>Zoom in</span></button>
    </figcaption>
  </figure>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
