{{-- Ayar sayfalarının ortak kabuğu: grup sekmeleri ve form. Kullanım: @include('admin.settings._layout', ['body' => ...]) yerine bileşen. --}}
<div class="mb-6 flex gap-1 overflow-x-auto rounded-xl bg-white p-1 shadow-card">
    @foreach ($groups as $key => $label)
        <a href="{{ route('admin.settings.edit', $key) }}"
           class="shrink-0 rounded-lg px-4 py-2 text-sm font-semibold {{ $key === $group ? 'bg-primary text-white' : 'text-muted hover:bg-surface hover:text-ink' }}">{{ $label }}</a>
    @endforeach
</div>
