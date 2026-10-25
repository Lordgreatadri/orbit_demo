@props(['name', 'label', 'hint' => null, 'required' => false, 'id' => null])

@php
    $id ??= $name;
    $hasError = $errors->has($name);
@endphp

<div {{ $attributes->merge(['class' => 'space-y-1.5']) }}>
    <label for="{{ $id }}" class="block text-sm font-medium text-fg">
        {{ $label }}
        @if ($required)
            <span class="text-danger" aria-hidden="true">*</span>
            <span class="sr-only">(required)</span>
        @endif
    </label>

    {{ $slot }}

    @if ($hint && ! $hasError)
        <p id="{{ $id }}-hint" class="text-xs text-fg-subtle">{{ $hint }}</p>
    @endif

    @error($name)
        <p id="{{ $id }}-error" class="flex items-start gap-1.5 text-sm text-danger" role="alert">
            <svg aria-hidden="true" viewBox="0 0 20 20" class="mt-0.5 size-4 shrink-0" fill="currentColor"><path fill-rule="evenodd" d="M18 10A8 8 0 1 1 2 10a8 8 0 0 1 16 0Zm-7 4a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm-1-9a1 1 0 0 0-1 1v4a1 1 0 1 0 2 0V6a1 1 0 0 0-1-1Z" clip-rule="evenodd"/></svg>
            <span>{{ $message }}</span>
        </p>
    @enderror
</div>
