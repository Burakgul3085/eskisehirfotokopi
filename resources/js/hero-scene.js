/**
 * Ana sayfa karşılama alanının 3D sahnesi (Three.js). Yalnız ana sayfada, sayfa açıldıktan sonra ayrı parça olarak yüklenir.
 *
 *  - Ortada stilize bir fotokopi makinesi; aralıklarla içinden belge çıkar ve kitaplardan birine uçar.
 *  - Çevresinde süzülen kitaplar: kapaklar vitrindeki gerçek ürün görselleri (yoksa renk geçişli soyut kapak).
 *    Üzerine gelinen kitap öne döner ve adı görünür; tıklanınca ürün sayfası açılır.
 *  - Fare sahneyi hafifçe çevirir; sayfa kaydırılınca kamera uzaklaşır ve sahne solar.
 *  - Performans: görünmezken ve sekme arka plandayken çizim durur; telefonda daha az nesne ve düşük çözünürlük.
 *  - "Hareketi azalt" tercihinde tek kare çizilir (animasyon yok). WebGL yoksa sahne hiç kurulmaz (CSS yedeği kalır).
 *
 * Renkler sitenin tema değişkenlerinden (--color-primary, --color-accent) okunur; yönetimden değişen renge uyar.
 */
import * as THREE from 'three';
import { RoundedBoxGeometry } from 'three/examples/jsm/geometries/RoundedBoxGeometry.js';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';

const BOOK = { w: 0.84, h: 1.19, d: 0.18 };

const clamp = (v, a, b) => Math.min(b, Math.max(a, v));
const lerp = (a, b, t) => a + (b - a) * t;
const easeOutBack = (t) => 1 + 2.2 * Math.pow(t - 1, 3) + 1.2 * Math.pow(t - 1, 2);
const easeInOut = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);

function cssColor(name, fallback) {
    const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    try {
        return new THREE.Color(v || fallback);
    } catch {
        return new THREE.Color(fallback);
    }
}

function canvasTexture(w, h, draw) {
    const c = document.createElement('canvas');
    c.width = w;
    c.height = h;
    draw(c.getContext('2d'), w, h);
    const t = new THREE.CanvasTexture(c);
    t.colorSpace = THREE.SRGBColorSpace;
    t.anisotropy = 4;
    return t;
}

/** Sayfa kenarı: krem zemin üzerinde ince yaprak çizgileri */
function pagesTexture() {
    return canvasTexture(64, 256, (g, w, h) => {
        g.fillStyle = '#f6f1e6';
        g.fillRect(0, 0, w, h);
        for (let x = 2; x < w; x += 3) {
            g.fillStyle = `rgba(120, 100, 70, ${0.06 + Math.random() * 0.08})`;
            g.fillRect(x, 0, 1, h);
        }
    });
}

/** Görseli olmayan kitap için soyut kapak (yazı yok: vitrin metinleri yönetimden gelir) */
function abstractCover(a, b, seed) {
    return canvasTexture(256, 360, (g, w, h) => {
        const grad = g.createLinearGradient(0, 0, w, h);
        grad.addColorStop(0, a);
        grad.addColorStop(1, b);
        g.fillStyle = grad;
        g.fillRect(0, 0, w, h);
        g.globalAlpha = 0.18;
        g.fillStyle = '#ffffff';
        for (let i = 0; i < 3; i++) {
            g.beginPath();
            g.arc(((seed * 97 + i * 71) % w), ((seed * 53 + i * 131) % h), 40 + ((seed + i) % 4) * 26, 0, Math.PI * 2);
            g.fill();
        }
        g.globalAlpha = 0.9;
        g.fillRect(28, 46, w * 0.55, 10);
        g.globalAlpha = 0.55;
        g.fillRect(28, 66, w * 0.38, 7);
        g.fillRect(28, h - 46, w * 0.3, 6);
    });
}

