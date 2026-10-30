{{-- KPI tile: label, value, optional hint/context and icon. Never shows fabricated precision. --}}
@props(['label', 'value', 'hint' => null, 'icon' => null, 'tone' => 'primary', 'href' => null])

@php
    $tones = [
        'primary' => 'bg-primary-soft text-primary',
        'accent' => 'bg-accent-soft text-accent',
        'success' => 'bg-success-soft text-success',
        'warning' => 'bg-warning-soft text-warning',
        'danger' => 'bg-danger-soft text-danger',
        'neutral' => 'bg-surface-muted text-fg-muted',
    ];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => ($href ? 'card-link' : 'card').' flex items-start gap-4 p-4 sm:p-5']) }}>
    @if ($icon)
        <span aria-hidden="true" class="grid size-10 shrink-0 place-items-center rounded-xl {{ $tones[$tone] ?? $tones['primary'] }}"><x-icon :name="$icon" class="size-5" /></span>
    @endif
    <div class="min-w-0 flex-1">
        <p class="truncate text-xs font-medium text-fg-muted">{{ $label }}</p>
        <p class="mt-1 text-2xl font-semibold tracking-tight text-fg tabular-nums">{{ $value }}</p>
        @if ($hint)
            <p class="mt-1 truncate text-xs text-fg-subtle">{{ $hint }}</p>
        @endif
    </div>
</{{ $tag }}>
