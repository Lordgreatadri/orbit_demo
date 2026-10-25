@props(['eyebrow' => null, 'title', 'lead' => null, 'align' => 'left'])

{{-- Shared hero band for inner public pages (how it works, guide, contact, legal). --}}
<section class="relative isolate overflow-hidden border-b border-edge bg-surface">
    <div class="bg-mesh absolute inset-0 -z-10" aria-hidden="true"></div>
    <div class="bg-grid absolute inset-0 -z-10" aria-hidden="true"></div>
    <div @class(['mx-auto max-w-6xl px-4 py-14 sm:px-6 lg:py-20', 'text-center' => $align === 'center'])>
        @if ($eyebrow)
            <p @class(['eyebrow', 'justify-center' => $align === 'center'])>{{ $eyebrow }}</p>
        @endif
        <h1 class="mt-4 text-4xl font-semibold tracking-tight text-balance text-fg sm:text-5xl">{{ $title }}</h1>
        @if ($lead)
            <p @class(['mt-5 max-w-2xl text-lg text-pretty text-fg-muted sm:text-xl', 'mx-auto' => $align === 'center'])>{{ $lead }}</p>
        @endif
        @if (trim($slot))
            <div @class(['mt-8 flex flex-wrap gap-3', 'justify-center' => $align === 'center'])>{{ $slot }}</div>
        @endif
    </div>
</section>
