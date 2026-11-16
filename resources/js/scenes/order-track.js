/**
 * Sipariş takibi 3D sahnesi: havada süzülen küçük bir şehir adası.
 *
 *  - Adanın bir ucunda dükkân (tenteli), öbür ucunda ev; aralarında kıvrılan bir yol, ortasında akan kesik çizgi.
 *  - Yolda üstünde paket taşıyan bir minibüs; evin üstünde zıplayan konum iğnesi, zeminde yayılan halka.
 *  - Sorgu sayfasında (data-step yok) minibüs döngüyle dükkândan eve gider, paket eve zıplar, yeniden başlar.
 *  - Sipariş sayfasında data-step (0–4) siparişin adımıdır: minibüs açılışta o adımın yerine sürer ve orada bekler;
 *    teslim edildiyse iğne yeşile döner ve ışık parçacıkları saçılır; data-cancelled ise minibüs dükkânda, iğne söner.
 *  - Fare adayı hafifçe çevirir. Renkler tema değişkenlerinden okunur.
 */
import { RoundedBoxGeometry } from 'three/examples/jsm/geometries/RoundedBoxGeometry.js';
import { THREE, canvasTexture, clamp, createStage, cssColor, easeInOut, lerp } from './kit.js';

const TOP = 0.16; // ada üst yüzeyinin yüksekliği
const ROAD_W = 0.46;

/** Yol dokusu: koyu asfalt, kenarlarda ince açık çizgi, ortada kesik çizgi */
function roadTexture(dash) {
    const t = canvasTexture(64, 256, (g, w, h) => {
        g.fillStyle = '#273449';
        g.fillRect(0, 0, w, h);
        g.fillStyle = 'rgba(255,255,255,0.28)';
        g.fillRect(3, 0, 3, h);
        g.fillRect(w - 6, 0, 3, h);
        g.fillStyle = dash;
        for (let y = 0; y < h; y += 64) {
            g.fillRect(w / 2 - 3, y + 8, 6, 34);
        }
    });
    t.wrapS = t.wrapT = THREE.RepeatWrapping;
    return t;
}

/** Tente: beyaz ve renkli şeritler */
function awningTexture(color) {
    return canvasTexture(128, 32, (g, w, h) => {
        for (let i = 0; i < 8; i++) {
            g.fillStyle = i % 2 ? '#ffffff' : color;
            g.fillRect((i * w) / 8, 0, w / 8 + 1, h);
        }
    });
}

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

/** Eğri boyunca düz şerit (yol) geometrisi; u = genişlik, v = uzunluk */
function ribbon(curve, width, segments) {
    const pos = [];
    const uv = [];
    const idx = [];
    const up = new THREE.Vector3(0, 1, 0);
    const side = new THREE.Vector3();
    const len = curve.getLength();
    for (let i = 0; i <= segments; i++) {
        const u = i / segments;
        const p = curve.getPointAt(u);
        side.crossVectors(curve.getTangentAt(u), up).normalize().multiplyScalar(width / 2);
        pos.push(p.x - side.x, p.y, p.z - side.z, p.x + side.x, p.y, p.z + side.z);
        uv.push(0, (u * len) / width / 2, 1, (u * len) / width / 2);
        if (i < segments) {
            const a = i * 2;
            idx.push(a, a + 1, a + 2, a + 1, a + 3, a + 2);
        }
    }
    const g = new THREE.BufferGeometry();
    g.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
    g.setAttribute('uv', new THREE.Float32BufferAttribute(uv, 2));
    g.setIndex(idx);
    g.computeVertexNormals();
    return g;
}

