/**
 * Toplu klasör yükleme (yönetim › Toplu yükleme). Düzen:
 *   KÖK / "X YAYINLARI EKRAN GÖRÜNTÜLERİ" / "YAYINEVİ - KİTAP ADI - YAZAR" / "Ekran görüntüsü….png" + "ChatGPT Görseli….png"
 * Görsel içeren her klasör bir kitaptır; üst klasörü yayınevi klasörüdür. "1.jpg" gibi adı yalnız rakam olan dosya
 * yüklenmez (K5). Sınıflandırma App\Domain\Import\BookFiles ile aynıdır.
 *
 * Akış: klasör tarayıcıda okunur ve özetlenir → onayla kitap listesi tek istekle gönderilir (daha önce eklenenler
 * sunucuda atlanır; yarıda kalan yükleme aynı klasör yeniden bırakılarak sürer) → dosyalar 6 paralel istekle yüklenir
 * (mockup tarayıcıda küçültülür; bilgi görseli OCR doğruluğu için özgün haliyle gider) → işlem durumu izlenir.
 */

const IMAGE_EXT = ['png', 'jpg', 'jpeg', 'webp'];
const INFO = /ekran\s*g[öo]r[üu]nt|screenshot|ekran al[ıi]nt|(^|[^\p{L}])bilgi([^\p{L}]|$)/u;
const MOCKUP = /chatgpt|mock\s*-?\s*up|mockup/u;
const COVER = /(^|[^\p{L}])kap(ak)?([^\p{L}]|$)/u;
const IMPRINT = /bas[ıi]m|k[üu]nye/u;
const IGNORE = /bas[ıi]m|[iı]?çindekiler|(^|[^\p{L}])[iı]?çin([^\p{L}]|$)|k[üu]nye/u;
const HIDDEN = /^(\.|~\$)|^(thumbs\.db|desktop\.ini)$/i;
const PARALLEL = 6;
const MOCKUP_EDGE = 1800;
const SHOWN = 8;

const extOf = (name) => (name.includes('.') ? name.split('.').pop().toLowerCase() : '');
// Türkçe küçük harf: "İ" → "i", "I" → "ı" (toLowerCase "İ"yi "i̇" yapar ve kalıplar kaçar).
const baseOf = (name) => name.replace(/\.[^.]+$/, '').toLocaleLowerCase('tr');
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/**
 * Klasördeki dosyalardan bilgi ve ürün görselini seçer (App\Domain\Import\BookFiles ile aynı kural):
 * mockup → kapak → adı tanınmayan tek görsel; "1.jpg" (K5), künye ve içindekiler sayfaları yok sayılır.
 */
export function classify(files) {
    let info = null;
    let mockup = null;
    let cover = null;
    const others = [];
    for (const f of files) {
        const base = baseOf(f.name);
        if (/^\d+$/.test(base)) {
            continue;
        }
        if (!info && INFO.test(base)) {
            info = f;
        } else if (!mockup && MOCKUP.test(base)) {
            mockup = f;
        } else if (IGNORE.test(base)) {
            continue;
        } else if (!cover && COVER.test(base)) {
            cover = f;
        } else {
            others.push(f);
        }
    }
    if (!mockup) {
        mockup = cover || (others.length === 1 ? others[0] : null);
    }
    // Okunacak ek sayfalar (BookFiles::pages): kapak (yazar, özellikler) ve künye (baskı yılı, ISBN).
    const imprint = files.find((f) => IMPRINT.test(baseOf(f.name))) || null;

    return { info, mockup, cover, imprint };
}

/** [{path, file}] listesinden kitapları çıkarır. */
export function scan(entries, maxBytes) {
    const folders = new Map();
    let ignored = 0;
    for (const { path, file } of entries) {
        const segs = path.split('/').filter(Boolean);
        const name = segs.pop();
        if (!name || HIDDEN.test(name) || segs.some((s) => s.startsWith('.'))) {
            continue;
        }
        if (!IMAGE_EXT.includes(extOf(name)) || segs.length === 0) {
            ignored++;
            continue;
        }
        const key = segs.join('/');
        if (!folders.has(key)) {
            folders.set(key, { segs, files: [] });
        }
        folders.get(key).files.push(file);
    }

    const books = [];
    const publishers = new Set();
    let tooBig = 0;
    for (const { segs, files } of folders.values()) {
        const { info, mockup, cover, imprint } = classify(files);
        const book = {
            publisher_folder: segs.length >= 2 ? segs[segs.length - 2] : null,
            folder: segs[segs.length - 1],
            infoFile: info && info.size <= maxBytes ? info : null,
            mockupFile: mockup && mockup.size <= maxBytes ? mockup : null,
            coverFile: cover && cover.size <= maxBytes ? cover : null,
            imprintFile: imprint && imprint.size <= maxBytes ? imprint : null,
        };
        tooBig += (info && !book.infoFile ? 1 : 0) + (mockup && !book.mockupFile ? 1 : 0);
        books.push(book);
        if (book.publisher_folder) {
            publishers.add(book.publisher_folder);
        }
    }
    const collator = new Intl.Collator('tr', { numeric: true, sensitivity: 'base' });
    books.sort((a, b) => collator.compare((a.publisher_folder || '') + '/' + a.folder, (b.publisher_folder || '') + '/' + b.folder));

    return { books, publishers: publishers.size, ignored, tooBig };
}

