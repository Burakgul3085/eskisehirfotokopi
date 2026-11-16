/**
 * Belge Yükle (/fotokopi) üst alanının 3D sahnesi — ana sayfadakinden ayrı bir kurgu:
 *
 *  - Ortada eğik duran ışıklı bir "baskı halkası" ve içinde dönen ikinci halka (jiroskop), merkezinde parlayan çekirdek.
 *  - Halkanın çevresinde yörüngede dönen biçim kutucukları (PDF, Word, PowerPoint, Excel, görsel).
 *  - Sırası gelen kutucuk kavis çizerek halkanın merkezine dalar; halka parlar, aşağıya basılı bir kâğıt süzülür
 *    ve ışık hüzmesi boyunca kaideye inip desteye yerleşir. Deste dolunca en alttaki kâğıt sönerek kaybolur.
 *  - Kâğıt yerleşince desteye tarama ışığı geçer. Üzerine gelinen kutucuk büyür, tıklanınca hemen halkaya dalar.
 *  - Fare sahneyi hafifçe eğer; üst alandaki fiyat rozeti aynı fare konumuyla kayar (--hx / --hy).
 *
 * Renkler tema değişkenlerinden okunur. Hareketi azalt tercihinde tek kare (yörünge + deste) çizilir.
 */
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';
import { RoundedBoxGeometry } from 'three/examples/jsm/geometries/RoundedBoxGeometry.js';
import { THREE, canvasTexture, clamp, createStage, cssColor, documentTexture, easeInOut, lerp } from './kit.js';

const FORMATS = [
    ['PDF', '#ef4444', '#991b1b'],
    ['DOC', '#3b82f6', '#1e40af'],
    ['PPT', '#f97316', '#9a3412'],
    ['XLS', '#22c55e', '#166534'],
    ['JPG', '#a855f7', '#6b21a8'],
];
const SHEET = { w: 0.92, d: 1.3, h: 0.014 };
const GAP = 0.034;
const MAX_STACK = 11;
const RING_Y = 0.85;
const PED_Y = -1.62;

const easeOutBack = (t) => 1 + 2.4 * Math.pow(t - 1, 3) + 1.4 * Math.pow(t - 1, 2);

/** Biçim kutucuğunun ön yüzü: renk geçişi, katlanmış köşeli belge simgesi, biçim adı */
function tileTexture(label, a, b) {
    return canvasTexture(256, 256, (g, w, h) => {
        const grad = g.createLinearGradient(0, 0, w, h);
        grad.addColorStop(0, a);
        grad.addColorStop(1, b);
        g.fillStyle = grad;
        g.fillRect(0, 0, w, h);
        // Üstten yumuşak parlama
        const shine = g.createRadialGradient(w * 0.3, h * 0.15, 0, w * 0.3, h * 0.15, w * 0.8);
        shine.addColorStop(0, 'rgba(255,255,255,0.35)');
        shine.addColorStop(1, 'rgba(255,255,255,0)');
        g.fillStyle = shine;
        g.fillRect(0, 0, w, h);
        // Belge simgesi
        const x = 78, y = 34, dw = 100, dh = 122, f = 30;
        g.fillStyle = 'rgba(255,255,255,0.95)';
        g.beginPath();
        g.moveTo(x, y);
        g.lineTo(x + dw - f, y);
        g.lineTo(x + dw, y + f);
        g.lineTo(x + dw, y + dh);
        g.lineTo(x, y + dh);
        g.closePath();
        g.fill();
        g.fillStyle = 'rgba(0,0,0,0.18)';
        g.beginPath();
        g.moveTo(x + dw - f, y);
        g.lineTo(x + dw - f, y + f);
        g.lineTo(x + dw, y + f);
        g.closePath();
        g.fill();
        g.fillStyle = b;
        for (let i = 0; i < 4; i++) {
            g.fillRect(x + 16, y + 48 + i * 16, (i === 3 ? 0.45 : 0.68) * dw, 7);
        }
        // Biçim adı
        g.fillStyle = '#ffffff';
        g.font = '800 54px Inter, "Segoe UI", system-ui, sans-serif';
        g.textAlign = 'center';
        g.textBaseline = 'middle';
        g.fillText(label, w / 2, 204);
    });
}

