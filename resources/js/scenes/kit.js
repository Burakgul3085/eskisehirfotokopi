/**
 * Vitrindeki küçük 3D sahnelerin (belge bandı, SSS, alt bilgi) ortak altyapısı.
 *  - Çizici, kamera ve yeniden boyutlandırma
 *  - Döngü yalnız sahne ekrandayken ve sekme öndeyken çalışır
 *  - "Hareketi azalt" tercihinde tek kare çizilir; WebGL yoksa null döner (sayfa CSS yedeğiyle kalır)
 *  - Fare konumu (-1..1) sahne kökü üzerinden izlenir; sayfa kapanırken GPU kaynakları bırakılır
 *  - Akıcılık: en çok 60 kare/sn (120–144 Hz ekranda boşa çizim yok); kareler sürekli yavaşsa çözünürlük
 *    kademeli düşer (sahne aynı kalır, yalnız piksel yoğunluğu azalır)
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

/** Yumuşak ışık noktası dokusu (kıvılcım, hale) */
export function dotTexture() {
    return canvasTexture(64, 64, (g, w) => {
        const r = g.createRadialGradient(w / 2, w / 2, 0, w / 2, w / 2, w / 2);
        r.addColorStop(0, 'rgba(255,255,255,1)');
        r.addColorStop(0.35, 'rgba(255,255,255,0.45)');
        r.addColorStop(1, 'rgba(255,255,255,0)');
        g.fillStyle = r;
        g.fillRect(0, 0, w, w);
    });
}

/**
 * Kıvılcım havuzu: eklemeli karışımla parlayan noktalar. Her nokta ömrü boyunca rengini siyaha söndürür
 * (eklemeli karışımda siyah görünmez), böylece tek bir çizim çağrısıyla yüzlerce parçacık solarak kaybolur.
 */
export function createSparks(parent, keep, { max = 200, size = 0.08 } = {}) {
    const pos = new Float32Array(max * 3);
    const col = new Float32Array(max * 3);
    const vel = new Float32Array(max * 3);
    const base = new Float32Array(max * 3);
    const life = new Float32Array(max);
    const span = new Float32Array(max).fill(1);
    const geo = keep(new THREE.BufferGeometry());
    geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
    geo.setAttribute('color', new THREE.BufferAttribute(col, 3));
    const mat = keep(new THREE.PointsMaterial({ size, map: keep(dotTexture()), vertexColors: true, transparent: true, depthWrite: false, blending: THREE.AdditiveBlending }));
    const points = new THREE.Points(geo, mat);
    points.frustumCulled = false;
    parent.add(points);
    let cursor = 0;
    let alive = 0;

    return {
        /** p: konum, n: adet, color: THREE.Color; speed: dağılma hızı, up: yukarı itki, life: saniye, spread: başlangıç dağınıklığı */
        emit(p, n, color, { speed = 0.6, up = 0.3, life: L = 0.9, spread = 0.05 } = {}) {
            for (let k = 0; k < n; k++) {
                const i = cursor;
                cursor = (cursor + 1) % max;
                const a = Math.random() * Math.PI * 2;
                const z = Math.random() * 2 - 1;
                const r = Math.sqrt(1 - z * z);
                const v = speed * (0.35 + Math.random() * 0.65);
                pos[i * 3] = p.x + (Math.random() - 0.5) * spread;
                pos[i * 3 + 1] = p.y + (Math.random() - 0.5) * spread;
                pos[i * 3 + 2] = p.z + (Math.random() - 0.5) * spread;
                vel[i * 3] = Math.cos(a) * r * v;
                vel[i * 3 + 1] = Math.sin(a) * r * v + up;
                vel[i * 3 + 2] = z * v;
                base[i * 3] = color.r;
                base[i * 3 + 1] = color.g;
                base[i * 3 + 2] = color.b;
                life[i] = span[i] = L * (0.6 + Math.random() * 0.7);
            }
            alive = max;
        },
        update(dt) {
            if (!alive) {
                return;
            }
            let any = 0;
            const damp = Math.exp(-dt * 1.6);
            for (let i = 0; i < max; i++) {
                if (life[i] <= 0) {
                    continue;
                }
                life[i] -= dt;
                const f = Math.max(0, life[i] / span[i]);
                const glow = f * f * (3 - 2 * f); // yumuşak sönüş
                for (let a = 0; a < 3; a++) {
                    const j = i * 3 + a;
                    vel[j] *= damp;
                    pos[j] += vel[j] * dt;
                    col[j] = base[j] * glow;
                }
                vel[i * 3 + 1] -= dt * 0.15;
                any++;
            }
            alive = any;
            geo.attributes.position.needsUpdate = true;
            geo.attributes.color.needsUpdate = true;
        },
    };
}

/** Halka dalgası: ince parlak halka büyüyerek söner (çarpma, iniş anları). */
export function createWaves(parent, keep, color, { pool = 6 } = {}) {
    const geo = keep(new THREE.RingGeometry(0.86, 1, 72));
    const waves = Array.from({ length: pool }, () => {
        const m = new THREE.Mesh(geo, keep(new THREE.MeshBasicMaterial({ color, transparent: true, opacity: 0, side: THREE.DoubleSide, depthWrite: false, blending: THREE.AdditiveBlending })));
        m.visible = false;
        parent.add(m);
        return { m, t: 1, size: 1, dur: 1 };
    });
    let next = 0;
    return {
        /** quaternion verilmezse halka kameraya bakar (XY düzlemi) */
        spawn(p, size = 1, quaternion = null, dur = 0.9) {
            const w = waves[next];
            next = (next + 1) % pool;
            w.m.position.copy(p);
            quaternion ? w.m.quaternion.copy(quaternion) : w.m.quaternion.identity();
            w.t = 0;
            w.size = size;
            w.dur = dur;
            w.m.visible = true;
        },
        update(dt) {
            waves.forEach((w) => {
                if (w.t >= 1) {
                    return;
                }
                w.t = Math.min(1, w.t + dt / w.dur);
                const e = 1 - Math.pow(1 - w.t, 3);
                w.m.scale.setScalar(0.15 + e * w.size);
                w.m.material.opacity = (1 - w.t) * 0.9;
                w.m.visible = w.t < 1;
            });
        },
    };
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
    const maxRatio = Math.min(window.devicePixelRatio || 1, small ? 1.25 : (opts.maxDpr ?? 1.5));
    const minRatio = Math.min(maxRatio, small ? 0.75 : 0.9);
    let ratio = maxRatio;
    renderer.setPixelRatio(ratio);
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
    let slow = 0;
    const fps = opts.fps || 60;

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
        if (acc >= 1 / fps - 0.004) {
            frameFn(acc, elapsed);
            render();
            // Kareler hedefin belirgin altındaysa (yaklaşık 1 sn boyunca) çözünürlüğü bir kademe düşür
            slow = acc > 1.5 / fps ? slow + acc : Math.max(0, slow - acc * 0.5);
            if (slow > 0.9 && ratio > minRatio) {
                ratio = Math.max(minRatio, ratio - 0.15);
                renderer.setPixelRatio(ratio);
                resize();
                slow = 0;
            }
            acc = 0;
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
