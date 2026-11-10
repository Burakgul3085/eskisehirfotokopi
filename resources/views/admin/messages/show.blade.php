@php
    use App\Models\MessageThread;
    use App\Rules\TurkishPhone;
@endphp
<x-layouts.admin :title="'Mesaj · '.\Illuminate\Support\Str::limit($thread->subject, 40)">
    <x-slot:actions>
        <a href="{{ route('admin.messages.index') }}" class="btn-ghost btn-sm">Tüm mesajlar</a>
    </x-slot:actions>

    <div class="grid gap-6 xl:grid-cols-[1fr_20rem]">
        <div class="min-w-0">
            <div class="card p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <h1 class="text-xl font-bold break-words">{{ $thread->subject }}</h1>
                    <span class="badge {{ MessageThread::tone($thread->status) }}">{{ $thread->statusLabel() }}</span>
                </div>
            </div>

            <ol class="mt-4 space-y-3">
                @foreach ($thread->messages as $m)
                    <li class="card p-5 {{ $m->fromAdmin() ? 'border-l-4 border-primary' : '' }}">
                        <p class="text-xs font-semibold text-muted">{{ $m->fromAdmin() ? ($m->admin?->name ?? 'Yönetici') : $thread->name }} · {{ $m->created_at->format('d.m.Y H:i') }}
                            @if (! $m->fromAdmin() && $m->ip) · <span class="font-normal">{{ $m->ip }}</span>@endif</p>
                        <p class="mt-2 text-sm whitespace-pre-line break-words">{{ $m->body }}</p>
                    </li>
                @endforeach
            </ol>

            <form method="post" action="{{ route('admin.messages.reply', $thread) }}" class="card mt-4 space-y-4 p-5">
                @csrf
                <x-field name="body" type="textarea" rows="5" label="Yanıtınız" required maxlength="5000" help="Yanıt, müşteriye e-postayla konuya dönüş bağlantısıyla gider." />
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="close" value="1" class="size-4 rounded border-line text-primary">Yanıtladıktan sonra konuyu kapat</label>
                    <button class="btn-primary"><x-icon name="mail" class="size-4" />Yanıtla</button>
                </div>
            </form>
        </div>

        <aside class="space-y-4">
            <div class="card space-y-2 p-5 text-sm">
                <h2 class="font-bold">Gönderen</h2>
                <p class="font-semibold">{{ $thread->name }}</p>
                <p><a href="mailto:{{ $thread->email }}" class="text-primary hover:underline">{{ $thread->email }}</a></p>
                @if ($thread->phone)<p><a href="tel:{{ $thread->phone }}" class="hover:underline">{{ TurkishPhone::format($thread->phone) }}</a></p>@endif
                @if ($thread->user)<p><a href="{{ route('admin.customers.show', $thread->user) }}" class="font-semibold text-primary hover:underline">Üye hesabını aç</a></p>@else<p class="text-muted">Üye değil</p>@endif
                @if ($thread->order)<p>Sipariş: <a href="{{ route('admin.orders.show', $thread->order) }}" class="font-semibold text-primary hover:underline">{{ $thread->order->number }}</a></p>@endif
            </div>

            <div class="card flex flex-wrap gap-2 p-5">
                <form method="post" action="{{ route('admin.messages.status', $thread) }}">
                    @csrf @method('put')
                    <input type="hidden" name="status" value="{{ $thread->status === 'closed' ? 'open' : 'closed' }}">
                    <button class="btn-ghost btn-sm">{{ $thread->status === 'closed' ? 'Yeniden aç' : 'Konuyu kapat' }}</button>
                </form>
                <form method="post" action="{{ route('admin.messages.destroy', $thread) }}" data-confirm="Bu mesaj konusu kalıcı olarak silinsin mi?">
                    @csrf @method('delete')
                    <button class="btn-ghost btn-sm text-danger"><x-icon name="trash" class="size-4" />Sil</button>
                </form>
            </div>

            @if ($others->isNotEmpty())
                <div class="card p-5 text-sm">
                    <h2 class="font-bold">Aynı kişinin diğer mesajları</h2>
                    <ul class="mt-2 space-y-2">
                        @foreach ($others as $o)
                            <li><a href="{{ route('admin.messages.show', $o) }}" class="hover:text-primary">{{ \Illuminate\Support\Str::limit($o->subject, 40) }}</a>
                                <span class="block text-xs text-muted">{{ $o->last_message_at?->format('d.m.Y') }} · {{ $o->statusLabel() }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </aside>
    </div>
</x-layouts.admin>
