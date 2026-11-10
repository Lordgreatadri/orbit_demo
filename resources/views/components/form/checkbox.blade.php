@props(['name', 'value' => '1', 'checked' => false, 'required' => false, 'id' => null])

@php
    $id = $id ?: $name;
    $hasError = $errors->has($name);
@endphp

<div {{ $attributes->merge(['class' => 'space-y-1.5']) }}>
    <label for="{{ $id }}" class="flex cursor-pointer items-start gap-3 text-sm text-fg">
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="checkbox"
            value="{{ $value }}"
            @checked(old($name, $checked))
            @if ($required) required @endif
            @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            class="mt-0.5 size-4 shrink-0 rounded border-edge-strong text-primary focus-visible:outline-2"
        >
        <span>{{ $slot }}</span>
    </label>
    @error($name)
        <p id="{{ $id }}-error" class="text-sm text-danger" role="alert">{{ $message }}</p>
    @enderror
</div>
