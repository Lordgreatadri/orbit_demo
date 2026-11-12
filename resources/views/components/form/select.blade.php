@props(['name', 'label', 'options' => [], 'value' => null, 'hint' => null, 'required' => false, 'placeholder' => null, 'id' => null])

@php
    $id = $id ?: $name;
    $hasError = $errors->has($name);
    $describedBy = $hasError ? "{$id}-error" : ($hint ? "{$id}-hint" : null);
    $selected = old($name, $value);
@endphp

<x-form.field :name="$name" :id="$id" :label="$label" :hint="$hint" :required="$required">
    <select
        id="{{ $id }}"
        name="{{ $name }}"
        @if ($required) required @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        @if ($hasError) aria-invalid="true" @endif
        {{ $attributes->merge(['class' => 'block min-h-11 w-full rounded-lg border bg-surface px-3 text-sm text-fg focus-visible:outline-2 '.($hasError ? 'border-danger' : 'border-edge hover:border-edge-strong')]) }}
    >
        @if ($placeholder)
            <option value="" @selected($selected === null || $selected === '')>{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $selected === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
</x-form.field>
