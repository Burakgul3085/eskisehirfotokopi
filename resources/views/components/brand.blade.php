@props(['logo' => null, 'name' => '', 'dark' => false])
{{-- Site işareti: yüklenmiş logo ya da düz iki sayfadan oluşan "E" işareti ve ad (header ve alt bilgi ortak). --}}
@if ($logo)
    <img src="{{ $logo }}" alt="{{ $name }}" class="h-9 w-auto transition duration-500 group-hover/brand:scale-105 sm:h-12">
@else
    {{-- Logo: düz renkli iki sayfa (fotokopi = kopya) ve ön sayfadaki satırlardan oluşan "E" harfi.
         Gradyansız, kurumsal işaret; üzerine gelince sayfalar hafifçe ayrılır. --}}
    <svg viewBox="0 0 40 40" class="brand-mark size-10 shrink-0 sm:size-11" aria-hidden="true">
        <path class="brand-back" d="M14 3h15.5a4 4 0 0 1 4 4v22.5a4 4 0 0 1-4 4H14a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4Z"/>
        <g class="brand-front">
            <path class="brand-sheet" d="M8.5 9h14.2L29 15.3V33a4 4 0 0 1-4 4H8.5a4 4 0 0 1-4-4V13a4 4 0 0 1 4-4Z"/>
            <path class="brand-fold" d="M22.7 9v4.3a2 2 0 0 0 2 2H29Z"/>
            <rect class="brand-ink" x="10" y="19" width="3" height="13" rx="1.5"/>
            <rect class="brand-ink" x="10" y="19" width="13" height="3" rx="1.5"/>
            <rect class="brand-ink" x="10" y="24" width="9.5" height="3" rx="1.5"/>
            <rect class="brand-ink" x="10" y="29" width="13" height="3" rx="1.5"/>
        </g>
    </svg>
    @php($nameWords = preg_split('/\s+/u', trim($name)))
    @php($nameLast = count($nameWords) > 1 ? array_pop($nameWords) : null)
    {{-- Yazı: üstte ad, altta son kelime aralıklı büyük harf (ör. ESKİŞEHİR / FOTOKOPİ) --}}
    <span class="brand-type {{ $dark ? 'block' : 'hidden sm:block' }}">
        <span class="block font-display text-[1.4rem] leading-none font-extrabold tracking-[-0.02em] {{ $dark ? 'text-white' : 'text-ink' }}">{{ implode(' ', $nameWords) }}</span>
        @if ($nameLast)
            <span class="mt-1 flex items-center gap-2 text-[0.68rem] leading-none font-bold tracking-[0.34em] {{ $dark ? 'text-accent' : 'text-primary' }} uppercase">
                <span class="brand-rule h-[2px] w-4 rounded-full bg-accent"></span>{{ $nameLast }}
            </span>
        @endif
    </span>
@endif
