{{-- Bilgi sayfası araçları: yazdır / PDF ve sayfa bağlantısını kopyala (yazdırmada gizli).
     variant="hero": koyu üst bantta yarı saydam butonlar. --}}
@props(['variant' => 'page'])
@php($btn = $variant === 'hero' ? 'info-hero-action' : 'btn-ghost')
@if ($settings['pages_print'] || $settings['pages_copy_link'])
    <div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2.5 print:hidden']) }}>
        @if ($settings['pages_print'])
            <button type="button" x-data="printPage" x-on:click="print" class="{{ $btn }}"><x-icon name="printer" class="size-4" />{{ $settings['pages_print'] }}</button>
        @endif
        @if ($settings['pages_copy_link'])
            <div x-data="copyText">
                <button type="button" x-on:click="copy" data-copy="{{ url()->current() }}" data-done="{{ $settings['pages_copied'] ?: $settings['pages_copy_link'] }}" class="{{ $btn }}">
                    <x-icon name="link" class="size-4" /><span x-text="label">{{ $settings['pages_copy_link'] }}</span>
                </button>
            </div>
        @endif
    </div>
@endif