/** Işık hüzmesi: yukarıdan aşağı sönen dikey şerit */
function beamTexture() {
    return canvasTexture(8, 256, (g, w, h) => {
        const grad = g.createLinearGradient(0, 0, 0, h);
        grad.addColorStop(0, 'rgba(255,255,255,0.0)');
        grad.addColorStop(0.15, 'rgba(255,255,255,0.55)');
        grad.addColorStop(1, 'rgba(255,255,255,0.0)');
        g.fillStyle = grad;
        g.fillRect(0, 0, w, h);
    });
}

function dotTexture() {
    return canvasTexture(64, 64, (g, w) => {
        const r = g.createRadialGradient(w / 2, w / 2, 0, w / 2, w / 2, w / 2);
        r.addColorStop(0, 'rgba(255,255,255,1)');
        r.addColorStop(0.35, 'rgba(255,255,255,0.45)');
        r.addColorStop(1, 'rgba(255,255,255,0)');
        g.fillStyle = r;
        g.fillRect(0, 0, w, w);
    });
}

/** Tarama ışığı: ortası parlak yatay bant */
function scanTexture() {
    return canvasTexture(256, 16, (g, w, h) => {
        const grad = g.createLinearGradient(0, 0, 0, h);
        grad.addColorStop(0, 'rgba(255,255,255,0)');
        grad.addColorStop(0.5, 'rgba(255,255,255,1)');
        grad.addColorStop(1, 'rgba(255,255,255,0)');
        g.fillStyle = grad;
        g.fillRect(0, 0, w, h);
    });
}

