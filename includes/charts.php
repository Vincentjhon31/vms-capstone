<?php
/**
 * Charts drawn as inline SVG on the server - no chart library, no CDN, and they
 * survive being printed or saved as PDF.
 */

require_once __DIR__ . '/helpers.php';

/** Category colours, ordered so neighbouring slices stay distinguishable. */
function chart_colors(): array
{
    return ['#1d4ed8', '#16a34a', '#d97706', '#7c3aed', '#0891b2', '#db2777', '#475569', '#65a30d', '#b45309'];
}

/**
 * Vertical bar chart. $data is [label => value]; $meta[label] can hold a longer
 * tooltip label.
 */
function svg_bar_chart(array $data, array $options = []): string
{
    $width   = $options['width'] ?? 640;
    $height  = $options['height'] ?? 240;
    $padding = ['t' => 16, 'r' => 12, 'b' => 34, 'l' => 40];
    $colour  = $options['color'] ?? '#1d4ed8';

    if (!$data) {
        return '<p class="empty">No data for this period.</p>';
    }

    $max = max(1, max($data));
    $niceMax = (int) (ceil($max / 5) * 5);
    $plotWidth  = $width - $padding['l'] - $padding['r'];
    $plotHeight = $height - $padding['t'] - $padding['b'];
    $count = count($data);
    $slot  = $plotWidth / $count;
    $barWidth = min(46, max(6, $slot * 0.6));

    $svg  = '<svg viewBox="0 0 ' . $width . ' ' . $height . '" class="chart" role="img" '
          . 'aria-label="' . e($options['label'] ?? 'Bar chart') . '" style="width:100%;height:auto">';

    // Horizontal grid and y-axis labels
    for ($i = 0; $i <= 4; $i++) {
        $y = $padding['t'] + $plotHeight - ($plotHeight * $i / 4);
        $value = round($niceMax * $i / 4);
        $svg .= '<line x1="' . $padding['l'] . '" y1="' . $y . '" x2="' . ($width - $padding['r']) . '" y2="' . $y
              . '" stroke="#e2e8f0" stroke-width="1"/>';
        $svg .= '<text x="' . ($padding['l'] - 8) . '" y="' . ($y + 4) . '" text-anchor="end" '
              . 'font-size="10" fill="#94a3b8">' . $value . '</text>';
    }

    $index = 0;
    foreach ($data as $label => $value) {
        $barHeight = $niceMax > 0 ? ($value / $niceMax) * $plotHeight : 0;
        $x = $padding['l'] + $slot * $index + ($slot - $barWidth) / 2;
        $y = $padding['t'] + $plotHeight - $barHeight;
        $svg .= '<rect x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($barWidth, 1)
              . '" height="' . round(max($barHeight, $value > 0 ? 2 : 0), 1) . '" rx="3" fill="' . $colour . '">'
              . '<title>' . e($label . ': ' . $value) . '</title></rect>';
        if ($value > 0) {
            $svg .= '<text x="' . round($x + $barWidth / 2, 1) . '" y="' . round($y - 5, 1)
                  . '" text-anchor="middle" font-size="10" font-weight="700" fill="#475569">' . $value . '</text>';
        }
        $svg .= '<text x="' . round($x + $barWidth / 2, 1) . '" y="' . ($height - 12)
              . '" text-anchor="middle" font-size="10" fill="#64748b">' . e($label) . '</text>';
        $index++;
    }

    return $svg . '</svg>';
}

/** Donut chart with a legend below. $data is [label => value]. */
function svg_donut(array $data, array $options = []): string
{
    $data = array_filter($data, fn ($value) => $value > 0);
    if (!$data) {
        return '<p class="empty">No data for this period.</p>';
    }

    $size   = $options['size'] ?? 190;
    $total  = array_sum($data);
    $radius = $size / 2 - 6;
    $inner  = $radius * 0.58;
    $centre = $size / 2;
    $colours = chart_colors();

    $svg = '<svg viewBox="0 0 ' . $size . ' ' . $size . '" style="width:' . $size . 'px;max-width:100%;height:auto" '
         . 'role="img" aria-label="' . e($options['label'] ?? 'Breakdown chart') . '">';

    $angle = -M_PI / 2;
    $index = 0;
    foreach ($data as $label => $value) {
        $slice = ($value / $total) * 2 * M_PI;
        $end   = $angle + $slice;
        $large = $slice > M_PI ? 1 : 0;

        $x1 = $centre + $radius * cos($angle);
        $y1 = $centre + $radius * sin($angle);
        $x2 = $centre + $radius * cos($end);
        $y2 = $centre + $radius * sin($end);
        $x3 = $centre + $inner * cos($end);
        $y3 = $centre + $inner * sin($end);
        $x4 = $centre + $inner * cos($angle);
        $y4 = $centre + $inner * sin($angle);

        // A single category would draw a zero-length arc, so draw a ring instead.
        if (count($data) === 1) {
            $svg .= '<circle cx="' . $centre . '" cy="' . $centre . '" r="' . ($radius + $inner) / 2
                  . '" fill="none" stroke="' . $colours[0] . '" stroke-width="' . ($radius - $inner) . '"/>';
        } else {
            $svg .= '<path d="M' . round($x1, 2) . ' ' . round($y1, 2)
                  . ' A' . $radius . ' ' . $radius . ' 0 ' . $large . ' 1 ' . round($x2, 2) . ' ' . round($y2, 2)
                  . ' L' . round($x3, 2) . ' ' . round($y3, 2)
                  . ' A' . $inner . ' ' . $inner . ' 0 ' . $large . ' 0 ' . round($x4, 2) . ' ' . round($y4, 2) . ' Z" '
                  . 'fill="' . $colours[$index % count($colours)] . '">'
                  . '<title>' . e($label . ': ' . $value . ' (' . round($value / $total * 100) . '%)') . '</title></path>';
        }
        $angle = $end;
        $index++;
    }

    $svg .= '<text x="' . $centre . '" y="' . ($centre - 2) . '" text-anchor="middle" font-size="22" font-weight="700" fill="#0f172a">'
          . $total . '</text>';
    $svg .= '<text x="' . $centre . '" y="' . ($centre + 15) . '" text-anchor="middle" font-size="10" fill="#64748b">total</text>';
    $svg .= '</svg>';

    $legend = '<ul class="legend">';
    $index = 0;
    foreach ($data as $label => $value) {
        $legend .= '<li><span class="legend__dot" style="background:' . $colours[$index % count($colours)] . '"></span>'
                 . '<span class="legend__label">' . e($label) . '</span>'
                 . '<span class="legend__value">' . $value . ' &middot; ' . round($value / $total * 100) . '%</span></li>';
        $index++;
    }
    $legend .= '</ul>';

    return '<div class="donut-wrap">' . $svg . $legend . '</div>';
}

/** Horizontal bars - compact, good for sex and age distribution. */
function bar_rows(array $data, string $colour = '#1d4ed8'): string
{
    if (!array_filter($data)) {
        return '<p class="empty">No data for this period.</p>';
    }
    $max = max(1, max($data));
    $html = '';
    foreach ($data as $label => $value) {
        $percent = round(($value / $max) * 100);
        $html .= '<div class="bar-row"><span>' . e($label) . '</span>'
               . '<span class="bar-track"><span class="bar-fill" style="width:' . $percent . '%;background:' . $colour . '"></span></span>'
               . '<span class="num">' . $value . '</span></div>';
    }
    return $html;
}
