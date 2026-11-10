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

document.addEventListener('change', (event) => {
    if (event.target.matches('[data-autosubmit]') && event.target.form) {
        event.target.form.requestSubmit();
    }
});
