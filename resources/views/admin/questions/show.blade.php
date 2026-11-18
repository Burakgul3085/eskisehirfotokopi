@php
    /** @var \App\Models\ProductQuestion $question */
    $answered = $question->isAnswered();
@endphp
<x-layouts.admin :title="'Soru · '.\Illuminate\Support\Str::limit($question->product?->title, 40)">
    <x-slot:actions>
        <a href="{{ route('admin.questions.index') }}" class="btn-ghost btn-sm">Tüm sorular</a>
    </x-slot:actions>

    <div class="grid gap-6 xl:grid-cols-[1fr_20rem]">
        <div class="min-w-0 space-y-4">
            <div class="card p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <p class="text-xs font-semibold text-muted">{{ $question->name }} · {{ $question->created_at->format('d.m.Y H:i') }}@if ($question->ip) · <span class="font-normal">{{ $question->ip }}</span>@endif</p>
                    <span class="badge {{ $question->tone() }}">{{ $question->statusLabel() }}</span>
                </div>
                <p class="mt-2 text-base whitespace-pre-line break-words">{{ $question->body }}</p>
            </div>

            <form method="post" action="{{ route('admin.questions.answer', $question) }}" class="card space-y-4 p-5 {{ $answered ? '' : 'border-l-4 border-primary' }}">
                @csrf @method('put')
                <h2 class="font-bold">{{ $answered ? 'Cevabı düzenle' : 'Cevapla' }}</h2>
                @if ($answered)<p class="text-xs text-muted">{{ $question->answerer?->name ?? 'Yönetici' }} · {{ $question->answered_at->format('d.m.Y H:i') }}</p>@endif
                <x-field name="answer" type="textarea" rows="5" label="Cevabınız" :value="$question->answer" required maxlength="3000"
                         :help="$answered ? 'Cevabı değiştirince soran kişiye yalnız aşağıdaki kutuyu işaretlerseniz e-posta gider.' : 'Cevap, soran kişiye e-postayla gider.'" />
                @if ($answered)
                    <label class="flex items-center gap-2 text-sm"><input type="hidden" name="notify" value="0"><input type="checkbox" name="notify" value="1" class="size-4 accent-primary">Müşteriye yeniden bildir</label>
                @endif
                <div class="flex flex-wrap justify-end gap-2">
                    <button name="publish" value="0" class="btn-ghost"><x-icon name="mail" class="size-4" />{{ $answered ? 'Kaydet · yalnız e-posta' : 'Yalnız e-postayla cevapla' }}</button>
                    <button name="publish" value="1" class="btn-primary"><x-icon name="check" class="size-4" />{{ $answered ? 'Kaydet · sayfada yayınla' : 'Cevapla ve yayınla' }}</button>
                </div>
                <p class="text-xs text-muted">“Yayınla” seçilirse soru ve cevap ürün sayfasında, soranın adı kısaltılarak (“{{ $question->authorName() }}”) görünür. Kişisel sorular (sipariş durumu, adres vb.) için “Yalnız e-posta”yı seçin.</p>
            </form>
        </div>

        <aside class="space-y-4">
            <div class="card space-y-2 p-5 text-sm">
                <h2 class="font-bold">Ürün</h2>
                @if ($question->product)
                    <p class="font-semibold">{{ $question->product->title }}</p>
                    <p class="flex flex-wrap gap-3"><a href="{{ $question->is_public && $answered ? $question->url() : route('product.show', $question->product) }}" target="_blank" class="font-semibold text-primary hover:underline">Sayfada gör</a>
                        <a href="{{ route('admin.products.edit', $question->product) }}" class="font-semibold text-primary hover:underline">Ürünü düzenle</a></p>
                @endif
            </div>
            <div class="card space-y-2 p-5 text-sm">
                <h2 class="font-bold">Soran</h2>
                <p class="font-semibold">{{ $question->name }}</p>
                <p><a href="mailto:{{ $question->email }}" class="text-primary hover:underline">{{ $question->email }}</a></p>
                @if ($question->user)<p><a href="{{ route('admin.customers.show', $question->user) }}" class="font-semibold text-primary hover:underline">Üye hesabını aç</a></p>@else<p class="text-muted">Üye değil</p>@endif
            </div>
            <div class="card flex flex-wrap gap-2 p-5">
                @if ($answered)
                    <form method="post" action="{{ route('admin.questions.visibility', $question) }}">
                        @csrf @method('put')
                        <input type="hidden" name="public" value="{{ $question->is_public ? '0' : '1' }}">
                        <button class="btn-ghost btn-sm">{{ $question->is_public ? 'Sayfadan kaldır' : 'Sayfada yayınla' }}</button>
                    </form>
                @endif
                <form method="post" action="{{ route('admin.questions.destroy', $question) }}" data-confirm="Soru kalıcı olarak silinsin mi? Soran kişiye e-posta gitmez.">
                    @csrf @method('delete')
                    <button class="btn-ghost btn-sm text-danger"><x-icon name="trash" class="size-4" />Sil (spam / uygunsuz)</button>
                </form>
            </div>
            @if ($others->isNotEmpty())
                <div class="card p-5 text-sm">
                    <h2 class="font-bold">Bu ürünün diğer soruları</h2>
                    <ul class="mt-2 space-y-2">
                        @foreach ($others as $o)
                            <li><a href="{{ route('admin.questions.show', $o) }}" class="hover:text-primary">{{ \Illuminate\Support\Str::limit($o->body, 60) }}</a>
                                <span class="block text-xs text-muted">{{ $o->statusLabel() }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </aside>
    </div>
</x-layouts.admin>
