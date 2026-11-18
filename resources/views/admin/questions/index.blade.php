@php
    use App\Http\Controllers\Admin\QuestionController;
@endphp
<x-layouts.admin title="Ürün soruları">
    <x-slot:actions>
        <a href="{{ route('admin.settings.edit', 'degerlendirme') }}" class="btn-ghost btn-sm"><x-icon name="settings" class="size-4" />Ayarlar ve e-postalar</a>
    </x-slot:actions>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div class="flex gap-1 overflow-x-auto rounded-xl bg-white p-1 shadow-card">
            @foreach (QuestionController::TABS as $key => $label)
                <a href="{{ route('admin.questions.index', array_filter(['durum' => $key, 'q' => request('q')])) }}"
                   class="flex shrink-0 items-center gap-1.5 rounded-lg px-3.5 py-2 text-sm font-semibold {{ $status === $key ? 'bg-primary text-white' : 'text-muted hover:bg-surface hover:text-ink' }}">
                    {{ $label }}<span class="text-xs opacity-70">{{ $counts[$key] ?? 0 }}</span>
                </a>
            @endforeach
        </div>
        <form method="get" class="flex flex-1 gap-2 sm:max-w-sm">
            <input type="hidden" name="durum" value="{{ $status }}">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Ürün, ad, e-posta, soru" class="field-input">
            <button class="btn-primary btn-sm shrink-0" aria-label="Ara"><x-icon name="search" class="size-4" /></button>
        </form>
    </div>

    <div class="card overflow-x-auto">
        <table class="table-panel">
            <thead><tr><th>Soru</th><th>Ürün</th><th>Soran</th><th>Tarih</th><th>Durum</th></tr></thead>
            <tbody>
                @forelse ($questions as $q)
                    <tr>
                        <td class="max-w-md"><a href="{{ route('admin.questions.show', $q) }}" class="block font-semibold hover:text-primary">{{ \Illuminate\Support\Str::limit($q->body, 110) }}</a>
                            @if ($q->answer)<span class="mt-0.5 block text-xs text-muted">↳ {{ \Illuminate\Support\Str::limit($q->answer, 90) }}</span>@endif</td>
                        <td class="text-sm">@if ($q->product)<a href="{{ route('product.show', $q->product) }}" target="_blank" class="hover:text-primary">{{ \Illuminate\Support\Str::limit($q->product->title, 50) }}</a>@endif</td>
                        <td class="text-sm">{{ $q->name }}<span class="block text-xs text-muted">{{ $q->email }}</span></td>
                        <td class="text-sm whitespace-nowrap">{{ $q->created_at->format('d.m.Y H:i') }}</td>
                        <td><span class="badge {{ $q->tone() }}">{{ $q->statusLabel() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-10 text-center text-muted">Bu listede soru yok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $questions->links() }}</div>
</x-layouts.admin>