export function mount(root) {
    const stage = createStage(root, { fov: 30, z: 9, maxDpr: 1.75, pointerHost: root.closest('section') || root });
    if (!stage) {
        return;
    }
    const { scene, camera, pointer, keep, small, reduced, renderer } = stage;
    const canvas = renderer.domElement;
    // Üst alandaki kartlar (sorgu formu, cam maddeler) fareyle hafifçe kayar/eğilir
    const section = root.closest('section');
    section?.addEventListener('pointermove', (e) => {
        const r = section.getBoundingClientRect();
        section.style.setProperty('--hx', (((e.clientX - r.left) / r.width) * 2 - 1).toFixed(3));
        section.style.setProperty('--hy', (((e.clientY - r.top) / r.height) * 2 - 1).toFixed(3));
    }, { passive: true });

    const hasStep = root.dataset.step !== undefined && root.dataset.step !== '';
    const step = clamp(parseInt(root.dataset.step || '0', 10) || 0, 0, 4);
    const cancelled = root.dataset.cancelled === '1';
    const delivered = hasStep && !cancelled && step === 4;

    const primary = cssColor('--color-primary', '#0f766e');
    const accent = cssColor('--color-accent', '#f59e0b');
    const success = cssColor('--color-success', '#16a34a');
    const white = new THREE.Color('#ffffff');
    const accentHex = '#' + accent.getHexString();
    const primaryHex = '#' + primary.getHexString();

    renderer.toneMappingExposure = 1.1;

    const fit = () => {
        const aspect = canvas.clientWidth / Math.max(1, canvas.clientHeight);
        const back = aspect < 1.25 ? (1.25 - aspect) * 6 : 0;
        camera.position.set(0, 5.3 + back * 0.6, 8.9 + back);
        camera.lookAt(0, -0.1, 0);
    };
    fit();
    new ResizeObserver(fit).observe(canvas);

    scene.add(new THREE.HemisphereLight(0xffffff, primary.clone().multiplyScalar(0.4), 1.35));
    const sun = new THREE.DirectionalLight(0xfff1dc, 2.1);
    sun.position.set(-3, 6, 4);
    scene.add(sun);
    const glow = new THREE.PointLight(accent, 8, 6, 2);
    glow.position.set(2, 1.6, -0.6);
    scene.add(glow);

    const rig = new THREE.Group();
    rig.rotation.y = -0.32;
    rig.scale.setScalar(small ? 0.9 : 1);
    scene.add(rig);

    // ---- Ada ----
    const islandTop = keep(new THREE.MeshStandardMaterial({ color: primary.clone().lerp(white, 0.12), roughness: 0.75 }));
    const islandSide = keep(new THREE.MeshStandardMaterial({ color: primary.clone().multiplyScalar(0.45), roughness: 0.6 }));
    const island = new THREE.Mesh(keep(new RoundedBoxGeometry(5.6, 0.32, 3.7, 4, 0.14)), [islandSide, islandSide, islandTop, islandSide, islandSide, islandSide]);
    rig.add(island);
    const under = new THREE.Mesh(keep(new THREE.CylinderGeometry(1.7, 0.2, 1.3, 6, 1)), keep(new THREE.MeshStandardMaterial({ color: primary.clone().multiplyScalar(0.3), roughness: 0.9, flatShading: true })));
    under.position.y = -0.8;
    under.scale.set(1.45, 1, 0.95);
    rig.add(under);
    const edge = new THREE.Mesh(keep(new THREE.BoxGeometry(5.62, 0.03, 3.72)), keep(new THREE.MeshStandardMaterial({ color: accent, emissive: accent, emissiveIntensity: 0.9 })));
    edge.position.y = -0.1;
    rig.add(edge);

    // ---- Yol ----
    const curve = new THREE.CatmullRomCurve3([
        new THREE.Vector3(-2.05, TOP + 0.005, 0.95),
        new THREE.Vector3(-1.2, TOP + 0.005, 1.2),
        new THREE.Vector3(-0.3, TOP + 0.005, 0.85),
        new THREE.Vector3(0.35, TOP + 0.005, -0.05),
        new THREE.Vector3(1.15, TOP + 0.005, -0.75),
        new THREE.Vector3(1.9, TOP + 0.005, -0.85),
    ]);
    const roadMap = keep(roadTexture(accentHex));
    const road = new THREE.Mesh(keep(ribbon(curve, ROAD_W, 120)), keep(new THREE.MeshStandardMaterial({ map: roadMap, roughness: 0.85 })));
    rig.add(road);
    // Kat edilen yol: yeşil iz (sipariş sayfasında adıma kadar)
    const trailMat = keep(new THREE.MeshBasicMaterial({ color: accent, transparent: true, opacity: 0.5, blending: THREE.AdditiveBlending, depthWrite: false }));

    // ---- Dükkân ----
    const shop = new THREE.Group();
    shop.position.set(-2.15, TOP, 0.05);
    shop.rotation.y = 0.35;
    rig.add(shop);
    const wallMat = keep(new THREE.MeshStandardMaterial({ color: 0xf8fafc, roughness: 0.6 }));
    const darkMat = keep(new THREE.MeshStandardMaterial({ color: 0x1e293b, roughness: 0.3, metalness: 0.4 }));
    const shopBody = new THREE.Mesh(keep(new RoundedBoxGeometry(1.0, 0.78, 0.8, 3, 0.05)), wallMat);
    shopBody.position.y = 0.39;
    shop.add(shopBody);
    const roof = new THREE.Mesh(keep(new RoundedBoxGeometry(1.1, 0.1, 0.9, 2, 0.04)), keep(new THREE.MeshStandardMaterial({ color: primary, roughness: 0.4 })));
    roof.position.y = 0.83;
    shop.add(roof);
    const awning = new THREE.Mesh(keep(new THREE.BoxGeometry(1.04, 0.03, 0.34)), keep(new THREE.MeshStandardMaterial({ map: keep(awningTexture(accentHex)), roughness: 0.6 })));
    awning.position.set(0, 0.6, 0.55);
    awning.rotation.x = 0.4;
    shop.add(awning);
    const win = new THREE.Mesh(keep(new THREE.PlaneGeometry(0.5, 0.28)), keep(new THREE.MeshStandardMaterial({ color: primary.clone().lerp(white, 0.5), emissive: accent, emissiveIntensity: 0.35, roughness: 0.1 })));
    win.position.set(-0.18, 0.36, 0.405);
    shop.add(win);
    const door = new THREE.Mesh(keep(new THREE.PlaneGeometry(0.2, 0.36)), darkMat);
    door.position.set(0.3, 0.18, 0.405);
    shop.add(door);

    // ---- Ev ----
    const house = new THREE.Group();
    house.position.set(2.15, TOP, -1.45);
    house.rotation.y = -0.4;
    rig.add(house);
    const houseBody = new THREE.Mesh(keep(new RoundedBoxGeometry(0.8, 0.56, 0.7, 3, 0.04)), wallMat);
    houseBody.position.y = 0.28;
    house.add(houseBody);
    const roofMat = keep(new THREE.MeshStandardMaterial({ color: accent, roughness: 0.45 }));
    const houseRoof = new THREE.Mesh(keep(new THREE.CylinderGeometry(0.01, 0.62, 0.42, 4, 1)), roofMat);
    houseRoof.position.y = 0.77;
    houseRoof.rotation.y = Math.PI / 4;
    houseRoof.scale.set(1, 1, 0.9);
    house.add(houseRoof);
    const hDoor = new THREE.Mesh(keep(new THREE.PlaneGeometry(0.18, 0.3)), keep(new THREE.MeshStandardMaterial({ color: primary, roughness: 0.4 })));
    hDoor.position.set(0, 0.15, 0.355);
    house.add(hDoor);
    const hWin = new THREE.Mesh(keep(new THREE.PlaneGeometry(0.16, 0.14)), keep(new THREE.MeshStandardMaterial({ color: 0xfff7d6, emissive: 0xffd27a, emissiveIntensity: 0.8 })));
    hWin.position.set(0.24, 0.34, 0.355);
    house.add(hWin);

    // ---- Ağaçlar ----
    const trunkMat = keep(new THREE.MeshStandardMaterial({ color: 0x7c5a3a, roughness: 0.8 }));
    const leafMat = keep(new THREE.MeshStandardMaterial({ color: success.clone().lerp(primary, 0.3), roughness: 0.55, flatShading: true }));
    const trunkGeo = keep(new THREE.CylinderGeometry(0.04, 0.05, 0.22, 8));
    const leafGeo = keep(new THREE.IcosahedronGeometry(0.22, 0));
    [[-0.9, -1.1], [-0.2, -1.35], [0.9, 1.15], [1.9, 0.6], [-2.4, 1.45], [2.5, -0.2], [0.2, 1.45]].forEach(([x, z], i) => {
        const tree = new THREE.Group();
        tree.position.set(x, TOP, z);
        const trunk = new THREE.Mesh(trunkGeo, trunkMat);
        trunk.position.y = 0.11;
        tree.add(trunk);
        const leaf = new THREE.Mesh(leafGeo, leafMat);
        leaf.position.y = 0.36;
        leaf.rotation.set(i, i * 2, 0);
        tree.add(leaf);
        tree.scale.setScalar(0.8 + (i % 3) * 0.18);
        rig.add(tree);
    });

    // ---- Minibüs (ileri yönü +z) ----
    const van = new THREE.Group();
    rig.add(van);
    const vanBody = new THREE.Group(); // zıplama için iç grup
    van.add(vanBody);
    const vanWhite = keep(new THREE.MeshPhysicalMaterial({ color: 0xffffff, roughness: 0.35, clearcoat: 0.8 }));
    const vanColor = keep(new THREE.MeshPhysicalMaterial({ color: primary, roughness: 0.3, clearcoat: 1 }));
    const cargo = new THREE.Mesh(keep(new RoundedBoxGeometry(0.36, 0.32, 0.5, 3, 0.05)), vanWhite);
    cargo.position.set(0, 0.24, -0.08);
    vanBody.add(cargo);
    const stripe = new THREE.Mesh(keep(new THREE.BoxGeometry(0.365, 0.05, 0.5)), vanColor);
    stripe.position.set(0, 0.2, -0.08);
    vanBody.add(stripe);
    const cab = new THREE.Mesh(keep(new RoundedBoxGeometry(0.36, 0.24, 0.22, 3, 0.05)), vanColor);
    cab.position.set(0, 0.2, 0.26);
    vanBody.add(cab);
    const shield = new THREE.Mesh(keep(new THREE.PlaneGeometry(0.28, 0.1)), keep(new THREE.MeshStandardMaterial({ color: 0x0f172a, roughness: 0.1, metalness: 0.6 })));
    shield.position.set(0, 0.26, 0.372);
    shield.rotation.x = -0.25;
    vanBody.add(shield);
    const lightMat = keep(new THREE.MeshStandardMaterial({ color: 0xfff7d6, emissive: 0xfff1c1, emissiveIntensity: 2 }));
    [-0.12, 0.12].forEach((x) => {
        const l = new THREE.Mesh(keep(new THREE.CircleGeometry(0.03, 12)), lightMat);
        l.position.set(x, 0.15, 0.373);
        vanBody.add(l);
    });
    const wheelGeo = keep(new THREE.CylinderGeometry(0.07, 0.07, 0.06, 16));
    wheelGeo.rotateZ(Math.PI / 2);
    const wheels = [];
    [[-0.17, 0.22], [0.17, 0.22], [-0.17, -0.2], [0.17, -0.2]].forEach(([x, z]) => {
        const w = new THREE.Mesh(wheelGeo, darkMat);
        w.position.set(x, 0.07, z);
        van.add(w);
        wheels.push(w);
    });
    // Paket (kargo kutusu, üstte bantlı)
    const boxMat = keep(new THREE.MeshStandardMaterial({ color: 0xd4a373, roughness: 0.8 }));
    const tapeMat = keep(new THREE.MeshStandardMaterial({ color: 0xf5e6c8, roughness: 0.6 }));
    const parcel = new THREE.Group();
    parcel.add(new THREE.Mesh(keep(new RoundedBoxGeometry(0.26, 0.18, 0.26, 2, 0.02)), boxMat));
    const tape = new THREE.Mesh(keep(new THREE.BoxGeometry(0.265, 0.185, 0.05)), tapeMat);
    parcel.add(tape);
    const parcelHome = new THREE.Vector3(0, 0.5, -0.1);
    parcel.position.copy(parcelHome);
    vanBody.add(parcel);

    // ---- Konum iğnesi ve halka ----
    const pinColor = delivered ? success : cancelled ? new THREE.Color('#94a3b8') : accent;
    const pinMat = keep(new THREE.MeshPhysicalMaterial({ color: pinColor, emissive: pinColor, emissiveIntensity: 0.35, roughness: 0.25, clearcoat: 1 }));
    const pin = new THREE.Group();
    const pinHead = new THREE.Mesh(keep(new THREE.SphereGeometry(0.22, 32, 24)), pinMat);
    pinHead.position.y = 0.32;
    pin.add(pinHead);
    const pinTip = new THREE.Mesh(keep(new THREE.ConeGeometry(0.17, 0.38, 32)), pinMat);
    pinTip.rotation.x = Math.PI;
    pinTip.position.y = 0.08;
    pin.add(pinTip);
    const pinDot = new THREE.Mesh(keep(new THREE.SphereGeometry(0.08, 16, 12)), wallMat);
    pinDot.position.set(0, 0.34, 0.16);
    pin.add(pinDot);
    const pinBase = new THREE.Vector3(2.15, TOP + 1.25, -1.45);
    pin.position.copy(pinBase);
    rig.add(pin);
    const ringMat = keep(new THREE.MeshBasicMaterial({ color: pinColor, transparent: true, opacity: 0.6, depthWrite: false, side: THREE.DoubleSide }));
    const ripples = [0, 0.5].map((offset) => {
        const r = new THREE.Mesh(keep(new THREE.RingGeometry(0.42, 0.5, 48)), ringMat.clone());
        keep(r.material);
        r.rotation.x = -Math.PI / 2;
        r.position.set(1.85, TOP + 0.01, -0.85);
        rig.add(r);
        return { r, offset };
    });

    // ---- Işık parçacıkları (havada süzülür; teslimde patlar) ----
    const dotMap = keep(dotTexture());
    const count = small ? 50 : 100;
    const pts = new Float32Array(count * 3);
    const seeds = [];
    for (let k = 0; k < count; k++) {
        seeds.push({ x: (Math.random() - 0.5) * 7, y: Math.random() * 3 - 0.5, z: (Math.random() - 0.5) * 4.5, s: 0.1 + Math.random() * 0.25, burst: new THREE.Vector3() });
    }
    const dustGeo = keep(new THREE.BufferGeometry());
    dustGeo.setAttribute('position', new THREE.BufferAttribute(pts, 3));
    const dustMat = keep(new THREE.PointsMaterial({ size: 0.07, map: dotMap, color: (delivered ? success : accent).clone().lerp(white, 0.5), transparent: true, opacity: 0.75, blending: THREE.AdditiveBlending, depthWrite: false }));
    rig.add(new THREE.Points(dustGeo, dustMat));

    // ---- Kat edilen yol izi (sipariş sayfası) ----
    const STEP_AT = [0, 0.2, 0.42, 0.7, 1]; // adımların yol üzerindeki yeri
    const target = hasStep && !cancelled ? STEP_AT[step] : 0;
    let trail = null;
    if (hasStep && target > 0.01) {
        const pts2 = [];
        for (let i = 0; i <= 40; i++) {
            pts2.push(curve.getPointAt((i / 40) * target).clone().add(new THREE.Vector3(0, 0.01, 0)));
        }
        trail = new THREE.Mesh(keep(ribbon(new THREE.CatmullRomCurve3(pts2), 0.12, 80)), trailMat);
        trail.geometry.setDrawRange(0, 0);
        rig.add(trail);
    }

    // ---- Döngü ----
    const tmp = new THREE.Vector3();
    const look = new THREE.Vector3();
    let progress = 0; // minibüsün yol üzerindeki yeri (0..1)
    let cycle = 0; // sorgu sayfası döngüsü (saniye)
    let arrived = 0; // teslim patlaması
    const placeVan = (u) => {
        const p = curve.getPointAt(clamp(u, 0, 1));
        van.position.copy(p);
        curve.getPointAt(clamp(u + 0.01, 0, 1), look);
        if (u >= 0.99) {
            curve.getPointAt(0.98, tmp);
            look.copy(p).add(p.clone().sub(tmp));
        }
        van.lookAt(look);
    };
    const DRIVE = 5.2;
    const STAY = 1.8;

    stage.onFrame((dt, t) => {
        rig.rotation.y = lerp(rig.rotation.y, -0.32 + pointer.x * 0.25, 0.05);
        rig.rotation.x = lerp(rig.rotation.x, -pointer.y * 0.08, 0.05);
        rig.position.y = Math.sin(t * 0.8) * 0.06;

        let speed = 0;
        let hop = -1; // paket zıplaması (0..1), yoksa -1
        if (!hasStep) {
            cycle += dt;
            const total = DRIVE + STAY + 0.6;
            const c = cycle % total;
            if (c < DRIVE) {
                const u = easeInOut(c / DRIVE);
                speed = Math.abs(u - progress) / Math.max(dt, 1e-4);
                progress = u;
                van.scale.setScalar(Math.min(1, c / 0.4 + 0.0001));
                parcel.visible = true;
                parcel.position.copy(parcelHome);
            } else if (c < DRIVE + STAY) {
                progress = 1;
                hop = (c - DRIVE) / STAY;
                if (hop > 0 && arrived < 0.01 && hop < 0.05) {
                    arrived = 1;
                }
            } else {
                // Sönüp dükkâna dön
                van.scale.setScalar(Math.max(0.0001, 1 - (c - DRIVE - STAY) / 0.6));
            }
        } else if (!cancelled) {
            const before = progress;
            progress = lerp(progress, target, Math.min(1, dt * 1.1));
            speed = Math.abs(progress - before) / Math.max(dt, 1e-4);
            if (trail) {
                const n = trail.geometry.index.count;
                trail.geometry.setDrawRange(0, Math.floor((n * clamp(progress / target, 0, 1)) / 6) * 6);
            }
            if (delivered && progress > 0.985 && arrived === 0) {
                arrived = 1;
                parcel.visible = false;
            }
        }
        placeVan(progress);

        // Paket eve zıplar
        if (hop >= 0) {
            if (hop < 0.55) {
                const u = hop / 0.55;
                const homeLocal = vanBody.worldToLocal(house.localToWorld(tmp.set(0, 0.2, 0.4)));
                parcel.position.set(lerp(parcelHome.x, homeLocal.x, u), lerp(parcelHome.y, homeLocal.y, u) + Math.sin(u * Math.PI) * 0.6, lerp(parcelHome.z, homeLocal.z, u));
                parcel.rotation.y = u * Math.PI * 2;
                parcel.scale.setScalar(1 - u * 0.5);
            } else {
                parcel.visible = false;
            }
        } else {
            parcel.rotation.y = 0;
            parcel.scale.setScalar(1);
        }

        // Tekerlek, sarsıntı
        wheels.forEach((w) => (w.rotation.x += speed * 8 * dt + (progress > 0 && progress < 1 && !hasStep ? dt * 4 : 0)));
        vanBody.position.y = Math.abs(Math.sin(t * 14)) * 0.012 * Math.min(1, speed * 6 + (hasStep ? 0.25 : 0.8));
        roadMap.offset.y -= dt * (hasStep ? 0.15 : 0.6);

        // İğne
        pin.position.y = pinBase.y + Math.sin(t * 2.2) * 0.1 + (cancelled ? -0.2 : 0);
        pin.rotation.y = t * (delivered ? 1.2 : 0.8);
        pinMat.emissiveIntensity = 0.3 + arrived * 0.8;
        ripples.forEach(({ r, offset }) => {
            const u = ((t * 0.6 + offset) % 1);
            r.scale.setScalar(0.6 + u * (cancelled ? 0.4 : 1.6));
            r.material.opacity = (1 - u) * (cancelled ? 0.2 : 0.6);
        });

        // Parçacıklar: süzülme + teslim patlaması
        const k2 = Math.max(0, arrived);
        if (arrived > 0) {
            arrived = Math.max(hasStep ? 0.0001 : 0, arrived - dt * 0.6);
        }
        const arr = dustGeo.attributes.position.array;
        seeds.forEach((s, k) => {
            s.y += dt * s.s;
            if (s.y > 2.8) {
                s.y = -0.4;
            }
            const burst = k2 > 0.01 ? Math.sin((1 - k2) * Math.PI) : 0;
            arr[k * 3] = lerp(s.x, 1.85 + (s.x / 7) * 2.5, burst * 0.7);
            arr[k * 3 + 1] = s.y + burst * 0.6;
            arr[k * 3 + 2] = lerp(s.z, -0.85 + (s.z / 4.5) * 2, burst * 0.7);
        });
        dustGeo.attributes.position.needsUpdate = true;
        dustMat.size = 0.07 + k2 * 0.05;
    });

    stage.start(() => {
        progress = hasStep && !cancelled ? target : 0.45;
        cycle = DRIVE * 0.45; // tek karede minibüs yolun ortasında görünsün
        if (trail) {
            trail.geometry.setDrawRange(0, trail.geometry.index.count);
        }
    });
    (root.closest('section') || root).classList.add('hero-3d-ready');
}