/** Sürükle-bırak girdilerini dolaşır. */
async function readDropped(items) {
    const out = [];
    const readAll = (dir) => new Promise((resolve, reject) => {
        const reader = dir.createReader();
        const all = [];
        const next = () => reader.readEntries((batch) => {
            if (batch.length === 0) {
                resolve(all);
            } else {
                all.push(...batch);
                next();
            }
        }, reject);
        next();
    });
    const walk = async (entry) => {
        if (entry.isFile) {
            const file = await new Promise((resolve, reject) => entry.file(resolve, reject));
            out.push({ path: entry.fullPath.replace(/^\/+/, ''), file });
        } else if (entry.isDirectory) {
            for (const child of await readAll(entry)) {
                await walk(child);
            }
        }
    };
    for (const entry of items) {
        await walk(entry);
    }

    return out;
}

/** Mockup'ı yüklemeden önce küçültür (sunucu en fazla 1400 px saklar; büyük PNG göndermek yalnız zaman kaybı). */
async function shrink(file) {
    let bitmap;
    try {
        bitmap = await createImageBitmap(file);
    } catch (e) {
        return file;
    }
    try {
        const edge = Math.max(bitmap.width, bitmap.height);
        if (edge <= MOCKUP_EDGE && file.size <= 1.5 * 1024 * 1024) {
            return file;
        }
        const scale = Math.min(1, MOCKUP_EDGE / edge);
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(bitmap.width * scale));
        canvas.height = Math.max(1, Math.round(bitmap.height * scale));
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.9));

        return blob ? new File([blob], 'mockup.jpg', { type: 'image/jpeg' }) : file;
    } finally {
        bitmap.close();
    }
}

