@php
    $svgs = [
        'trend'     => '<path d="M4 17h16M6 14l4-5 4 3 4-7"/><path d="M16 5h4v4"/>',
        'factory'   => '<path d="M4 20V9l5 3V9l5 3h6v8H4Z"/><path d="M8 16h1M12 16h1M16 16h1"/>',
        'bank'      => '<path d="M4 10h16M6 10v8M10 10v8M14 10v8M18 10v8M3 20h18M12 4l8 4H4l8-4Z"/>',
        'price'     => '<path d="M12 3v18"/><path d="M17 7.5C16.2 6 14.7 5 12.3 5H11a3 3 0 0 0 0 6h2a3 3 0 0 1 0 6h-1.3C9.3 17 7.8 16 7 14.5"/>',
        'rocket'    => '<path d="M12 15l-3-3c1-5 4-8 10-9-1 6-4 9-9 10Z"/><path d="M9 12l-4 1-2 4 4-2 1-4M12 15l-1 4-4 2 2-4 4-1"/><circle cx="15" cy="8" r="1.5"/>',
        'globe'     => '<circle cx="12" cy="12" r="8"/><path d="M4 12h16M12 4c2 2 3 5 3 8s-1 6-3 8M12 4c-2 2-3 5-3 8s1 6 3 8"/>',
        'briefcase' => '<path d="M10 6V5a2 2 0 0 1 2-2h0a2 2 0 0 1 2 2v1"/><rect x="4" y="6" width="16" height="14" rx="2"/><path d="M4 12h16M10 12v2h4v-2"/>',
        'users'     => '<path d="M16 19v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="3"/><path d="M20 19v-2a3 3 0 0 0-2-2.8M16 4.2a3 3 0 0 1 0 5.6"/>',
    ];
    $body = $svgs[$name ?? 'trend'] ?? $svgs['trend'];
@endphp
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor">{!! $body !!}</svg>
