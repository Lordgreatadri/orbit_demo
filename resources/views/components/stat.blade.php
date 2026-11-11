{{-- A figure in a strip. Meaning comes from the label and hint, not an icon chip. --}}
@props(['label', 'value', 'hint' => null, 'icon' => null, 'tone' => 'primary', 'href' => null, 'emphasis' => null])

@php
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'group block min-h-11 min-w-0 rounded-md focus-visible:outline-2']) }}>
    <p class="text-[0.8125rem] font-medium text-fg-muted">{{ $label }}</p>
    <p @class([
        'mt-1 text-[1.75rem] leading-none font-semibold tracking-tight tabular-nums',
        'text-danger' => $emphasis === 'danger',
        'text-fg' => $emphasis !== 'danger',
        'group-hover:underline' => (bool) $href,
    ])>{{ $value }}</p>
    @if ($hint)
        <p class="mt-1.5 text-xs leading-relaxed text-fg-subtle">{{ $hint }}</p>
    @endif
</{{ $tag }}>
