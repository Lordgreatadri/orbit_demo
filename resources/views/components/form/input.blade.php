@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'required' => false,
    'autocomplete' => null,
    'placeholder' => null,
])

@php
    $hasError = $errors->has($name);
    $describedBy = $hasError ? "{$name}-error" : ($hint ? "{$name}-hint" : null);
@endphp

<x-form.field :name="$name" :label="$label" :hint="$hint" :required="$required">
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        @if ($required) required @endif
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        @if ($hasError) aria-invalid="true" @endif
        {{ $attributes->merge(['class' => 'block h-10 w-full rounded-lg border bg-surface px-3 text-sm text-fg placeholder:text-fg-subtle focus-visible:outline-2 '.($hasError ? 'border-danger' : 'border-edge hover:border-edge-strong')]) }}
    >
</x-form.field>
