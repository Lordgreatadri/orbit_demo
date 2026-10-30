@props(['name' => 'q', 'label' => 'Search', 'placeholder' => 'Search…'])

<label class="flex min-w-[12rem] flex-1 flex-col gap-1 text-xs font-medium text-fg-muted">
    <span>{{ $label }}</span>
    <span class="relative">
        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-fg-subtle" />
        <input type="search" name="{{ $name }}" value="{{ request($name) }}" placeholder="{{ $placeholder }}" {{ $attributes->merge(['class' => 'h-9 w-full rounded-lg border border-edge bg-surface pl-8 pr-3 text-sm text-fg placeholder:text-fg-subtle hover:border-edge-strong focus-visible:outline-2']) }}>
    </span>
</label>
