{{-- Avatar + name + e-mail, used in tables. --}}
@props(['user', 'href' => null])

<div class="flex items-center gap-3">
    <span aria-hidden="true" class="grid size-8 shrink-0 place-items-center rounded-full bg-primary-soft text-[0.7rem] font-semibold text-primary">{{ $user->initials }}</span>
    <div class="min-w-0">
        @if ($href)
            <a href="{{ $href }}" class="block truncate font-medium text-fg hover:text-primary hover:underline">{{ $user->name }}</a>
        @else
            <p class="truncate font-medium text-fg">{{ $user->name }}</p>
        @endif
        <p class="truncate text-xs text-fg-subtle">{{ $user->email }}</p>
    </div>
</div>
