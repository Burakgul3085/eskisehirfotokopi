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

/** Çerez bildirimi: bir kez kapatılınca tarayıcıda hatırlanır (yalnız zorunlu çerezler; onay kaydı tutulmaz). */
export function cookieNotice() {
    return {
        visible: false,
        init() {
            try {
                this.visible = localStorage.getItem('cookie-notice') !== '1';
            } catch {
                this.visible = true;
            }
        },
        accept() {
            this.visible = false;
            try {
                localStorage.setItem('cookie-notice', '1');
            } catch {
                // depolama kapalıysa yalnız bu sayfada gizlenir
            }
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
        // $root: bileşenin kendisi (form). $el, olayı tetikleyen kutuyu gösterir; "Tümünü seç" içinde kutu bulunmuyordu.
        boxes() {
            return [...this.$root.querySelectorAll('input[name="ids[]"]')];
        },
        toggleAll(event) {
            // Kapalı (gizli) satırlar seçilmez: görünmeyen kategori yanlışlıkla silinmesin
            this.boxes().forEach((b) => {
                b.checked = event.target.checked && !b.closest('tr[hidden]');
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
 * Anlık arama (header, ana sayfa). Yazdıkça /ara/oneri'den yalnız eşleşen sonuçları alır; adı birebir eşleşen hedef
 * en üstte "doğrudan git" satırıdır. Klavye: ↑/↓ ile sonuçlar arasında gezinilir, Enter gider, Esc kapatır.
 */
export function liveSearch() {
    let timer = null;
    let controller = null;
    let root = null; // form öğesi ($el, ifadenin bağlı olduğu alt öğeyi gösterebilir)
    const cache = new Map();
    const blank = { direct: null, products: [], categories: [], publishers: [], authors: [], pages: [], faq: [], total: 0, all_url: '' };
    // Ürün dışı gruplar (sağ sütun) ve başlıkları
    const groups = [['pages', 'Sayfalar ve bölümler'], ['categories', 'Kategoriler'], ['publishers', 'Yayınevleri'], ['authors', 'Yazarlar'], ['faq', 'Sıkça sorulan sorular']];

    return {
        q: '',
        open: false,
        loading: false,
        active: -1,
        results: { ...blank },
        init() {
            root = this.$el;
            this.q = this.$refs.input.value;
        },
        onInput() {
            clearTimeout(timer);
            const q = this.q.trim();
            if (q.length < 2) {
                // Kutu boşken panel açılmaz (öneri gösterilmez)
                controller?.abort();
                this.open = false;
                this.loading = false;
                return;
            }
            timer = setTimeout(() => this.fetch(q), 160);
        },
        async fetch(q) {
            if (cache.has(q)) {
                this.show(cache.get(q));
                return;
            }
            controller?.abort();
            controller = new AbortController();
            this.loading = true;
            this.open = true;
            try {
                const url = root.dataset.url + '?q=' + encodeURIComponent(q);
                const res = await fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal });
                if (res.ok) {
                    const data = { ...blank, ...(await res.json()) };
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
            const q = this.q.trim();
            if (q.length >= 2) {
                this.fetch(q);
            }
        },
        close() {
            this.open = false;
        },
        links() {
            return [...root.querySelectorAll('[data-suggest]')].filter((a) => a.offsetParent !== null);
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
        hasQuery() {
            return this.results.all_url !== '';
        },
        // Adı birebir eşleşen hedef: en üstte "doğrudan git" satırı (Enter da oraya götürür)
        hasDirect() {
            return this.results.direct !== null;
        },
        directType() {
            return this.results.direct?.type || '';
        },
        directHl() {
            return this.results.direct?.hl || '';
        },
        directUrl() {
            return this.results.direct?.url || '';
        },
        products() {
            return this.results.products.filter((p) => p.url !== this.directUrl());
        },
        hasProducts() {
            return this.products().length > 0;
        },
        otherGroups() {
            return groups.map(([key, label]) => ({ key, label, items: (this.results[key] || []).filter((i) => i.url !== this.directUrl()) }))
                .filter((g) => g.items.length > 0);
        },
        hasOthers() {
            return this.otherGroups().length > 0;
        },
        gridClass() {
            return this.hasProducts() && this.hasOthers() ? 'md:grid-cols-[1.35fr_1fr]' : '';
        },
        sideClass() {
            return this.hasProducts() ? 'md:border-l md:border-line md:pl-2' : '';
        },
        isEmpty() {
            return !this.hasDirect() && !this.hasProducts() && !this.hasOthers();
        },
        hasAll() {
            return this.results.total > 6;
        },
        totalLabel() {
            return 'Tüm ürün sonuçlarını gör (' + this.results.total.toLocaleString('tr-TR') + ')';
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
        // Bölüm (ör. bir harf) içindeki öğelerden hiçbiri aramaya uymuyorsa bölüm gizlenir
        groupMatches() {
            const t = asciiKey(this.term);
            return t === '' || [...this.$el.querySelectorAll('[data-name]')].some((e) => e.dataset.name.includes(t));
        },
        // Sayfadaki hiçbir öğe aramaya uymuyorsa "sonuç yok" yazısı görünür
        noMatch() {
            const t = asciiKey(this.term);
            return t !== '' && ![...this.$root.querySelectorAll('[data-name]')].some((e) => e.dataset.name.includes(t));
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

/**
 * Ürün listesi toplu işlemleri: satır seçimi, "aramaya uyan tümünü seç" (scope=all), kategoriye taşırken yeni kategori
 * alanları ve onay penceresinde sayı + hedefle açık ileti ("42 ürün “Sınav Hazırlık › Biyoloji” kategorisine taşınacak").
 * İleti form.dataset.confirm'a yazılır; app.js gönderimde onu sorar.
 */
export function productBulk() {
    return {
        count: 0,
        total: 0,
        allMatching: false,
        showSelectAll: false,
        selectedCount: 0,
        scope: 'selected',
        isMove: false,
        isNew: false,
        init() {
            this.total = Number(this.$root.dataset.total || 0);
            this.refresh();
        },
        boxes() {
            return [...this.$root.querySelectorAll('input[name="ids[]"]')];
        },
        toggleAll(event) {
            this.boxes().forEach((b) => {
                b.checked = event.target.checked;
            });
            this.allMatching = false;
            this.recount();
        },
        recount() {
            const boxes = this.boxes();
            this.count = boxes.filter((b) => b.checked).length;
            if (this.count < boxes.length) {
                this.allMatching = false;
            }
            this.refresh();
        },
        selectAllMatching() {
            this.boxes().forEach((b) => {
                b.checked = true;
            });
            this.count = this.boxes().length;
            this.allMatching = true;
            this.refresh();
        },
        clearAllMatching() {
            this.boxes().forEach((b) => {
                b.checked = false;
            });
            this.allMatching = false;
            this.recount();
        },
        optionText(select) {
            const option = select?.selectedOptions?.[0];
            return option ? (option.dataset.full || option.textContent.trim()) : '';
        },
        refresh() {
            const form = this.$root;
            const boxes = this.boxes();
            this.scope = this.allMatching ? 'all' : 'selected';
            this.selectedCount = this.allMatching ? this.total : this.count;
            this.showSelectAll = !this.allMatching && boxes.length > 0 && this.count === boxes.length && this.total > boxes.length;

            const action = form.elements.action?.value || '';
            const category = form.elements.category_id;
            this.isMove = action === 'category';
            this.isNew = this.isMove && category?.value === 'new';

            const n = this.selectedCount;
            let message = '';
            if (n > 0 && action !== '') {
                if (this.isMove) {
                    let target = this.optionText(category);
                    if (this.isNew) {
                        const name = (form.elements.new_category_name?.value || '').trim();
                        target = `${this.optionText(form.elements.new_category_parent)} › ${name || '?'} (yeni kategori)`;
                    }
                    message = category?.value ? `${n} ürün “${target}” kategorisine taşınacak.` : '';
                    if (message && form.elements.save_rule?.checked) {
                        message += ' Bundan sonra yüklenen ve bu aramaya uyan kitaplar da buraya gidecek.';
                    }
                } else if (action === 'delete') {
                    message = `${n} ürün kalıcı olarak silinecek. Bu işlem geri alınamaz. Emin misiniz?`;
                } else {
                    message = `${n} ürüne “${this.optionText(form.elements.action)}” uygulanacak.`;
                }
                if (message && this.allMatching) {
                    message += ' (Yalnız bu sayfadakiler değil, aramaya uyan tüm ürünler.)';
                }
            }
            if (message) {
                form.dataset.confirm = message;
            } else {
                delete form.dataset.confirm;
            }
        },
    };
}

/**
 * Yönetim › Kategoriler: ana kategorilerin alt kategorilerini aç/kapa. Alt satırlar data-parent taşır, düğmeler data-id.
 * Açık olanlar bu tarayıcıda hatırlanır (yalnız kolaylık; saklanamazsa hepsi kapalı başlar).
 */
export function categoryTree() {
    const KEY = 'yonetim-kategori-acik';
    return {
        open: [],
        init() {
            try {
                this.open = JSON.parse(window.localStorage.getItem(KEY) || '[]').map(String);
            } catch (e) {
                this.open = [];
            }
            this.apply();
        },
        save() {
            try {
                window.localStorage.setItem(KEY, JSON.stringify(this.open));
            } catch (e) {
                // tarayıcı saklamaya izin vermiyor; yalnız bu sayfada geçerli
            }
        },
        toggle(event) {
            const id = String(event.currentTarget.dataset.id);
            this.open = this.open.includes(id) ? this.open.filter((x) => x !== id) : [...this.open, id];
            this.save();
            this.apply();
        },
        ids() {
            return [...this.$root.querySelectorAll('[data-toggle-id]')].map((b) => String(b.dataset.id));
        },
        expandAll() {
            this.open = this.ids();
            this.save();
            this.apply();
        },
        collapseAll() {
            this.open = [];
            this.save();
            this.apply();
        },
        apply() {
            this.$root.querySelectorAll('tr[data-parent]').forEach((row) => {
                row.hidden = !this.open.includes(String(row.dataset.parent));
            });
            this.$root.querySelectorAll('[data-toggle-id]').forEach((button) => {
                const isOpen = this.open.includes(String(button.dataset.id));
                button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                button.querySelector('svg')?.classList.toggle('rotate-90', isOpen);
            });
        },
    };
}

/** Kategori araması için sade biçim (sunucudaki Text::key ile aynı: küçük harf, Türkçe harfler sadeleşir). */
function categoryKey(value) {
    return String(value || '')
        .toLocaleLowerCase('tr')
        .replace(/[ıîİ]/g, 'i').replace(/ş/g, 's').replace(/ğ/g, 'g').replace(/[üû]/g, 'u').replace(/ö/g, 'o').replace(/ç/g, 'c').replace(/â/g, 'a')
        .normalize('NFD').replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, ' ')
        .trim();
}

/** Arama kelimelerinin hepsi adın içinde geçiyor mu? */
function categoryMatches(key, terms) {
    return terms.every((t) => key.includes(t));
}

/**
 * Header "Tüm kategoriler" paneli: tıklayınca aç/kapa; soldaki ana kategorinin üstüne gelince sağda onun alt kategorileri;
 * arama ana ve alt kategori adlarında süzer (eşleşen alt kategoriler vurgulanır, eşleşmeyenler gizlenir).
 */
export function categoryMenu() {
    return {
        open: false,
        expanded: 'false',
        chevronClass: '',
        active: null,
        terms: [],
        toggle() {
            if (this.open) {
                this.close();
            } else {
                this.open = true;
                this.apply();
                this.$nextTick(() => this.$refs.search?.focus({ preventScroll: true }));
            }
        },
        close() {
            this.open = false;
            this.apply();
        },
        show(event) {
            this.active = String(event.currentTarget.dataset.id);
            this.apply();
        },
        search(event) {
            this.terms = categoryKey(event.target.value).split(' ').filter((t) => t !== '');
            this.active = null;
            this.apply();
        },
        apply() {
            this.expanded = this.open ? 'true' : 'false';
            this.chevronClass = this.open ? 'rotate-180' : '';
            this.$refs.panel?.classList.toggle('is-open', this.open);
            const root = this.$root;
            const groups = [...root.querySelectorAll('li[data-group]')];
            const searching = this.terms.length > 0;
            const visibleIds = [];
            groups.forEach((li) => {
                const link = li.querySelector('a[data-id]');
                const childKeys = (li.dataset.childKeys || '').split('|').filter((k) => k !== '');
                const hit = !searching || categoryMatches(li.dataset.key || '', this.terms) || childKeys.some((k) => categoryMatches(k, this.terms));
                li.hidden = !hit;
                if (hit) {
                    visibleIds.push(String(link.dataset.id));
                }
            });
            if (this.active === null || !visibleIds.includes(this.active)) {
                this.active = visibleIds[0] ?? null;
            }
            const noMatch = root.querySelector('[data-no-match]');
            if (noMatch) {
                noMatch.hidden = visibleIds.length > 0;
            }
            groups.forEach((li) => {
                const link = li.querySelector('a[data-id]');
                const isActive = String(link.dataset.id) === this.active;
                link.classList.toggle('is-active', isActive);
                link.setAttribute('aria-current', isActive ? 'true' : 'false');
            });
            root.querySelectorAll('section[data-pane]').forEach((pane) => {
                pane.hidden = String(pane.dataset.pane) !== this.active;
                const group = groups.find((li) => String(li.querySelector('a[data-id]').dataset.id) === String(pane.dataset.pane));
                // Ana kategori adı aramaya uyuyorsa bütün alt kategoriler, uymuyorsa yalnız eşleşenler
                const groupHit = !searching || categoryMatches(group?.dataset.key || '', this.terms);
                pane.querySelectorAll('li[data-child]').forEach((li) => {
                    const hit = searching && categoryMatches(li.dataset.key || '', this.terms);
                    li.hidden = !groupHit && !hit;
                    li.querySelector('a')?.classList.toggle('is-match', hit);
                });
            });
        },
    };
}

/** Telefon menüsündeki kategoriler: arama ana/alt kategori adlarında süzer, eşleşen grubu açar. */
export function mobileCategories() {
    return {
        search(event) {
            const terms = categoryKey(event.target.value).split(' ').filter((t) => t !== '');
            const searching = terms.length > 0;
            let any = false;
            this.$root.querySelectorAll('details[data-group]').forEach((group) => {
                const groupHit = !searching || categoryMatches(group.dataset.key || '', terms);
                let childHit = false;
                group.querySelectorAll('[data-child]').forEach((child) => {
                    const hit = searching && categoryMatches(child.dataset.key || '', terms);
                    child.hidden = !groupHit && !hit;
                    childHit = childHit || hit;
                });
                group.hidden = !groupHit && !childHit;
                group.open = searching && (groupHit || childHit);
                any = any || !group.hidden;
            });
            const noMatch = this.$root.querySelector('[data-no-match]');
            if (noMatch) {
                noMatch.hidden = any;
            }
        },
    };
}

/** Metin kopyalama düğmesi: data-copy değerini panoya yazar, kısa süre data-done yazısını gösterir. */
/** Yasal metin sayfası: "Yazdır / PDF olarak kaydet" butonu. */
export function printPage() {
    return {
        print() {
            window.print();
        },
    };
}

export function copyText() {
    return {
        copied: false,
        idle: '',
        doneText: '',
        init() {
            // Yazılar şablondan: düğmenin ilk metni ve data-done
            const btn = this.$el.querySelector('[data-copy]');
            this.idle = btn?.querySelector('[x-text]')?.textContent.trim() || '';
            this.doneText = btn?.dataset.done || this.idle;
        },
        get label() {
            return this.copied ? this.doneText : this.idle;
        },
        copy(event) {
            const value = event.currentTarget.dataset.copy || '';
            navigator.clipboard?.writeText(value).then(() => {
                this.copied = true;
                setTimeout(() => (this.copied = false), 1800);
            }).catch(() => {});
        },
    };
}

/**
 * Ürün sayfası alt sekmeleri (Değerlendirmeler / Soru & Cevap). Başlangıç sekmesi data-tab'dan; adres çubuğundaki
 * #soru… ve #degerlendirme… bağlantıları ilgili sekmeyi açar, #degerlendirme-yaz formu da açar.
 */
export function productTabs() {
    return {
        tab: 'reviews',
        init() {
            this.tab = this.$el.dataset.tab || 'reviews';
            this.fromHash();
            window.addEventListener('hashchange', () => this.fromHash());
        },
        fromHash() {
            const hash = window.location.hash;
            if (hash.startsWith('#soru')) {
                this.tab = 'questions';
            } else if (hash.startsWith('#degerlendirme')) {
                this.tab = 'reviews';
                const form = document.getElementById('degerlendirme-yaz');
                if (hash === '#degerlendirme-yaz' && form && form.tagName === 'DETAILS') {
                    form.open = true;
                }
            } else {
                return;
            }
            this.$nextTick(() => document.getElementById(hash.slice(1))?.scrollIntoView({ block: 'start' }));
        },
        showReviews() {
            this.tab = 'reviews';
        },
        showQuestions() {
            this.tab = 'questions';
        },
        isReviews() {
            return this.tab === 'reviews';
        },
        isQuestions() {
            return this.tab === 'questions';
        },
        reviewsSelected() {
            return this.tab === 'reviews' ? 'true' : 'false';
        },
        questionsSelected() {
            return this.tab === 'questions' ? 'true' : 'false';
        },
    };
}

/** Yıldızla puan verme: seçilen yıldızın anlamı ("İyi") yanında yazar. Etiketler data-labels'ta "|" ile. */
export function ratingInput() {
    return {
        value: 0,
        labels: [],
        init() {
            this.labels = (this.$el.dataset.labels || '').split('|');
            this.value = Number(this.$el.dataset.value || 0);
        },
        pick(event) {
            this.value = Number(event.target.value || 0);
        },
        text() {
            return this.value ? this.labels[this.value - 1] || '' : '';
        },
    };
}

/**
 * Değerlendirme fotoğrafları: seçilenlerin önizlemesi; sınırı aşan dosyalar seçimden çıkarılır ve uyarı gösterilir.
 * Sınır = data-max + "Kaldır" işaretlenen mevcut fotoğraf sayısı.
 */
export function photoPicker() {
    let urls = [];
    return {
        previews: [],
        warning: '',
        pick(event) {
            const input = event.target;
            const room = Number(this.$el.dataset.max || 0) + this.$el.querySelectorAll('input[name="remove_photos[]"]:checked').length;
            let files = Array.from(input.files || []);
            this.warning = '';
            if (files.length > room) {
                files = files.slice(0, Math.max(0, room));
                const keep = new DataTransfer();
                files.forEach((f) => keep.items.add(f));
                input.files = keep.files;
                this.warning = this.$el.dataset.warn || '';
            }
            urls.forEach((u) => URL.revokeObjectURL(u));
            urls = files.map((f) => URL.createObjectURL(f));
            this.previews = urls;
        },
        hasPreviews() {
            return this.previews.length > 0;
        },
        hasWarning() {
            return this.warning !== '';
        },
    };
}

/** Fotoğrafı sayfa üstünde büyütme (bağlantı JS'siz yeni sekmede açılır). Esc veya tıklama kapatır. */
export function lightbox() {
    return {
        src: '',
        open(event) {
            event.preventDefault();
            this.src = event.currentTarget.getAttribute('href') || '';
            document.documentElement.classList.add('overflow-hidden');
        },
        close() {
            this.src = '';
            document.documentElement.classList.remove('overflow-hidden');
        },
        isOpen() {
            return this.src !== '';
        },
    };
}
