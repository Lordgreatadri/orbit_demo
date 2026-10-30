{{-- GET filter form for index pages. Fields go in the slot; reset link clears the query string. --}}
@props(['action', 'reset' => null])

<form method="GET" action="{{ $action }}" role="search" {{ $attributes->merge(['class' => 'card flex flex-wrap items-end gap-3 p-4']) }}>
    {{ $slot }}
    <div class="flex items-center gap-2">
        <x-button type="submit" variant="secondary" size="sm"><x-icon name="filter" class="size-4" /> Apply</x-button>
        @if ($reset && request()->query())
            <x-button :href="$reset" variant="ghost" size="sm">Reset</x-button>
        @endif
    </div>
</form>
