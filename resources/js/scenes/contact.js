/**
 * İletişim sayfası sahnesi:
 *  - Ortada zarf: kapağı açılır, içinden mektup yükselir (kıvılcım + halka dalgası), sonra geri girer ve kapak kapanır.
 *  - Zarfın çevresinde iz bırakarak süzülen kâğıt uçak.
 *  - İki konuşma balonu: içlerindeki üç nokta sırayla "yazıyor" gibi zıplar.
 *  - Sağ üstte zıplayan konum iğnesi; altındaki zeminde nabız dalgaları.
 *  - Zarfın altında dönen halkalı ışık platformu. Fare sahneyi çevirir; üst alanın --hx/--hy değişkenleri güncellenir.
 * Renkler tema değişkenlerinden; yazı yok (metinler yönetimden). Hareketi azalt tercihinde tek kare çizilir.
 */
import { RoundedBoxGeometry } from 'three/examples/jsm/geometries/RoundedBoxGeometry.js';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';
import { THREE, canvasTexture, clamp, createSparks, createStage, createWaves, cssColor, documentTexture, dotTexture, easeInOut, lerp } from './kit.js';

const ENV = { w: 2.5, h: 1.65, d: 0.14 };
const easeOutBack = (t) => 1 + 2.2 * Math.pow(t - 1, 3) + 1.2 * Math.pow(t - 1, 2);

/** Zarfın ön yüzü: krem kâğıt, iki yanda katlanma çizgileri, köşede pul */
function envelopeTexture(accent, primary) {
    return canvasTexture(512, 338, (g, w, h) => {
        const grad = g.createLinearGradient(0, 0, 0, h);
        grad.addColorStop(0, '#fffdf8');
        grad.addColorStop(1, '#f1ebdf');
        g.fillStyle = grad;
        g.fillRect(0, 0, w, h);
        // Alt kat çizgileri (V)
        g.strokeStyle = 'rgba(120, 100, 70, 0.22)';
        g.lineWidth = 3;
        g.beginPath();
        g.moveTo(0, h);
        g.lineTo(w * 0.5, h * 0.48);
        g.lineTo(w, h);
        g.stroke();
        // Pul
        g.fillStyle = accent;
        g.fillRect(w - 98, 26, 70, 84);
        g.strokeStyle = '#ffffff';
        g.setLineDash([5, 5]);
        g.lineWidth = 3;
        g.strokeRect(w - 92, 32, 58, 72);
        g.setLineDash([]);
        g.fillStyle = primary;
        g.beginPath();
        g.arc(w - 63, 68, 16, 0, Math.PI * 2);
        g.fill();
        // Adres satırları
        g.fillStyle = 'rgba(51, 65, 85, 0.35)';
        [0, 1, 2].forEach((i) => g.fillRect(w * 0.36, h * 0.56 + i * 26, w * (0.36 - i * 0.06), 9));
    });
}

/** Kâğıt uçak: burun, iki kanat ve omurga (yazı tipi/model dosyası gerekmez) */
function planeGeometry() {
    const v = [
        [0, 0, 0.62], [-0.48, 0.02, -0.42], [0, 0, -0.36], // sol kanat
        [0, 0, 0.62], [0, 0, -0.36], [0.48, 0.02, -0.42], // sağ kanat
        [0, 0, 0.62], [0, -0.17, -0.38], [0, 0, -0.36], // omurga
    ].flat();
    const geo = new THREE.BufferGeometry();
    geo.setAttribute('position', new THREE.Float32BufferAttribute(v, 3));
    geo.computeVertexNormals();
    return geo;
}

