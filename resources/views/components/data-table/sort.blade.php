@props(['column', 'sort', 'label' => null])

@php
    /** @var \App\Support\TableSort $sort */
    $active = $sort->is($column);
    $url = request()->fullUrlWithQuery(['sort' => $column, 'dir' => $sort->nextDirection($column)]);
    $aria = $active ? ($sort->direction === 'asc' ? 'ascending' : 'descending') : 'none';
@endphp

<th scope="col" aria-sort="{{ $aria }}" {{ $attributes }}>
    <a href="{{ $url }}" class="group inline-flex items-center gap-1 hover:text-fg">
        <span>{{ $label ?? $slot }}</span>
        <span class="text-fg-subtle" aria-hidden="true">
            @if ($active && $sort->direction === 'asc')
                <x-icon name="chevron-up" class="size-3.5 text-primary" />
            @elseif ($active)
                <x-icon name="chevron-down" class="size-3.5 text-primary" />
            @else
                <x-icon name="chevron-down" class="size-3.5 opacity-40 group-hover:opacity-100" />
            @endif
        </span>
    </a>
</th>