/** Belge kâğıdı: başlık şeridi ve satırlar */
function documentTexture(accent) {
    return canvasTexture(256, 360, (g, w, h) => {
        g.fillStyle = '#ffffff';
        g.fillRect(0, 0, w, h);
        g.fillStyle = accent;
        g.fillRect(24, 26, 90, 12);
        g.fillStyle = '#cbd5e1';
        for (let y = 62, i = 0; y < h - 30; y += 16, i++) {
            g.fillRect(24, y, (i % 5 === 4 ? 0.5 : 0.82 + (i % 3) * 0.04) * (w - 48), 5);
        }
        g.fillStyle = '#e2e8f0';
        g.fillRect(w - 98, h - 100, 74, 60);
    });
}

/** Zemindeki yumuşak gölge */
function shadowTexture() {
    return canvasTexture(256, 256, (g, w, h) => {
        const r = g.createRadialGradient(w / 2, h / 2, 0, w / 2, h / 2, w / 2);
        r.addColorStop(0, 'rgba(0, 20, 18, 0.55)');
        r.addColorStop(1, 'rgba(0, 20, 18, 0)');
        g.fillStyle = r;
        g.fillRect(0, 0, w, h);
    });
}

/** Parçacık için yumuşak nokta */
function dotTexture() {
    return canvasTexture(64, 64, (g, w) => {
        const r = g.createRadialGradient(w / 2, w / 2, 0, w / 2, w / 2, w / 2);
        r.addColorStop(0, 'rgba(255,255,255,1)');
        r.addColorStop(1, 'rgba(255,255,255,0)');
        g.fillStyle = r;
        g.fillRect(0, 0, w, w);
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

export function mount(root) {
    if (!hasWebGL()) {
        return;
    }
    const canvas = root.querySelector('[data-hero-canvas]');
    const tip = root.querySelector('[data-hero-tip]');
    const section = root.closest('section') || root;
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const small = window.matchMedia('(max-width: 767px)').matches;
    let books = [];
    try {
        books = JSON.parse(root.querySelector('[data-hero-books]')?.textContent || '[]');
    } catch {
        books = [];
    }

    const primary = cssColor('--color-primary', '#0f766e');
    const accent = cssColor('--color-accent', '#f59e0b');
    const primaryHex = '#' + primary.getHexString();
    const accentHex = '#' + accent.getHexString();

    // ---- Çizici, sahne, kamera ----
    let renderer;
    try {
        renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true, powerPreference: 'high-performance' });
    } catch {
        return;
    }
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, small ? 1.25 : 1.75));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.05;

    const scene = new THREE.Scene();
    const pmrem = new THREE.PMREMGenerator(renderer);
    scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;
    scene.environmentIntensity = 0.75;

    const camera = new THREE.PerspectiveCamera(small ? 42 : 34, 1, 0.1, 60);
    const baseZ = small ? 11 : 10.5;
    camera.position.set(0, 0.45, baseZ);
    camera.lookAt(0, 0.25, 0);

    scene.add(new THREE.HemisphereLight(0xffffff, primary.clone().multiplyScalar(0.4), 0.9));
    const key = new THREE.DirectionalLight(0xfff4e0, 2.2);
    key.position.set(3.5, 5, 6);
    scene.add(key);
    const rim = new THREE.PointLight(accent, 30, 14, 2);
    rim.position.set(-3.5, 2.5, -2.5);
    scene.add(rim);
    const fill = new THREE.PointLight(primary.clone().lerp(new THREE.Color('#ffffff'), 0.5), 18, 12, 2);
    fill.position.set(3, -2, 3);
    scene.add(fill);

    // Fare ile dönen ana grup
    const rig = new THREE.Group();
    scene.add(rig);

    const disposables = [];
    const keep = (o) => {
        disposables.push(o);
        return o;
    };

    // ---- Fotokopi makinesi ----
    const printer = new THREE.Group();
    printer.position.set(0, -0.62, 0);
    printer.scale.setScalar(small ? 1.05 : 1.22);
    rig.add(printer);

    const shell = keep(new THREE.MeshPhysicalMaterial({ color: 0xf3f6f5, roughness: 0.42, metalness: 0.05, clearcoat: 0.6, clearcoatRoughness: 0.35 }));
    const graphite = keep(new THREE.MeshStandardMaterial({ color: 0x1e293b, roughness: 0.35, metalness: 0.3 }));
    const glass = keep(new THREE.MeshPhysicalMaterial({ color: primary.clone().multiplyScalar(0.35), roughness: 0.08, metalness: 0.1, transmission: 0, clearcoat: 1 }));
    const glow = keep(new THREE.MeshStandardMaterial({ color: accent, emissive: accent, emissiveIntensity: 1.4, roughness: 0.4 }));
    const screen = keep(new THREE.MeshStandardMaterial({ color: primary, emissive: primary.clone().lerp(new THREE.Color('#ffffff'), 0.25), emissiveIntensity: 1.1 }));

    const body = new THREE.Mesh(keep(new RoundedBoxGeometry(2.5, 1.05, 1.75, 4, 0.16)), shell);
    printer.add(body);
    const base = new THREE.Mesh(keep(new RoundedBoxGeometry(2.56, 0.16, 1.8, 3, 0.07)), graphite);
    base.position.y = -0.56;
    printer.add(base);
    const lid = new THREE.Mesh(keep(new RoundedBoxGeometry(2.42, 0.14, 1.66, 3, 0.06)), shell);
    lid.position.set(0, 0.6, -0.02);
    printer.add(lid);
    const scanGlass = new THREE.Mesh(keep(new THREE.BoxGeometry(1.9, 0.02, 1.2)), glass);
    scanGlass.position.set(-0.1, 0.68, -0.05);
    printer.add(scanGlass);
    // Ön çıkış yuvası ve ışık şeridi
    const slot = new THREE.Mesh(keep(new RoundedBoxGeometry(1.55, 0.12, 0.08, 2, 0.04)), graphite);
    slot.position.set(-0.15, 0.12, 0.88);
    printer.add(slot);
    const strip = new THREE.Mesh(keep(new THREE.BoxGeometry(1.4, 0.025, 0.02)), glow);
    strip.position.set(-0.15, -0.08, 0.89);
    printer.add(strip);
    // Kontrol paneli
    const panel = new THREE.Mesh(keep(new RoundedBoxGeometry(0.62, 0.1, 0.42, 2, 0.04)), graphite);
    panel.position.set(0.86, 0.62, 0.62);
    panel.rotation.x = -0.25;
    printer.add(panel);
    const display = new THREE.Mesh(keep(new THREE.PlaneGeometry(0.34, 0.2)), screen);
    display.position.set(0.8, 0.675, 0.6);
    display.rotation.x = -Math.PI / 2 - 0.25;
    printer.add(display);
    const btnGeo = keep(new THREE.CylinderGeometry(0.03, 0.03, 0.03, 16));
    [0, 1, 2].forEach((i) => {
        const b = new THREE.Mesh(btnGeo, i === 0 ? glow : shell);
        b.position.set(1.05, 0.68, 0.48 + i * 0.09);
        printer.add(b);
    });
    // Çıkış tepsisi ve arkadaki kâğıt destesi
    const tray = new THREE.Mesh(keep(new RoundedBoxGeometry(1.5, 0.04, 0.7, 2, 0.02)), graphite);
    tray.position.set(-0.15, -0.2, 1.12);
    tray.rotation.x = 0.12;
    printer.add(tray);
    const paperMat = keep(new THREE.MeshStandardMaterial({ color: 0xffffff, roughness: 0.8 }));
    const stack = new THREE.Mesh(keep(new THREE.BoxGeometry(1.2, 0.16, 0.9)), paperMat);
    stack.position.set(-0.1, 0.4, -0.75);
    stack.rotation.x = -0.5;
    printer.add(stack);

    // Zemin gölgesi
    const shadowMat = keep(new THREE.MeshBasicMaterial({ map: keep(shadowTexture()), transparent: true, depthWrite: false }));
    const shadow = new THREE.Mesh(keep(new THREE.PlaneGeometry(4.8, 3.4)), shadowMat);
    shadow.rotation.x = -Math.PI / 2;
    shadow.position.set(0, -1.3, 0.1);
    rig.add(shadow);

    // ---- Kitaplar ----
    const loader = new THREE.TextureLoader();
    const pagesMap = keep(pagesTexture());
    const pagesMat = keep(new THREE.MeshStandardMaterial({ map: pagesMap, roughness: 0.9 }));
    const bookGeo = keep(new RoundedBoxGeometry(BOOK.w, BOOK.h, BOOK.d, 2, 0.025));

    // Yerleşim: makinenin çevresinde, derinlikleri farklı bir halka
    const slots = small
        ? [[-2.0, 1.55, -0.6], [2.05, 1.35, -0.2], [-2.35, -0.35, 0.6], [2.3, -0.55, 0.9], [0.1, 2.25, -1.6]]
        : [[-2.4, 1.35, -0.5], [-1.0, 2.15, -1.9], [0.95, 2.3, -1.3], [2.5, 1.4, -0.5], [3.0, 0.0, -1.1], [-2.9, -0.2, 0.2], [2.4, -0.55, 0.9]];
    const palette = [[primaryHex, '#0b3b36'], [accentHex, '#b45309'], ['#334155', '#0f172a'], ['#0e7490', '#164e63'], ['#7c3aed', '#4c1d95'], ['#be123c', '#881337'], ['#15803d', '#14532d']];

    const bookObjs = slots.map((pos, i) => {
        const info = books[i % Math.max(1, books.length)] || null;
        const pair = palette[i % palette.length];
        const coverColor = new THREE.Color(pair[1]);
        const coverMat = keep(new THREE.MeshPhysicalMaterial({ map: keep(abstractCover(pair[0], pair[1], i + 3)), roughness: 0.38, clearcoat: 0.5, clearcoatRoughness: 0.3 }));
        const edgeMat = keep(new THREE.MeshStandardMaterial({ color: coverColor, roughness: 0.5 }));
        // RoundedBox yüz sırası: +x, -x, +y, -y, +z, -z
        const mesh = new THREE.Mesh(bookGeo, [pagesMat, edgeMat, pagesMat, pagesMat, coverMat, edgeMat]);
        const g = new THREE.Group();
        g.add(mesh);
        g.position.set(pos[0], pos[1], pos[2]);
        const baseRot = new THREE.Euler(((i * 37) % 20 - 10) * 0.012, -pos[0] * 0.09 + ((i * 13) % 10 - 5) * 0.03, ((i * 29) % 16 - 8) * 0.02);
        g.rotation.copy(baseRot);
        g.scale.setScalar(0.0001);
        rig.add(g);

        const obj = { g, mesh, base: new THREE.Vector3(...pos), baseRot, phase: i * 1.7, speed: 0.55 + (i % 3) * 0.12, hover: 0, pulse: 0, info, intro: i * 0.09 };

        if (info && info.img) {
            loader.load(info.img, (tex) => {
                tex.colorSpace = THREE.SRGBColorSpace;
                tex.anisotropy = renderer.capabilities.getMaxAnisotropy();
                // Kapağı "cover" yerleşimiyle kırp (görsel oranı kitap oranından farklı olabilir)
                const img = tex.image;
                const target = BOOK.w / BOOK.h;
                const ratio = img.width / img.height;
                if (ratio > target) {
                    tex.repeat.set(target / ratio, 1);
                    tex.offset.set((1 - target / ratio) / 2, 0);
                } else {
                    tex.repeat.set(1, ratio / target);
                    tex.offset.set(0, (1 - ratio / target) / 2);
                }
                coverMat.map?.dispose();
                coverMat.map = keep(tex);
                coverMat.needsUpdate = true;
                // Sırt ve arka kapak: görselin ortalama rengi
                try {
                    const c = document.createElement('canvas');
                    c.width = c.height = 8;
                    const g2 = c.getContext('2d');
                    g2.drawImage(img, 0, 0, 8, 8);
                    const d = g2.getImageData(0, 0, 8, 8).data;
                    let r = 0, gg = 0, b = 0;
                    for (let k = 0; k < d.length; k += 4) {
                        r += d[k];
                        gg += d[k + 1];
                        b += d[k + 2];
                    }
                    const n = d.length / 4;
                    edgeMat.color.setRGB(r / n / 255, gg / n / 255, b / n / 255, THREE.SRGBColorSpace).multiplyScalar(0.8);
                } catch {
                    /* renk okunamadı: palet rengi kalır */
                }
                if (reduced) {
                    render();
                }
            });
        }
        return obj;
    });

    // ---- Uçan belgeler ----
    const docMap = keep(documentTexture(accentHex));
    const docGeo = keep(new THREE.PlaneGeometry(0.86, 1.2, 1, 10));
    const p = docGeo.attributes.position;
    for (let k = 0; k < p.count; k++) {
        p.setZ(k, 0.06 * Math.sin((p.getY(k) / 1.2 + 0.5) * Math.PI));
    }
    docGeo.computeVertexNormals();
    const sheets = [];
    const spawnSheet = () => {
        const mat = new THREE.MeshStandardMaterial({ map: docMap, roughness: 0.7, side: THREE.DoubleSide, transparent: true });
        const m = new THREE.Mesh(docGeo, mat);
        const target = bookObjs[Math.floor(Math.random() * bookObjs.length)];
        const start = rig.worldToLocal(printer.localToWorld(new THREE.Vector3(-0.15, 0.12, 0.8)));
        const out = rig.worldToLocal(printer.localToWorld(new THREE.Vector3(-0.15, 0.16, 1.6)));
        const end = target.base.clone();
        const ctrl = out.clone().lerp(end, 0.5).add(new THREE.Vector3((Math.random() - 0.5) * 2, 2.2, 1.2));
        m.position.copy(start);
        m.rotation.set(-1.25, 0, 0);
        rig.add(m);
        sheets.push({ m, t: 0, start, out, ctrl, end, target, spin: (Math.random() - 0.5) * 2.4 });
        strip.material.emissiveIntensity = 3.2;
    };

    // ---- Parçacıklar ----
    const count = small ? 90 : 220;
    const pts = new Float32Array(count * 3);
    for (let k = 0; k < count; k++) {
        pts[k * 3] = (Math.random() - 0.5) * 12;
        pts[k * 3 + 1] = (Math.random() - 0.5) * 7;
        pts[k * 3 + 2] = (Math.random() - 0.5) * 6 - 1;
    }
    const dustGeo = keep(new THREE.BufferGeometry());
    dustGeo.setAttribute('position', new THREE.BufferAttribute(pts, 3));
    const dustMat = keep(new THREE.PointsMaterial({ size: 0.06, map: keep(dotTexture()), transparent: true, opacity: 0.55, depthWrite: false, blending: THREE.AdditiveBlending, color: 0xffffff }));
    const dust = new THREE.Points(dustGeo, dustMat);
    scene.add(dust);

    // ---- Etkileşim ----
    const pointer = new THREE.Vector2(0, 0); // -1..1, sahne dönüşü
    const ndc = new THREE.Vector2(9, 9); // tuval içi, ışın için
    const ray = new THREE.Raycaster();
    let hovered = null;
    let scrollFade = 0;

    const onMove = (e) => {
        const r = section.getBoundingClientRect();
        pointer.set(((e.clientX - r.left) / r.width) * 2 - 1, ((e.clientY - r.top) / r.height) * 2 - 1);
        section.style.setProperty('--hx', pointer.x.toFixed(3));
        section.style.setProperty('--hy', pointer.y.toFixed(3));
        section.style.setProperty('--mx', `${e.clientX - r.left}px`);
        section.style.setProperty('--my', `${e.clientY - r.top}px`);
        const c = canvas.getBoundingClientRect();
        ndc.set(((e.clientX - c.left) / c.width) * 2 - 1, -((e.clientY - c.top) / c.height) * 2 + 1);
        if (tip) {
            tip.style.transform = `translate(${e.clientX - c.left + 14}px, ${e.clientY - c.top + 14}px)`;
        }
    };
    const onLeave = () => {
        pointer.set(0, 0);
        ndc.set(9, 9);
    };
    const onClick = () => {
        if (hovered?.info?.url) {
            window.location.href = hovered.info.url;
        }
    };
    section.addEventListener('pointermove', onMove, { passive: true });
    section.addEventListener('pointerleave', onLeave);
    canvas.addEventListener('click', onClick);

    const onScroll = () => {
        const r = section.getBoundingClientRect();
        scrollFade = clamp(-r.top / Math.max(1, r.height), 0, 1);
    };
    window.addEventListener('scroll', onScroll, { passive: true });

    const resize = () => {
        const w = canvas.clientWidth;
        const h = canvas.clientHeight;
        if (!w || !h) {
            return;
        }
        renderer.setSize(w, h, false);
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
    };
    const ro = new ResizeObserver(() => {
        resize();
        if (reduced) {
            render();
        }
    });
    ro.observe(canvas);
    resize();

    // ---- Döngü ----
    let last = performance.now();
    let elapsed = 0;
    let running = false;
    let visible = true;
    let nextSheet = 1.6;
    let raf = 0;

    function update(dt, t) {
        // Fare: yumuşak dönüş
        rig.rotation.y = lerp(rig.rotation.y, pointer.x * 0.28, 0.05);
        rig.rotation.x = lerp(rig.rotation.x, pointer.y * 0.12, 0.05);
        // Kaydırma: kamera uzaklaşır, tuval solar
        camera.position.z = lerp(camera.position.z, baseZ + scrollFade * 4, 0.12);
        canvas.style.opacity = String(1 - scrollFade * 0.9);

        printer.position.y = -0.62 + Math.sin(t * 0.8) * 0.04;
        strip.material.emissiveIntensity = lerp(strip.material.emissiveIntensity, 1.2 + Math.sin(t * 2) * 0.25, 0.04);
        screen.emissiveIntensity = 1 + Math.sin(t * 3.1) * 0.15;

        // Işın: üzerine gelinen kitap
        ray.setFromCamera(ndc, camera);
        const hit = ray.intersectObjects(bookObjs.map((b) => b.mesh), false)[0];
        const now = hit ? bookObjs.find((b) => b.mesh === hit.object) : null;
        if (now !== hovered) {
            hovered = now;
            canvas.style.cursor = hovered?.info?.url ? 'pointer' : '';
            if (tip) {
                tip.textContent = hovered?.info?.title || '';
                tip.dataset.show = hovered?.info?.title ? '1' : '0';
            }
        }

        bookObjs.forEach((b) => {
            const intro = clamp((t - 0.25 - b.intro) / 0.9, 0, 1);
            b.hover = lerp(b.hover, b === hovered ? 1 : 0, 0.12);
            b.pulse = Math.max(0, b.pulse - dt * 2.2);
            const s = (intro < 1 ? easeOutBack(intro) : 1) * (1 + b.hover * 0.14 + Math.sin(b.pulse * Math.PI) * 0.1);
            b.g.scale.setScalar(Math.max(0.0001, s));
            b.g.position.set(
                b.base.x,
                b.base.y + Math.sin(t * b.speed + b.phase) * 0.14 + (1 - intro) * -0.6,
                b.base.z + b.hover * 0.6,
            );
            b.g.rotation.set(
                lerp(b.baseRot.x + Math.sin(t * 0.5 + b.phase) * 0.06, 0, b.hover),
                lerp(b.baseRot.y + Math.sin(t * 0.35 + b.phase) * 0.22, -rig.rotation.y, b.hover),
                lerp(b.baseRot.z + Math.cos(t * 0.45 + b.phase) * 0.05, 0, b.hover),
            );
        });

        // Belgeler
        nextSheet -= dt;
        if (nextSheet <= 0 && sheets.length < 3) {
            spawnSheet();
            nextSheet = 2.6 + Math.random() * 1.6;
        }
        for (let k = sheets.length - 1; k >= 0; k--) {
            const s = sheets[k];
            s.t += dt / 3.2;
            const m = s.m;
            const EXIT = 0.24;
            if (s.t < EXIT) {
                // Makineden çıkış: eğik, öne doğru kayar (kameradan görünsün diye yatay değil)
                const u = easeInOut(s.t / EXIT);
                m.position.lerpVectors(s.start, s.out, u);
                m.rotation.set(-1.25 + u * 0.35, 0, 0);
                m.material.opacity = Math.min(1, u * 3);
            } else {
                // Eğri üzerinde hedef kitaba uçuş: kameraya döner, sona doğru küçülüp söner
                const u = easeInOut((s.t - EXIT) / (1 - EXIT));
                const a = s.out.clone().lerp(s.ctrl, u);
                const b2 = s.ctrl.clone().lerp(s.end, u);
                m.position.copy(a.lerp(b2, u));
                m.rotation.set(lerp(-0.9, 0.05, Math.min(1, u * 2.2)), Math.sin(u * Math.PI) * s.spin * 0.6, Math.sin(u * Math.PI) * 0.35 * s.spin);
                m.scale.setScalar(lerp(1, 0.4, u * u * u));
                m.material.opacity = u > 0.85 ? 1 - (u - 0.85) / 0.15 : 1;
            }
            if (s.t >= 1) {
                s.target.pulse = 1;
                rig.remove(m);
                m.material.dispose();
                sheets.splice(k, 1);
            }
        }

        // Toz: yavaşça yükselir
        const arr = dustGeo.attributes.position.array;
        for (let k = 1; k < arr.length; k += 3) {
            arr[k] += dt * 0.12;
            if (arr[k] > 3.5) {
                arr[k] = -3.5;
            }
        }
        dustGeo.attributes.position.needsUpdate = true;
        dust.rotation.y = t * 0.015;
    }

    function render() {
        renderer.render(scene, camera);
    }

    function frame() {
        if (!running) {
            return;
        }
        const now = performance.now();
        const dt = Math.min((now - last) / 1000, 0.05);
        last = now;
        elapsed += dt;
        update(dt, elapsed);
        render();
        raf = requestAnimationFrame(frame);
    }

    const start = () => {
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

    if (reduced) {
        // Tek kare: kitaplar yerinde, belge yok
        bookObjs.forEach((b) => b.g.scale.setScalar(1));
        render();
    } else {
        const io = new IntersectionObserver(([entry]) => {
            visible = entry.isIntersecting;
            visible ? start() : stop();
        });
        io.observe(canvas);
        document.addEventListener('visibilitychange', () => (document.hidden ? stop() : start()));
        start();
    }

    section.classList.add('hero-3d-ready');

    // Sayfa gerçekten kapanırken GPU kaynaklarını bırak; geri tuşu önbelleğine (bfcache) giriyorsa yalnız durdur.
    window.addEventListener('pagehide', (e) => {
        stop();
        if (e.persisted) {
            return;
        }
        ro.disconnect();
        disposables.forEach((d) => d.dispose?.());
        pmrem.dispose();
        renderer.dispose();
    });
    window.addEventListener('pageshow', (e) => {
        if (e.persisted) {
            start();
        }
    });
}
