@php use App\Models\MessageThread; @endphp
<x-layouts.store title="Mesajlarım" :noindex="true">
    <div class="container-page py-10">
        <h1 class="text-3xl font-extrabold">Mesajlarım</h1>
        @include('account._nav')
        <x-flash class="mt-6" />

        @if ($threads->isEmpty())
            <div class="card mt-6 px-6 py-14 text-center">
                <x-icon name="chat" class="mx-auto size-10 text-slate-300" />
                <p class="mt-3 font-semibold">Henüz mesajınız yok.</p>
                <a href="{{ route('contact') }}" class="btn-primary mt-5">Bize yazın</a>
            </div>
        @else
            <div class="mt-6 flex justify-end"><a href="{{ route('contact') }}" class="btn-ghost btn-sm"><x-icon name="plus" class="size-4" />Yeni mesaj</a></div>
            <div class="card mt-3 divide-y divide-line">
                @foreach ($threads as $t)
                    <a href="{{ $t->url() }}" class="flex items-center gap-4 px-5 py-4 hover:bg-surface">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-semibold">{{ $t->subject }}</span>
                            <span class="text-xs text-muted">{{ $t->last_message_at?->format('d.m.Y H:i') }} · {{ $t->messages_count }} mesaj</span>
                        </span>
                        <span class="badge {{ MessageThread::tone($t->status) }}">{{ $t->statusLabel() }}</span>
                    </a>
                @endforeach
            </div>
            <div class="mt-6">{{ $threads->links() }}</div>
        @endif
    </div>
</x-layouts.store>
