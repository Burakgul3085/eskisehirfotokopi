/**
 * Vitrindeki küçük 3D sahnelerin (belge bandı, SSS, alt bilgi) ortak altyapısı.
 *  - Çizici, kamera ve yeniden boyutlandırma
 *  - Döngü yalnız sahne ekrandayken ve sekme öndeyken çalışır
 *  - "Hareketi azalt" tercihinde tek kare çizilir; WebGL yoksa null döner (sayfa CSS yedeğiyle kalır)
 *  - Fare konumu (-1..1) sahne kökü üzerinden izlenir; sayfa kapanırken GPU kaynakları bırakılır
 */
import * as THREE from 'three';

export { THREE };

export const clamp = (v, a, b) => Math.min(b, Math.max(a, v));
export const lerp = (a, b, t) => a + (b - a) * t;
export const easeInOut = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);

export function cssColor(name, fallback) {
    const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    try {
        return new THREE.Color(v || fallback);
    } catch {
        return new THREE.Color(fallback);
    }
}

export function canvasTexture(w, h, draw) {
    const c = document.createElement('canvas');
    c.width = w;
    c.height = h;
    draw(c.getContext('2d'), w, h);
    const t = new THREE.CanvasTexture(c);
    t.colorSpace = THREE.SRGBColorSpace;
    t.anisotropy = 4;
    return t;
}

/** Belge kâğıdı dokusu: başlık şeridi, satırlar, köşede görsel kutusu (renkliyse renkli) */
export function documentTexture(accent, colorful = false, primary = '#0f766e') {
    return canvasTexture(256, 362, (g, w, h) => {
        g.fillStyle = '#ffffff';
        g.fillRect(0, 0, w, h);
        g.fillStyle = colorful ? accent : '#334155';
        g.fillRect(24, 26, 96, 12);
        g.fillStyle = colorful ? '#94a3b8' : '#cbd5e1';
        for (let y = 60, i = 0; y < h - 34; y += 15, i++) {
            g.fillRect(24, y, (i % 5 === 4 ? 0.48 : 0.8 + (i % 3) * 0.05) * (w - 48), 5);
        }
        if (colorful) {
            const grad = g.createLinearGradient(w - 104, h - 108, w - 24, h - 40);
            grad.addColorStop(0, primary);
            grad.addColorStop(1, accent);
            g.fillStyle = grad;
        } else {
            g.fillStyle = '#e2e8f0';
        }
        g.fillRect(w - 104, h - 108, 80, 64);
    });
}

function hasWebGL() {
    try {
        const c = document.createElement('canvas');
        return !!(window.WebGLRenderingContext && (c.getContext('webgl2') || c.getContext('webgl')));
    } catch {
        return false;
    }
}

/**
 * @param {HTMLElement} root  sahne kökü (içinde canvas[data-scene-canvas])
 * @param {{fov?: number, z?: number, maxDpr?: number}} opts
 * @returns {null | {renderer, scene, camera, pointer, small, reduced, keep, onFrame, start}}
 */
export function createStage(root, opts = {}) {
    if (!hasWebGL()) {
        return null;
    }
    const canvas = root.querySelector('[data-scene-canvas]');
    if (!canvas) {
        return null;
    }
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const small = window.matchMedia('(max-width: 767px)').matches;

    let renderer;
    try {
        renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true, powerPreference: 'low-power' });
    } catch {
        return null;
    }
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, small ? 1.25 : (opts.maxDpr ?? 1.6)));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(opts.fov ?? 35, 1, 0.1, 80);
    camera.position.set(0, 0, opts.z ?? 8);

    const disposables = [];
    const keep = (o) => {
        disposables.push(o);
        return o;
    };

    // Fare: kökün üzerinde -1..1 (dışarı çıkınca yavaşça ortaya döner)
    const pointer = new THREE.Vector2(0, 0);
    const target = new THREE.Vector2(0, 0);
    const host = opts.pointerHost || root;
    host.addEventListener('pointermove', (e) => {
        const r = host === window ? { left: 0, top: 0, width: innerWidth, height: innerHeight } : host.getBoundingClientRect();
        target.set(((e.clientX - r.left) / r.width) * 2 - 1, -(((e.clientY - r.top) / r.height) * 2 - 1));
    }, { passive: true });
    (host === window ? document.documentElement : host).addEventListener('pointerleave', () => target.set(0, 0));

    let frameFn = () => {};
    const render = () => renderer.render(scene, camera);

    const resize = () => {
        const w = canvas.clientWidth;
        const h = canvas.clientHeight;
        if (!w || !h) {
            return;
        }
        renderer.setSize(w, h, false);
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
        opts.onResize?.(w, h);
    };
    const ro = new ResizeObserver(() => {
        resize();
        if (reduced) {
            render();
        }
    });
    ro.observe(canvas);
    resize();

    let running = false;
    let visible = false;
    let raf = 0;
    let last = performance.now();
    let elapsed = 0;
    let acc = 0;

    const frame = () => {
        if (!running) {
            return;
        }
        const now = performance.now();
        const dt = Math.min((now - last) / 1000, 0.05);
        last = now;
        elapsed += dt;
        pointer.lerp(target, 0.06);
        // İsteğe bağlı kare sınırı (geniş arka plan sahnesi için GPU tasarrufu)
        acc += dt;
        if (!opts.fps || acc >= 1 / opts.fps - 0.004) {
            frameFn(acc, elapsed);
            acc = 0;
            render();
        }
        raf = requestAnimationFrame(frame);
    };
    const run = () => {
        if (reduced || running || !visible || document.hidden) {
            return;
        }
        running = true;
        last = performance.now();
        raf = requestAnimationFrame(frame);
    };
    const stop = () => {
        running = false;
        cancelAnimationFrame(raf);
    };

    const io = new IntersectionObserver(([entry]) => {
        visible = entry.isIntersecting;
        visible ? run() : stop();
    }, { rootMargin: '80px' });
    document.addEventListener('visibilitychange', () => (document.hidden ? stop() : run()));
    window.addEventListener('pagehide', (e) => {
        stop();
        if (e.persisted) {
            return;
        }
        ro.disconnect();
        io.disconnect();
        disposables.forEach((d) => d.dispose?.());
        renderer.dispose();
    });
    window.addEventListener('pageshow', (e) => e.persisted && run());

    return {
        THREE, renderer, scene, camera, pointer, small, reduced, keep, render,
        /** Her karede çağrılır: (dt, geçen süre) */
        onFrame(fn) {
            frameFn = fn;
        },
        /** Sahne kurulduktan sonra çağrılır: hareketi azalt tercihinde son hâli tek kare çizer */
        start(settle) {
            if (reduced) {
                settle?.();
                frameFn(0, 4);
                render();
            } else {
                io.observe(canvas);
            }
            root.classList.add('scene-ready');
        },
    };
}
