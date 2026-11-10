@php($customer = auth('web')->user())
{{-- Header'daki backdrop-blur sabit konumlu çocukları header'a hapsettiği için mobil menü body düzeyindedir. --}}
{{-- Mobil menü (kategori menüsü Aşama 4'te yönetimden gelir) --}}
<div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-ink/40 lg:hidden" x-on:click="close"></div>
<aside x-show="open" x-cloak x-transition:enter="transition duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
       class="fixed inset-y-0 left-0 z-50 w-80 max-w-[85vw] overflow-y-auto bg-white p-5 shadow-lift lg:hidden">
    <div class="mb-6 flex items-center justify-between">
        <span class="font-display text-lg font-bold">{{ $settings['site_name'] }}</span>
        <button type="button" x-on:click="close" class="rounded-lg p-2 hover:bg-surface" aria-label="Menüyü kapat"><x-icon name="x" /></button>
    </div>
    <nav class="space-y-1 text-[15px] font-medium">
        <a href="{{ route('home') }}" class="block rounded-lg px-3 py-2.5 hover:bg-surface">Ana sayfa</a>
        <a href="{{ $customer ? route('account.index') : route('login') }}" class="block rounded-lg px-3 py-2.5 hover:bg-surface">{{ $customer ? 'Hesabım' : 'Giriş yap / Üye ol' }}</a>
    </nav>
</aside>
