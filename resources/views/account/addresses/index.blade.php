<x-layouts.store title="Adreslerim" :noindex="true">
    <div class="container-page py-10">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h1 class="text-3xl font-extrabold">Adreslerim</h1>
            <a href="{{ route('account.addresses.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" />Yeni adres</a>
        </div>
        @include('account._nav')
        <x-flash class="mt-6" />

        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($addresses as $a)
                <div class="card flex flex-col p-5 text-sm">
                    <p class="flex items-center gap-2 font-bold">{{ $a->title }}@if ($a->is_default)<span class="badge bg-primary-soft text-primary-dark">Varsayılan</span>@endif</p>
                    <p class="mt-2">{{ $a->name }} · {{ \App\Rules\TurkishPhone::format($a->phone) }}</p>
                    <p class="mt-1 flex-1 text-muted">{{ $a->oneLine() }}</p>
                    @unless ($a->district?->is_active)<p class="mt-2 text-xs text-danger">Bu ilçeye şu anda teslimat yapılmıyor.</p>@endunless
                    <div class="mt-4 flex gap-2">
                        <a href="{{ route('account.addresses.edit', $a) }}" class="btn-ghost btn-sm"><x-icon name="edit" class="size-3.5" />Düzenle</a>
                        <form method="post" action="{{ route('account.addresses.destroy', $a) }}" data-confirm="Adres silinsin mi?">@csrf @method('delete')
                            <button class="btn-ghost btn-sm text-danger"><x-icon name="trash" class="size-3.5" />Sil</button></form>
                    </div>
                </div>
            @empty
                <p class="card p-8 text-center text-muted sm:col-span-2 lg:col-span-3">Kayıtlı adresiniz yok. Siparişte yazdığınız adresi kaydedebilir ya da buradan ekleyebilirsiniz.</p>
            @endforelse
        </div>
    </div>
</x-layouts.store>
