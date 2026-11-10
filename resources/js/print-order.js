/**
 * Fotokopi sayfası (B2B'den uyarlandı, Alpine CSP sürümüne göre). Belgeler parça parça yüklenir; sunucu sayfa sayısını
 * çıkarınca seçenekler açılır. Fiyat her değişiklikte sunucudan alınır (tarayıcı fiyat hesaplamaz). Yarım kalan yükleme
 * 409 yanıtındaki "received" değerinden sürer.
 *
 * CSP sürümünde şablonda argümanlı çağrı ve nesne literali kullanılmadığı için her belgenin görünür alanları decorate()
 * ile önceden hesaplanır; düğmeler hangi belgeye ve seçeneğe ait olduklarını data-uid / data-key / data-value ile bildirir.
 * Yapılandırma sayfadaki <script type="application/json" data-print-config> içinden okunur.
 */

const ON = 'border-primary bg-primary-soft text-primary-dark ring-2 ring-primary/15';
const OFF = 'border-line bg-white text-ink hover:border-primary/50';
const CHOICES = {
    color: ['bw', 'color'],
    duplex: ['0', '1'],
    layout: ['1', '2', '4', '8'],
    size: ['A4', 'A3'],
    binding: ['none', 'spiral'],
};

function sizeText(bytes) {
    return bytes >= 1048576 ? (bytes / 1048576).toFixed(1).replace('.', ',') + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
}

function extClass(name) {
    const ext = (name.split('.').pop() || '').toLowerCase();
    if (ext === 'pdf') {
        return 'bg-red-50 text-red-600 ring-red-200';
    }
    if (['doc', 'docx', 'odt'].includes(ext)) {
        return 'bg-blue-50 text-blue-700 ring-blue-200';
    }
    if (['ppt', 'pptx', 'odp'].includes(ext)) {
        return 'bg-orange-50 text-orange-700 ring-orange-200';
    }
    if (['xls', 'xlsx', 'ods'].includes(ext)) {
        return 'bg-emerald-50 text-emerald-700 ring-emerald-200';
    }
    return 'bg-violet-50 text-violet-700 ring-violet-200';
}

