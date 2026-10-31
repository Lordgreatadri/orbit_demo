@props(['name', 'label', 'value' => null, 'hint' => null])

@php $current = old($name, $value ?? '#0F766E'); @endphp

<div class="space-y-1.5">
    <label for="{{ $name }}" class="text-sm font-medium text-fg">{{ $label }}</label>
    <div class="flex items-center gap-3">
        <input type="color" value="{{ str_starts_with((string) $current, '#') ? $current : '#'.ltrim((string) $current, '#') }}" class="size-10 cursor-pointer rounded-lg border border-edge bg-surface p-1" oninput="document.getElementById('{{ $name }}').value = this.value">
        <input id="{{ $name }}" name="{{ $name }}" type="text" value="{{ $current }}" maxlength="7" class="w-32 rounded-lg border border-edge bg-surface px-3 py-2 font-mono text-sm uppercase" pattern="#?[0-9A-Fa-f]{6}" {{ $attributes }}>
    </div>
    @if ($hint)
        <p class="text-xs text-fg-subtle">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="text-sm text-danger">{{ $message }}</p>
    @enderror
</div>
