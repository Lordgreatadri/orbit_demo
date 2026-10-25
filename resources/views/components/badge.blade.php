@props(['tone' => 'neutral'])

@php
    $tones = [
        'neutral' => 'bg-surface-muted text-fg-muted border-edge',
        'success' => 'bg-success-soft text-success border-success/30',
        'warning' => 'bg-warning-soft text-warning border-warning/30',
        'danger' => 'bg-danger-soft text-danger border-danger/30',
        'info' => 'bg-info-soft text-info border-info/30',
        'accent' => 'bg-accent-soft text-accent border-accent/30',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium '.($tones[$tone] ?? $tones['neutral'])]) }}>{{ $slot }}</span>