/** Alpine bileşeni (CSP). Ayarlar kök öğenin data-* özniteliklerinden okunur. */
export function folderImport() {
    let books = [];
    let tasks = [];
    let rootName = '';
    let urls = {};
    let pollTimer = null;

    return {
        phase: 'pick',
        dragging: false,
        error: '',
        paused: false,
        summary: { books: 0, publishers: 0, missingInfo: 0, missingMockup: 0, ignored: 0, tooBig: 0 },
        preview: [],
        sent: 0,
        total: 0,
        uploadErrors: 0,
        skipped: 0,
        counts: { imported: 0, review: 0, failed: 0, queued: 0, processing: 0, waiting_files: 0 },
        showUrl: '',

        init() {
            urls = { store: this.$el.dataset.storeUrl, upload: this.$el.dataset.uploadUrl };
            this.maxBytes = Number(this.$el.dataset.maxMb || 15) * 1024 * 1024;
            window.addEventListener('beforeunload', (e) => {
                if (this.phase === 'uploading') {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });
        },

        // Görünüm yardımcıları
        isPick() { return this.phase === 'pick'; },
        isReading() { return this.phase === 'reading'; },
        isReview() { return this.phase === 'review'; },
        isUploading() { return this.phase === 'uploading'; },
        isDone() { return this.phase === 'done'; },
        isWorking() { return this.phase === 'uploading' || this.phase === 'done'; },
        hasError() { return this.error !== ''; },
        hasWarnings() { return this.summary.missingInfo + this.summary.missingMockup + this.summary.tooBig > 0; },
        dropClass() { return this.dragging ? 'border-primary bg-primary-soft' : 'border-line bg-white'; },
        percent() { return this.total ? Math.round((this.sent / this.total) * 100) : 0; },
        barStyle() { return 'width: ' + this.percent() + '%'; },
        processed() { return this.counts.imported + this.counts.review + this.counts.failed; },
        processTotal() { return Object.values(this.counts).reduce((a, b) => a + b, 0) - (this.counts.skipped || 0); },
        processPercent() { const t = this.processTotal(); return t ? Math.round((this.processed() / t) * 100) : 0; },
        processStyle() { return 'width: ' + this.processPercent() + '%'; },
        pauseLabel() { return this.paused ? 'Sürdür' : 'Duraklat'; },
        fmt(n) { return Number(n).toLocaleString('tr-TR'); },

        onDragOver(e) {
            e.preventDefault();
            this.dragging = true;
        },
        onDragLeave() {
            this.dragging = false;
        },
        async onDrop(e) {
            e.preventDefault();
            this.dragging = false;
            const items = [...(e.dataTransfer?.items || [])].map((i) => i.webkitGetAsEntry && i.webkitGetAsEntry()).filter(Boolean);
            if (!items.length) {
                return;
            }
            rootName = items.length === 1 ? items[0].name : items.length + ' klasör';
            this.phase = 'reading';
            try {
                this.analyse(await readDropped(items));
            } catch (err) {
                this.fail('Klasör okunamadı: ' + err.message);
            }
        },
        onPick(e) {
            const files = [...(e.target.files || [])];
            if (!files.length) {
                return;
            }
            rootName = (files[0].webkitRelativePath || '').split('/')[0] || 'Klasör';
            this.phase = 'reading';
            this.analyse(files.map((file) => ({ path: file.webkitRelativePath || file.name, file })));
            e.target.value = '';
        },

        analyse(entries) {
            if (!rootName && entries.length) {
                rootName = entries[0].path.split('/')[0] || 'Klasör';
            }
            const result = scan(entries, this.maxBytes);
            books = result.books;
            if (!books.length) {
                this.fail('Klasörde kitap bulunamadı. Düzen: Kök / Yayınevi / Kitap klasörü / görseller.');

                return;
            }
            this.summary = {
                books: books.length,
                publishers: result.publishers,
                missingInfo: books.filter((b) => !b.infoFile).length,
                missingMockup: books.filter((b) => !b.mockupFile).length,
                ignored: result.ignored,
                tooBig: result.tooBig,
            };
            this.preview = books.slice(0, SHOWN).map((b, i) => ({
                key: i,
                publisher: b.publisher_folder || '—',
                folder: b.folder,
                files: [b.infoFile ? 'bilgi ✓' : 'bilgi yok', b.mockupFile ? 'mockup ✓' : 'mockup yok'].join(' · '),
            }));
            this.error = '';
            this.phase = 'review';
        },

        fail(message) {
            this.error = message;
            this.phase = 'pick';
        },
        reset() {
            books = [];
            tasks = [];
            this.error = '';
            this.phase = 'pick';
        },
        togglePause() {
            this.paused = !this.paused;
        },

        async start() {
            this.phase = 'uploading';
            this.error = '';
            let res;
            try {
                res = await this.request(urls.store, JSON.stringify({
                    name: rootName,
                    books: books.map((b) => ({
                        publisher_folder: b.publisher_folder,
                        folder: b.folder,
                        info: b.infoFile ? b.infoFile.name : null,
                        mockup: b.mockupFile ? b.mockupFile.name : null,
                        cover: b.coverFile ? b.coverFile.name : null,
                        imprint: b.imprintFile ? b.imprintFile.name : null,
                    })),
                }), { 'Content-Type': 'application/json' });
            } catch (err) {
                this.fail('Yükleme başlatılamadı: ' + err.message);

                return;
            }
            this.showUrl = res.show_url;
            urls.status = res.status_url;
            tasks = [];
            this.skipped = 0;
            res.items.forEach((id, i) => {
                if (id === null) {
                    this.skipped++;

                    return;
                }
                const url = urls.upload.replace('__BATCH__', res.batch).replace('__ITEM__', id);
                if (books[i].infoFile) {
                    tasks.push({ url, role: 'info', file: books[i].infoFile });
                }
                if (books[i].mockupFile) {
                    tasks.push({ url, role: 'mockup', file: books[i].mockupFile });
                }
                if (books[i].coverFile) {
                    tasks.push({ url, role: 'cover', file: books[i].coverFile });
                }
                if (books[i].imprintFile) {
                    tasks.push({ url, role: 'imprint', file: books[i].imprintFile });
                }
            });
            this.total = tasks.length;
            this.sent = 0;
            this.poll();

            let next = 0;
            const worker = async () => {
                while (next < tasks.length) {
                    const task = tasks[next++];
                    while (this.paused) {
                        await sleep(500);
                    }
                    await this.send(task);
                    this.sent++;
                }
            };
            await Promise.all(Array.from({ length: PARALLEL }, worker));
            books = [];
            tasks = [];
            this.phase = 'done';
        },

        async send(task) {
            for (let attempt = 1; attempt <= 4; attempt++) {
                try {
                    const body = new FormData();
                    body.append('role', task.role);
                    body.append('file', task.role === 'mockup' ? await shrink(task.file) : task.file);
                    await this.request(task.url, body);

                    return;
                } catch (err) {
                    if (err.status && err.status !== 429 && err.status < 500) {
                        break; // doğrulama hatası: tekrar denemenin yararı yok
                    }
                    await sleep(1000 * attempt * attempt);
                }
            }
            this.uploadErrors++;
        },

        async request(url, body, headers = {}) {
            const response = await fetch(url, {
                method: 'POST',
                body,
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, ...headers },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                let message = 'Sunucu hatası (' + response.status + ')';
                try {
                    const data = await response.json();
                    message = data.message || message;
                } catch (e) { /* JSON değil */ }
                const error = new Error(message);
                error.status = response.status;
                throw error;
            }

            return response.json();
        },

        async poll() {
            clearTimeout(pollTimer);
            try {
                const data = await (await fetch(urls.status, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })).json();
                this.counts = { ...this.counts, ...data.counts };
                if (data.status === 'done' && this.phase === 'done') {
                    return;
                }
            } catch (e) { /* geçici ağ hatası: sonraki turda */ }
            pollTimer = setTimeout(() => this.poll(), 3000);
        },
    };
}
