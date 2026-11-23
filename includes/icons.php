<?php
/**
 * Inline SVG icons. Kept local rather than pulled from a CDN so the app works
 * offline and on a municipal network with no outbound access.
 *
 * Usage: echo icon('home'); echo icon('user', 18, 'field-icon');
 */

function icon_paths(): array
{
    return [
        'home'        => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9.5 21v-6h5v6"/>',
        'book'        => '<path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v18H6.5A2.5 2.5 0 0 0 4 22z"/><path d="M4 17.5A2.5 2.5 0 0 1 6.5 15H20"/>',
        'search'      => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'help'        => '<circle cx="12" cy="12" r="9"/><path d="M9.2 9.3a2.9 2.9 0 0 1 5.6 1c0 1.9-2.8 2.4-2.8 4"/><path d="M12 17.5h.01"/>',
        'user'        => '<circle cx="12" cy="8" r="3.6"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/>',
        'user-plus'   => '<circle cx="9.5" cy="8" r="3.6"/><path d="M2.5 20a7 7 0 0 1 14 0"/><path d="M19 8v6M22 11h-6"/>',
        'phone'       => '<path d="M7 2.5h10a1.5 1.5 0 0 1 1.5 1.5v16a1.5 1.5 0 0 1-1.5 1.5H7A1.5 1.5 0 0 1 5.5 20V4A1.5 1.5 0 0 1 7 2.5z"/><path d="M11 18.5h2"/>',
        'pin'         => '<path d="M12 21s7-5.5 7-11a7 7 0 1 0-14 0c0 5.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/>',
        'target'      => '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4.8"/><circle cx="12" cy="12" r="1.3" fill="currentColor" stroke="none"/>',
        'pen'         => '<path d="M3 20.5h4L20 7.5a2.1 2.1 0 0 0-3-3L4 17.5z"/><path d="m15.5 6 3 3"/>',
        'clipboard'   => '<path d="M9 4.5H7.5A1.5 1.5 0 0 0 6 6v14a1.5 1.5 0 0 0 1.5 1.5h9A1.5 1.5 0 0 0 18 20V6a1.5 1.5 0 0 0-1.5-1.5H15"/><rect x="9" y="2.5" width="6" height="4" rx="1.2"/><path d="m9.5 13 2 2 3.5-4"/>',
        'message'     => '<path d="M20.5 15.5A2.5 2.5 0 0 1 18 18H8l-4.5 3.5V6A2.5 2.5 0 0 1 6 3.5h12A2.5 2.5 0 0 1 20.5 6z"/>',
        'alert'       => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5"/><path d="M12 16.5h.01"/>',
        'check'       => '<path d="m5 12.5 4.5 4.5L19 7"/>',
        'check-circle'=> '<circle cx="12" cy="12" r="9"/><path d="m8 12.2 2.8 2.8L16 9.5"/>',
        'x'           => '<path d="m6 6 12 12M18 6 6 18"/>',
        'chevron'     => '<path d="m6 9 6 6 6-6"/>',
        'chevron-right'=> '<path d="m9 6 6 6-6 6"/>',
        'download'    => '<path d="M12 3v12"/><path d="m7.5 11 4.5 4.5L16.5 11"/><path d="M4.5 20.5h15"/>',
        'printer'     => '<path d="M7 9V3.5h10V9"/><rect x="3.5" y="9" width="17" height="7.5" rx="1.5"/><rect x="7" y="14" width="10" height="6.5" rx="1"/>',
        'logout'      => '<path d="M14.5 4.5H18A1.5 1.5 0 0 1 19.5 6v12a1.5 1.5 0 0 1-1.5 1.5h-3.5"/><path d="M10 12h9.5"/><path d="m13 8.5 3.5 3.5-3.5 3.5"/><path d="M4.5 4.5v15"/>',
        'grid'        => '<rect x="3.5" y="3.5" width="7" height="7" rx="1"/><rect x="13.5" y="3.5" width="7" height="7" rx="1"/><rect x="3.5" y="13.5" width="7" height="7" rx="1"/><rect x="13.5" y="13.5" width="7" height="7" rx="1"/>',
        'dashboard'   => '<rect x="3.5" y="3.5" width="7.5" height="9" rx="1.2"/><rect x="13.5" y="3.5" width="7" height="5.5" rx="1.2"/><rect x="3.5" y="15.5" width="7.5" height="5" rx="1.2"/><rect x="13.5" y="12" width="7" height="8.5" rx="1.2"/>',
        'list'        => '<path d="M8 6.5h12M8 12h12M8 17.5h12"/><path d="M4 6.5h.01M4 12h.01M4 17.5h.01"/>',
        'chart'       => '<path d="M4 20V4"/><path d="M4 20h16"/><rect x="7.5" y="12" width="3" height="5" rx=".6"/><rect x="13" y="8" width="3" height="9" rx=".6"/><rect x="18" y="14" width="3" height="3" rx=".6"/>',
        'calendar'    => '<rect x="3.5" y="5" width="17" height="16" rx="2"/><path d="M3.5 10h17"/><path d="M8 3v4M16 3v4"/>',
        'clock'       => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'mail'        => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6 8.5-6"/>',
        'menu'        => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'edit'        => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m14.5 5.5 4 4"/>',
        'trash'       => '<path d="M4.5 7h15"/><path d="M9 7V4.5h6V7"/><path d="M6.5 7 7.5 20.5h9L17.5 7"/>',
        'plus'        => '<path d="M12 5v14M5 12h14"/>',
        'save'        => '<path d="M5 4.5h11L19.5 8v11.5a1 1 0 0 1-1 1h-13a1 1 0 0 1-1-1v-14a1 1 0 0 1 1-1z"/><path d="M8 4.5v5h7v-5"/><rect x="8" y="13" width="8" height="7.5" rx="1"/>',
        'filter'      => '<path d="M3.5 5.5h17l-6.5 8V20l-4-2v-4.5z"/>',
        'eye'         => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>',
        'shield'      => '<path d="M12 3 5 6v6c0 4.4 3 8.1 7 9 4-.9 7-4.6 7-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'refresh'     => '<path d="M4 12a8 8 0 0 1 13.7-5.6L20 8.5"/><path d="M20 4v4.5h-4.5"/><path d="M20 12a8 8 0 0 1-13.7 5.6L4 15.5"/><path d="M4 20v-4.5h4.5"/>',
        'upload'      => '<path d="M12 16V4"/><path d="m7.5 8 4.5-4.5L16.5 8"/><path d="M4.5 20.5h15"/>',
        'lock'        => '<rect x="4.5" y="10" width="15" height="10.5" rx="2"/><path d="M8 10V7.5a4 4 0 0 1 8 0V10"/>',
        'building'    => '<path d="M4.5 21V6.5L12 3l7.5 3.5V21"/><path d="M4.5 21h15"/><path d="M9.5 21v-5h5v5"/><path d="M9 10h.01M15 10h.01M9 13.5h.01M15 13.5h.01"/>',
        'arrow-right' => '<path d="M4 12h15"/><path d="m13.5 6.5 5.5 5.5-5.5 5.5"/>',
        'star'        => '<path d="m12 3.5 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17.5l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/>',
        'qr'          => '<rect x="3.5" y="3.5" width="7" height="7" rx="1"/><rect x="13.5" y="3.5" width="7" height="7" rx="1"/><rect x="3.5" y="13.5" width="7" height="7" rx="1"/><path d="M13.5 13.5h3v3h-3z"/><path d="M20.5 13.5v3M17.5 20.5h3M13.5 20.5h.01"/>',
        'scan'        => '<path d="M3.5 8.5V5A1.5 1.5 0 0 1 5 3.5h3.5"/><path d="M15.5 3.5H19A1.5 1.5 0 0 1 20.5 5v3.5"/><path d="M20.5 15.5V19a1.5 1.5 0 0 1-1.5 1.5h-3.5"/><path d="M8.5 20.5H5A1.5 1.5 0 0 1 3.5 19v-3.5"/><path d="M3.5 12h17"/>',
        'camera'      => '<path d="M3.5 8.5A1.5 1.5 0 0 1 5 7h2.5l1.4-2.2a1 1 0 0 1 .85-.47h4.5a1 1 0 0 1 .85.47L16.5 7H19a1.5 1.5 0 0 1 1.5 1.5v9A1.5 1.5 0 0 1 19 19H5a1.5 1.5 0 0 1-1.5-1.5z"/><circle cx="12" cy="12.7" r="3.4"/>',
        'bell'        => '<path d="M6.5 10a5.5 5.5 0 0 1 11 0c0 4 1.5 5.5 1.5 5.5H5S6.5 14 6.5 10z"/><path d="M10 18.5a2.2 2.2 0 0 0 4 0"/>',
        'users'       => '<circle cx="9" cy="8" r="3.4"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 5.2a3.4 3.4 0 0 1 0 5.6"/><path d="M17.5 14.2A6.5 6.5 0 0 1 21.5 20"/>',
    ];
}

function icon(string $name, int $size = 20, string $class = ''): string
{
    $paths = icon_paths();
    if (!isset($paths[$name])) {
        return '';
    }
    return sprintf(
        '<svg class="icon %s" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
        htmlspecialchars($class, ENT_QUOTES),
        $size,
        $size,
        $paths[$name]
    );
}
