{{--
    Panel. `decision` states, in one line, which decision the content informs.
    `icon` is a quiet 16px glyph beside the title, never a coloured chip.
--}}
@props(['title' => null, 'description' => null, 'padding' => true, 'actions' => null, 'decision' => null, 'icon' => null, 'divided' => true])

<section {{ $attributes->merge(['class' => 'card flex min-w-0 max-w-full flex-col']) }}>
    @if ($title || $description || $actions)
        <header @class([
            'px-5 pt-4',
            'border-b border-edge pb-4' => $divided,
            'pb-0' => ! $divided,
        ])>
            <div class="flex items-start justify-between gap-x-3 gap-y-2">
                @if ($title)
                    <h2 class="flex min-w-0 items-start gap-2 pt-px text-base leading-snug font-semibold tracking-tight text-fg">
                        @if ($icon)
                            <x-icon :name="$icon" class="mt-0.5 size-4 shrink-0 text-fg-subtle" />
                        @endif
                        <span class="min-w-0">{{ $title }}</span>
                    </h2>
                @endif
                @if ($actions)
                    <div class="-my-1.5 ml-auto flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
                @endif
            </div>
            @if ($description)
                <p class="mt-1 max-w-3xl text-sm leading-relaxed text-fg-muted">{{ $description }}</p>
            @endif
        </header>
    @endif
    <div @class(['min-w-0 flex-1', 'p-5' => $padding])>
        {{ $slot }}
    </div>
    @if ($decision)
        <footer class="flex items-start gap-2 rounded-b-xl border-t border-edge bg-surface-muted/50 px-5 py-2.5 text-xs leading-relaxed text-fg-muted">
            <x-icon name="arrow-right" class="mt-0.5 size-3.5 shrink-0 text-primary" />
            <p><span class="font-semibold text-fg">Informs</span> {{ $decision }}</p>
        </footer>
    @endif
</section>
