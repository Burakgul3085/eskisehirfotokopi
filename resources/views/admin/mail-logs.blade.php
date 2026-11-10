@php use App\Models\MailLog; @endphp
<x-layouts.admin title="E-posta kayıtları">
    <x-slot:actions>
        <a href="{{ route('admin.settings.edit', 'eposta') }}" class="btn-ghost btn-sm"><x-icon name="settings" class="size-4" />E-posta ayarları</a>
    </x-slot:actions>

    @if ($failed)
        <p class="mb-4 flex items-center gap-2 rounded-xl bg-danger-soft px-4 py-3 text-sm text-danger"><x-icon name="alert" class="size-4 shrink-0" />Son 24 saatte {{ $failed }} e-posta gönderilemedi. SMTP ayarlarını ve hata ayrıntısını kontrol edin.</p>
    @endif

    <form method="get" class="card mb-4 flex flex-wrap gap-3 p-4">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Alıcı adresi" class="field-input min-w-48 flex-1">
        <select name="tur" class="field-input w-auto" data-autosubmit>
            <option value="">Tüm türler</option>
            @foreach (MailLog::TYPES as $k => $l)<option value="{{ $k }}" @selected(request('tur') === $k)>{{ $l }}</option>@endforeach
        </select>
        <select name="durum" class="field-input w-auto" data-autosubmit>
            <option value="">Tüm durumlar</option>
            <option value="hata" @selected(request('durum') === 'hata')>Yalnız hatalar</option>
        </select>
        <button class="btn-primary btn-sm" aria-label="Ara"><x-icon name="search" class="size-4" /></button>
    </form>

    <div class="card overflow-x-auto">
        <table class="table-panel">
            <thead><tr><th>Zaman</th><th>Tür</th><th>Alıcı</th><th>Konu</th><th>Durum</th></tr></thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap text-sm">{{ $log->created_at->format('d.m.Y H:i') }}</td>
                        <td class="text-sm">{{ $log->typeLabel() }}</td>
                        <td class="text-sm break-all">{{ $log->to }}</td>
                        <td class="text-sm">{{ $log->subject }}</td>
                        <td>
                            @if ($log->status === 'sent')<span class="badge bg-success-soft text-success">Gönderildi</span>
                            @else<span class="badge bg-danger-soft text-danger">Hata</span><span class="mt-1 block max-w-xs text-xs text-danger">{{ $log->error }}</span>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-10 text-center text-muted">Kayıt yok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $logs->links() }}</div>
</x-layouts.admin>
