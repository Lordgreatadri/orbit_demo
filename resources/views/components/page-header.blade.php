@props(['title', 'description' => null, 'eyebrow' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between']) }}>
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="text-xs font-semibold tracking-wide text-primary uppercase">{{ $eyebrow }}</p>
        @endif
        <h1 class="text-2xl font-semibold tracking-tight text-fg">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 max-w-2xl text-sm text-fg-muted">{{ $description }}</p>
        @endif
    </div>
    @if (trim($slot))
        <div class="flex shrink-0 flex-wrap gap-2">{{ $slot }}</div>
    @endif
</div>
