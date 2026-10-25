@props(['name', 'label', 'options' => [], 'value' => null, 'hint' => null, 'required' => false, 'placeholder' => null])

@php
    $hasError = $errors->has($name);
    $describedBy = $hasError ? "{$name}-error" : ($hint ? "{$name}-hint" : null);
    $selected = old($name, $value);
@endphp

<x-form.field :name="$name" :label="$label" :hint="$hint" :required="$required">
    <select
        id="{{ $name }}"
        name="{{ $name }}"
        @if ($required) required @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        @if ($hasError) aria-invalid="true" @endif
        {{ $attributes->merge(['class' => 'block h-10 w-full rounded-lg border bg-surface px-3 text-sm text-fg focus-visible:outline-2 '.($hasError ? 'border-danger' : 'border-edge hover:border-edge-strong')]) }}
    >
        @if ($placeholder)
            <option value="" @selected($selected === null || $selected === '')>{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $selected === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
</x-form.field>
