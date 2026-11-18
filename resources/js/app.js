import Alpine from '@alpinejs/csp';
import * as components from './components.js';

// Alpine'in CSP sürümü: ifadeler eval olmadan yorumlanır (politikada 'unsafe-eval' yok). Mantık içeren
// davranışlar components.js'te bileşen olarak tanımlanır; şablonda yalnızca basit özellik/metot adları kullanılır.
window.Alpine = Alpine;
Object.entries(components).forEach(([name, component]) => Alpine.data(name, component));

// x-mark: sunucunun vurguladığı metni (<mark> içeren, kaçışlı HTML) güvenle yazar. CSP sürümünde x-html yasak;
// burada yalnız düz metin ve <mark> düğümleri oluşturulur, başka hiçbir etiket/öznitelik sayfaya girmez.
Alpine.directive('mark', (el, { expression }, { effect, evaluateLater }) => {
    const read = evaluateLater(expression);
    effect(() => read((html) => {
        const doc = new DOMParser().parseFromString(`<body>${html ?? ''}</body>`, 'text/html');
        const out = [];
        doc.body.childNodes.forEach((node) => {
            if (node.nodeName === 'MARK') {
                const m = document.createElement('mark');
                m.className = 'search-mark';
                m.textContent = node.textContent;
                out.push(m);
            } else {
                out.push(document.createTextNode(node.textContent));
            }
        });
        el.replaceChildren(...out);
    }));
});
Alpine.start();

// İçerik güvenlik politikası satır içi olay işleyicilerini engeller; onay ve otomatik gönderim öznitelikle tanımlanır:
// data-confirm="Soru?" (form veya buton), data-autosubmit (seçim değişince formu gönder).
document.addEventListener('submit', (event) => {
    const form = event.target;
    const message = event.submitter?.dataset.confirm ?? form.dataset.confirm;
    if (message && !window.confirm(message)) {
        event.preventDefault();
        event.stopImmediatePropagation();
    }
}, true);

// Header: kaydırınca süzülen gölge, fareyi izleyen ışık, sepet sayısı değişince rozet zıplar
const siteHeader = document.querySelector('[data-site-header]');
if (siteHeader) {
    // Kaydırma: kare başına en çok bir güncelleme; öznitelik yalnız durum değişince yazılır.
    // Header'ın boyu değişmez (üst bant header dışında doğal kayar), bu yüzden eşikte titreme olmaz.
    const ticker = document.querySelector('.header-ticker');
    const headerNav = siteHeader.querySelector('[data-header-nav]');
    let tickerH = ticker ? ticker.offsetHeight : 0;
    window.addEventListener('resize', () => (tickerH = ticker ? ticker.offsetHeight : 0), { passive: true });
    let lastY = window.scrollY;
    let scrolled = false;
    let navHidden = false;
    let scrollQueued = false;
    const setState = (name, on) => siteHeader.toggleAttribute(name, on);
    const onScroll = () => {
        scrollQueued = false;
        const y = Math.max(0, window.scrollY);
        const wantScrolled = scrolled ? y > tickerH - 4 : y > tickerH + 12;
        if (wantScrolled !== scrolled) {
            scrolled = wantScrolled;
            setState('data-scrolled', scrolled);
        }
        const dy = y - lastY;
        if (Math.abs(dy) > 8 || y < tickerH + 200) {
            // Aşağı inerken menüyü gizle (kategori paneli açıkken veya üstteyken gizleme), yukarı çıkınca göster
            const busy = headerNav && headerNav.matches(':hover, :focus-within');
            const wantHidden = dy > 0 && y > tickerH + 200 && !busy;
            if (wantHidden !== navHidden) {
                navHidden = wantHidden;
                setState('data-nav-hidden', navHidden);
            }
            lastY = y;
        }
    };
    window.addEventListener('scroll', () => {
        if (!scrollQueued) {
            scrollQueued = true;
            requestAnimationFrame(onScroll);
        }
    }, { passive: true });
    onScroll();
    // Fare ışığı: kare başına en çok bir stil yazımı
    let headerX = 0;
    let headerQueued = false;
    siteHeader.addEventListener('pointermove', (e) => {
        headerX = e.clientX;
        if (headerQueued) {
            return;
        }
        headerQueued = true;
        requestAnimationFrame(() => {
            headerQueued = false;
            siteHeader.style.setProperty('--hx', `${headerX - siteHeader.getBoundingClientRect().left}px`);
        });
    }, { passive: true });
    siteHeader.querySelectorAll('[data-cart-count]').forEach((badge) => {
        new MutationObserver(() => {
            badge.classList.remove('cart-bump');
            void badge.offsetWidth; // animasyonu yeniden başlat
            badge.classList.add('cart-bump');
        }).observe(badge, { childList: true, characterData: true, subtree: true });
    });
}

// Ana sayfa 3D sahnesi: Three.js ayrı parça olarak yalnız bu sayfada ve sayfa boştayken yüklenir.
const heroScene = document.querySelector('[data-hero-scene]');
if (heroScene) {
    const load = () => import('./hero-scene.js').then((m) => m.mount(heroScene)).catch(() => {});
    'requestIdleCallback' in window ? window.requestIdleCallback(load, { timeout: 1200 }) : setTimeout(load, 300);
}

