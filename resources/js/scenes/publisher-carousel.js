/**
 * Yayınevleri sayfası 3D sahnesi: ışıklı bir kaidenin çevresinde yavaşça dönen kitap karuseli.
 *
 *  - Her kitap bir yayınevi: kapakta logosu (yoksa baş harfleri), adı ve ürün sayısı. Veri sayfadaki
 *    <script type="application/json" data-carousel> içinden gelir (en çok ürünü olan yayınevleri).
 *  - Fare karuseli çevirir; üzerine gelinen kitap halkadan dışarı kayar, büyür ve adı ipucunda görünür;
 *    tıklanınca yayınevi sayfası açılır. Ortada nabız gibi atan ışık sütunu ve yükselen parçacıklar.
 *  - Az yayınevi varsa halka dolsun diye kitaplar tekrarlanır.
 */
import { RoundedBoxGeometry } from 'three/examples/jsm/geometries/RoundedBoxGeometry.js';
import { THREE, canvasTexture, createStage, cssColor, lerp } from './kit.js';

const BOOK = { w: 0.86, h: 1.2, d: 0.2 };
const PALETTE = [
    ['#0f766e', '#064e3b'], ['#f59e0b', '#b45309'], ['#2563eb', '#1e3a8a'], ['#db2777', '#831843'],
    ['#7c3aed', '#4c1d95'], ['#0891b2', '#164e63'], ['#16a34a', '#14532d'], ['#ea580c', '#7c2d12'],
];

function dotTexture() {
    return canvasTexture(64, 64, (g, w) => {
        const r = g.createRadialGradient(w / 2, w / 2, 0, w / 2, w / 2, w / 2);
        r.addColorStop(0, 'rgba(255,255,255,1)');
        r.addColorStop(0.4, 'rgba(255,255,255,0.4)');
        r.addColorStop(1, 'rgba(255,255,255,0)');
        g.fillStyle = r;
        g.fillRect(0, 0, w, w);
    });
}

function beamTexture() {
    return canvasTexture(8, 256, (g, w, h) => {
        const grad = g.createLinearGradient(0, 0, 0, h);
        grad.addColorStop(0, 'rgba(255,255,255,0)');
        grad.addColorStop(0.6, 'rgba(255,255,255,0.5)');
        grad.addColorStop(1, 'rgba(255,255,255,0.05)');
        g.fillStyle = grad;
        g.fillRect(0, 0, w, h);
    });
}

/** Kapak: renk geçişi, beyaz logo kutusu (logo ya da baş harfler), alt bantta ad ve ürün sayısı */
function drawCover(g, w, h, pub, [a, b], logo) {
    const grad = g.createLinearGradient(0, 0, w, h);
    grad.addColorStop(0, a);
    grad.addColorStop(1, b);
    g.fillStyle = grad;
    g.fillRect(0, 0, w, h);
    g.globalAlpha = 0.14;
    g.fillStyle = '#fff';
    g.beginPath();
    g.arc(w * 0.85, h * 0.12, w * 0.45, 0, Math.PI * 2);
    g.fill();
    g.beginPath();
    g.arc(w * 0.05, h * 0.7, w * 0.3, 0, Math.PI * 2);
    g.fill();
    g.globalAlpha = 1;
    // Sırt gölgesi
    const spine = g.createLinearGradient(0, 0, 26, 0);
    spine.addColorStop(0, 'rgba(0,0,0,0.35)');
    spine.addColorStop(1, 'rgba(0,0,0,0)');
    g.fillStyle = spine;
    g.fillRect(0, 0, 26, h);
    // Logo kutusu
    const bx = w * 0.16, by = h * 0.16, bw = w * 0.68, bh = w * 0.68, r = 26;
    g.fillStyle = '#ffffff';
    g.beginPath();
    g.roundRect(bx, by, bw, bh, r);
    g.fill();
    if (logo) {
        const s = Math.min((bw - 36) / logo.width, (bh - 36) / logo.height);
        g.drawImage(logo, bx + (bw - logo.width * s) / 2, by + (bh - logo.height * s) / 2, logo.width * s, logo.height * s);
    } else {
        g.fillStyle = b;
        g.font = '800 120px Inter, "Segoe UI", system-ui, sans-serif';
        g.textAlign = 'center';
        g.textBaseline = 'middle';
        g.fillText(pub.initials || '?', w / 2, by + bh / 2 + 6);
    }
    // Ad ve ürün sayısı
    g.fillStyle = '#ffffff';
    g.textAlign = 'center';
    g.textBaseline = 'alphabetic';
    let size = 40;
    g.font = `800 ${size}px Inter, "Segoe UI", system-ui, sans-serif`;
    while (g.measureText(pub.name).width > w - 50 && size > 22) {
        size -= 2;
        g.font = `800 ${size}px Inter, "Segoe UI", system-ui, sans-serif`;
    }
    const words = pub.name.split(' ');
    const lines = [];
    let line = '';
    words.forEach((word) => {
        const test = line ? line + ' ' + word : word;
        if (g.measureText(test).width > w - 50 && line) {
            lines.push(line);
            line = word;
        } else {
            line = test;
        }
    });
    lines.push(line);
    lines.slice(0, 2).forEach((l, i) => g.fillText(l, w / 2, h * 0.73 + i * (size + 6)));
    g.globalAlpha = 0.75;
    g.font = '600 26px Inter, "Segoe UI", system-ui, sans-serif';
    g.fillText(String(pub.count ?? ''), w / 2, h - 34);
    g.globalAlpha = 1;
}