export function printOrder() {
    let config = { urls: {}, files: [], maxBytes: 0, maxFiles: 20, maxCopies: 100, colors: ['bw'], cartUrl: '#' };
    let uid = 0;
    const csrf = () => document.querySelector('meta[name="csrf-token"]').content;
    const fileUrl = (token, suffix) => config.urls.file.replace('__T__', token) + (suffix || '');
    const defaults = () => ({ color: config.colors[0] || 'bw', duplex: '0', layout: '1', size: 'A4', range: '', copies: 1, binding: 'none', note: '' });

    return {
        files: [],
        consent: false,
        dragging: false,
        message: '',
        hasFiles: false,
        hasMessage: false,
        addedCount: 0,
        hasAdded: false,
        cartUrl: '#',

        init() {
            const node = this.$el.querySelector('[data-print-config]');
            config = Object.assign(config, JSON.parse(node.textContent));
            this.cartUrl = config.cartUrl;
            this.files = config.files.map((f) => this.prepare(f));
            this.files.forEach((f) => {
                if (f.status === 'processing') {
                    this.poll(f);
                } else if (f.status === 'ready') {
                    this.quote(f);
                }
            });
            this.refresh();
        },

        prepare(f) {
            const file = Object.assign(
                { uid: ++uid, progress: 100, total: null, summary: '', lines: [], special: null, busy: false, added: false, opts: defaults(), timer: null, cls: {} },
                f,
                { error: f.error || '' },
            );
            this.decorate(file);
            return file;
        },

        /** Şablonda kullanılan, belgeye ait görünür alanlar. */
        decorate(f) {
            f.isUploading = f.status === 'uploading';
            f.isProcessing = f.status === 'processing';
            f.isBusy = f.isUploading || f.isProcessing;
            f.isReady = f.status === 'ready';
            f.isFailed = f.status === 'failed';
            f.statusText = f.isUploading ? 'Yükleniyor… %' + f.progress : f.isProcessing ? 'Sayfalar sayılıyor…' : f.isReady ? f.pages + ' sayfa' : 'Yüklenemedi';
            f.sizeText = sizeText(f.size);
            f.progressStyle = 'width: ' + f.progress + '%';
            f.extLabel = (f.name.split('.').pop() || '').toUpperCase().slice(0, 4);
            f.extClass = extClass(f.name);
            f.hasTotal = f.total !== null;
            f.hasSpecial = f.special !== null;
            f.specialText = f.special ? f.special.text : '';
            f.hasError = f.error !== '';
            f.canAdd = f.isReady && f.total !== null && !f.busy && !f.added;
            f.cannotAdd = !f.canAdd;
            f.addLabel = f.added ? 'Sepete eklendi' : f.busy ? 'Ekleniyor…' : 'Sepete ekle';
            f.lineItems = (f.lines || []).map((l, i) => ({ key: i, label: l[0], amount: l[1] }));
            const cls = {};
            Object.keys(CHOICES).forEach((key) => {
                CHOICES[key].forEach((value) => {
                    cls[key + '_' + value] = String(f.opts[key]) === value ? ON : OFF;
                });
            });
            f.cls = cls;
        },

        refresh() {
            this.hasFiles = this.files.length > 0;
            this.hasMessage = this.message !== '';
            this.addedCount = this.files.filter((f) => f.added).length;
            this.hasAdded = this.addedCount > 0;
        },

        /** Olayın geldiği satırdaki belge. */
        fileOf(event) {
            const row = event.currentTarget.closest('[data-uid]');
            return row ? this.files.find((f) => String(f.uid) === row.dataset.uid) : null;
        },

        setMessage(text) {
            this.message = text;
            this.refresh();
        },

        pick(event) {
            this.addFiles(event.target.files);
            event.target.value = '';
        },
        drop(event) {
            event.preventDefault();
            this.dragging = false;
            this.addFiles(event.dataTransfer.files);
        },
        dragOver(event) {
            event.preventDefault();
            this.dragging = true;
        },
        dragLeave() {
            this.dragging = false;
        },
        dropClass() {
            return this.dragging ? 'border-primary bg-primary-soft' : 'border-line bg-white';
        },

        addFiles(list) {
            this.setMessage('');
            if (!this.consent) {
                this.setMessage('Yüklemeden önce belgeyi çoğaltma hakkınız olduğunu onaylayın.');
                return;
            }
            Array.from(list).forEach((file) => {
                if (this.files.length >= config.maxFiles) {
                    this.setMessage('En fazla ' + config.maxFiles + ' belge yükleyebilirsiniz.');
                } else if (file.size > config.maxBytes) {
                    this.setMessage(file.name + ': dosya ' + Math.round(config.maxBytes / 1048576) + ' MB sınırını aşıyor.');
                } else {
                    this.upload(file);
                }
            });
        },

        async request(url, method, body) {
            const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' };
            if (body && !(body instanceof FormData)) {
                headers['Content-Type'] = 'application/json';
                body = JSON.stringify(body);
            }
            const res = await window.fetch(url, { method, headers, body, credentials: 'same-origin' });
            const data = await res.json().catch(() => ({}));
            return { status: res.status, ok: res.ok, data };
        },

        async upload(source) {
            this.files.push(this.prepare({ token: null, name: source.name, size: source.size, received: 0, status: 'uploading', pages: null, preview: null, progress: 0 }));
            this.refresh();
            const f = this.files[this.files.length - 1];
            try {
                const started = await this.request(config.urls.start, 'POST', { name: source.name, size: source.size, consent: this.consent ? 1 : 0 });
                if (!started.ok) {
                    throw new Error(started.data.message || 'Yükleme başlatılamadı.');
                }
                f.token = started.data.token;
                const chunk = started.data.chunk;
                let offset = 0;
                let retries = 0;
                while (offset < source.size) {
                    const form = new FormData();
                    form.append('offset', offset);
                    form.append('chunk', source.slice(offset, offset + chunk), 'parca');
                    let res;
                    try {
                        res = await this.request(fileUrl(f.token, '/parca'), 'POST', form);
                    } catch (e) {
                        res = { status: 0, ok: false, data: {} };
                    }
                    if (res.ok) {
                        offset = res.data.received;
                        retries = 0;
                        f.status = res.data.status;
                        f.error = res.data.error || '';
                        f.progress = Math.floor((offset / source.size) * 100);
                    } else if (res.status === 409 && typeof res.data.received === 'number') {
                        offset = res.data.received;
                    } else if ((res.status === 0 || res.status >= 500) && retries < 5) {
                        retries++;
                        await new Promise((r) => setTimeout(r, 2000 * retries));
                    } else {
                        throw new Error(res.data.message || 'Yükleme yarıda kaldı.');
                    }
                    this.decorate(f);
                }
                f.progress = 100;
                this.decorate(f);
                if (f.status === 'ready') {
                    this.quote(f);
                } else if (f.status === 'processing') {
                    this.poll(f);
                }
            } catch (e) {
                f.status = 'failed';
                f.error = e.message;
                this.decorate(f);
            }
        },

        poll(f) {
            setTimeout(async () => {
                if (!this.files.includes(f)) {
                    return;
                }
                const res = await this.request(fileUrl(f.token), 'GET').catch(() => ({ ok: false }));
                if (res.ok) {
                    Object.assign(f, res.data, { error: res.data.error || '' });
                }
                this.decorate(f);
                if (f.status === 'ready') {
                    this.quote(f);
                } else if (f.status === 'processing' || !res.ok) {
                    this.poll(f);
                }
            }, 2000);
        },

        requote(f) {
            f.added = false;
            this.decorate(f);
            this.refresh();
            clearTimeout(f.timer);
            f.timer = setTimeout(() => this.quote(f), 350);
        },

        async quote(f) {
            const res = await this.request(fileUrl(f.token, '/fiyat'), 'POST', f.opts);
            f.total = null;
            f.lines = [];
            f.special = null;
            f.error = '';
            if (res.ok) {
                f.total = res.data.total;
                f.summary = res.data.summary;
                f.lines = res.data.quote.lines;
            } else if (res.data.special) {
                // Özel teklif eşiği: fiyat verilmez, sepete eklenemez; iletişim bilgileri gösterilir.
                f.special = { text: res.data.message };
            } else {
                f.error = res.data.message || 'Fiyat hesaplanamadı.';
            }
            this.decorate(f);
        },

        async addToCart(event) {
            const f = this.fileOf(event);
            if (!f || !f.canAdd) {
                return;
            }
            f.busy = true;
            this.decorate(f);
            const res = await this.request(fileUrl(f.token, '/sepet'), 'POST', f.opts).catch(() => ({ ok: false, data: {} }));
            f.busy = false;
            if (res.ok) {
                f.added = true;
                document.querySelectorAll('[data-cart-count]').forEach((el) => {
                    el.textContent = res.data.count;
                    el.hidden = false;
                });
            } else {
                f.error = res.data.message || 'Sepete eklenemedi.';
            }
            this.decorate(f);
            this.refresh();
        },

        async remove(event) {
            const f = this.fileOf(event);
            if (!f) {
                return;
            }
            this.files = this.files.filter((x) => x !== f);
            this.refresh();
            if (f.token) {
                await this.request(fileUrl(f.token), 'DELETE').catch(() => null);
            }
        },

        /** Seçenek düğmesi: data-key / data-value. */
        choose(event) {
            const f = this.fileOf(event);
            if (!f) {
                return;
            }
            const key = event.currentTarget.dataset.key;
            f.opts[key] = event.currentTarget.dataset.value;
            this.requote(f);
        },

        /** Metin alanı (sayfa aralığı, not, kopya): data-key. */
        setText(event) {
            const f = this.fileOf(event);
            if (!f) {
                return;
            }
            const key = event.currentTarget.dataset.key;
            let value = event.currentTarget.value;
            if (key === 'copies') {
                value = Math.min(Math.max(parseInt(value, 10) || 1, 1), config.maxCopies);
                event.currentTarget.value = value;
            }
            f.opts[key] = value;
            this.requote(f);
        },

        /** Kopya artır/azalt: data-delta. */
        step(event) {
            const f = this.fileOf(event);
            if (!f) {
                return;
            }
            const next = (parseInt(f.opts.copies, 10) || 1) + parseInt(event.currentTarget.dataset.delta, 10);
            f.opts.copies = Math.min(Math.max(next, 1), config.maxCopies);
            this.requote(f);
        },
    };
}
