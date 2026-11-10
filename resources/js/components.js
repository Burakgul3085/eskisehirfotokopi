// Alpine bileşenleri (CSP uyumlu: şablonda yalnızca bu nesnelerin özellik ve metotları kullanılır).

/** Aç/kapa (menü, açılır panel). */
export function toggle() {
    return {
        open: false,
        toggle() {
            this.open = !this.open;
        },
        close() {
            this.open = false;
        },
        isOpen() {
            return this.open;
        },
        isClosed() {
            return !this.open;
        },
        /** Yan menü: açıkken ekrana kayar. */
        slideInClass() {
            return this.open ? 'translate-x-0' : '';
        },
    };
}

/** Parola alanında göster/gizle. */
export function passwordField() {
    return {
        visible: false,
        toggleVisible() {
            this.visible = !this.visible;
        },
        inputType() {
            return this.visible ? 'text' : 'password';
        },
        label() {
            return this.visible ? 'Gizle' : 'Göster';
        },
    };
}

/** Bildirim (flash) mesajı: birkaç saniye sonra kendiliğinden kapanır. */
export function flash() {
    return {
        shown: true,
        init() {
            setTimeout(() => {
                this.shown = false;
            }, 6000);
        },
        dismiss() {
            this.shown = false;
        },
    };
}

/** Renk seçici ile metin alanını eşler (yönetim → görünüm). */
export function colorField() {
    return {
        value: '',
        init() {
            this.value = this.$el.dataset.value || '#000000';
        },
        sync(event) {
            this.value = event.target.value;
        },
    };
}

/** Tablo satırı seçimi (toplu işlemler). Kapsayıcıdaki name="ids[]" kutularını yönetir. */
export function bulkSelect() {
    return {
        count: 0,
        boxes() {
            return [...this.$el.querySelectorAll('input[name="ids[]"]')];
        },
        toggleAll(event) {
            this.boxes().forEach((b) => {
                b.checked = event.target.checked;
            });
            this.recount();
        },
        recount() {
            this.count = this.boxes().filter((b) => b.checked).length;
        },
    };
}

/** Seçilen görselin önizlemesi (logo, favicon). */
export function imagePreview() {
    return {
        url: '',
        init() {
            this.url = this.$el.dataset.current || '';
        },
        pick(event) {
            const file = event.target.files?.[0];
            if (file) {
                this.url = URL.createObjectURL(file);
            }
        },
        hasImage() {
            return this.url !== '';
        },
        noImage() {
            return this.url === '';
        },
    };
}

export { folderImport } from './folder-import.js';
export { printOrder } from './print-order.js';

/** Yatay kaydırmalı satır: oklar bir ekran genişliği kaydırır. Şablonda x-ref="track". */
export function scroller() {
    return {
        prev() {
            this.$refs.track.scrollBy({ left: -this.$refs.track.clientWidth * 0.9, behavior: 'smooth' });
        },
        next() {
            this.$refs.track.scrollBy({ left: this.$refs.track.clientWidth * 0.9, behavior: 'smooth' });
        },
    };
}

/** Görsel slayt: kendiliğinden geçer, fareyle üzerine gelince durur. data-count: slayt sayısı. */
export function slider() {
    let timer = null;

    return {
        index: 0,
        count: 1,
        init() {
            this.count = Number(this.$el.dataset.count || 1);
            this.play();
        },
        play() {
            clearInterval(timer);
            if (this.count > 1 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                timer = setInterval(() => this.next(), 6000);
            }
        },
        stop() {
            clearInterval(timer);
        },
        next() {
            this.go((this.index + 1) % this.count);
        },
        prev() {
            this.go((this.index - 1 + this.count) % this.count);
        },
        go(i) {
            this.index = i;
            this.$refs.track.scrollTo({ left: this.$refs.track.clientWidth * i, behavior: 'smooth' });
        },
        goTo() {
            this.go(Number(this.$el.dataset.index));
            this.play();
        },
        onScroll() {
            const w = this.$refs.track.clientWidth;
            if (w) {
                this.index = Math.round(this.$refs.track.scrollLeft / w);
            }
        },
        dotClass() {
            return Number(this.$el.dataset.index) === this.index ? 'w-6 bg-white' : 'w-2 bg-white/50';
        },
    };
}

