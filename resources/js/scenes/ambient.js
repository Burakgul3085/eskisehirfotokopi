/**
 * Ana sayfa arka zemini: sayfanın arkasında sabit duran "kâğıt evreni".
 *  - Derinlikte süzülüp yavaşça dönen belge yaprakları (S-B ve renkli), uzaktakiler sisle zemine karışır
 *  - Ana renk / vurgu renginde ışık tozları
 *  - Kaydırınca katmanlar derinliğe göre farklı hızda kayar (paralaks), fare kamerayı hafifçe kaydırır
 *  - Yakın yapraklar kenarlarda durur, orta sütun (içerik) temiz kalır
 * Telefonda çalışmaz (yalnız CSS harelerini görür); kare hızı sınırlıdır.
 */
import { THREE, canvasTexture, createStage, cssColor, lerp } from './kit.js';

const FOV = 40;
const CAM_Z = 10;

export function mount(root) {
    if (window.matchMedia('(max-width: 1023px)').matches) {
        return;
    }
    const build = () => setup(root);
    'requestIdleCallback' in window ? requestIdleCallback(build, { timeout: 2500 }) : setTimeout(build, 900);
}

function setup(root) {
    const stage = createStage(root, { fov: FOV, z: CAM_Z, maxDpr: 1.25, fps: 40, pointerHost: window });
    if (!stage) {
        return;
    }
    const { renderer, scene, camera, pointer, keep } = stage;
    // Sis rengi CSS zeminiyle birebir aynı görünsün diye ton eşleme kapalı
    renderer.toneMapping = THREE.NoToneMapping;

    const surface = cssColor('--color-surface', '#f6f7f9');
    const primary = cssColor('--color-primary', '#0f766e');
    const accent = cssColor('--color-accent', '#f59e0b');
    const primaryHex = '#' + primary.getHexString();
    const accentHex = '#' + accent.getHexString();

    scene.fog = new THREE.Fog(surface, 11, 25);
    scene.add(new THREE.HemisphereLight(0xffffff, surface.clone().lerp(primary, 0.15), 2.2));
    const sun = new THREE.DirectionalLight(0xffffff, 1.3);
    sun.position.set(-4, 6, 6);
    scene.add(sun);

    // ---- Yapraklar: iki örnekli ağ (S-B ve renkli) ----
    const geo = keep(new THREE.PlaneGeometry(0.9, 1.27));
    const makeMat = (map) => keep(new THREE.MeshStandardMaterial({ map, roughness: 0.9, metalness: 0, side: THREE.DoubleSide }));
    const matBw = makeMat(keep(documentLite(accentHex, false, primaryHex)));
    const matColor = makeMat(keep(documentLite(accentHex, true, primaryHex)));

    const rand = mulberry(7);
    const sheets = [];
    const groups = [
        { mat: matBw, count: 24 },
        { mat: matColor, count: 10 },
    ].map(({ mat, count }) => {
        const mesh = new THREE.InstancedMesh(geo, mat, count);
        mesh.instanceMatrix.setUsage(THREE.DynamicDrawUsage);
        mesh.frustumCulled = false;
        scene.add(mesh);
        for (let i = 0; i < count; i++) {
            const z = lerp(-13, 1.2, Math.pow(rand(), 0.8));
            const near = z > -5;
            // Yakın yapraklar ekranın kenar bölgesinde (|xr| ≥ 0.55), uzaktakiler her yerde
            const side = rand() < 0.5 ? -1 : 1;
            const xr = near ? side * lerp(0.55, 1.02, rand()) : lerp(-1, 1, rand());
            const s = {
                mesh, i, z, xr,
                y0: rand(), rise: lerp(0.05, 0.13, rand()), par: lerp(0.18, 0.55, rand()),
                rx: rand() * 6.28, ry: rand() * 6.28, rz: rand() * 6.28,
                sx: lerp(-0.18, 0.18, rand()), sy: lerp(-0.22, 0.22, rand()), sz: lerp(-0.08, 0.08, rand()),
                scale: lerp(0.7, 1.1, rand()),
            };
            mesh.setColorAt(i, new THREE.Color(0xffffff).lerp(primary, rand() * 0.06));
            sheets.push(s);
        }
        mesh.instanceColor.needsUpdate = true;
        return mesh;
    });

    // ---- Işık tozları ----
    const DUST = 240;
    const dustPos = new Float32Array(DUST * 3);
    const dustCol = new Float32Array(DUST * 3);
    const dustBase = [];
    for (let i = 0; i < DUST; i++) {
        const z = lerp(-12, 3, rand());
        dustBase.push({ xr: lerp(-1.05, 1.05, rand()), y0: rand(), z, rise: lerp(0.02, 0.07, rand()), par: lerp(0.1, 0.6, rand()), ph: rand() * 6.28 });
        const c = (rand() < 0.3 ? accent : primary).clone().lerp(new THREE.Color(0xffffff), rand() * 0.35);
        dustCol.set([c.r, c.g, c.b], i * 3);
    }
    const dustGeo = keep(new THREE.BufferGeometry());
    dustGeo.setAttribute('position', new THREE.BufferAttribute(dustPos, 3).setUsage(THREE.DynamicDrawUsage));
    dustGeo.setAttribute('color', new THREE.BufferAttribute(dustCol, 3));
    const dot = keep(canvasTexture(64, 64, (g, w) => {
        const grad = g.createRadialGradient(w / 2, w / 2, 0, w / 2, w / 2, w / 2);
        grad.addColorStop(0, 'rgba(255,255,255,1)');
        grad.addColorStop(0.35, 'rgba(255,255,255,0.65)');
        grad.addColorStop(1, 'rgba(255,255,255,0)');
        g.fillStyle = grad;
        g.fillRect(0, 0, w, w);
    }));
    const dust = new THREE.Points(dustGeo, keep(new THREE.PointsMaterial({
        size: 0.09, map: dot, vertexColors: true, transparent: true, opacity: 0.7, depthWrite: false, sizeAttenuation: true,
    })));
    dust.frustumCulled = false;
    scene.add(dust);

    // ---- Kare güncellemesi ----
    const tanHalf = Math.tan(THREE.MathUtils.degToRad(FOV / 2));
    const halfH = (z) => tanHalf * (CAM_Z - z);
    const o = new THREE.Object3D();
    const wrap = (v) => v - Math.floor(v);
    let camY = 0;

    stage.onFrame((dt, t) => {
        const aspect = camera.aspect || 1.8;
        const scroll = window.scrollY || 0;
        const px = 1 / Math.max(innerHeight, 1); // 1 pikselin ekran yüksekliğine oranı

        camera.position.x = lerp(camera.position.x, pointer.x * 0.55, 0.08);
        camY = lerp(camY, pointer.y * 0.35, 0.08);
        camera.position.y = camY;
        camera.lookAt(camera.position.x * 0.4, camY * 0.4, -4);

        for (const s of sheets) {
            const hh = halfH(s.z);
            const span = hh * 2 + 2.4;
            // Yukarı süzülme + kaydırma paralaksı, ekran dışına çıkan alttan geri gelir
            const f = wrap(s.y0 + (t * s.rise) / span + scroll * px * s.par * ((hh * 2) / span));
            o.position.set(s.xr * hh * aspect, f * span - span / 2, s.z);
            o.rotation.set(s.rx + t * s.sx, s.ry + t * s.sy, s.rz + t * s.sz + Math.sin(t * 0.7 + s.rx) * 0.08);
            o.scale.setScalar(s.scale);
            o.updateMatrix();
            s.mesh.setMatrixAt(s.i, o.matrix);
        }
        groups.forEach((g) => (g.instanceMatrix.needsUpdate = true));

        for (let i = 0; i < DUST; i++) {
            const d = dustBase[i];
            const hh = halfH(d.z);
            const span = hh * 2 + 1;
            const f = wrap(d.y0 + (t * d.rise) / span + scroll * px * d.par * ((hh * 2) / span));
            dustPos[i * 3] = d.xr * hh * aspect + Math.sin(t * 0.4 + d.ph) * 0.15;
            dustPos[i * 3 + 1] = f * span - span / 2;
            dustPos[i * 3 + 2] = d.z;
        }
        dustGeo.attributes.position.needsUpdate = true;
    });
    stage.start();
}

/** Uzaktan okunacak sade belge dokusu (küçük çözünürlük: GPU dostu) */
function documentLite(accent, colorful, primary) {
    return canvasTexture(128, 181, (g, w, h) => {
        g.fillStyle = '#ffffff';
        g.fillRect(0, 0, w, h);
        g.fillStyle = colorful ? accent : '#475569';
        g.fillRect(12, 14, 48, 6);
        g.fillStyle = '#d5dbe3';
        for (let y = 30, i = 0; y < h - 18; y += 8, i++) {
            g.fillRect(12, y, (i % 5 === 4 ? 0.5 : 0.82 + (i % 3) * 0.04) * (w - 24), 3);
        }
        if (colorful) {
            const grad = g.createLinearGradient(w - 52, h - 56, w - 12, h - 20);
            grad.addColorStop(0, primary);
            grad.addColorStop(1, accent);
            g.fillStyle = grad;
            g.fillRect(w - 52, h - 56, 40, 34);
        }
    });
}

/** Her yüklemede aynı dizilim için tohumlu rastgele sayı */
function mulberry(a) {
    return () => {
        a |= 0;
        a = (a + 0x6d2b79f5) | 0;
        let t = Math.imul(a ^ (a >>> 15), 1 | a);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}
