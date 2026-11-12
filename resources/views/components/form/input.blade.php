@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'required' => false,
    'autocomplete' => null,
    'placeholder' => null,
    'id' => null,
])

@php
    $id = $id ?: $name;
    $hasError = $errors->has($name);
    $describedBy = $hasError ? "{$id}-error" : ($hint ? "{$id}-hint" : null);
@endphp

<x-form.field :name="$name" :id="$id" :label="$label" :hint="$hint" :required="$required">
    @if ($type === 'password')
        {{-- Show/hide toggle. The field stays type="password" until the person asks to see it. --}}
        <div class="relative" x-data="{ reveal: false }">
            <input
                id="{{ $id }}"
                name="{{ $name }}"
                type="password"
                x-bind:type="reveal ? 'text' : 'password'"
                autocapitalize="off"
                spellcheck="false"
                @if ($required) required @endif
                @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
                @if ($placeholder) placeholder="{{ $placeholder }}" @endif
                @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                @if ($hasError) aria-invalid="true" @endif
                {{ $attributes->merge(['class' => 'block min-h-11 w-full rounded-lg border bg-surface pr-12 pl-3 text-sm text-fg placeholder:text-fg-subtle focus-visible:outline-2 '.($hasError ? 'border-danger' : 'border-edge hover:border-edge-strong')]) }}
            >
            <button
                type="button"
                class="absolute inset-y-0 right-0 inline-flex w-11 items-center justify-center rounded-r-lg text-fg-subtle transition-colors hover:text-fg focus-visible:outline-2"
                x-on:click="reveal = ! reveal"
                x-bind:aria-pressed="reveal.toString()"
                x-bind:aria-label="reveal ? 'Hide {{ strtolower($label) }}' : 'Show {{ strtolower($label) }}'"
                aria-label="Show {{ strtolower($label) }}"
                aria-controls="{{ $id }}"
                x-cloak
            >
                <x-icon name="eye" class="size-5" x-show="! reveal" />
                <x-icon name="eye-off" class="size-5" x-show="reveal" />
            </button>
        </div>
    @else
    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        @if ($required) required @endif
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        @if ($hasError) aria-invalid="true" @endif
        {{ $attributes->merge(['class' => 'block min-h-11 w-full rounded-lg border bg-surface px-3 text-sm text-fg placeholder:text-fg-subtle focus-visible:outline-2 '.($hasError ? 'border-danger' : 'border-edge hover:border-edge-strong')]) }}
    >
    @endif
</x-form.field>