/**
 * Anlık arama (header). Yazdıkça 180 ms bekleyip /ara/oneri'den ürün, yayınevi ve kategori önerileri alır.
 * Klavye: ↑/↓ ile öneriler arasında gezinilir, Enter ile seçilene gidilir, Esc kapatır.
 */
export function liveSearch() {
    let timer = null;
    let controller = null;
    let root = null; // form öğesi ($el, ifadenin bağlı olduğu alt öğeyi gösterebilir)
    const cache = new Map();

    return {
        q: '',
        open: false,
        loading: false,
        active: -1,
        results: { products: [], publishers: [], categories: [], total: 0, all_url: '' },
        init() {
            root = this.$el;
            this.q = this.$refs.input.value;
        },
        onInput() {
            clearTimeout(timer);
            const q = this.q.trim();
            if (q.length < 2) {
                this.open = false;
                return;
            }
            timer = setTimeout(() => this.fetch(q), 180);
        },
        async fetch(q) {
            if (cache.has(q)) {
                this.show(cache.get(q));
                return;
            }
            controller?.abort();
            controller = new AbortController();
            this.loading = true;
            try {
                const url = root.dataset.url + '?q=' + encodeURIComponent(q);
                const res = await fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal });
                if (res.ok) {
                    const data = await res.json();
                    cache.set(q, data);
                    if (this.q.trim() === q) {
                        this.show(data);
                    }
                }
            } catch (e) { /* iptal edildi veya ağ hatası */ }
            this.loading = false;
        },
        show(data) {
            this.results = data;
            this.active = -1;
            this.open = true;
        },
        onFocus() {
            if (this.q.trim().length >= 2 && this.results.all_url) {
                this.open = true;
            }
        },
        close() {
            this.open = false;
        },
        links() {
            return [...root.querySelectorAll('[data-suggest]')];
        },
        down(e) {
            if (!this.open) {
                return;
            }
            e.preventDefault();
            const links = this.links();
            this.active = Math.min(this.active + 1, links.length - 1);
            links[this.active]?.focus();
        },
        up(e) {
            if (!this.open) {
                return;
            }
            e.preventDefault();
            const links = this.links();
            this.active = Math.max(this.active - 1, -1);
            if (this.active === -1) {
                this.$refs.input.focus();
            } else {
                links[this.active]?.focus();
            }
        },
        isOpen() {
            return this.open;
        },
        isLoading() {
            return this.loading;
        },
        hasProducts() {
            return this.results.products.length > 0;
        },
        hasPublishers() {
            return this.results.publishers.length > 0;
        },
        hasCategories() {
            return this.results.categories.length > 0;
        },
        isEmpty() {
            return this.results.total === 0 && this.results.publishers.length === 0 && this.results.categories.length === 0;
        },
        totalLabel() {
            return this.results.total > 0 ? 'Tüm sonuçları gör (' + this.results.total.toLocaleString('tr-TR') + ')' : '';
        },
    };
}

/** Türkçe karakterleri sadeleştirir (PHP Text::key ile aynı mantık). */
function asciiKey(text) {
    return String(text).toLocaleLowerCase('tr')
        .replace(/[çğıöşü]/g, (c) => ({ ç: 'c', ğ: 'g', ı: 'i', ö: 'o', ş: 's', ü: 'u' })[c])
        .normalize('NFD').replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, ' ').trim();
}

/** Uzun seçenek listesinde arama (filtre paneli, yayınevleri sayfası). Öğelerde data-name="ascii ad". */
export function filterList() {
    return {
        term: '',
        matches() {
            const t = asciiKey(this.term);
            return t === '' || (this.$el.dataset.name || '').includes(t);
        },
    };
}

/** Tutarı "1.234,50 ₺" biçiminde yazar. */
function money(value) {
    return new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value) + ' ₺';
}