// Diğer 3D sahneler (belge bandı, SSS, alt bilgi): yalnız ekrana yaklaşınca yüklenir; Three.js ortak parçadır.
const sceneLoaders = {
    'print-hero': () => import('./scenes/print-hero.js'),
    'order-track': () => import('./scenes/order-track.js'),
    'publisher-carousel': () => import('./scenes/publisher-carousel.js'),
    'print-banner': () => import('./scenes/print-banner.js'),
    faq: () => import('./scenes/faq.js'),
    about: () => import('./scenes/about.js'),
    contact: () => import('./scenes/contact.js'),
    auth: () => import('./scenes/auth.js'),
    'not-found': () => import('./scenes/not-found.js'),
    'footer-wave': () => import('./scenes/footer-wave.js'),
};
const sceneRoots = document.querySelectorAll('[data-scene]');
if (sceneRoots.length && 'IntersectionObserver' in window) {
    const sceneObserver = new IntersectionObserver((entries) => {
        entries.filter((e) => e.isIntersecting).forEach(({ target }) => {
            sceneObserver.unobserve(target);
            sceneLoaders[target.dataset.scene]?.().then((m) => m.mount(target)).catch(() => {});
        });
    }, { rootMargin: '400px' });
    sceneRoots.forEach((el) => sceneObserver.observe(el));
}

// Yukarı çık butonu: 400px'ten sonra görünür; halka kaydırma ilerlemesini gösterir
const backToTop = document.querySelector('[data-back-to-top]');
if (backToTop) {
    const ring = backToTop.querySelector('[data-back-to-top-progress]');
    let ticking = false;
    const update = () => {
        ticking = false;
        const max = document.documentElement.scrollHeight - window.innerHeight;
        const ratio = max > 0 ? Math.min(1, window.scrollY / max) : 0;
        backToTop.classList.toggle('is-visible', window.scrollY > 400);
        ring?.style.setProperty('stroke-dashoffset', String(100 - ratio * 100));
    };
    window.addEventListener('scroll', () => {
        if (!ticking) {
            ticking = true;
            requestAnimationFrame(update);
        }
    }, { passive: true });
    window.addEventListener('resize', update, { passive: true });
    backToTop.addEventListener('click', () => {
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.scrollTo({ top: 0, behavior: reduced ? 'auto' : 'smooth' });
        document.getElementById('icerik')?.focus({ preventScroll: true });
    });
    update();
}

// SSS sorusu bağlantısı (#sss-3, ör. aramadan): ilgili soru açılır ve ekrana kaydırılır
const openFaqFromHash = () => {
    const el = /^#sss-\d+$/.test(location.hash) ? document.querySelector(location.hash) : null;
    if (el && el.tagName === 'DETAILS') {
        el.open = true;
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
};
openFaqFromHash();
window.addEventListener('hashchange', openFaqFromHash);

// Yasal metin içindekiler: bulunulan bölüm vurgulanır, okunan oran çubukta ve yüzde olarak gösterilir
const toc = document.querySelector('[data-toc]');
const tocContent = document.querySelector('[data-toc-content]');
if (toc && tocContent) {
    const links = [...toc.querySelectorAll('[data-toc-link]')];
    const heads = links.map((a) => document.getElementById(a.hash.slice(1))).filter(Boolean);
    const bar = toc.querySelector('[data-toc-bar]');
    const percent = toc.querySelector('[data-toc-percent]');
    let ticking = false;
    const update = () => {
        ticking = false;
        const offset = 160;
        let active = 0;
        heads.forEach((h, i) => {
            if (h.getBoundingClientRect().top - offset <= 0) active = i;
        });
        links.forEach((a, i) => a.classList.toggle('is-active', i === active));
        const box = tocContent.getBoundingClientRect();
        const ratio = Math.min(1, Math.max(0, (offset - box.top) / Math.max(1, box.height - window.innerHeight + offset)));
        if (bar) bar.style.width = `${Math.round(ratio * 100)}%`;
        if (percent) percent.textContent = `${Math.round(ratio * 100)}%`;
    };
    window.addEventListener('scroll', () => {
        if (!ticking) {
            ticking = true;
            requestAnimationFrame(update);
        }
    }, { passive: true });
    update();
}

// Rakam sayaçları (Hakkımızda): ekrana gelince 0'dan hedef değere sayar; hareketi azalt tercihinde dokunulmaz
const counters = document.querySelectorAll('[data-count-to]');
if (counters.length && 'IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const fmt = new Intl.NumberFormat('tr-TR');
    const countObserver = new IntersectionObserver((entries) => {
        entries.forEach(({ isIntersecting, target }) => {
            if (!isIntersecting) return;
            countObserver.unobserve(target);
            const to = Number(target.dataset.countTo) || 0;
            const start = performance.now();
            const tick = (now) => {
                const p = Math.min(1, (now - start) / 1400);
                target.textContent = fmt.format(Math.round(to * (1 - Math.pow(1 - p, 3))));
                if (p < 1) requestAnimationFrame(tick);
            };
            target.textContent = '0';
            requestAnimationFrame(tick);
            // Arka planda çizim durursa bile son değer kesin yazılır
            setTimeout(() => (target.textContent = fmt.format(to)), 1600);
        });
    }, { threshold: 0.4 });
    counters.forEach((el) => countObserver.observe(el));
}

// Yazdırmada tüm açılır bölümler (SSS cevapları) açık çıkar; çıktıdan sonra eski hâline döner
let printOpened = [];
window.addEventListener('beforeprint', () => {
    printOpened = [...document.querySelectorAll('details.info-faq:not([open])')];
    printOpened.forEach((el) => (el.open = true));
});
window.addEventListener('afterprint', () => {
    printOpened.forEach((el) => (el.open = false));
    printOpened = [];
});

document.addEventListener('change', (event) => {
    if (event.target.matches('[data-autosubmit]') && event.target.form) {
        event.target.form.requestSubmit();
    }
});
