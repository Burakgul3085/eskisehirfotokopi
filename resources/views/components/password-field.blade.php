@props(['name' => 'password', 'label' => 'Parola', 'help' => null, 'autocomplete' => 'current-password', 'required' => true])
@php($key = str_replace(['[', ']'], ['.', ''], $name))
<div x-data="passwordField" {{ $attributes->only('class') }}>
    <label for="f_{{ $name }}" class="field-label">{{ $label }}</label>
    <div class="relative">
        <input id="f_{{ $name }}" name="{{ $name }}" x-bind:type="inputType" type="password" autocomplete="{{ $autocomplete }}"
            @if ($required) required @endif aria-invalid="{{ $errors->has($key) ? 'true' : 'false' }}" class="field-input pr-20">
        <button type="button" x-on:click="toggleVisible" x-text="label"
            class="absolute inset-y-0 right-0 px-3.5 text-xs font-semibold text-muted hover:text-primary">Göster</button>
    </div>
    @error($key)<p class="field-error">{{ $message }}</p>@enderror
    @if ($help)<p class="field-help">{{ $help }}</p>@endif
</div>
