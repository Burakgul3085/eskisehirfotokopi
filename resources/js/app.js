import Alpine from '@alpinejs/csp';
import * as components from './components.js';

// Alpine'in CSP sürümü: ifadeler eval olmadan yorumlanır (politikada 'unsafe-eval' yok). Mantık içeren
// davranışlar components.js'te bileşen olarak tanımlanır; şablonda yalnızca basit özellik/metot adları kullanılır.
window.Alpine = Alpine;
Object.entries(components).forEach(([name, component]) => Alpine.data(name, component));
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

// Ana sayfa 3D sahnesi: Three.js ayrı parça olarak yalnız bu sayfada ve sayfa boştayken yüklenir.
const heroScene = document.querySelector('[data-hero-scene]');
if (heroScene) {
    const load = () => import('./hero-scene.js').then((m) => m.mount(heroScene)).catch(() => {});
    'requestIdleCallback' in window ? window.requestIdleCallback(load, { timeout: 1200 }) : setTimeout(load, 300);
}

// Diğer 3D sahneler (belge bandı, SSS, alt bilgi): yalnız ekrana yaklaşınca yüklenir; Three.js ortak parçadır.
const sceneLoaders = {
    'print-banner': () => import('./scenes/print-banner.js'),
    faq: () => import('./scenes/faq.js'),
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

document.addEventListener('change', (event) => {
    if (event.target.matches('[data-autosubmit]') && event.target.form) {
        event.target.form.requestSubmit();
    }
});
