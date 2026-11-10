@php use App\Models\MessageThread; @endphp
<x-layouts.store :title="$thread->subject" :noindex="true">
    <div class="container-page max-w-3xl py-8 sm:py-12">
        <x-breadcrumb :items="auth('web')->check() ? [['Hesabım', route('account.index')], ['Mesajlarım', route('account.messages')], [$thread->subject, null]] : [['İletişim', route('contact')], [$thread->subject, null]]" />
        <x-flash class="mt-5" />

        <div class="mt-4 flex flex-wrap items-end justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm text-muted">{{ $thread->created_at->locale('tr')->translatedFormat('j F Y, H:i') }}</p>
                <h1 class="text-2xl font-extrabold break-words">{{ $thread->subject }}</h1>
            </div>
            <span class="badge {{ MessageThread::tone($thread->status) }} px-3 py-1 text-sm">{{ $thread->statusLabel() }}</span>
        </div>

        <ol class="mt-6 space-y-4">
            @foreach ($thread->messages as $m)
                <li class="flex {{ $m->fromAdmin() ? 'justify-start' : 'justify-end' }}">
                    <div class="max-w-[85%] rounded-2xl px-5 py-4 {{ $m->fromAdmin() ? 'bg-white shadow-card' : 'bg-primary text-white' }}">
                        <p class="text-xs font-semibold {{ $m->fromAdmin() ? 'text-primary' : 'text-white/80' }}">{{ $m->fromAdmin() ? $settings['site_name'] : $thread->name }} · {{ $m->created_at->format('d.m.Y H:i') }}</p>
                        <p class="mt-1.5 text-sm whitespace-pre-line break-words">{{ $m->body }}</p>
                    </div>
                </li>
            @endforeach
        </ol>

        @if ($thread->status === 'closed')
            <p class="card mt-6 p-5 text-center text-sm text-muted">Bu konu kapatıldı. Yeni bir sorunuz için <a href="{{ route('contact') }}" class="font-semibold text-primary hover:underline">iletişim formunu</a> kullanın.</p>
        @else
            <form method="post" action="{{ route('messages.reply', ['thread' => $thread->id, 'token' => $thread->token]) }}" class="card mt-6 space-y-4 p-5 sm:p-6">
                @csrf
                <x-field name="body" type="textarea" rows="4" label="Yanıtınız" required maxlength="5000" />
                <div class="flex justify-end"><button class="btn-primary">Gönder</button></div>
            </form>
        @endif
    </div>
</x-layouts.store>
