/**
 * Giriş / üye ol sahnesi (küçük panel):
 *  - Ortada parlak asma kilit: belli aralıklarla kilit halkası kalkıp döner (açılır), sonra yerine oturur;
 *    oturduğu anda anahtar deliğinden kıvılcım ve halka dalgası çıkar.
 *  - Kilidin çevresinde süzülen anahtar, kitap ve belge; altta dönen ışık platformu ve yükselen toz.
 *  - Formda yazdıkça anahtar deliği parlar ve küçük kıvılcımlar saçar; parola alanına girince kilit kameraya döner.
 * Renkler tema değişkenlerinden; yazı yok. Hareketi azalt tercihinde tek kare çizilir.
 */
import { RoundedBoxGeometry } from 'three/examples/jsm/geometries/RoundedBoxGeometry.js';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';
import { THREE, clamp, createSparks, createStage, createWaves, cssColor, documentTexture, dotTexture, easeInOut, lerp } from './kit.js';

const easeOutBack = (t) => 1 + 2.2 * Math.pow(t - 1, 3) + 1.2 * Math.pow(t - 1, 2);

export function mount(root) {
    const panel = root.closest('[data-auth-panel]') || root;
    const stage = createStage(root, { fov: 34, z: 9, maxDpr: 1.6, pointerHost: panel.closest('[data-auth-shell]') || panel });
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
    scene.environmentIntensity = 0.75;
    scene.add(new THREE.HemisphereLight(0xffffff, primary.clone().multiplyScalar(0.4), 0.9));
    const key = new THREE.DirectionalLight(0xfff4e0, 2.2);
    key.position.set(3, 5, 6);
    scene.add(key);
    const rim = new THREE.PointLight(accent, 24, 12, 2);
    rim.position.set(-3, 2.2, -2);
    scene.add(rim);

    const rig = new THREE.Group();
    rig.scale.setScalar(small ? 1.05 : 1.3);
    rig.position.y = 0.15;
    scene.add(rig);
    const glowTex = keep(dotTexture());

    // ---- Asma kilit ----
    const lock = new THREE.Group();
    rig.add(lock);
    const bodyMat = keep(new THREE.MeshPhysicalMaterial({ color: primary, roughness: 0.28, metalness: 0.25, clearcoat: 1, clearcoatRoughness: 0.12 }));
    const body = new THREE.Mesh(keep(new RoundedBoxGeometry(1.7, 1.4, 0.62, 4, 0.18)), bodyMat);
    body.position.y = -0.35;
    lock.add(body);
    // Ön yüzde altın çerçeve
    const goldMat = keep(new THREE.MeshPhysicalMaterial({ color: accent, roughness: 0.22, metalness: 0.75, clearcoat: 0.8 }));
    const plate = new THREE.Mesh(keep(new RoundedBoxGeometry(1.2, 0.95, 0.06, 3, 0.1)), goldMat);
    plate.position.set(0, -0.35, 0.31);
    lock.add(plate);
    // Anahtar deliği: daire + aşağı inen yarık; yazdıkça parlar
    const holeMat = keep(new THREE.MeshStandardMaterial({ color: 0x0b1220, roughness: 0.5, emissive: accent, emissiveIntensity: 0 }));
    const holeTop = new THREE.Mesh(keep(new THREE.CylinderGeometry(0.13, 0.13, 0.04, 28)), holeMat);
    holeTop.rotation.x = Math.PI / 2;
    holeTop.position.set(0, -0.25, 0.35);
    const holeSlot = new THREE.Mesh(keep(new THREE.BoxGeometry(0.09, 0.28, 0.04)), holeMat);
    holeSlot.position.set(0, -0.42, 0.35);
    lock.add(holeTop, holeSlot);
    const holeGlow = new THREE.Mesh(keep(new THREE.PlaneGeometry(1.1, 1.1)), keep(new THREE.MeshBasicMaterial({ map: glowTex, color: accent, transparent: true, opacity: 0, blending: THREE.AdditiveBlending, depthWrite: false })));
    holeGlow.position.set(0, -0.32, 0.4);
    lock.add(holeGlow);

    // Kilit halkası: sağ bacağın ekseninde döner (açılınca kalkar ve yana çevrilir)
    const shackleMat = keep(new THREE.MeshPhysicalMaterial({ color: 0xdfe6ee, roughness: 0.18, metalness: 1, clearcoat: 0.5 }));
    const R = 0.5;
    const T = 0.11;
    const pivot = new THREE.Group();
    pivot.position.set(R, 0.2, 0);
    lock.add(pivot);
    const shackle = new THREE.Group();
    shackle.position.x = -R;
    pivot.add(shackle);
    const arc = new THREE.Mesh(keep(new THREE.TorusGeometry(R, T, 18, 48, Math.PI)), shackleMat);
    arc.position.y = 0.45;
    const legGeo = keep(new THREE.CylinderGeometry(T, T, 0.6, 18));
    const legL = new THREE.Mesh(legGeo, shackleMat);
    legL.position.set(-R, 0.15, 0);
    const legR = new THREE.Mesh(legGeo, shackleMat);
    legR.position.set(R, 0.15, 0);
    shackle.add(arc, legL, legR);

    // ---- Işık platformu ----
    const platform = new THREE.Group();
    platform.position.set(0, -1.45, 0);
    rig.add(platform);
    const ringColor = primary.clone().lerp(white, 0.5);
    const ringMat = (o) => keep(new THREE.MeshBasicMaterial({ color: ringColor, transparent: true, opacity: o, blending: THREE.AdditiveBlending, depthWrite: false }));
    const ringA = new THREE.Mesh(keep(new THREE.TorusGeometry(1.5, 0.012, 8, 160)), ringMat(0.55));
    const ringB = new THREE.Mesh(keep(new THREE.TorusGeometry(1.9, 0.006, 8, 160)), ringMat(0.28));
    ringA.rotation.x = ringB.rotation.x = Math.PI / 2;
    const discMat = keep(new THREE.MeshBasicMaterial({ map: glowTex, color: ringColor, transparent: true, opacity: 0.3, blending: THREE.AdditiveBlending, depthWrite: false }));
    const disc = new THREE.Mesh(keep(new THREE.PlaneGeometry(4, 4)), discMat);
    disc.rotation.x = -Math.PI / 2;
    platform.add(ringA, ringB, disc);
    const flat = new THREE.Quaternion().setFromEuler(new THREE.Euler(-Math.PI / 2, 0, 0));

    // ---- Yörüngedeki nesneler: anahtar, kitap, belge ----
    const keyObj = new THREE.Group();
    const bow = new THREE.Mesh(keep(new THREE.TorusGeometry(0.16, 0.05, 12, 32)), goldMat);
    bow.position.x = -0.32;
    const shaft = new THREE.Mesh(keep(new THREE.CylinderGeometry(0.04, 0.04, 0.5, 12)), goldMat);
    shaft.rotation.z = Math.PI / 2;
    shaft.position.x = 0.07;
    const bit = new THREE.Mesh(keep(new THREE.BoxGeometry(0.06, 0.14, 0.05)), goldMat);
    bit.position.set(0.26, -0.08, 0);
    const bit2 = bit.clone();
    bit2.position.x = 0.16;
    bit2.scale.y = 0.7;
    keyObj.add(bow, shaft, bit, bit2);

    const book = new THREE.Group();
    const coverMat = keep(new THREE.MeshPhysicalMaterial({ color: accent, roughness: 0.35, clearcoat: 0.6 }));
    const pagesMat = keep(new THREE.MeshStandardMaterial({ color: 0xfaf7ef, roughness: 0.8 }));
    book.add(new THREE.Mesh(keep(new RoundedBoxGeometry(0.5, 0.66, 0.14, 2, 0.03)), [coverMat, pagesMat, pagesMat, pagesMat, coverMat, coverMat]));

    const doc = new THREE.Mesh(keep(new THREE.PlaneGeometry(0.5, 0.68)), keep(new THREE.MeshStandardMaterial({ map: keep(documentTexture(accentHex, true, primaryHex)), roughness: 0.7, side: THREE.DoubleSide })));

    const orbiters = [
        { o: keyObj, r: 2.05, y: 0.55, speed: 0.5, phase: 0, tilt: 0.25 },
        { o: book, r: 2.2, y: -0.35, speed: 0.5, phase: Math.PI * 0.68, tilt: 0.25 },
        { o: doc, r: 2.1, y: 0.15, speed: 0.5, phase: Math.PI * 1.36, tilt: 0.25 },
    ];
    orbiters.forEach((b) => {
        b.o.scale.setScalar(0.0001);
        rig.add(b.o);
    });

    // ---- Kıvılcım, dalga ve toz ----
    const sparks = createSparks(rig, keep, { max: small ? 120 : 220, size: small ? 0.1 : 0.085 });
    const waves = createWaves(rig, keep, accent.clone().lerp(white, 0.25), { pool: 5 });
    const sparkWhite = new THREE.Color('#fff7e0');
    const dustCount = small ? 40 : 90;
    const dustPos = new Float32Array(dustCount * 3);
    for (let k = 0; k < dustCount; k++) {
        dustPos[k * 3] = (Math.random() - 0.5) * 8;
        dustPos[k * 3 + 1] = (Math.random() - 0.5) * 5.5;
        dustPos[k * 3 + 2] = (Math.random() - 0.5) * 4 - 1;
    }
    const dustGeo = keep(new THREE.BufferGeometry());
    dustGeo.setAttribute('position', new THREE.BufferAttribute(dustPos, 3));
    scene.add(new THREE.Points(dustGeo, keep(new THREE.PointsMaterial({ size: 0.05, map: glowTex, transparent: true, opacity: 0.5, depthWrite: false, blending: THREE.AdditiveBlending }))));

    // ---- Formla etkileşim ----
    let typing = 0;
    let typed = 0;
    let focusPassword = false;
    const form = (panel.closest('[data-auth-shell]') || document).querySelector('form');
    form?.addEventListener('input', () => {
        typing = 1;
        typed += 1;
    });
    form?.addEventListener('focusin', (e) => (focusPassword = e.target.type === 'password' || e.target.autocomplete?.includes('password')));
    form?.addEventListener('focusout', () => (focusPassword = false));

    // ---- Döngü ----
    const CYCLE = 5.5; // kapalı → halka kalkar → yana döner → bekler → geri döner → oturur
    let wasOpen = false;
    const hole = new THREE.Vector3();

    stage.onFrame((dt, t) => {
        rig.rotation.y = lerp(rig.rotation.y, pointer.x * 0.35, 0.06);
        rig.rotation.x = lerp(rig.rotation.x, -pointer.y * 0.15, 0.06);
        const intro = clamp((t - 0.1) / 1, 0, 1);
        lock.scale.setScalar(Math.max(0.0001, intro < 1 ? easeOutBack(intro) : 1));
        lock.position.y = Math.sin(t * 1.1) * 0.08;
        const sway = focusPassword ? 0 : Math.sin(t * 0.55) * 0.35 - 0.12;
        lock.rotation.y = lerp(lock.rotation.y, sway, 0.05);
        lock.rotation.z = Math.sin(t * 0.8) * 0.03;

        const c = (t % CYCLE) / CYCLE;
        const lift = c < 0.1 ? easeInOut(c / 0.1) : c < 0.7 ? 1 : c < 0.82 ? 1 - easeInOut((c - 0.7) / 0.12) : 0;
        const turn = c < 0.1 ? 0 : c < 0.22 ? easeInOut((c - 0.1) / 0.12) : c < 0.6 ? 1 : c < 0.7 ? 1 - easeInOut((c - 0.6) / 0.1) : 0;
        pivot.position.y = 0.2 + lift * 0.32;
        pivot.rotation.y = turn * 1.15;
        const open = lift > 0.02;
        if (wasOpen && !open && intro >= 1) {
            // Halka yerine oturdu: "klik"
            lock.updateMatrixWorld();
            hole.set(0, -0.3, 0.45);
            lock.localToWorld(hole);
            rig.worldToLocal(hole);
            sparks.emit(hole, small ? 16 : 26, accent, { speed: 1.3, up: 0.3, life: 0.9, spread: 0.3 });
            sparks.emit(hole, small ? 6 : 12, sparkWhite, { speed: 0.9, up: 0.25, life: 0.7, spread: 0.25 });
            waves.spawn(hole, 1.2, null, 0.8);
            waves.spawn(platform.position, 2.2, flat, 1.2);
            discMat.opacity = 0.7;
            holeMat.emissiveIntensity = 1.6;
        }
        wasOpen = open;

        // Yazarken anahtar deliği parlar, ara ara kıvılcım saçar
        typing = Math.max(0, typing - dt * 1.6);
        holeMat.emissiveIntensity = lerp(holeMat.emissiveIntensity, typing * 1.2 + (focusPassword ? 0.5 : 0.08), 0.12);
        holeGlow.material.opacity = holeMat.emissiveIntensity * 0.45;
        if (typed > 0) {
            lock.updateMatrixWorld();
            hole.set(0, -0.3, 0.45);
            lock.localToWorld(hole);
            rig.worldToLocal(hole);
            sparks.emit(hole, Math.min(typed, 3) * 2, Math.random() < 0.6 ? accent : sparkWhite, { speed: 0.7, up: 0.35, life: 0.6, spread: 0.12 });
            typed = 0;
        }

        discMat.opacity = lerp(discMat.opacity, 0.28 + Math.sin(t * 1.4) * 0.05, 0.04);
        ringA.rotation.z = t * 0.2;
        ringB.rotation.z = -t * 0.12;

        orbiters.forEach((b, i) => {
            const a = t * b.speed + b.phase;
            const bi = clamp((t - 0.5 - i * 0.2) / 0.8, 0, 1);
            b.o.scale.setScalar(Math.max(0.0001, bi < 1 ? easeOutBack(bi) : 1));
            b.o.position.set(Math.cos(a) * b.r, b.y + Math.sin(a * 1.3) * 0.15 + Math.sin(a) * b.tilt, Math.sin(a) * 1.1);
            b.o.rotation.set(Math.sin(t * 0.7 + i) * 0.4, t * 0.6 + i, Math.cos(t * 0.5 + i) * 0.25);
        });

        const arr = dustGeo.attributes.position.array;
        for (let k = 1; k < arr.length; k += 3) {
            arr[k] += dt * 0.1;
            if (arr[k] > 2.8) {
                arr[k] = -2.8;
            }
        }
        dustGeo.attributes.position.needsUpdate = true;
        sparks.update(dt);
        waves.update(dt);
    });

    camera.lookAt(0, 0, 0);
    stage.start(() => {
        lock.scale.setScalar(1);
        orbiters.forEach((b) => b.o.scale.setScalar(1));
    });
    panel.classList.add('hero-3d-ready');
}
