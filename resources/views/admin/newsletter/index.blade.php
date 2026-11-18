<x-layouts.admin title="E-bülten">
    <x-slot:actions>
        <a href="{{ route('admin.newsletter.export') }}" class="btn-ghost btn-sm"><x-icon name="download" class="size-4" />Aboneleri indir (CSV)</a>
        <a href="{{ route('admin.settings.edit', 'genel') }}#bulten-ayarlari" class="btn-ghost btn-sm"><x-icon name="settings" class="size-4" />Form metinleri</a>
    </x-slot:actions>
    <x-flash class="mb-5" />

    {{-- Özet --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        @foreach ([['Aktif abone', $activeCount, 'users', 'text-primary'], ['Son 30 günde katılan', $last30, 'plus', 'text-success'], ['Abonelikten çıkan', $leftCount, 'x', 'text-muted']] as [$label, $value, $icon, $color])
            <div class="card flex items-center gap-4 p-5">
                <span class="flex size-11 items-center justify-center rounded-xl bg-surface {{ $color }}"><x-icon :name="$icon" class="size-5" /></span>
                <div><p class="text-2xl font-extrabold">{{ number_format($value, 0, ',', '.') }}</p><p class="text-sm text-muted">{{ $label }}</p></div>
            </div>
        @endforeach
    </div>

    <div class="grid items-start gap-6 xl:grid-cols-[1.15fr_1fr]">
        {{-- Toplu e-posta --}}
        <section class="card overflow-hidden">
            <h2 class="flex items-center gap-2 border-b border-line px-5 py-4 font-bold"><x-icon name="send" class="size-4 text-primary" />Toplu e-posta gönder</h2>
            <form method="post" action="{{ route('admin.newsletter.send') }}" class="space-y-4 p-5">
                @csrf
                <x-field name="subject" label="E-posta konusu" required placeholder="Yeni dönem kitapları geldi" />
                <x-field name="heading" label="Başlık" required placeholder="Yeni dönem kitaplarında %15 indirim" help="E-postanın içinde büyük yazılan başlık." />
                <x-field name="body" type="textarea" rows="7" label="Metin" required help="Paragrafları boş bir satırla ayırın. Alt kısma iletişim bilgileriniz ve abonelikten çıkış bağlantısı otomatik eklenir." />
                <div class="grid gap-4 sm:grid-cols-[1fr_1.6fr]">
                    <x-field name="button_text" label="Buton yazısı (isteğe bağlı)" placeholder="Kitapları incele" />
                    <x-field name="button_url" label="Buton bağlantısı" placeholder="{{ route('products.index') }}" />
                </div>
                <div class="flex flex-wrap items-center gap-3 border-t border-line pt-4">
                    <button name="mode" value="test" class="btn-ghost" @disabled(! $testTo)><x-icon name="mail" class="size-4" />Bana deneme gönder</button>
                    <button name="mode" value="all" class="btn-primary" @disabled(! $activeCount)
                            data-confirm="E-posta {{ number_format($activeCount, 0, ',', '.') }} aboneye gönderilsin mi? Bu işlem geri alınamaz.">
                        <x-icon name="send" class="size-4" />{{ number_format($activeCount, 0, ',', '.') }} aboneye gönder</button>
                    <p class="w-full text-xs text-muted">
                        @if ($testTo)Deneme {{ implode(', ', $testTo) }} adresine gider.@else Deneme için Ayarlar › E-posta bölümünde bildirim adresi girin.@endif
                        Her aboneye ayrı e-posta gider; alıcılar birbirini görmez. Gmail hesapları günde yaklaşık 500 e-postayla sınırlıdır.
                    </p>
                </div>
            </form>

            <h3 class="border-t border-line px-5 pt-4 pb-2 text-sm font-bold">Gönderilenler</h3>
            <table class="table-panel">
                <thead><tr><th>Konu</th><th>Tarih</th><th class="text-right">Gönderildi</th><th class="text-right">Hata</th></tr></thead>
                <tbody>
                    @forelse ($campaigns as $c)
                        <tr>
                            <td><p class="font-semibold">{{ $c->subject }}</p><p class="text-xs text-muted">{{ $c->admin?->name }}</p></td>
                            <td class="text-sm whitespace-nowrap text-muted">{{ ($c->sent_at ?? $c->created_at)->format('d.m.Y H:i') }}</td>
                            <td class="text-right text-sm whitespace-nowrap">
                                {{ number_format($c->sent_count, 0, ',', '.') }} / {{ number_format($c->recipients_count, 0, ',', '.') }}
                                @if ($c->sent_count + $c->failed_count < $c->recipients_count)<span class="badge ml-1 bg-warning-soft text-warning">Gönderiliyor</span>@endif
                            </td>
                            <td class="text-right text-sm">
                                @if ($c->failed_count)<a href="{{ route('admin.mail-logs', ['tur' => 'newsletter', 'durum' => 'hata']) }}" class="font-semibold text-danger hover:underline">{{ $c->failed_count }}</a>@else<span class="text-muted">0</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-8 text-center text-muted">Henüz toplu e-posta gönderilmedi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        {{-- Aboneler --}}
        <section class="card overflow-hidden">
            <h2 class="flex items-center gap-2 border-b border-line px-5 py-4 font-bold"><x-icon name="users" class="size-4 text-accent-ink" />Aboneler</h2>
            <div class="space-y-3 border-b border-line bg-surface/60 p-4">
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.newsletter.index') }}" class="btn-sm {{ $filter === 'aktif' ? 'btn-primary' : 'btn-ghost' }}">Aktif ({{ number_format($activeCount, 0, ',', '.') }})</a>
                    <a href="{{ route('admin.newsletter.index', ['durum' => 'ayrilan']) }}" class="btn-sm {{ $filter === 'ayrilan' ? 'btn-primary' : 'btn-ghost' }}">Çıkanlar ({{ number_format($leftCount, 0, ',', '.') }})</a>
                </div>
                <form method="get" class="flex gap-2">
                    @if ($filter === 'ayrilan')<input type="hidden" name="durum" value="ayrilan">@endif
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="E-posta ara" class="field-input min-w-0 flex-1">
                    <button class="btn-ghost btn-sm" aria-label="Ara"><x-icon name="search" class="size-4" /></button>
                </form>
                <form method="post" action="{{ route('admin.newsletter.subscribers.store') }}" class="flex gap-2">
                    @csrf
                    <input type="email" name="email" required placeholder="Elle abone ekle: ornek@eposta.com" class="field-input min-w-0 flex-1">
                    <button class="btn-primary btn-sm"><x-icon name="plus" class="size-4" />Ekle</button>
                </form>
                @error('email')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <table class="table-panel">
                <thead><tr><th>E-posta</th><th>{{ $filter === 'aktif' ? 'Katılma' : 'Çıkma' }}</th><th class="w-0"></th></tr></thead>
                <tbody>
                    @forelse ($subscribers as $s)
                        <tr>
                            <td class="break-all"><p class="font-semibold">{{ $s->email }}</p>
                                <p class="text-xs text-muted">{{ $s->source === 'admin' ? 'Yönetimden eklendi' : 'Site formu' }}@if ($s->consented_at) · onay {{ $s->consented_at->format('d.m.Y H:i') }}@endif</p></td>
                            <td class="text-sm whitespace-nowrap text-muted">{{ ($filter === 'aktif' ? $s->created_at : $s->unsubscribed_at)?->format('d.m.Y') }}</td>
                            <td class="text-right">
                                <form method="post" action="{{ route('admin.newsletter.subscribers.destroy', $s) }}" data-confirm="{{ $s->email }} silinsin mi?">@csrf @method('delete')
                                    <button class="btn-ghost btn-sm text-danger" aria-label="Sil"><x-icon name="trash" class="size-3.5" /></button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-8 text-center text-muted">{{ request('q') ? 'Eşleşen abone yok.' : 'Henüz abone yok. Alt bilgideki form ile kaydolanlar burada görünür.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if ($subscribers->hasPages())<div class="border-t border-line p-4">{{ $subscribers->links() }}</div>@endif
        </section>
    </div>
</x-layouts.admin>
