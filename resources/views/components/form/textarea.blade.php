@props(['name', 'label', 'value' => null, 'hint' => null, 'required' => false, 'rows' => 5, 'placeholder' => null, 'id' => null])

@php
    $id = $id ?: $name;
    $hasError = $errors->has($name);
    $describedBy = $hasError ? "{$id}-error" : ($hint ? "{$id}-hint" : null);
@endphp

<x-form.field :name="$name" :id="$id" :label="$label" :hint="$hint" :required="$required">
    <textarea
        id="{{ $id }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        @if ($required) required @endif
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        @if ($hasError) aria-invalid="true" @endif
        {{ $attributes->merge(['class' => 'block w-full rounded-lg border bg-surface px-3 py-2 text-sm text-fg placeholder:text-fg-subtle focus-visible:outline-2 '.($hasError ? 'border-danger' : 'border-edge hover:border-edge-strong')]) }}
    >{{ old($name, $value) }}</textarea>
</x-form.field>
