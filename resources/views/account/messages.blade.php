@php use App\Models\MessageThread; @endphp
<x-account-layout title="Mesajlarım" :text="$settings['account_messages_text']">
    @if ($threads->isNotEmpty())
        <x-slot:actions><a href="{{ route('contact') }}" class="btn-primary"><x-icon name="plus" class="size-4" />Yeni mesaj</a></x-slot:actions>
    @endif

    @if ($threads->isEmpty())
        @include('account._empty', ['icon' => 'chat', 'title' => 'Henüz mesajınız yok.', 'text' => 'Sorunuzu yazın; yanıtımız hem e-postanıza hem buraya gelsin.', 'cta' => ['Bize yazın', route('contact')]])
    @else
        <div class="card divide-y divide-line overflow-hidden">
            @foreach ($threads as $t)
                <a href="{{ $t->url() }}" class="search-hit flex items-center gap-4 px-5 py-4">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-primary-soft text-primary"><x-icon name="chat" class="size-4" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-semibold">{{ $t->subject }}</span>
                        <span class="text-xs text-muted">{{ $t->last_message_at?->locale('tr')->translatedFormat('j F Y, H:i') }} · {{ $t->messages_count }} mesaj</span>
                    </span>
                    <span class="badge {{ MessageThread::tone($t->status) }}">{{ $t->statusLabel() }}</span>
                    <x-icon name="chevron-right" class="size-4 text-slate-300" />
                </a>
            @endforeach
        </div>
        <div class="mt-6">{{ $threads->links() }}</div>
    @endif
</x-account-layout>
