@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition-[background-color,border-color,color,box-shadow,transform] duration-200 focus-visible:outline-2 disabled:cursor-not-allowed disabled:opacity-60';
    $sizes = [
        'sm' => 'h-9 px-3.5 text-sm',
        'md' => 'h-10 px-4 text-[0.9375rem]',
        'lg' => 'h-12 px-6 text-base',
    ];
    $variants = [
        'primary' => 'bg-primary text-primary-fg shadow-sm hover:bg-primary-hover hover:shadow-md active:translate-y-px',
        'secondary' => 'border border-edge bg-surface text-fg hover:border-edge-strong hover:bg-surface-muted',
        'ghost' => 'text-fg-muted hover:bg-surface-muted hover:text-fg',
        'danger' => 'bg-danger text-white hover:opacity-90',
        'link' => 'h-auto px-0 text-primary underline-offset-2 hover:underline',
        'inverse' => 'bg-white text-brand-deep shadow-sm hover:bg-white/90',
        'outline-inverse' => 'border border-white/30 text-white hover:border-white/60 hover:bg-white/10',
    ];
    $classes = $base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