/**
 * Sipariş sayfası: ilçe/mahalle seçimine göre teslimat ücreti, ücretsiz teslimata kalan, en düşük tutar ve tahmini
 * teslim günü anında hesaplanır (gösterim amaçlı; asıl tutar sunucuda yeniden hesaplanır). Veri, sayfadaki
 * <script type="application/json" data-checkout> içinden okunur.
 */
export function checkout() {
    return {
        data: { districts: {}, addresses: {}, subtotal: 0, initial: {} },
        name: '',
        phone: '',
        districtId: '',
        neighborhood: '',
        address: '',
        hoods: [],
        hasHoods: false,
        noHoods: true,
        feeText: '—',
        totalText: '',
        etaText: '',
        freeText: '',
        minText: '',
        belowMin: false,
        blocked: false,
        hasDistrict: false,
        init() {
            const node = this.$el.querySelector('[data-checkout]');
            this.data = JSON.parse(node.textContent);
            const i = this.data.initial;
            this.name = i.name || '';
            this.phone = i.phone || '';
            this.districtId = i.district_id ? String(i.district_id) : '';
            this.neighborhood = i.neighborhood || '';
            this.address = i.address || '';
            this.refresh();
        },
        pickDistrict() {
            this.neighborhood = '';
            this.refresh();
        },
        refresh() {
            const d = this.data.districts[this.districtId];
            const subtotal = this.data.subtotal;
            this.hasDistrict = Boolean(d);
            this.hoods = d ? d.hoods : [];
            this.hasHoods = this.hoods.length > 0;
            this.noHoods = !this.hasHoods;
            if (!d) {
                this.feeText = '—';
                this.totalText = money(subtotal);
                this.etaText = this.freeText = this.minText = '';
                this.belowMin = false;
                this.blocked = Boolean(this.data.closed);
                return;
            }
            const hood = this.hoods.find((h) => h.name === this.neighborhood);
            const free = d.free !== null && subtotal >= d.free;
            const fee = free ? 0 : hood && hood.fee !== null ? hood.fee : d.fee;
            this.feeText = fee === 0 ? 'Ücretsiz' : money(fee);
            this.totalText = money(subtotal + fee);
            this.etaText = d.eta;
            this.freeText = d.free !== null && !free ? money(d.free - subtotal) + ' daha ekleyin, teslimat ücretsiz olsun.' : '';
            this.belowMin = d.min !== null && subtotal < d.min;
            this.minText = this.belowMin ? 'Bu bölge için en düşük sipariş tutarı ' + money(d.min) + '.' : '';
            this.blocked = Boolean(this.data.closed) || this.belowMin;
            this.$nextTick(() => {
                if (this.$refs.hood) {
                    this.$refs.hood.value = this.neighborhood;
                }
            });
        },
        /** Kayıtlı adresi forma doldurur. */
        useAddress(event) {
            const a = this.data.addresses[event.currentTarget.dataset.address];
            if (!a) {
                return;
            }
            this.name = a.name;
            this.phone = a.phone;
            this.districtId = String(a.district_id || '');
            this.neighborhood = a.neighborhood;
            this.address = a.address;
            this.refresh();
        },
    };
}

/** Favori düğmesi: sayfa yenilenmeden ekler/çıkarır. */
export function favoriteButton() {
    return {
        active: false,
        busy: false,
        init() {
            this.active = this.$el.dataset.active === '1';
        },
        async toggle(event) {
            const form = event.currentTarget.closest('form');
            if (!form || this.busy) {
                return;
            }
            event.preventDefault();
            this.busy = true;
            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                });
                if (res.status === 401) {
                    window.location.href = form.dataset.login;
                    return;
                }
                const json = await res.json();
                this.active = json.favorite;
            } finally {
                this.busy = false;
            }
        },
        label() {
            return this.active ? 'Favorilerde' : 'Favorilere ekle';
        },
        iconClass() {
            return this.active ? 'fill-danger text-danger' : '';
        },
    };
}
