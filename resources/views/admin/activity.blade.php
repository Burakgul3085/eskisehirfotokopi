<x-layouts.admin title="İşlem günlüğü">
    <div class="card overflow-x-auto">
        <table class="table-panel">
            <thead><tr><th>Zaman</th><th>Yönetici</th><th>İşlem</th><th>Ayrıntı</th><th>IP</th></tr></thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap text-muted">{{ $log->created_at->format('d.m.Y H:i') }}</td>
                        <td class="font-semibold">{{ $log->admin?->name ?? 'Sistem' }}</td>
                        <td>{{ __('activity.'.$log->action) }}</td>
                        <td class="max-w-md truncate font-mono text-xs text-muted">{{ $log->changes ? json_encode($log->changes, JSON_UNESCAPED_UNICODE) : '' }}</td>
                        <td class="text-muted">{{ $log->ip }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-muted">Kayıt yok.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $logs->links() }}</div>
</x-layouts.admin>
