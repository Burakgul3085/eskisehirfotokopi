<x-layouts.admin title="Klasörden toplu yükleme">
    <x-slot:actions><a href="{{ route('admin.imports.index') }}" class="btn-ghost btn-sm">Geçmiş yüklemeler</a></x-slot:actions>

    @unless ($ocrReady)
        <div class="mb-5 flex gap-3 rounded-xl border border-warning/30 bg-warning-soft p-4 text-sm text-warning">
            <x-icon name="alert" class="size-5 shrink-0" />
            <p>OCR motoru (Tesseract) bu sunucuda bulunamadı. Yükleme yine çalışır; bilgiler yalnızca klasör adından alınır ve tüm kitaplar
                “kontrol gerekli” olarak işaretlenir. Sunucuda: <code class="font-mono">apt install tesseract-ocr tesseract-ocr-tur</code></p>
        </div>
    @endunless

    <div x-data="folderImport" data-store-url="{{ route('admin.imports.store') }}"
         data-upload-url="{{ route('admin.imports.upload', ['batch' => '__BATCH__', 'item' => '__ITEM__']) }}" data-max-mb="{{ $maxMb }}" class="space-y-5">

        <p x-show="hasError" x-text="error" class="rounded-xl bg-danger-soft p-4 text-sm text-danger" x-cloak></p>

        {{-- 1. Klasör seçimi --}}
        <section x-show="isPick">
            <label x-on:dragover="onDragOver" x-on:dragleave="onDragLeave" x-on:drop="onDrop" x-bind:class="dropClass"
                   class="flex cursor-pointer flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed px-6 py-16 text-center transition">
                <span class="flex size-14 items-center justify-center rounded-2xl bg-primary-soft text-primary"><x-icon name="upload" class="size-7" /></span>
                <span class="font-display text-lg font-bold">Kök klasörü buraya sürükleyin</span>
                <span class="max-w-xl text-sm text-muted">veya tıklayıp seçin. Düzen: <b>Kök › Yayınevi klasörü › “YAYINEVİ - KİTAP - YAZAR” › Ekran görüntüsü + ChatGPT görseli</b>.
                    Adı yalnız rakam olan dosyalar (ör. “1.jpg”) yüklenmez. Daha önce eklenen kitaplar atlanır; yarıda kalırsa aynı klasörü yeniden bırakın.</span>
                <input type="file" webkitdirectory directory multiple class="sr-only" x-on:change="onPick">
            </label>
        </section>

        <section x-show="isReading" x-cloak class="card flex items-center gap-3 p-6">
            <span class="size-5 animate-spin rounded-full border-2 border-primary border-t-transparent"></span>
            <span>Klasör okunuyor…</span>
        </section>

        {{-- 2. Özet ve onay --}}
        <section x-show="isReview" x-cloak class="card space-y-5 p-6">
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl bg-surface p-4"><p class="text-xs text-muted">Kitap</p><p class="font-display text-2xl font-bold" x-text="summary.books"></p></div>
                <div class="rounded-xl bg-surface p-4"><p class="text-xs text-muted">Yayınevi klasörü</p><p class="font-display text-2xl font-bold" x-text="summary.publishers"></p></div>
                <div class="rounded-xl bg-surface p-4"><p class="text-xs text-muted">Yok sayılan dosya</p><p class="font-display text-2xl font-bold" x-text="summary.ignored"></p></div>
            </div>
            <ul x-show="hasWarnings" class="space-y-1 rounded-xl bg-warning-soft p-4 text-sm text-warning">
                <li><span x-text="summary.missingInfo"></span> kitapta bilgi görseli yok (klasör adından okunur, kontrole düşer).</li>
                <li><span x-text="summary.missingMockup"></span> kitapta mockup görseli yok.</li>
                <li><span x-text="summary.tooBig"></span> dosya {{ $maxMb }} MB sınırını aştığı için atlanacak.</li>
            </ul>
            <div class="table-panel">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-xs text-muted"><th class="px-4 py-2">Yayınevi klasörü</th><th class="px-4 py-2">Kitap klasörü</th><th class="px-4 py-2">Dosyalar</th></tr></thead>
                    <tbody>
                        <template x-for="row in preview" x-bind:key="row.key">
                            <tr class="border-t border-line"><td class="px-4 py-2 text-muted" x-text="row.publisher"></td><td class="px-4 py-2 font-medium" x-text="row.folder"></td><td class="px-4 py-2 text-xs" x-text="row.files"></td></tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-primary" x-on:click="start"><x-icon name="upload" class="size-4" />Yüklemeyi başlat</button>
                <button type="button" class="btn-ghost" x-on:click="reset">Vazgeç</button>
            </div>
        </section>

        {{-- 3. İlerleme --}}
        <section x-show="isWorking" x-cloak class="card space-y-6 p-6">
            <div>
                <div class="mb-1 flex justify-between text-sm"><span class="font-semibold">Dosyalar gönderiliyor</span><span><span x-text="sent"></span> / <span x-text="total"></span></span></div>
                <div class="h-2.5 overflow-hidden rounded-full bg-surface"><div class="h-full rounded-full bg-primary transition-all" x-bind:style="barStyle"></div></div>
                <p class="mt-1 text-xs text-muted"><span x-text="skipped"></span> kitap daha önce eklendiği için atlandı · <span x-text="uploadErrors"></span> dosya gönderilemedi</p>
            </div>
            <div>
                <div class="mb-1 flex justify-between text-sm"><span class="font-semibold">Okunuyor ve ürüne dönüştürülüyor</span><span><span x-text="processed"></span> / <span x-text="processTotal"></span></span></div>
                <div class="h-2.5 overflow-hidden rounded-full bg-surface"><div class="h-full rounded-full bg-accent transition-all" x-bind:style="processStyle"></div></div>
                <div class="mt-3 grid gap-2 text-sm sm:grid-cols-4">
                    <p class="rounded-lg bg-success-soft px-3 py-2 text-success">Eklendi: <b x-text="counts.imported"></b></p>
                    <p class="rounded-lg bg-warning-soft px-3 py-2 text-warning">Kontrol: <b x-text="counts.review"></b></p>
                    <p class="rounded-lg bg-danger-soft px-3 py-2 text-danger">Hata: <b x-text="counts.failed"></b></p>
                    <p class="rounded-lg bg-info-soft px-3 py-2 text-info">Sırada: <b x-text="counts.queued"></b></p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-ghost" x-show="isUploading" x-on:click="togglePause" x-text="pauseLabel"></button>
                <a x-bind:href="showUrl" class="btn-primary">Ayrıntılar ve kontrol listesi</a>
            </div>
            <p class="text-xs text-muted">Dosyalar gönderilirken sayfayı kapatmayın. Gönderim bittikten sonra okuma arka planda sürer; sayfayı kapatabilirsiniz.</p>
        </section>
    </div>
</x-layouts.admin>
