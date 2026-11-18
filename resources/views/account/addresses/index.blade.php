<x-account-layout title="Adreslerim" :text="$settings['account_addresses_text']">
    <x-slot:actions><a href="{{ route('account.addresses.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" />Yeni adres</a></x-slot:actions>

    @if ($addresses->isEmpty())
        @include('account._empty', ['icon' => 'pin', 'title' => 'Kayıtlı adresiniz yok.', 'text' => 'Adres eklerseniz siparişte tek tıkla seçebilirsiniz. Siparişte yazdığınız adres de buraya kaydedilebilir.', 'cta' => ['Adres ekle', route('account.addresses.create')]])
    @else
        <div class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
            @foreach ($addresses as $a)
                <div class="card account-address flex flex-col p-5 text-sm {{ $a->is_default ? 'is-default' : '' }}">
                    <div class="flex items-center gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-accent-soft text-accent-ink"><x-icon name="pin" class="size-4" /></span>
                        <p class="min-w-0 flex-1 truncate font-display text-base font-extrabold">{{ $a->title }}</p>
                        @if ($a->is_default)<span class="badge bg-primary-soft text-primary-dark">Varsayılan</span>@endif
                    </div>
                    <p class="mt-4 font-semibold">{{ $a->name }} · {{ \App\Rules\TurkishPhone::format($a->phone) }}</p>
                    <p class="mt-1 flex-1 text-muted">{{ $a->oneLine() }}</p>
                    @unless ($a->district?->is_active)<p class="mt-2 text-xs font-semibold text-danger">Bu ilçeye şu anda teslimat yapılmıyor.</p>@endunless
                    <div class="mt-4 flex gap-2 border-t border-line pt-4">
                        <a href="{{ route('account.addresses.edit', $a) }}" class="btn-ghost btn-sm"><x-icon name="edit" class="size-3.5" />Düzenle</a>
                        <form method="post" action="{{ route('account.addresses.destroy', $a) }}" data-confirm="Adres silinsin mi?">@csrf @method('delete')
                            <button class="btn-ghost btn-sm text-danger"><x-icon name="trash" class="size-3.5" />Sil</button></form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-account-layout>
