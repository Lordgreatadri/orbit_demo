{{-- Compact labelled select for filter bars (no error handling; GET forms). --}}
@props(['name', 'label', 'options' => [], 'value' => null, 'placeholder' => 'All'])

<label class="flex min-w-[9rem] flex-1 flex-col gap-1 text-xs font-medium text-fg-muted sm:flex-none">
    <span>{{ $label }}</span>
    <select name="{{ $name }}" {{ $attributes->merge(['class' => 'h-9 rounded-lg border border-edge bg-surface px-2.5 text-sm text-fg hover:border-edge-strong focus-visible:outline-2']) }}>
        <option value="">{{ $placeholder }}</option>
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) request($name, $value) === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
</label>
