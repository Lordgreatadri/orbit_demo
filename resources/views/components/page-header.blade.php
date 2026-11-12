@props(['title', 'description' => null, 'eyebrow' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between']) }}>
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="text-xs font-semibold tracking-wide text-primary">{{ $eyebrow }}</p>
        @endif
        <h1 class="mt-1 text-[1.75rem] leading-tight font-semibold tracking-tight text-fg md:text-[2rem]">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1.5 max-w-3xl text-sm leading-relaxed text-fg-muted">{{ $description }}</p>
        @endif
    </div>
    @if (trim($slot))
        <div class="flex shrink-0 flex-wrap gap-2">{{ $slot }}</div>
    @endif
</div>
