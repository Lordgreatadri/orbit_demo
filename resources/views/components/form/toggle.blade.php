{{-- Accessible checkbox rendered as a row with label + description (used in role/permission matrices). --}}
@props(['name', 'value', 'checked' => false, 'label', 'description' => null, 'disabled' => false])

<label class="flex cursor-pointer items-start gap-3 rounded-lg border border-edge bg-surface px-3 py-2.5 text-sm transition-colors hover:border-edge-strong has-[:checked]:border-primary/50 has-[:checked]:bg-primary-soft/40 has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-60">
    <input type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked($checked) @disabled($disabled) class="mt-0.5 size-4 shrink-0 rounded border-edge-strong text-primary focus-visible:outline-2">
    <span class="min-w-0">
        <span class="block font-medium text-fg">{{ $label }}</span>
        @if ($description)
            <span class="mt-0.5 block text-xs leading-snug text-fg-muted">{{ $description }}</span>
        @endif
    </span>
</label>
