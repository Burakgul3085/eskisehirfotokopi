{{-- Yazdırılan belgenin sonundaki not: site adı, çıktı tarihi ve güncel metnin adresi (ekranda gizli). --}}
@if ($settings['pages_print_note'])
    <p class="info-print-note hidden print:block">{{ strtr($settings['pages_print_note'], [':site' => $settings['site_name'], ':date' => now()->translatedFormat('j F Y H:i'), ':url' => url()->current()]) }}</p>
@endif
