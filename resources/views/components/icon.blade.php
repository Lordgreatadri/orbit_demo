@props(['name'])

@php
    // Minimal outline icon set (24×24, stroke-based) so public pages stay dependency-free.
    $paths = [
        'qr' => 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h3v3h-3zM20 14v6h-3M17 20h3',
        'map-pin' => 'M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11Zm0-8.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z',
        'calendar' => 'M7 3v3m10-3v3M4 9h16M6 5h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm2 8h3v3H8z',
        'document-check' => 'M9 3h6l5 5v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h3Zm5 0v6h6M9 15l2 2 4-4',
        'bell' => 'M6 16V11a6 6 0 1 1 12 0v5l1.5 2h-15L6 16Zm4 4a2 2 0 0 0 4 0',
        'chart' => 'M4 20h16M7 16v-5m5 5V8m5 8v-3',
        'building' => 'M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16M4 21h16m-4-9h2a2 2 0 0 1 2 2v7M8 7h2m-2 4h2m-2 4h2m2-8h2m-2 4h2',
        'academic-cap' => 'M2 9l10-5 10 5-10 5L2 9Zm4 3v5c0 1.5 3 3 6 3s6-1.5 6-3v-5m4-3v6',
        'landmark' => 'M3 9l9-5 9 5H3Zm2 0v9m4-9v9m6-9v9m4-9v9M3 21h18M3 18h18',
        'heart-hand' => 'M12 20s-7-4.4-7-9.5A3.5 3.5 0 0 1 12 8a3.5 3.5 0 0 1 7 2.5C19 15.6 12 20 12 20Z',
        'users' => 'M16 19a4 4 0 0 0-8 0M12 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm7 8a3 3 0 0 0-3-3m0-4a2.5 2.5 0 0 0 0-5M5 19a3 3 0 0 1 3-3m0-4a2.5 2.5 0 0 1 0-5',
        'shield-check' => 'M12 3l7 3v6c0 4.5-3 7.8-7 9-4-1.2-7-4.5-7-9V6l7-3Zm-3 9 2 2 4-4',
        'lock' => 'M7 11V8a5 5 0 0 1 10 0v3M6 11h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-8a1 1 0 0 1 1-1Zm6 4v2',
        'clock' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0-13v5l3 2',
        'check' => 'm5 12 4 4L19 6',
        'check-circle' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm-3.5-9 2.5 2.5 5-5',
        'arrow-right' => 'M5 12h14m-6-6 6 6-6 6',
        'arrow-up-right' => 'M7 17 17 7M8 7h9v9',
        'mail' => 'M4 6h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Zm0 1 8 6 8-6',
        'chat' => 'M4 5h16a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1h-9l-5 4v-4H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z',
        'globe' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0-18c-3 3.5-3 14.5 0 18m0-18c3 3.5 3 14.5 0 18M3 12h18',
        'sparkles' => 'M12 3v4m0 10v4m-9-9h4m10 0h4M6.3 6.3l2.8 2.8m5.8 5.8 2.8 2.8M6.3 17.7l2.8-2.8m5.8-5.8 2.8-2.8',
        'layers' => 'm12 3 9 5-9 5-9-5 9-5Zm-9 9 9 5 9-5M3 16l9 5 9-5',
        'eye-off' => 'M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A10.4 10.4 0 0 1 12 5c5 0 9 4.5 10 7a12.8 12.8 0 0 1-2.4 3.4M6.5 6.5C4.3 8 2.7 10.2 2 12c1 2.5 5 7 10 7 1.6 0 3.1-.4 4.4-1.1',
        'device-phone' => 'M8 3h8a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm3 15h2',
        'book' => 'M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2V5Zm0 16a2 2 0 0 1 2-2h13M8 7h7',
        'life-buoy' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0-5a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7.4-7.4 4.6 4.6m5.6 0 4.6-4.6M4.6 15.4l4.6-4.6m5.6 0 4.6 4.6',
        'scale' => 'M12 3v18M5 7l-3 7a3.5 3.5 0 0 0 6 0L5 7Zm14 0-3 7a3.5 3.5 0 0 0 6 0l-3-7ZM4 7h16M8 21h8',
        'id-card' => 'M3 6h18a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Zm6 6a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm-3 4a3 3 0 0 1 6 0m2-5h4m-4 3h3',
    ];
    $d = $paths[$name] ?? $paths['check'];
@endphp

<svg {{ $attributes->merge(['class' => 'size-5']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    <path d="{{ $d }}" />
</svg>