export function mount(root) {
    const section = root.closest('section') || root;
    const stage = createStage(root, { fov: 32, z: 10.5, maxDpr: 1.6, pointerHost: section });
    if (!stage) {
        return;
    }
    const { renderer, scene, camera, pointer, keep, small } = stage;
    const primary = cssColor('--color-primary', '#0f766e');
    const accent = cssColor('--color-accent', '#f59e0b');
    const white = new THREE.Color('#ffffff');
    const accentHex = '#' + accent.getHexString();
    const primaryHex = '#' + primary.getHexString();

    const pmrem = keep(new THREE.PMREMGenerator(renderer));
    scene.environment = keep(pmrem.fromScene(new RoomEnvironment(), 0.04).texture);
    scene.environmentIntensity = 0.7;
    scene.add(new THREE.HemisphereLight(0xffffff, primary.clone().multiplyScalar(0.4), 0.9));
    const key = new THREE.DirectionalLight(0xfff4e0, 2.1);
    key.position.set(3, 5, 6);
    scene.add(key);
    const rim = new THREE.PointLight(accent, 26, 14, 2);
    rim.position.set(-3.5, 2.5, -2);
    scene.add(rim);

    const rig = new THREE.Group();
    rig.position.y = small ? 0.1 : -0.1;
    rig.scale.setScalar(small ? 0.82 : 1.15);
    scene.add(rig);
    const glowTex = keep(dotTexture());

    // ---- Zarf ----
    const envelope = new THREE.Group();
    envelope.position.set(0, -0.15, 0);
    envelope.rotation.set(-0.08, -0.18, 0.03);
    rig.add(envelope);
    const paper = keep(new THREE.MeshStandardMaterial({ color: 0xf6f1e6, roughness: 0.75 }));
    const front = keep(new THREE.MeshStandardMaterial({ map: keep(envelopeTexture(accentHex, primaryHex)), roughness: 0.7 }));
    // RoundedBox yüz sırası: +x, -x, +y, -y, +z, -z
    envelope.add(new THREE.Mesh(keep(new RoundedBoxGeometry(ENV.w, ENV.h, ENV.d, 2, 0.04)), [paper, paper, paper, paper, front, paper]));

    // Mektup: zarfın içinden yukarı kayar (gövde dolu olduğundan içerideyken görünmez)
    const letter = new THREE.Mesh(keep(new THREE.PlaneGeometry(ENV.w * 0.86, ENV.h * 0.92)), keep(new THREE.MeshStandardMaterial({ map: keep(documentTexture(accentHex, true, primaryHex)), roughness: 0.7, side: THREE.DoubleSide })));
    letter.position.set(0, 0, 0.005);
    envelope.add(letter);

    // Kapak: üst kenardan menteşeli üçgen; kapalıyken ön yüze iner, açılınca arkaya katlanır
    const flapPivot = new THREE.Group();
    flapPivot.position.set(0, ENV.h / 2, ENV.d / 2 + 0.006);
    envelope.add(flapPivot);
    const flapShape = new THREE.Shape();
    flapShape.moveTo(-ENV.w / 2, 0);
    flapShape.lineTo(ENV.w / 2, 0);
    flapShape.lineTo(0, -ENV.h * 0.6);
    flapShape.closePath();
    const flapMat = keep(new THREE.MeshStandardMaterial({ color: 0xece4d4, roughness: 0.72, side: THREE.DoubleSide }));
    flapPivot.add(new THREE.Mesh(keep(new THREE.ShapeGeometry(flapShape)), flapMat));
    // Mühür
    const sealMat = keep(new THREE.MeshPhysicalMaterial({ color: accent, roughness: 0.3, metalness: 0.2, clearcoat: 0.8, emissive: accent, emissiveIntensity: 0.15 }));
    const seal = new THREE.Mesh(keep(new THREE.CylinderGeometry(0.17, 0.17, 0.05, 32)), sealMat);
    seal.rotation.x = Math.PI / 2;
    seal.position.set(0, -ENV.h * 0.55, 0.03);
    flapPivot.add(seal);

    // ---- Işık platformu ----
    const platform = new THREE.Group();
    platform.position.set(0, -1.55, 0);
    rig.add(platform);
    const ringColor = primary.clone().lerp(white, 0.5);
    const ringMat = (o) => keep(new THREE.MeshBasicMaterial({ color: ringColor, transparent: true, opacity: o, blending: THREE.AdditiveBlending, depthWrite: false }));
    const ringA = new THREE.Mesh(keep(new THREE.TorusGeometry(1.9, 0.012, 8, 180)), ringMat(0.55));
    const ringB = new THREE.Mesh(keep(new THREE.TorusGeometry(2.35, 0.006, 8, 180)), ringMat(0.28));
    ringA.rotation.x = ringB.rotation.x = Math.PI / 2;
    const discMat = keep(new THREE.MeshBasicMaterial({ map: glowTex, color: ringColor, transparent: true, opacity: 0.3, blending: THREE.AdditiveBlending, depthWrite: false }));
    const disc = new THREE.Mesh(keep(new THREE.PlaneGeometry(5, 5)), discMat);
    disc.rotation.x = -Math.PI / 2;
    platform.add(ringA, ringB, disc);
    const flat = new THREE.Quaternion().setFromEuler(new THREE.Euler(-Math.PI / 2, 0, 0));

    // ---- Kâğıt uçak ----
    const plane = new THREE.Mesh(keep(planeGeometry()), keep(new THREE.MeshStandardMaterial({ color: 0xffffff, roughness: 0.55, side: THREE.DoubleSide, flatShading: true })));
    plane.scale.setScalar(0.75);
    rig.add(plane);
    const planeAt = (a) => new THREE.Vector3(Math.cos(a) * 3.1, 0.35 + Math.sin(a * 2) * 0.45, Math.sin(a) * 1.7);

    // ---- Konuşma balonları ----
    const bubbleGeo = keep(new RoundedBoxGeometry(1.05, 0.66, 0.2, 3, 0.16));
    const tailGeo = keep(new THREE.ConeGeometry(0.12, 0.26, 4));
    const dotGeo = keep(new THREE.SphereGeometry(0.075, 16, 16));
    const dotMat = keep(new THREE.MeshStandardMaterial({ color: 0xffffff, roughness: 0.4, emissive: 0xffffff, emissiveIntensity: 0.2 }));
    const bubbles = [
        { pos: [-2.75, 1.15, 0.2], color: primary, flip: 1 },
        { pos: [2.65, -0.55, 0.7], color: accent, flip: -1 },
    ].map((b, i) => {
        const g = new THREE.Group();
        g.position.set(...b.pos);
        g.rotation.set(0, b.flip * 0.28, b.flip * 0.05);
        const mat = keep(new THREE.MeshPhysicalMaterial({ color: b.color, roughness: 0.25, clearcoat: 1, clearcoatRoughness: 0.15 }));
        g.add(new THREE.Mesh(bubbleGeo, mat));
        const tail = new THREE.Mesh(tailGeo, mat);
        tail.position.set(b.flip * 0.3, -0.38, 0);
        tail.rotation.z = Math.PI + b.flip * 0.5;
        g.add(tail);
        const dots = [-1, 0, 1].map((k) => {
            const d = new THREE.Mesh(dotGeo, dotMat);
            d.position.set(k * 0.24, 0, 0.11);
            g.add(d);
            return d;
        });
        g.scale.setScalar(0.0001);
        rig.add(g);
        return { g, dots, base: new THREE.Vector3(...b.pos), phase: i * 1.9 };
    });

    // ---- Konum iğnesi ----
    const pin = new THREE.Group();
    const pinBase = new THREE.Vector3(2.35, 1.35, -0.9);
    pin.position.copy(pinBase);
    rig.add(pin);
    const pinProfile = [];
    for (let k = 0; k <= 24; k++) {
        const t = k / 24;
        // Damla: altta sivri uç, üstte yuvarlak baş
        const y = -0.62 + t * 1.12;
        const r = t < 0.55 ? Math.pow(t / 0.55, 1.4) * 0.36 : Math.sqrt(Math.max(0, 1 - Math.pow((t - 0.55) / 0.45, 2))) * 0.36;
        pinProfile.push(new THREE.Vector2(Math.max(0.001, r), y));
    }
    const pinMat = keep(new THREE.MeshPhysicalMaterial({ color: accent, roughness: 0.22, metalness: 0.15, clearcoat: 1, clearcoatRoughness: 0.1 }));
    pin.add(new THREE.Mesh(keep(new THREE.LatheGeometry(pinProfile, 40)), pinMat));
    const pinHole = new THREE.Mesh(keep(new THREE.SphereGeometry(0.13, 24, 24)), keep(new THREE.MeshStandardMaterial({ color: 0xffffff, roughness: 0.35 })));
    pinHole.position.set(0, 0.17, 0.27);
    pin.add(pinHole);
    const pinGround = new THREE.Vector3(pinBase.x, pinBase.y - 0.85, pinBase.z);
    const pinShadow = new THREE.Mesh(keep(new THREE.PlaneGeometry(0.9, 0.9)), keep(new THREE.MeshBasicMaterial({ map: glowTex, color: accent, transparent: true, opacity: 0.45, blending: THREE.AdditiveBlending, depthWrite: false })));
    pinShadow.rotation.x = -Math.PI / 2;
    pinShadow.position.copy(pinGround);
    rig.add(pinShadow);

    // ---- Kıvılcım, dalga ve toz ----
    const sparks = createSparks(rig, keep, { max: small ? 160 : 280, size: small ? 0.1 : 0.085 });
    const waves = createWaves(rig, keep, accent.clone().lerp(white, 0.25), { pool: 6 });
    const sparkWhite = new THREE.Color('#fff7e0');
    const dustCount = small ? 60 : 140;
    const dustPos = new Float32Array(dustCount * 3);
    for (let k = 0; k < dustCount; k++) {
        dustPos[k * 3] = (Math.random() - 0.5) * 11;
        dustPos[k * 3 + 1] = (Math.random() - 0.5) * 6.5;
        dustPos[k * 3 + 2] = (Math.random() - 0.5) * 5 - 1;
    }
    const dustGeo = keep(new THREE.BufferGeometry());
    dustGeo.setAttribute('position', new THREE.BufferAttribute(dustPos, 3));
    const dust = new THREE.Points(dustGeo, keep(new THREE.PointsMaterial({ size: 0.05, map: glowTex, transparent: true, opacity: 0.5, depthWrite: false, blending: THREE.AdditiveBlending })));
    scene.add(dust);

    // Üst alanın CSS değişkenleri (cam kartlar fareye tepki verir): kare başına bir kez
    let queued = false;
    section.addEventListener('pointermove', (e) => {
        if (queued) {
            return;
        }
        queued = true;
        requestAnimationFrame(() => {
            queued = false;
            const r = section.getBoundingClientRect();
            section.style.setProperty('--hx', (((e.clientX - r.left) / r.width) * 2 - 1).toFixed(3));
            section.style.setProperty('--hy', (((e.clientY - r.top) / r.height) * 2 - 1).toFixed(3));
        });
    }, { passive: true });

    // ---- Döngü ----
    const CYCLE = 6.5; // zarf: aç → mektup çık → bekle → geri gir → kapan
    let lastPhase = -1;
    let planeA = 0;
    let pinPulse = 0;
    const tmp = new THREE.Vector3();
    const letterTop = new THREE.Vector3();

    stage.onFrame((dt, t) => {
        rig.rotation.y = lerp(rig.rotation.y, pointer.x * 0.3, 0.06);
        rig.rotation.x = lerp(rig.rotation.x, -pointer.y * 0.14, 0.06);
        const intro = clamp((t - 0.15) / 1.1, 0, 1);
        envelope.scale.setScalar(Math.max(0.0001, intro < 1 ? easeOutBack(intro) : 1));
        envelope.position.y = -0.15 + Math.sin(t * 0.9) * 0.07;
        envelope.rotation.y = -0.18 + Math.sin(t * 0.45) * 0.12;

        // Zarf döngüsü
        const c = (t % CYCLE) / CYCLE;
        const open = c < 0.12 ? easeInOut(c / 0.12) : c < 0.82 ? 1 : c < 0.94 ? 1 - easeInOut((c - 0.82) / 0.12) : 0;
        const rise = c < 0.12 ? 0 : c < 0.3 ? easeInOut((c - 0.12) / 0.18) : c < 0.66 ? 1 : c < 0.82 ? 1 - easeInOut((c - 0.66) / 0.16) : 0;
        flapPivot.rotation.x = open * Math.PI * 0.92;
        letter.position.y = rise * ENV.h * 0.78;
        letter.rotation.z = Math.sin(rise * Math.PI) * 0.04;
        const phase = c < 0.3 ? 0 : 1;
        if (phase === 1 && lastPhase === 0) {
            // Mektup tam çıktı: üst kenarında kıvılcım ve kameraya bakan halka
            envelope.updateMatrixWorld();
            letterTop.set(0, ENV.h * 0.78 + ENV.h * 0.4, 0.05);
            envelope.localToWorld(letterTop);
            rig.worldToLocal(letterTop);
            sparks.emit(letterTop, small ? 18 : 30, accent, { speed: 1.4, up: 0.35, life: 1, spread: 0.6 });
            sparks.emit(letterTop, small ? 8 : 14, sparkWhite, { speed: 0.9, up: 0.25, life: 0.8, spread: 0.5 });
            waves.spawn(letterTop, 1.4, null, 0.9);
            waves.spawn(platform.position, 2.6, flat, 1.3);
            discMat.opacity = 0.7;
        }
        lastPhase = phase;
        discMat.opacity = lerp(discMat.opacity, 0.28 + Math.sin(t * 1.4) * 0.05, 0.04);
        ringA.rotation.z = t * 0.18;
        ringB.rotation.z = -t * 0.1;

        // Kâğıt uçak: eğik elips yörünge, gidiş yönüne bakar, dönüşte yatar; arkasında ışık izi
        planeA += dt * 0.55;
        plane.position.copy(planeAt(planeA));
        tmp.copy(planeAt(planeA + 0.05));
        plane.lookAt(rig.localToWorld(tmp.clone()));
        plane.rotateZ(Math.cos(planeA) * 0.5);
        plane.visible = intro > 0.6;
        if (plane.visible) {
            sparks.emit(plane.position, 1, Math.random() < 0.5 ? accent : sparkWhite, { speed: 0.08, up: 0.02, life: 0.8, spread: 0.12 });
        }

        // Balonlar: süzülür, noktalar sırayla zıplar
        bubbles.forEach((b, i) => {
            const bi = clamp((t - 0.5 - i * 0.25) / 0.9, 0, 1);
            b.g.scale.setScalar(Math.max(0.0001, bi < 1 ? easeOutBack(bi) : 1));
            b.g.position.y = b.base.y + Math.sin(t * 0.9 + b.phase) * 0.12;
            b.dots.forEach((d, k) => {
                const bounce = Math.max(0, Math.sin(t * 5 - k * 0.9 + b.phase));
                d.position.y = bounce * 0.09;
                d.scale.setScalar(0.85 + bounce * 0.3);
            });
        });

        // İğne: zıplar, yere değince nabız dalgası
        const hop = Math.abs(Math.sin(t * 1.6));
        pin.position.y = pinBase.y + hop * 0.32;
        pin.rotation.y = t * 0.8;
        pin.scale.setScalar(Math.max(0.0001, clamp((t - 0.8) / 0.8, 0, 1)) * (1 + (1 - hop) * 0.06));
        pinShadow.material.opacity = 0.2 + (1 - hop) * 0.4;
        pinPulse -= dt;
        if (hop < 0.08 && pinPulse <= 0) {
            waves.spawn(pinGround, 0.9, flat, 0.9);
            pinPulse = 0.5;
        }

        // Toz
        const arr = dustGeo.attributes.position.array;
        for (let k = 1; k < arr.length; k += 3) {
            arr[k] += dt * 0.1;
            if (arr[k] > 3.3) {
                arr[k] = -3.3;
            }
        }
        dustGeo.attributes.position.needsUpdate = true;
        sparks.update(dt);
        waves.update(dt);
    });

    camera.lookAt(0, 0, 0);
    stage.start(() => {
        // Hareketi azalt: zarf açık, mektup dışarıda, nesneler yerinde
        envelope.scale.setScalar(1);
        bubbles.forEach((b) => b.g.scale.setScalar(1));
    });
    section.classList.add('hero-3d-ready');
}
