@props(['title' => null, 'description' => null, 'padding' => true, 'actions' => null])

<section {{ $attributes->merge(['class' => 'card']) }}>
    @if ($title || $description || $actions)
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-edge px-5 py-4">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="text-base font-semibold text-fg">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-0.5 text-sm text-fg-muted">{{ $description }}</p>
                @endif
            </div>
            @if ($actions)
                <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
            @endif
        </header>
    @endif
    <div @class(['p-5' => $padding])>
        {{ $slot }}
    </div>
</section>
