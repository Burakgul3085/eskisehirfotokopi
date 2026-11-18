{{-- "Yardıma mı ihtiyacınız var?" kartı: ayarlardaki telefon, WhatsApp, e-posta ve iletişim sayfası. --}}
@php($wa = \App\Support\Settings::whatsappUrl())
<div class="rounded-3xl bg-surface p-5">
    @if ($settings['pages_help_title'])<p class="font-display font-extrabold text-ink">{{ $settings['pages_help_title'] }}</p>@endif
    @if ($settings['pages_help_text'])<p class="mt-1 text-sm text-muted">{{ $settings['pages_help_text'] }}</p>@endif
    <ul class="mt-4 space-y-2.5 text-sm font-semibold">
        @if ($settings['phone'])
            <li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $settings['phone']) }}" class="flex items-center gap-2.5 hover:text-primary"><span class="info-help-icon"><x-icon name="phone" class="size-4" /></span>{{ $settings['phone'] }}</a></li>
        @endif
        @if ($wa)
            <li><a href="{{ $wa }}" target="_blank" rel="noopener" class="flex items-center gap-2.5 hover:text-primary"><span class="info-help-icon"><x-icon name="whatsapp" class="size-4" /></span>WhatsApp</a></li>
        @endif
        @if ($settings['email'])
            <li><a href="mailto:{{ $settings['email'] }}" class="flex items-center gap-2.5 break-all hover:text-primary"><span class="info-help-icon"><x-icon name="mail" class="size-4" /></span>{{ $settings['email'] }}</a></li>
        @endif
    </ul>
    @if ($settings['pages_contact_link'])
        <a href="{{ route('contact') }}" class="mt-4 inline-flex items-center gap-1.5 text-sm font-bold text-primary hover:text-primary-dark">{{ $settings['pages_contact_link'] }}<x-icon name="arrow-right" class="size-4" /></a>
    @endif
</div>
