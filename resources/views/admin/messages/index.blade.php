@php
    use App\Models\MessageThread;
    $tabs = MessageThread::STATUSES + ['tumu' => 'Tümü'];
@endphp
<x-layouts.admin title="Mesajlar">
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div class="flex gap-1 overflow-x-auto rounded-xl bg-white p-1 shadow-card">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('admin.messages.index', array_filter(['durum' => $key, 'q' => request('q')])) }}"
                   class="flex shrink-0 items-center gap-1.5 rounded-lg px-3.5 py-2 text-sm font-semibold {{ $status === $key ? 'bg-primary text-white' : 'text-muted hover:bg-surface hover:text-ink' }}">
                    {{ $label }}<span class="text-xs opacity-70">{{ $key === 'tumu' ? $counts->sum() : ($counts[$key] ?? 0) }}</span>
                </a>
            @endforeach
        </div>
        <form method="get" class="flex flex-1 gap-2 sm:max-w-sm">
            <input type="hidden" name="durum" value="{{ $status }}">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Ad, e-posta, konu" class="field-input">
            <button class="btn-primary btn-sm shrink-0" aria-label="Ara"><x-icon name="search" class="size-4" /></button>
        </form>
    </div>

    <div class="card overflow-x-auto">
        <table class="table-panel">
            <thead><tr><th>Konu</th><th>Gönderen</th><th>Son mesaj</th><th>Durum</th></tr></thead>
            <tbody>
                @forelse ($threads as $t)
                    <tr>
                        <td><a href="{{ route('admin.messages.show', $t) }}" class="flex items-center gap-2 font-semibold hover:text-primary">
                                @if ($t->unread_admin)<span class="size-2 shrink-0 rounded-full bg-accent" title="Okunmadı"></span>@endif
                                <span class="{{ $t->unread_admin ? 'font-bold' : '' }}">{{ \Illuminate\Support\Str::limit($t->subject, 70) }}</span></a>
                            <span class="block text-xs text-muted">{{ $t->messages_count }} mesaj{{ $t->order_id ? ' · siparişli' : '' }}</span></td>
                        <td>{{ $t->name }}<span class="block text-xs text-muted">{{ $t->email }}</span></td>
                        <td class="whitespace-nowrap text-sm">{{ $t->last_message_at?->format('d.m.Y H:i') }}</td>
                        <td><span class="badge {{ MessageThread::tone($t->status) }}">{{ $t->statusLabel() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-10 text-center text-muted">Mesaj yok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $threads->links() }}</div>
</x-layouts.admin>
