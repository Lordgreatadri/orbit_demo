@props(['title', 'description' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center rounded-xl border border-dashed border-edge-strong bg-surface-muted/60 px-6 py-10 text-center']) }}>
    <span aria-hidden="true" class="mb-3 grid size-10 place-items-center rounded-full bg-surface text-fg-subtle">
        <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h10M4 17h7"/></svg>
    </span>
    <p class="text-sm font-semibold text-fg">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-fg-muted">{{ $description }}</p>
    @endif
    @if (trim($slot))
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
