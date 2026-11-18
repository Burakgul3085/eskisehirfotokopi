@php
    use App\Models\ProductReview;
    /** @var ProductReview $review */
    $changed = $review->isPendingEdit() && ($review->rating !== $review->published_rating || $review->title !== $review->published_title || $review->body !== $review->published_body);
@endphp
<x-layouts.admin :title="'Değerlendirme · '.\Illuminate\Support\Str::limit($review->product?->title, 40)">
    <x-slot:actions>
        <a href="{{ route('admin.reviews.index') }}" class="btn-ghost btn-sm">Tüm değerlendirmeler</a>
    </x-slot:actions>

    <div class="grid gap-6 xl:grid-cols-[1fr_20rem]">
        <div class="min-w-0 space-y-4">
            <div class="card p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <span class="admin-stars admin-stars-lg" aria-label="{{ $review->rating }} yıldız">@foreach (range(1, 5) as $i)<x-icon name="star" class="size-5 {{ $i <= $review->rating ? 'is-on' : '' }}" />@endforeach</span>
                        @if ($review->title)<h1 class="mt-2 text-xl font-bold break-words">{{ $review->title }}</h1>@endif
                    </div>
                    <span class="badge {{ ProductReview::tone($review->status) }}">{{ $review->statusLabel() }}</span>
                </div>
                <p class="mt-3 text-sm whitespace-pre-line break-words">{{ $review->body }}</p>
                <p class="mt-3 text-xs text-muted">Gönderildi: {{ $review->submitted_at?->format('d.m.Y H:i') }}@if ($review->published_at) · İlk yayın: {{ $review->published_at->format('d.m.Y H:i') }}@endif</p>
                @if ($review->reject_reason)<p class="mt-3 rounded-xl bg-danger-soft px-3 py-2 text-sm text-danger">Ret nedeni: {{ $review->reject_reason }}</p>@endif

                @if ($changed)
                    <div class="mt-4 rounded-xl border border-dashed border-line bg-surface p-4 text-sm">
                        <p class="font-semibold">Şu an sayfada görünen (önceki) hâli</p>
                        <span class="admin-stars mt-2" aria-label="{{ $review->published_rating }} yıldız">@foreach (range(1, 5) as $i)<x-icon name="star" class="size-3.5 {{ $i <= $review->published_rating ? 'is-on' : '' }}" />@endforeach</span>
                        @if ($review->published_title)<p class="mt-1 font-semibold">{{ $review->published_title }}</p>@endif
                        <p class="mt-1 whitespace-pre-line text-muted">{{ $review->published_body }}</p>
                    </div>
                @endif
            </div>

            @if ($review->photos->isNotEmpty())
                <div class="card p-5">
                    <h2 class="font-bold">Fotoğraflar</h2>
                    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach ($review->photos as $photo)
                            <figure class="relative overflow-hidden rounded-xl border border-line bg-surface">
                                <a href="{{ $photo->url() }}" target="_blank" rel="noopener"><img src="{{ $photo->thumbUrl() }}" alt="" class="aspect-square w-full object-cover"></a>
                                <figcaption class="flex items-center justify-between gap-2 px-2 py-1.5 text-xs">
                                    <span class="{{ $photo->is_approved ? 'text-success' : 'text-accent-ink' }}">{{ $photo->is_approved ? 'Yayında' : 'Onay bekliyor' }}</span>
                                    <form method="post" action="{{ route('admin.reviews.photos.destroy', [$review, $photo]) }}" data-confirm="Bu fotoğraf silinsin mi?">
                                        @csrf @method('delete')
                                        <button class="text-danger hover:underline" aria-label="Fotoğrafı sil"><x-icon name="trash" class="size-3.5" /></button>
                                    </form>
                                </figcaption>
                            </figure>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($review->status === 'pending')
                <form method="post" action="{{ route('admin.reviews.approve', $review) }}" class="card space-y-4 border-l-4 border-success p-5">
                    @csrf
                    <h2 class="font-bold">Yayınla</h2>
                    <x-field name="reply" type="textarea" rows="3" :label="'Mağaza cevabı (isteğe bağlı)'" :value="$review->reply" maxlength="2000"
                             help="Yazarsanız değerlendirmenin altında “{{ $settings['reviews_store_reply'] }}” olarak görünür; müşteriye yayın ve cevap tek e-postada gider." />
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <label class="flex items-center gap-2 text-sm"><input type="hidden" name="notify" value="0"><input type="checkbox" name="notify" value="1" checked class="size-4 accent-primary">Müşteriye e-posta gönder</label>
                        <button class="btn-primary"><x-icon name="check" class="size-4" />Onayla ve yayınla</button>
                    </div>
                </form>
            @elseif ($review->isPublished())
                <form method="post" action="{{ route('admin.reviews.reply', $review) }}" class="card space-y-4 p-5">
                    @csrf @method('put')
                    <h2 class="font-bold">{{ $settings['reviews_store_reply'] }}</h2>
                    @if ($review->replied_at)<p class="text-xs text-muted">{{ $review->replyAdmin?->name ?? 'Yönetici' }} · {{ $review->replied_at->format('d.m.Y H:i') }}</p>@endif
                    <x-field name="reply" type="textarea" rows="4" label="Cevabınız" :value="$review->reply" maxlength="2000" help="Değerlendirmenin altında görünür. Boş kaydederseniz cevap kaldırılır." />
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <label class="flex items-center gap-2 text-sm"><input type="hidden" name="notify" value="0"><input type="checkbox" name="notify" value="1" @checked(! $review->reply) class="size-4 accent-primary">Müşteriye e-postayla bildir</label>
                        <button class="btn-primary"><x-icon name="mail" class="size-4" />Cevabı kaydet</button>
                    </div>
                </form>
            @endif

            @if ($review->status !== 'rejected')
                <form method="post" action="{{ route('admin.reviews.reject', $review) }}" class="card space-y-4 p-5">
                    @csrf
                    <h2 class="font-bold">{{ $review->status === 'approved' ? 'Yayından kaldır' : 'Reddet' }}</h2>
                    @if ($review->isPendingEdit())<p class="text-sm text-muted">Düzenleme reddedilirse sayfada önceki hâli kalır.</p>@endif
                    <x-field name="reason" label="Neden (isteğe bağlı, müşteriye gider)" maxlength="300" placeholder="Örn. Fotoğraf ürünle ilgili değil." />
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <label class="flex items-center gap-2 text-sm"><input type="hidden" name="notify" value="0"><input type="checkbox" name="notify" value="1" checked class="size-4 accent-primary">Müşteriye “yayınlanamadı” e-postası gönder</label>
                        <button class="btn-ghost text-danger"><x-icon name="x" class="size-4" />{{ $review->status === 'approved' ? 'Yayından kaldır' : 'Reddet' }}</button>
                    </div>
                </form>
            @else
                <form method="post" action="{{ route('admin.reviews.approve', $review) }}" class="card flex flex-wrap items-center justify-between gap-3 p-5">
                    @csrf
                    <input type="hidden" name="notify" value="0">
                    <p class="text-sm text-muted">Reddedildi. Yine de yayınlamak isterseniz:</p>
                    <button class="btn-ghost btn-sm"><x-icon name="check" class="size-4" />E-posta göndermeden yayınla</button>
                </form>
            @endif
        </div>

        <aside class="space-y-4">
            <div class="card space-y-2 p-5 text-sm">
                <h2 class="font-bold">Ürün</h2>
                @if ($review->product)
                    <p class="font-semibold">{{ $review->product->title }}</p>
                    @if ($review->product->publisher)<p class="text-muted">{{ $review->product->publisher->name }}</p>@endif
                    <p class="flex flex-wrap gap-3"><a href="{{ $review->isPublished() ? $review->url() : route('product.show', $review->product) }}" target="_blank" class="font-semibold text-primary hover:underline">Sayfada gör</a>
                        <a href="{{ route('admin.products.edit', $review->product) }}" class="font-semibold text-primary hover:underline">Ürünü düzenle</a></p>
                    <p class="text-muted">Ortalama: {{ $review->product->rating_avg ? number_format($review->product->rating_avg, 1, ',', '') : '—' }} · {{ $review->product->rating_count }} yayında</p>
                @endif
            </div>
            <div class="card space-y-2 p-5 text-sm">
                <h2 class="font-bold">Müşteri</h2>
                <p class="font-semibold">{{ $review->user?->name }} <span class="font-normal text-muted">→ sayfada “{{ $review->authorName() }}”</span></p>
                <p><a href="mailto:{{ $review->user?->email }}" class="text-primary hover:underline">{{ $review->user?->email }}</a></p>
                @if ($review->user)<p><a href="{{ route('admin.customers.show', $review->user) }}" class="font-semibold text-primary hover:underline">Üye hesabını aç</a></p>@endif
                @if ($review->order)<p>Sipariş: <a href="{{ route('admin.orders.show', $review->order) }}" class="font-semibold text-primary hover:underline">{{ $review->order->number }}</a> · {{ $review->order->shortStatus() }}</p>@endif
            </div>
            <div class="card p-5">
                <form method="post" action="{{ route('admin.reviews.destroy', $review) }}" data-confirm="Değerlendirme ve fotoğrafları kalıcı olarak silinsin mi? Müşteriye e-posta gitmez.">
                    @csrf @method('delete')
                    <button class="btn-ghost btn-sm text-danger"><x-icon name="trash" class="size-4" />Kalıcı olarak sil</button>
                </form>
            </div>
            @if ($others->isNotEmpty())
                <div class="card p-5 text-sm">
                    <h2 class="font-bold">Aynı müşterinin diğer değerlendirmeleri</h2>
                    <ul class="mt-2 space-y-2">
                        @foreach ($others as $o)
                            <li><a href="{{ route('admin.reviews.show', $o) }}" class="hover:text-primary">{{ \Illuminate\Support\Str::limit($o->product?->title, 40) }}</a>
                                <span class="block text-xs text-muted">{{ $o->rating }}/5 · {{ $o->statusLabel() }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </aside>
    </div>
</x-layouts.admin>
