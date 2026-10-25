@props(['type' => 'info', 'title' => null, 'dismissible' => false])

@php
    $styles = [
        'success' => ['bg-success-soft text-success border-success/30', 'Success'],
        'warning' => ['bg-warning-soft text-warning border-warning/30', 'Attention'],
        'danger' => ['bg-danger-soft text-danger border-danger/30', 'Error'],
        'info' => ['bg-info-soft text-info border-info/30', 'Information'],
    ];
    [$classes, $defaultTitle] = $styles[$type] ?? $styles['info'];
    $role = in_array($type, ['danger', 'warning'], true) ? 'alert' : 'status';
@endphp

<div
    role="{{ $role }}"
    @if ($dismissible) x-data="{ open: true }" x-show="open" @endif
    {{ $attributes->merge(['class' => 'flex gap-3 rounded-lg border px-4 py-3 text-sm '.$classes]) }}
>
    <span class="sr-only">{{ $title ?? $defaultTitle }}:</span>
    <div class="min-w-0 flex-1 space-y-0.5">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        <div class="text-fg">{{ $slot }}</div>
    </div>
    @if ($dismissible)
        <button type="button" @click="open = false" class="-mr-1 shrink-0 rounded p-1 hover:bg-surface/60" aria-label="Dismiss">
            <svg aria-hidden="true" viewBox="0 0 20 20" class="size-4" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
        </button>
    @endif
</div>
