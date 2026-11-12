{{--
    Radial gauge for one rate (0–100). A null value means there is nothing to
    measure and is drawn as an empty track with a dash, never as 0%.
--}}
@props(['value' => null, 'label' => null, 'tone' => 'primary', 'size' => '7.5rem'])

@php
    $ink = match ($tone) {
        'success' => 'var(--success)',
        'warning' => 'var(--warning)',
        'danger' => 'var(--danger)',
        'accent' => 'var(--accent)',
        'info' => 'var(--info)',
        default => 'var(--primary)',
    };
    $pct = $value === null ? null : max(0, min(100, (float) $value));
@endphp

<div {{ $attributes->merge(['class' => 'relative shrink-0']) }} style="width: {{ $size }}; height: {{ $size }}">
    <svg viewBox="0 0 42 42" class="size-full" role="img" aria-label="{{ $label ? $label.': ' : '' }}{{ $pct === null ? 'no figure' : (int) round($pct).'%' }}">
        <circle cx="21" cy="21" r="15.915" fill="none" stroke="var(--surface-muted)" stroke-width="4.5" />
        @if ($pct !== null && $pct > 0)
            <circle cx="21" cy="21" r="15.915" fill="none" stroke="{{ $ink }}" stroke-width="4.5" stroke-linecap="round" stroke-dasharray="{{ sprintf('%.3F %.3F', $pct, 100 - $pct) }}" stroke-dashoffset="25" />
        @endif
    </svg>
    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center" aria-hidden="true">
        <span class="text-xl leading-none font-semibold tracking-tight tabular-nums text-fg">{{ $pct === null ? '—' : (int) round($pct).'%' }}</span>
        @if ($label)
            <span class="mt-1 text-[0.6875rem] text-fg-subtle">{{ $label }}</span>
        @endif
    </div>
</div>
