{{-- compact: icon only · size: sm|md|lg · tagline: show "Track · Manage · Empower" --}}
@props(['compact' => false, 'size' => 'md', 'tagline' => false])

@php
    $icon = ['sm' => 'size-7 rounded-md', 'md' => 'size-9 rounded-lg', 'lg' => 'size-12 rounded-xl'][$size] ?? 'size-9 rounded-lg';
    $word = ['sm' => 'text-base', 'md' => 'text-xl', 'lg' => 'text-2xl'][$size] ?? 'text-xl';
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <img
        src="{{ asset('images/brand/orbit-icon.png') }}"
        alt=""
        aria-hidden="true"
        width="96"
        height="96"
        class="{{ $icon }} shrink-0 shadow-sm"
        decoding="async"
    >
    @unless ($compact)
        <span class="flex flex-col leading-none">
            <span class="{{ $word }} font-bold tracking-[0.08em] text-fg">{{ config('orbit.product_name') }}</span>
            @if ($tagline)
                <span class="mt-1 text-[0.65rem] font-medium tracking-[0.18em] text-fg-subtle uppercase">Track · Manage · Empower</span>
            @endif
        </span>
    @endunless
</span>