export function mount(root) {
    const section = root.closest('section') || root;
    let fitRef = null; // createStage ilk boyutlandırmayı kamera hazır olmadan yapar
    const stage = createStage(root, { fov: 32, z: 10, maxDpr: 1.75, pointerHost: section, onResize: (w, h) => fitRef?.(w / h) });
    if (!stage) {
        return;
    }
    const { renderer, scene, camera, pointer, keep, small, reduced } = stage;
    const canvas = renderer.domElement;

    const primary = cssColor('--color-primary', '#0f766e');
    const accent = cssColor('--color-accent', '#f59e0b');
    const primaryHex = '#' + primary.getHexString();
    const accentHex = '#' + accent.getHexString();
    const white = new THREE.Color('#ffffff');

    renderer.toneMappingExposure = 1.1;
    const pmrem = keep(new THREE.PMREMGenerator(renderer));
    scene.environment = keep(pmrem.fromScene(new RoomEnvironment(), 0.04).texture);
    scene.environmentIntensity = 0.7;

    // Dar tuvalde kamera geri çekilir (yörünge sığsın)
    function fit(aspect) {
        camera.position.z = aspect < 1.2 ? 8.8 + (1.2 - aspect) * 7 : 8.8;
        camera.position.y = 0.55;
        camera.lookAt(0, -0.2, 0);
    }
    fitRef = fit;
    fit(canvas.clientWidth / Math.max(1, canvas.clientHeight));

    scene.add(new THREE.HemisphereLight(0xffffff, primary.clone().multiplyScalar(0.35), 1.0));
    const key = new THREE.DirectionalLight(0xfff4e0, 2.0);
    key.position.set(3, 5, 6);
    scene.add(key);
    const core = new THREE.PointLight(accent, 12, 7, 2);
    core.position.set(0, RING_Y, 0.4);
    scene.add(core);
    const fill = new THREE.PointLight(primary.clone().lerp(white, 0.4), 16, 12, 2);
    fill.position.set(-3, -1.5, 3);
    scene.add(fill);

    const rig = new THREE.Group();
    rig.scale.setScalar(small ? 0.92 : 1);
    scene.add(rig);

    // ---- Baskı halkası (jiroskop) ----
    const metal = keep(new THREE.MeshPhysicalMaterial({ color: 0x1e293b, metalness: 0.85, roughness: 0.22, clearcoat: 1, clearcoatRoughness: 0.15 }));
    const glowMat = keep(new THREE.MeshStandardMaterial({ color: accent, emissive: accent, emissiveIntensity: 2.2, roughness: 0.3 }));
    const primaryGlow = keep(new THREE.MeshStandardMaterial({ color: primary.clone().lerp(white, 0.35), emissive: primary.clone().lerp(white, 0.35), emissiveIntensity: 1.6, roughness: 0.3 }));

    const ring = new THREE.Group();
    ring.position.y = RING_Y;
    rig.add(ring);
    const hoop = new THREE.Group();
    hoop.rotation.x = Math.PI / 2 - 0.42;
    ring.add(hoop);
    hoop.add(new THREE.Mesh(keep(new THREE.TorusGeometry(1.2, 0.075, 28, 140)), metal));
    const hoopGlow = new THREE.Mesh(keep(new THREE.TorusGeometry(1.2, 0.022, 12, 140)), glowMat);
    hoopGlow.position.z = 0.07;
    hoop.add(hoopGlow);
    // Halka üzerindeki ışık boncukları (dönen)
    const beads = new THREE.Group();
    hoop.add(beads);
    const beadGeo = keep(new THREE.SphereGeometry(0.05, 16, 12));
    for (let i = 0; i < 6; i++) {
        const a = (i / 6) * Math.PI * 2;
        const b = new THREE.Mesh(beadGeo, i % 2 ? glowMat : primaryGlow);
        b.position.set(Math.cos(a) * 1.2, Math.sin(a) * 1.2, 0.06);
        beads.add(b);
    }
    const gyro = new THREE.Group();
    ring.add(gyro);
    const inner = new THREE.Mesh(keep(new THREE.TorusGeometry(0.82, 0.03, 16, 120)), primaryGlow);
    gyro.add(inner);
    const inner2 = new THREE.Mesh(keep(new THREE.TorusGeometry(0.62, 0.022, 12, 100)), glowMat);
    gyro.add(inner2);

    const dotMap = keep(dotTexture());
    const coreSprite = new THREE.Sprite(keep(new THREE.SpriteMaterial({ map: dotMap, color: accent.clone().lerp(white, 0.2), transparent: true, opacity: 0.85, blending: THREE.AdditiveBlending, depthWrite: false })));
    coreSprite.scale.setScalar(1.3);
    ring.add(coreSprite);
    const halo = new THREE.Sprite(keep(new THREE.SpriteMaterial({ map: dotMap, color: primary.clone().lerp(white, 0.2), transparent: true, opacity: 0.55, blending: THREE.AdditiveBlending, depthWrite: false })));
    halo.scale.setScalar(4.2);
    ring.add(halo);

    // ---- Işık hüzmesi (halkadan kaideye) ----
    const beamH = RING_Y - PED_Y;
    const beamMat = keep(new THREE.MeshBasicMaterial({ map: keep(beamTexture()), color: accent.clone().lerp(white, 0.3), transparent: true, opacity: 0.32, blending: THREE.AdditiveBlending, depthWrite: false, side: THREE.DoubleSide }));
    const beam = new THREE.Mesh(keep(new THREE.CylinderGeometry(0.62, 1.05, beamH, 48, 1, true)), beamMat);
    beam.position.y = PED_Y + beamH / 2;
    rig.add(beam);

    // ---- Kaide ----
    const pedestal = new THREE.Group();
    pedestal.position.y = PED_Y;
    rig.add(pedestal);
    const pedMat = keep(new THREE.MeshPhysicalMaterial({ color: 0x0f172a, metalness: 0.7, roughness: 0.3, clearcoat: 1, clearcoatRoughness: 0.2 }));
    const ped = new THREE.Mesh(keep(new THREE.CylinderGeometry(1.25, 1.38, 0.22, 72)), pedMat);
    ped.position.y = -0.11;
    pedestal.add(ped);
    const pedTop = new THREE.Mesh(keep(new THREE.CylinderGeometry(1.12, 1.12, 0.02, 72)), keep(new THREE.MeshPhysicalMaterial({ color: 0x334155, metalness: 0.5, roughness: 0.25, clearcoat: 1 })));
    pedestal.add(pedTop);
    const pedRing = new THREE.Mesh(keep(new THREE.TorusGeometry(1.19, 0.018, 10, 120)), glowMat);
    pedRing.rotation.x = Math.PI / 2;
    pedRing.position.y = 0.005;
    pedestal.add(pedRing);
    const floorGlow = new THREE.Mesh(keep(new THREE.PlaneGeometry(5, 5)), keep(new THREE.MeshBasicMaterial({ map: dotMap, color: primary.clone().lerp(white, 0.15), transparent: true, opacity: 0.5, blending: THREE.AdditiveBlending, depthWrite: false })));
    floorGlow.rotation.x = -Math.PI / 2;
    floorGlow.position.y = -0.24;
    pedestal.add(floorGlow);

    // ---- Kâğıt destesi ----
    const stack = new THREE.Group();
    stack.position.y = 0.012;
    stack.rotation.y = -0.18;
    pedestal.add(stack);
    const sheetGeo = keep(new THREE.BoxGeometry(SHEET.w, SHEET.h, SHEET.d));
    const edgeMat = keep(new THREE.MeshStandardMaterial({ color: 0xf1f5f9, roughness: 0.85 }));
    const texBw = keep(documentTexture(accentHex, false));
    const texColor = keep(documentTexture(accentHex, true, primaryHex));
    const makeSheet = (colorful) => {
        const top = new THREE.MeshStandardMaterial({ map: colorful ? texColor : texBw, roughness: 0.7, transparent: true });
        const m = new THREE.Mesh(sheetGeo, [edgeMat, edgeMat, top, edgeMat, edgeMat, edgeMat]);
        m.userData.top = top;
        return m;
    };
    const stacked = []; // {m, y, fade}
    const place = (m, i) => {
        m.position.set(Math.sin(i * 2.3) * 0.04, i * GAP, Math.cos(i * 1.7) * 0.04);
        m.rotation.set(0, Math.sin(i * 1.9) * 0.08, 0);
    };
    const preload = reduced ? 7 : 4;
    for (let i = 0; i < preload; i++) {
        const m = makeSheet(i % 2 === 1);
        place(m, i);
        stack.add(m);
        stacked.push({ m, fade: 0 });
    }
    // Tarama ışığı
    const scanMat = keep(new THREE.MeshBasicMaterial({ map: keep(scanTexture()), color: accent.clone().lerp(white, 0.4), transparent: true, opacity: 0, blending: THREE.AdditiveBlending, depthWrite: false, side: THREE.DoubleSide }));
    const scan = new THREE.Mesh(keep(new THREE.PlaneGeometry(SHEET.w * 1.25, 0.12)), scanMat);
    scan.rotation.x = -Math.PI / 2;
    stack.add(scan);
    let scanT = 1;

    // ---- Biçim kutucukları ----
    const R = small ? 2.05 : 2.45;
    const tileGeo = keep(new RoundedBoxGeometry(0.66, 0.66, 0.12, 4, 0.08));
    const tiles = FORMATS.map(([label, a, b], i) => {
        const face = keep(new THREE.MeshPhysicalMaterial({ map: keep(tileTexture(label, a, b)), roughness: 0.28, clearcoat: 1, clearcoatRoughness: 0.12 }));
        const side = keep(new THREE.MeshStandardMaterial({ color: new THREE.Color(b), roughness: 0.4, metalness: 0.2 }));
        // RoundedBox yüz sırası: +x, -x, +y, -y, +z, -z
        const mesh = new THREE.Mesh(tileGeo, [side, side, side, side, face, side]);
        const g = new THREE.Group();
        g.add(mesh);
        rig.add(g);
        return { g, mesh, angle: (i / FORMATS.length) * Math.PI * 2, state: 'intro', t: -i * 0.12, hover: 0, from: new THREE.Vector3(), ctrl: new THREE.Vector3(), colorful: i % 2 === 0 };
    });
    const ringCenter = new THREE.Vector3(0, RING_Y, 0);
    let orbit = 0;
    const orbitPos = (tile, out) => {
        const a = tile.angle + orbit;
        return out.set(Math.cos(a) * R, RING_Y + Math.sin(a * 2 + 0.6) * 0.22 - 0.05, Math.sin(a) * R * 0.5);
    };

    // ---- Süzülen kâğıtlar ----
    const falling = []; // {m, t, side}
    const spawnFall = (colorful) => {
        const m = makeSheet(colorful);
        m.scale.setScalar(0.2);
        rig.add(m);
        falling.push({ m, t: 0, side: Math.random() < 0.5 ? -1 : 1 });
    };
    let flash = 0;

    // ---- Parçacıklar: halka çevresinde yükselen sarmal ----
    const count = small ? 70 : 160;
    const pos = new Float32Array(count * 3);
    const seeds = [];
    for (let k = 0; k < count; k++) {
        seeds.push({ a: Math.random() * Math.PI * 2, r: 0.9 + Math.random() * 2.6, y: Math.random() * 5 - 2.5, s: 0.15 + Math.random() * 0.35 });
    }
    const dustGeo = keep(new THREE.BufferGeometry());
    dustGeo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
    const dust = new THREE.Points(dustGeo, keep(new THREE.PointsMaterial({ size: 0.07, map: dotMap, color: accent.clone().lerp(white, 0.55), transparent: true, opacity: 0.7, blending: THREE.AdditiveBlending, depthWrite: false })));
    rig.add(dust);

    // ---- Etkileşim: üzerine gelinen kutucuk büyür, tıklanan hemen dalar ----
    const ndc = new THREE.Vector2(9, 9);
    const ray = new THREE.Raycaster();
    let hovered = null;
    section.addEventListener('pointermove', (e) => {
        const r = section.getBoundingClientRect();
        section.style.setProperty('--hx', (((e.clientX - r.left) / r.width) * 2 - 1).toFixed(3));
        section.style.setProperty('--hy', (((e.clientY - r.top) / r.height) * 2 - 1).toFixed(3));
        const c = canvas.getBoundingClientRect();
        ndc.set(((e.clientX - c.left) / c.width) * 2 - 1, -((e.clientY - c.top) / c.height) * 2 + 1);
    }, { passive: true });
    section.addEventListener('pointerleave', () => ndc.set(9, 9));
    canvas.addEventListener('click', () => {
        if (hovered && hovered.state === 'orbit') {
            dive(hovered);
            nextDive = 2.4;
        }
    });

    const tmp = new THREE.Vector3();
    const tmp2 = new THREE.Vector3();
    const dive = (tile) => {
        tile.state = 'dive';
        tile.t = 0;
        tile.from.copy(tile.g.position);
        tile.ctrl.copy(tile.from).lerp(ringCenter, 0.45).add(tmp.set(0, 1.1, 0.9));
    };
    let nextDive = 1.4;
    let turn = 0;

    stage.onFrame((dt, t) => {
        rig.rotation.y = lerp(rig.rotation.y, pointer.x * 0.32, 0.06);
        rig.rotation.x = lerp(rig.rotation.x, -pointer.y * 0.1, 0.06);
        orbit += dt * 0.32;

        // Halka
        flash = Math.max(0, flash - dt * 1.8);
        beads.rotation.z = t * 0.6;
        gyro.rotation.set(t * 0.9, t * 0.55, t * 0.3);
        inner2.rotation.y = t * 1.4;
        const pulse = 1 + Math.sin(t * 2.4) * 0.08 + flash * 0.9;
        coreSprite.scale.setScalar(0.95 * pulse);
        halo.material.opacity = 0.45 + flash * 0.4 + Math.sin(t * 1.3) * 0.05;
        glowMat.emissiveIntensity = 2 + flash * 3 + Math.sin(t * 3) * 0.2;
        core.intensity = 10 + flash * 30;
        beamMat.opacity = 0.22 + flash * 0.35 + Math.sin(t * 1.7) * 0.04;
        ring.position.y = RING_Y + Math.sin(t * 0.9) * 0.05;

        // Üzerine gelinen kutucuk
        ray.setFromCamera(ndc, camera);
        const hit = ray.intersectObjects(tiles.map((x) => x.mesh), false)[0];
        hovered = hit ? tiles.find((x) => x.mesh === hit.object) : null;
        canvas.style.cursor = hovered && hovered.state === 'orbit' ? 'pointer' : '';

        // Sıradaki dalış
        nextDive -= dt;
        if (nextDive <= 0) {
            const ready = tiles.filter((x) => x.state === 'orbit');
            if (ready.length > 1) {
                // Kameraya en yakın (önde) olanlardan biri dalar: hareket görünür olsun
                ready.sort((a, b) => b.g.position.z - a.g.position.z);
                dive(ready[turn++ % 2]);
            }
            nextDive = 2.3 + Math.random() * 0.8;
        }

        tiles.forEach((tile) => {
            tile.hover = lerp(tile.hover, tile === hovered && tile.state === 'orbit' ? 1 : 0, 0.15);
            const g = tile.g;
            if (tile.state === 'intro' || tile.state === 'return') {
                tile.t += dt / 0.8;
                const u = clamp(tile.t, 0, 1);
                orbitPos(tile, g.position);
                g.scale.setScalar(Math.max(0.0001, easeOutBack(u)));
                g.rotation.set(0, (1 - u) * Math.PI, 0);
                if (u >= 1) {
                    tile.state = 'orbit';
                }
            } else if (tile.state === 'orbit') {
                orbitPos(tile, g.position);
                const a = tile.angle + orbit;
                g.scale.setScalar(1 + tile.hover * 0.22);
                g.rotation.set(
                    lerp(Math.sin(t * 0.7 + a) * 0.12, 0, tile.hover),
                    lerp(-Math.cos(a) * 0.45 + Math.sin(t * 0.5 + a) * 0.12, -rig.rotation.y, tile.hover),
                    lerp(Math.sin(t * 0.6 + a * 2) * 0.1, 0, tile.hover),
                );
            } else if (tile.state === 'dive') {
                tile.t += dt / 1.15;
                const u = easeInOut(clamp(tile.t, 0, 1));
                tmp.copy(tile.from).lerp(tile.ctrl, u);
                tmp2.copy(tile.ctrl).lerp(ringCenter, u);
                g.position.copy(tmp.lerp(tmp2, u));
                g.scale.setScalar(Math.max(0.0001, lerp(1, 0.15, u * u)));
                g.rotation.set(u * 0.6, u * Math.PI * 3, u * 0.4);
                if (tile.t >= 1) {
                    flash = 1;
                    spawnFall(tile.colorful);
                    tile.colorful = !tile.colorful;
                    tile.state = 'hidden';
                    tile.t = 0;
                    g.scale.setScalar(0.0001);
                }
            } else if (tile.state === 'hidden') {
                tile.t += dt;
                if (tile.t > 0.9) {
                    tile.state = 'return';
                    tile.t = 0;
                }
            }
        });

        // Süzülen kâğıtlar: halkadan kaideye, dönerek yatar
        for (let k = falling.length - 1; k >= 0; k--) {
            const f = falling[k];
            f.t += dt / 1.6;
            const u = easeInOut(clamp(f.t, 0, 1));
            const topY = PED_Y + stack.position.y + stacked.length * GAP;
            f.m.position.set(
                Math.sin(u * Math.PI) * 0.55 * f.side,
                lerp(RING_Y - 0.1, topY, u),
                Math.sin(u * Math.PI) * 0.5,
            );
            f.m.rotation.set(lerp(Math.PI / 2, 0, u), stack.rotation.y * u + Math.sin(u * Math.PI * 2) * 0.35 * f.side, Math.sin(u * Math.PI * 1.5) * 0.4 * f.side);
            f.m.scale.setScalar(lerp(0.25, 1, Math.min(1, u * 1.6)));
            if (f.t >= 1) {
                // Desteye yerleş
                rig.remove(f.m);
                f.m.scale.setScalar(1);
                place(f.m, stacked.length);
                stack.add(f.m);
                stacked.push({ m: f.m, fade: 0 });
                falling.splice(k, 1);
                scanT = 0;
                if (stacked.length > MAX_STACK) {
                    stacked[0].fade = 0.0001;
                }
            }
        }

        // Deste: en alttaki sönerek kaybolur, diğerleri yavaşça aşağı iner
        if (stacked[0]?.fade > 0) {
            const s0 = stacked[0];
            s0.fade += dt * 2;
            s0.m.userData.top.opacity = 1 - s0.fade;
            s0.m.scale.setScalar(1 - s0.fade * 0.3);
            if (s0.fade >= 1) {
                stack.remove(s0.m);
                s0.m.userData.top.dispose();
                stacked.shift();
            }
        }
        stacked.forEach((s, i) => {
            if (!s.fade) {
                s.m.position.y = lerp(s.m.position.y, i * GAP, 0.12);
            }
        });

        // Tarama ışığı desteye geçer
        if (scanT < 1) {
            scanT += dt / 0.9;
            const u = clamp(scanT, 0, 1);
            scan.position.set(0, stacked.length * GAP + 0.02, lerp(-SHEET.d / 2, SHEET.d / 2, u));
            scanMat.opacity = Math.sin(u * Math.PI) * 0.9;
        } else {
            scanMat.opacity = 0;
        }
        pedRing.material.emissiveIntensity = glowMat.emissiveIntensity;

        // Sarmal parçacıklar
        const arr = dustGeo.attributes.position.array;
        seeds.forEach((s, k) => {
            s.y += dt * s.s;
            if (s.y > 2.8) {
                s.y = -2.4;
            }
            const a = s.a + t * 0.25 * (s.r < 2 ? 1.4 : 0.7);
            arr[k * 3] = Math.cos(a) * s.r;
            arr[k * 3 + 1] = s.y;
            arr[k * 3 + 2] = Math.sin(a) * s.r * 0.6 - 0.3;
        });
        dustGeo.attributes.position.needsUpdate = true;
    });

    stage.start(() => {
        // Tek kare: kutucuklar yörüngede, deste dolu
        tiles.forEach((tile) => {
            tile.state = 'orbit';
            orbitPos(tile, tile.g.position);
        });
    });
    section.classList.add('hero-3d-ready');
}
