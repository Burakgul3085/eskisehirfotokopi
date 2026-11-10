@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'help' => null, 'required' => false])
@php
    $id = 'f_'.str_replace(['[', ']', '.'], '_', $name);
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $invalid = $errors->has($key);
@endphp
<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $id }}" class="field-label">{{ $label }}@if ($required)<span class="text-danger"> *</span>@endif</label>
    @endif
    @if ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $attributes->get('rows', 3) }}" @if ($required) required @endif
            aria-invalid="{{ $invalid ? 'true' : 'false' }}" {{ $attributes->except(['class', 'rows'])->merge(['class' => 'field-input']) }}>{{ old($key, $value) }}</textarea>
    @else
        <input id="{{ $id }}" type="{{ $type }}" name="{{ $name }}" @if ($type !== 'password') value="{{ old($key, $value) }}" @endif
            @if ($required) required @endif aria-invalid="{{ $invalid ? 'true' : 'false' }}"
            {{ $attributes->except('class')->merge(['class' => 'field-input']) }}>
    @endif
    @error($key)<p class="field-error">{{ $message }}</p>@enderror
    @if ($help)<p class="field-help">{{ $help }}</p>@endif
</div>
