@props(['items' => []])

@if (count($items) > 1)
    <nav aria-label="Breadcrumb" class="text-sm text-fg-muted">
        <ol class="flex flex-wrap items-center gap-1.5">
            @foreach ($items as $item)
                <li class="inline-flex items-center gap-1.5">
                    @if (! $loop->first)
                        <span aria-hidden="true" class="text-fg-subtle">/</span>
                    @endif
                    @if (! empty($item['url']) && ! $loop->last)
                        <a href="{{ $item['url'] }}" class="rounded hover:text-fg focus-visible:outline-2">{{ $item['label'] }}</a>
                    @else
                        <span @if ($loop->last) aria-current="page" @endif class="{{ $loop->last ? 'font-medium text-fg' : '' }}">{{ $item['label'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
