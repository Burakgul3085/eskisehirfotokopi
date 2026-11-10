<x-layouts.admin title="Ayarlar · SEO">
    @include('admin.settings._layout')
    <form method="post" action="{{ route('admin.settings.update', $group) }}" class="card max-w-3xl space-y-5 p-6 sm:p-8">
        @csrf @method('put')
        <x-field name="meta_title" label="Ana sayfa başlığı" :value="$s['meta_title']" maxlength="70" help="Google sonuçlarındaki başlık (en fazla 70 karakter). Boşsa site adı kullanılır." />
        <x-field name="meta_description" type="textarea" label="Ana sayfa açıklaması" :value="$s['meta_description']" maxlength="170" help="Google sonuçlarındaki açıklama (en fazla 170 karakter). Boşsa slogan kullanılır." />
        <div class="flex justify-end"><button class="btn-primary">Kaydet</button></div>
    </form>
</x-layouts.admin>
