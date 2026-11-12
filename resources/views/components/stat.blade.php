{{--
    KPI tile. The label and hint carry the meaning; the icon reinforces it.
    `emphasis` marks a figure that needs attention (danger/warning) and adds a
    severity edge. `meter` (0–100) draws a thin share bar under the figure.
    `plain` drops the tile surface for use inside an existing panel.
--}}
@props([
    'label',
    'value',
    'hint' => null,
    'icon' => null,
    'tone' => 'neutral',
    'href' => null,
    'emphasis' => null,
    'meter' => null,
    'meterLabel' => null,
    'plain' => false,
])

@php
    $tag = $href ? 'a' : 'div';
    $iconTone = $emphasis ?: $tone;
    $meterInk = match ($emphasis ?: $tone) {
        'success' => 'var(--success)',
        'warning' => 'var(--warning)',
        'danger' => 'var(--danger)',
        'accent' => 'var(--accent)',
        'info' => 'var(--info)',
        'neutral' => 'var(--fg-subtle)',
        default => 'var(--primary)',
    };
    $meter = $meter === null ? null : max(0, min(100, (float) $meter));
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class([
    'group relative flex min-h-11 min-w-0 flex-col focus-visible:outline-2',
    'card-link overflow-hidden p-4 sm:p-5' => ! $plain && $href,
    'card overflow-hidden p-4 sm:p-5' => ! $plain && ! $href,
    'rounded-lg' => $plain,
]) }}>
    @if (! $plain && in_array($emphasis, ['danger', 'warning'], true))
        <span aria-hidden="true" @class([
            'absolute inset-x-0 top-0 h-[3px]',
            'bg-danger' => $emphasis === 'danger',
            'bg-warning' => $emphasis === 'warning',
        ])></span>
    @endif
    <p class="pr-1 text-[0.8125rem] leading-snug font-medium text-fg-muted">{{ $label }}</p>
    <div class="mt-2 flex items-center justify-between gap-3">
        <p @class([
            'min-w-0 text-[1.75rem] leading-none font-semibold tracking-tight tabular-nums sm:text-[1.875rem]',
            'text-danger' => $emphasis === 'danger',
            'text-warning' => $emphasis === 'warning',
            'text-fg' => ! in_array($emphasis, ['danger', 'warning'], true),
        ])>{{ $value }}</p>
        @if ($icon)
            <span aria-hidden="true" class="icon-well tone-{{ $iconTone }}"><x-icon :name="$icon" class="size-4" /></span>
        @endif
    </div>
    @if ($meter !== null)
        <div class="mt-3 meter" role="presentation" @if ($meterLabel) title="{{ $meterLabel }}" @endif>
            <span style="width: {{ $meter }}%; background: {{ $meterInk }}"></span>
        </div>
    @endif
    @if ($hint)
        <p class="mt-2 text-xs leading-relaxed text-fg-subtle">{{ $hint }}</p>
    @endif
    @if ($href)
        <x-icon name="arrow-up-right" class="absolute right-3 bottom-3 size-3.5 text-fg-subtle opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100" />
    @endif
</{{ $tag }}>
