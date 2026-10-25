@props(['eyebrow' => null, 'title', 'lead' => null, 'align' => 'left'])

<div {{ $attributes->class(['max-w-3xl', 'mx-auto text-center' => $align === 'center']) }}>
    @if ($eyebrow)
        <p @class(['eyebrow', 'justify-center' => $align === 'center'])>{{ $eyebrow }}</p>
    @endif
    <h2 class="mt-3 text-3xl font-semibold tracking-tight text-balance text-fg sm:text-4xl">{{ $title }}</h2>
    @if ($lead)
        <p class="mt-4 text-lg text-pretty text-fg-muted">{{ $lead }}</p>
    @endif
</div>
