<x-layouts.admin :title="$admin->exists ? 'Yönetici düzenle' : 'Yönetici ekle'">
    <form method="post" action="{{ $admin->exists ? route('admin.admins.update', $admin) : route('admin.admins.store') }}" class="card max-w-2xl space-y-5 p-6 sm:p-8">
        @csrf
        @if ($admin->exists) @method('put') @endif
        <x-field name="name" label="Ad soyad" :value="$admin->name" required />
        <x-field name="email" type="email" label="E-posta" :value="$admin->email" required autocomplete="off" />
        <div>
            <label class="field-label" for="f_role">Rol</label>
            <select id="f_role" name="role" class="field-input">
                @foreach (\App\Models\Admin::ROLES as $v => $l)
                    <option value="{{ $v }}" @selected(old('role', $admin->role) === $v)>{{ $l }}</option>
                @endforeach
            </select>
            <p class="field-help">Süper yönetici, yönetici hesaplarını ve işlem günlüğünü de yönetir.</p>
            @error('role')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <x-password-field label="Parola" autocomplete="new-password" :required="! $admin->exists"
                :help="$admin->exists ? 'Değiştirmek istemiyorsanız boş bırakın.' : 'En az 10 karakter; harf ve rakam.'" />
            <x-password-field name="password_confirmation" label="Parola (tekrar)" autocomplete="new-password" :required="! $admin->exists" />
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $admin->is_active)) class="size-4 accent-primary"> Hesap etkin</label>
        <div class="flex justify-end gap-2">
            <a href="{{ route('admin.admins.index') }}" class="btn-ghost">Vazgeç</a>
            <button class="btn-primary">Kaydet</button>
        </div>
    </form>
</x-layouts.admin>
