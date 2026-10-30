{{-- Labelled placeholder for a planned module. Never implies the feature is live. --}}
@props(['module'])

<div class="card overflow-hidden">
    <div class="bg-mesh relative isolate px-6 py-12 text-center sm:px-10 sm:py-16">
        <div class="bg-grid absolute inset-0 -z-10 opacity-70" aria-hidden="true"></div>
        <span class="inline-flex items-center gap-2 rounded-full border border-edge bg-surface px-3 py-1 text-xs font-semibold tracking-wide text-fg-muted uppercase shadow-sm">
            <x-icon name="sparkles" class="size-3.5 text-primary" /> Planned module · {{ $module['stage'] }}
        </span>
        <h2 class="mt-5 text-2xl font-semibold tracking-tight text-fg sm:text-3xl">{{ $module['label'] }} is coming soon</h2>
        <p class="mx-auto mt-3 max-w-xl text-[0.95rem] leading-relaxed text-fg-muted">{{ $module['description'] }}</p>
        <p class="mx-auto mt-4 max-w-lg text-sm text-fg-subtle">This page is a labelled placeholder: no data on it is live. The module is delivered in the stage shown above, following the project plan and acceptance criteria.</p>
        @if (trim($slot))
            <div class="mt-7 flex flex-wrap items-center justify-center gap-3">{{ $slot }}</div>
        @endif
    </div>
</div>
