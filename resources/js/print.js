// Yazdırılabilir sayfalar (teslimat fişi): CSP satır içi betiğe izin vermediği için düğme burada bağlanır.
document.querySelectorAll('[data-print]').forEach((button) => button.addEventListener('click', () => window.print()));