export function mount(root) {
    let pubs = [];
    try {
        pubs = JSON.parse(root.querySelector('[data-carousel]')?.textContent || '[]');
    } catch {
        pubs = [];
    }
    if (!pubs.length) {
        return;
    }
    const section = root.closest('section') || root;
    const stage = createStage(root, { fov: 30, z: 9, maxDpr: 1.75, pointerHost: section });
    if (!stage) {
        return;
    }
    const { scene, camera, pointer, keep, small, renderer } = stage;
    const canvas = renderer.domElement;
    const tip = root.querySelector('[data-carousel-tip]');
    const countLabel = root.dataset.countLabel || '';

    const primary = cssColor('--color-primary', '#0f766e');
    const accent = cssColor('--color-accent', '#f59e0b');
    const white = new THREE.Color('#ffffff');
    PALETTE[0] = ['#' + primary.clone().lerp(white, 0.1).getHexString(), '#' + primary.clone().multiplyScalar(0.45).getHexString()];
    PALETTE[1] = ['#' + accent.getHexString(), '#' + accent.clone().multiplyScalar(0.6).getHexString()];
    renderer.toneMappingExposure = 1.08;

    const fit = () => {
        const aspect = canvas.clientWidth / Math.max(1, canvas.clientHeight);
        const back = aspect < 1.3 ? (1.3 - aspect) * 6 : 0;
        camera.position.set(0, 2.9 + back * 0.3, 10.2 + back);
        camera.lookAt(0, -0.25, 0);
    };
    fit();
    new ResizeObserver(fit).observe(canvas);

    scene.add(new THREE.HemisphereLight(0xffffff, primary.clone().multiplyScalar(0.4), 1.4));
    const key = new THREE.DirectionalLight(0xfff4e0, 1.8);
    key.position.set(2, 5, 6);
    scene.add(key);
    const coreLight = new THREE.PointLight(accent, 10, 7, 2);
    coreLight.position.set(0, 0.6, 0);
    scene.add(coreLight);

    const rig = new THREE.Group();
    rig.position.y = -0.15;
    scene.add(rig);

    // ---- Kaide ve ışık sütunu ----
    const pedMat = keep(new THREE.MeshPhysicalMaterial({ color: primary.clone().multiplyScalar(0.35), metalness: 0.6, roughness: 0.28, clearcoat: 1, transparent: true, opacity: 0.92 }));
    const ped = new THREE.Mesh(keep(new THREE.CylinderGeometry(3.25, 3.4, 0.16, 96)), pedMat);
    ped.position.y = -BOOK.h / 2 - 0.1;
    rig.add(ped);
    const glowMat = keep(new THREE.MeshStandardMaterial({ color: accent, emissive: accent, emissiveIntensity: 2 }));
    const pedRing = new THREE.Mesh(keep(new THREE.TorusGeometry(3.3, 0.018, 8, 160)), glowMat);
    pedRing.rotation.x = Math.PI / 2;
    pedRing.position.y = ped.position.y + 0.08;
    rig.add(pedRing);
    const innerRing = new THREE.Mesh(keep(new THREE.TorusGeometry(1.05, 0.03, 10, 100)), keep(new THREE.MeshStandardMaterial({ color: primary.clone().lerp(white, 0.4), emissive: primary.clone().lerp(white, 0.4), emissiveIntensity: 1.6 })));
    innerRing.rotation.x = Math.PI / 2;
    innerRing.position.y = ped.position.y + 0.09;
    rig.add(innerRing);
    const beamMat = keep(new THREE.MeshBasicMaterial({ map: keep(beamTexture()), color: accent.clone().lerp(white, 0.35), transparent: true, opacity: 0.35, blending: THREE.AdditiveBlending, depthWrite: false, side: THREE.DoubleSide }));
    const beam = new THREE.Mesh(keep(new THREE.CylinderGeometry(0.75, 1.0, 3.2, 48, 1, true)), beamMat);
    beam.position.y = ped.position.y + 1.6;
    rig.add(beam);
    const dotMap = keep(dotTexture());
    const core = new THREE.Sprite(keep(new THREE.SpriteMaterial({ map: dotMap, color: accent.clone().lerp(white, 0.3), transparent: true, opacity: 0.9, blending: THREE.AdditiveBlending, depthWrite: false })));
    core.position.y = 0.2;
    core.scale.setScalar(1.6);
    rig.add(core);

    // ---- Kitap halkası ----
    const carousel = new THREE.Group();
    rig.add(carousel);
    const slots = small ? Math.max(6, Math.min(8, pubs.length)) : Math.max(8, Math.min(12, pubs.length));
    const R = small ? 2.35 : 2.75;
    const bookGeo = keep(new RoundedBoxGeometry(BOOK.w, BOOK.h, BOOK.d, 2, 0.025));
    const pagesMat = keep(new THREE.MeshStandardMaterial({ color: 0xf6f1e6, roughness: 0.9 }));
    const books = [];
    for (let i = 0; i < slots; i++) {
        const pub = pubs[i % pubs.length];
        const colors = PALETTE[(i % pubs.length) % PALETTE.length];
        const c = document.createElement('canvas');
        c.width = 360;
        c.height = 500;
        const ctx = c.getContext('2d');
        drawCover(ctx, c.width, c.height, pub, colors, null);
        const tex = keep(new THREE.CanvasTexture(c));
        tex.colorSpace = THREE.SRGBColorSpace;
        tex.anisotropy = 4;
        if (pub.logo) {
            const img = new Image();
            img.onload = () => {
                drawCover(ctx, c.width, c.height, pub, colors, img);
                tex.needsUpdate = true;
            };
            img.src = pub.logo;
        }
        const cover = keep(new THREE.MeshPhysicalMaterial({ map: tex, roughness: 0.35, clearcoat: 0.6, clearcoatRoughness: 0.25 }));
        const edge = keep(new THREE.MeshStandardMaterial({ color: new THREE.Color(colors[1]), roughness: 0.5 }));
        // RoundedBox yüz sırası: +x, -x, +y, -y, +z, -z
        const mesh = new THREE.Mesh(bookGeo, [pagesMat, edge, pagesMat, pagesMat, cover, edge]);
        const g = new THREE.Group();
        g.add(mesh);
        carousel.add(g);
        const a = (i / slots) * Math.PI * 2;
        books.push({ g, mesh, pub, a, hover: 0, phase: i * 0.9 });
    }

    // ---- Parçacıklar ----
    const count = small ? 50 : 110;
    const pos = new Float32Array(count * 3);
    const seeds = Array.from({ length: count }, () => ({ a: Math.random() * Math.PI * 2, r: 0.3 + Math.random() * 4, y: Math.random() * 4 - 1.5, s: 0.1 + Math.random() * 0.3 }));
    const dustGeo = keep(new THREE.BufferGeometry());
    dustGeo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
    rig.add(new THREE.Points(dustGeo, keep(new THREE.PointsMaterial({ size: 0.06, map: dotMap, color: accent.clone().lerp(white, 0.5), transparent: true, opacity: 0.7, blending: THREE.AdditiveBlending, depthWrite: false }))));

    // ---- Etkileşim ----
    const ndc = new THREE.Vector2(9, 9);
    const ray = new THREE.Raycaster();
    let hovered = null;
    section.addEventListener('pointermove', (e) => {
        const r = section.getBoundingClientRect();
        section.style.setProperty('--hx', (((e.clientX - r.left) / r.width) * 2 - 1).toFixed(3));
        section.style.setProperty('--hy', (((e.clientY - r.top) / r.height) * 2 - 1).toFixed(3));
        const c = canvas.getBoundingClientRect();
        ndc.set(((e.clientX - c.left) / c.width) * 2 - 1, -((e.clientY - c.top) / c.height) * 2 + 1);
        if (tip) {
            tip.style.transform = `translate(${e.clientX - c.left + 14}px, ${e.clientY - c.top + 14}px)`;
        }
    }, { passive: true });
    section.addEventListener('pointerleave', () => ndc.set(9, 9));
    canvas.addEventListener('click', () => {
        if (hovered?.pub.url) {
            window.location.href = hovered.pub.url;
        }
    });

    let spin = 0;
    let spinSpeed = 0.18;

    stage.onFrame((dt, t) => {
        // Üzerine gelinen kitap varken halka yavaşlar
        spinSpeed = lerp(spinSpeed, hovered ? 0.02 : 0.18, 0.05);
        spin += dt * spinSpeed;
        carousel.rotation.y = spin + pointer.x * 0.6;
        rig.rotation.x = lerp(rig.rotation.x, -pointer.y * 0.06, 0.05);

        ray.setFromCamera(ndc, camera);
        const hit = ray.intersectObjects(books.map((b) => b.mesh), false)[0];
        const now = hit ? books.find((b) => b.mesh === hit.object) : null;
        if (now !== hovered) {
            hovered = now;
            canvas.style.cursor = hovered ? 'pointer' : '';
            if (tip) {
                tip.textContent = hovered ? `${hovered.pub.name} · ${hovered.pub.count} ${countLabel}` : '';
                tip.dataset.show = hovered ? '1' : '0';
            }
        }

        books.forEach((b) => {
            b.hover = lerp(b.hover, b === hovered ? 1 : 0, 0.12);
            const r = R + b.hover * 0.55;
            b.g.position.set(Math.sin(b.a) * r, Math.sin(t * 1.1 + b.phase) * 0.06 + b.hover * 0.15, Math.cos(b.a) * r);
            b.g.rotation.set(0, b.a + Math.sin(t * 0.6 + b.phase) * 0.06, Math.sin(t * 0.8 + b.phase) * 0.025);
            b.g.scale.setScalar(1 + b.hover * 0.15);
        });

        const pulse = Math.sin(t * 2.2);
        core.scale.setScalar(1.5 + pulse * 0.12);
        beamMat.opacity = 0.3 + pulse * 0.05;
        glowMat.emissiveIntensity = 1.8 + pulse * 0.3;
        innerRing.scale.setScalar(1 + Math.sin(t * 1.4) * 0.04);

        const arr = dustGeo.attributes.position.array;
        seeds.forEach((s, k) => {
            s.y += dt * s.s;
            if (s.y > 2.6) {
                s.y = -1.2;
            }
            const a = s.a + t * 0.12;
            arr[k * 3] = Math.cos(a) * s.r;
            arr[k * 3 + 1] = s.y;
            arr[k * 3 + 2] = Math.sin(a) * s.r;
        });
        dustGeo.attributes.position.needsUpdate = true;
    });

    stage.start();
    section.classList.add('hero-3d-ready');
}
