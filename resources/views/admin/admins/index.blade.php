<x-layouts.admin title="Yöneticiler">
    <x-slot:actions><a href="{{ route('admin.admins.create') }}" class="btn-primary btn-sm"><x-icon name="plus" class="size-4" />Yönetici ekle</a></x-slot:actions>
    <div class="card overflow-x-auto">
        <table class="table-panel">
            <thead><tr><th>Ad</th><th>E-posta</th><th>Rol</th><th>Durum</th><th>Son giriş</th><th></th></tr></thead>
            <tbody>
                @foreach ($admins as $a)
                    <tr>
                        <td class="font-semibold">{{ $a->name }}</td>
                        <td>{{ $a->email }}</td>
                        <td>{{ $a->roleLabel() }}</td>
                        <td>
                            @if ($a->is_active)<span class="badge bg-success-soft text-success">Etkin</span>@else<span class="badge bg-slate-100 text-muted">Pasif</span>@endif
                            @if ($a->isLocked())<span class="badge bg-warning-soft text-warning">Kilitli</span>@endif
                        </td>
                        <td class="text-muted">{{ $a->last_login_at?->format('d.m.Y H:i') ?? '—' }}</td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('admin.admins.edit', $a) }}" class="btn-ghost btn-sm"><x-icon name="edit" class="size-3.5" />Düzenle</a>
                            @unless ($a->is(auth('admin')->user()))
                                <form method="post" action="{{ route('admin.admins.destroy', $a) }}" class="inline" data-confirm="{{ $a->name }} silinsin mi?">
                                    @csrf @method('delete')
                                    <button class="btn-ghost btn-sm text-danger"><x-icon name="trash" class="size-3.5" /></button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.admin>
