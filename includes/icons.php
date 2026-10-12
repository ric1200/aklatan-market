<?php
/**
 * Inline SVG icon set.
 * Everything is bundled locally so the storefront never depends on an icon CDN
 * and never shows empty squares when a CDN is blocked or offline.
 */
function icon(string $name, string $class = '', int $size = 20): string
{
    static $paths = [
        'book'        => '<path d="M12 6.5S10.2 4.5 6.6 4.5H3.5v13h3.1c3.6 0 5.4 2 5.4 2s1.8-2 5.4-2h3.1v-13h-3.1C14.2 4.5 12 6.5 12 6.5Z"/><path d="M12 6.5v13"/>',
        'book-open'   => '<path d="M12 7.2C10.6 5.9 8.8 5 6.6 5H3.4v12.6h3.2c2.2 0 4 .9 5.4 2.2 1.4-1.3 3.2-2.2 5.4-2.2h3.2V5h-3.2c-2.2 0-4 .9-5.4 2.2Z"/><path d="M12 7.2v12.6"/>',
        'search'      => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
        'cart'        => '<path d="M3 4h2.2l2.3 11.2h10.8L21 7H6"/><circle cx="9.5" cy="19.5" r="1.4"/><circle cx="17.5" cy="19.5" r="1.4"/>',
        'heart'       => '<path d="M12 20.3S3.8 15.6 3.8 9.9A4.6 4.6 0 0 1 12 7a4.6 4.6 0 0 1 8.2 2.9c0 5.7-8.2 10.4-8.2 10.4Z"/>',
        'user'        => '<circle cx="12" cy="8.5" r="3.6"/><path d="M4.8 20a7.4 7.4 0 0 1 14.4 0"/>',
        'menu'        => '<path d="M3.5 6.5h17M3.5 12h17M3.5 17.5h17"/>',
        'close'       => '<path d="M6 6l12 12M18 6 6 18"/>',
        'star'        => '<path d="m12 3.6 2.6 5.4 5.9.8-4.3 4.1 1 5.9-5.2-2.8-5.2 2.8 1-5.9L3.5 9.8l5.9-.8Z"/>',
        'chevron-down'=> '<path d="m6 9.5 6 6 6-6"/>',
        'chevron-up'  => '<path d="m6 14.5 6-6 6 6"/>',
        'chevron-left'=> '<path d="m14.5 5.5-6.5 6.5 6.5 6.5"/>',
        'chevron-right'=>'<path d="m9.5 5.5 6.5 6.5-6.5 6.5"/>',
        'arrow-right' => '<path d="M4 12h15m-5.5-5.5L19 12l-5.5 5.5"/>',
        'truck'       => '<path d="M2.8 6.5h10.4v9.2H2.8z"/><path d="M13.2 9.6h4l3 3v3.1h-7z"/><circle cx="6.6" cy="18" r="1.6"/><circle cx="16.6" cy="18" r="1.6"/>',
        'shield'      => '<path d="M12 3.4 5 6v5.4c0 4.3 3 7.6 7 9.2 4-1.6 7-4.9 7-9.2V6Z"/><path d="m9 11.8 2.2 2.2L15.4 9.8"/>',
        'tag'         => '<path d="M12.6 3.5H20v7.4l-9 9-7.4-7.4z"/><circle cx="16.4" cy="7.4" r="1.3"/>',
        'trash'       => '<path d="M4.5 6.6h15M9.6 6.6V4.4h4.8v2.2M6.6 6.6l1 13h8.8l1-13"/>',
        'plus'        => '<path d="M12 5v14M5 12h14"/>',
        'minus'       => '<path d="M5 12h14"/>',
        'check'       => '<path d="m5 12.8 4.5 4.4L19 6.6"/>',
        'check-circle'=> '<circle cx="12" cy="12" r="8.6"/><path d="m8.2 12.3 2.6 2.6 5-5.2"/>',
        'x-circle'    => '<circle cx="12" cy="12" r="8.6"/><path d="m9.2 9.2 5.6 5.6M14.8 9.2l-5.6 5.6"/>',
        'clock'       => '<circle cx="12" cy="12" r="8.6"/><path d="M12 7.4V12l3.4 2"/>',
        'box'         => '<path d="m12 3.5 8 4.2v9L12 21l-8-4.3v-9Z"/><path d="m4 7.7 8 4.3 8-4.3M12 12v9"/>',
        'package'     => '<path d="m12 3.5 8 4.2v9L12 21l-8-4.3v-9Z"/><path d="m4 7.7 8 4.3 8-4.3M12 12v9"/>',
        'grid'        => '<path d="M4 4h6.5v6.5H4zM13.5 4H20v6.5h-6.5zM4 13.5h6.5V20H4zM13.5 13.5H20V20h-6.5z"/>',
        'users'       => '<circle cx="9.2" cy="8.8" r="3.2"/><path d="M3.4 19.4a5.8 5.8 0 0 1 11.6 0"/><path d="M16 6.2a3.2 3.2 0 0 1 0 6.2M17.4 19.4a5.8 5.8 0 0 0-2.1-4.4"/>',
        'settings'    => '<circle cx="12" cy="12" r="3.2"/><path d="M12 3.2v2.4M12 18.4v2.4M4.8 12H2.4M21.6 12H19.2M6.9 6.9 5.2 5.2M18.8 18.8l-1.7-1.7M17.1 6.9l1.7-1.7M5.2 18.8l1.7-1.7"/>',
        'image'       => '<rect x="3.5" y="4.5" width="17" height="15" rx="2.4"/><circle cx="9" cy="9.6" r="1.6"/><path d="m4.5 17.5 4.6-4.4 3.6 3.3 3-2.7 4.8 4.2"/>',
        'message'     => '<path d="M4 5.5h16v10.4H13l-4.4 3.6v-3.6H4z"/>',
        'mail'        => '<rect x="3" y="5.5" width="18" height="13" rx="2.2"/><path d="m3.8 7 8.2 6 8.2-6"/>',
        'phone'       => '<path d="M7.4 3.8h3l1.4 4-2 1.4a11 11 0 0 0 5 5l1.4-2 4 1.4v3a2 2 0 0 1-2.2 2A16.4 16.4 0 0 1 5.4 6a2 2 0 0 1 2-2.2Z"/>',
        'pin'         => '<path d="M12 21s6.6-6 6.6-11a6.6 6.6 0 0 0-13.2 0C5.4 15 12 21 12 21Z"/><circle cx="12" cy="10" r="2.4"/>',
        'chart'       => '<path d="M4 20V4M4 20h16"/><path d="M8 17v-5M12.6 17V7.5M17.2 17v-7"/>',
        'clipboard'   => '<rect x="5.5" y="4.5" width="13" height="15.5" rx="2"/><path d="M9.2 4.5V3.4h5.6v1.1M9 11h6M9 15h4"/>',
        'list'        => '<path d="M8 6.5h12M8 12h12M8 17.5h12M4 6.5h.01M4 12h.01M4 17.5h.01"/>',
        'edit'        => '<path d="M15.6 4.6 19.4 8.4 9.2 18.6 4.6 19.4l.8-4.6z"/>',
        'eye'         => '<path d="M2.6 12S6 5.8 12 5.8 21.4 12 21.4 12 18 18.2 12 18.2 2.6 12 2.6 12Z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off'     => '<path d="M4 4l16 16"/><path d="M9.6 5.9A9.6 9.6 0 0 1 12 5.8c6 0 9.4 6.2 9.4 6.2a17 17 0 0 1-2.9 3.7M6.3 7.9A17.4 17.4 0 0 0 2.6 12S6 18.2 12 18.2a9.4 9.4 0 0 0 3.2-.5"/>',
        'logout'      => '<path d="M14 6.5V4.8H5.5v14.4H14v-1.7"/><path d="M10 12h10m-3-3 3 3-3 3"/>',
        'lock'        => '<rect x="4.8" y="10.4" width="14.4" height="9.4" rx="2.2"/><path d="M8.2 10.4V7.8a3.8 3.8 0 0 1 7.6 0v2.6"/>',
        'alert'       => '<path d="M12 4.4 21 19.6H3Z"/><path d="M12 10v4M12 16.6h.01"/>',
        'info'        => '<circle cx="12" cy="12" r="8.6"/><path d="M12 11v5.4M12 7.8h.01"/>',
        'sparkle'     => '<path d="M12 3.5l1.7 4.9 4.9 1.7-4.9 1.7L12 16.7l-1.7-4.9-4.9-1.7 4.9-1.7Z"/><path d="M18.6 15.6l.7 2 2 .7-2 .7-.7 2-.7-2-2-.7 2-.7Z"/>',
        'newspaper'   => '<path d="M4 5.5h12.5v14H5.8a1.8 1.8 0 0 1-1.8-1.8Z"/><path d="M16.5 8.5H20v9.2a1.8 1.8 0 0 1-3.5 0Z"/><path d="M7 9h6.5M7 12.4h6.5M7 15.8h4"/>',
        'music'       => '<circle cx="7.4" cy="17.4" r="2.6"/><circle cx="17.4" cy="15.6" r="2.6"/><path d="M10 17.4V6.6l10-2v11"/>',
        'film'        => '<rect x="3" y="5" width="18" height="14" rx="2.2"/><path d="M7.6 5v14M16.4 5v14M3 12h18M3 8.4h4.6M3 15.6h4.6M16.4 8.4H21M16.4 15.6H21"/>',
        'pen'         => '<path d="M4.5 19.5 8 18.6 19.2 7.4l-3.1-3.1L4.5 15.5Z"/>',
        'download'    => '<path d="M12 4v10.5m0 0-3.6-3.6M12 14.5l3.6-3.6"/><path d="M5 18.5h14"/>',
        'upload'      => '<path d="M12 15.5V5m0 0L8.4 8.6M12 5l3.6 3.6"/><path d="M5 18.5h14"/>',
        'filter'      => '<path d="M4 6.5h16l-6.2 7v5.2l-3.6 1.8V13.5Z"/>',
        'home'        => '<path d="m4 10.6 8-6.2 8 6.2V19H4Z"/><path d="M9.6 19v-5.4h4.8V19"/>',
        'gift'        => '<rect x="3.6" y="9" width="16.8" height="4" rx="1.4"/><path d="M5.2 13v7h13.6v-7M12 9v11"/><path d="M12 9S11 4.4 8.8 4.4A2.3 2.3 0 0 0 8.8 9M12 9s1-4.6 3.2-4.6A2.3 2.3 0 0 1 15.2 9"/>',
        'external'    => '<path d="M14 4.5h5.5V10"/><path d="M19.5 4.5 11 13"/><path d="M18 14v4.5a1.8 1.8 0 0 1-1.8 1.8H5.8A1.8 1.8 0 0 1 4 18.5V8a1.8 1.8 0 0 1 1.8-1.8H10"/>',
        'refresh'     => '<path d="M20 12a8 8 0 1 1-2.6-5.9"/><path d="M20 4.5V10h-5.5"/>',
    ];
    $body = $paths[$name] ?? $paths['info'];
    $classAttr = $class !== '' ? ' class="' . e($class) . '"' : '';
    return '<svg' . $classAttr . ' width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24"'
        . ' fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"'
        . ' aria-hidden="true" focusable="false">' . $body . '</svg>';
}

/** The brand wordmark: an original open-book mark plus the store name. */
function brand_mark(int $size = 30): string
{
    return '<svg class="brand-mark" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 40 40" role="img" aria-label="Aklatan Market logo" focusable="false">'
        . '<rect x="1.5" y="1.5" width="37" height="37" rx="10" fill="none" stroke="currentColor" stroke-width="2.4"/>'
        . '<path d="M20 13.4c-1.9-1.7-4.3-2.5-7-2.5H10.5v15.9H13c2.7 0 5.1.8 7 2.5 1.9-1.7 4.3-2.5 7-2.5h2.5V10.9H27c-2.7 0-5.1.8-7 2.5Z" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"/>'
        . '<path d="M20 13.4v15.9" fill="none" stroke="currentColor" stroke-width="2.2"/>'
        . '<path d="M20 8.2v3" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>'
        . '</svg>';
}
