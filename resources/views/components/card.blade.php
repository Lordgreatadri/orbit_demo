@props(['title' => null, 'description' => null, 'padding' => true])

<section {{ $attributes->merge(['class' => 'rounded-xl border border-edge bg-surface shadow-card']) }}>
    @if ($title || $description)
        <header class="border-b border-edge px-5 py-4">
            @if ($title)
                <h2 class="text-base font-semibold text-fg">{{ $title }}</h2>
            @endif
            @if ($description)
                <p class="mt-0.5 text-sm text-fg-muted">{{ $description }}</p>
            @endif
        </header>
    @endif
    <div @class(['p-5' => $padding])>
        {{ $slot }}
    </div>
</section>
