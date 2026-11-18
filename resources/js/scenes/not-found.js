/**
 * 404 sahnesi: parlak tüplerden iki "4" rakamı, ortada "0" yerine iz süren bir büyüteç.
 * Büyüteç sağa sola tarar, belli aralıklarla merceğinden tarama dalgası yayar; çevrede belge sayfaları süzülür.
 * Fare sahneyi çevirir ve büyüteci kendine doğru çeker; tıklayınca büyüteç döner ve kıvılcım saçar.
 */
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';
import { THREE, createSparks, createStage, createWaves, cssColor, documentTexture, easeInOut, lerp } from './kit.js';

export function mount(root) {
    const stage = createStage(root, { fov: 32, z: 9 });
    if (!stage) {
        return;
    }
    const { renderer, scene, pointer, keep, small } = stage;
    const primary = cssColor('--color-primary', '#0f766e');
    const accent = cssColor('--color-accent', '#f59e0b');
    const white = new THREE.Color('#ffffff');

    const pmrem = keep(new THREE.PMREMGenerator(renderer));
    scene.environment = keep(pmrem.fromScene(new RoomEnvironment(), 0.04).texture);
    scene.add(new THREE.AmbientLight(0xffffff, 0.5));
    const key = new THREE.DirectionalLight(0xffffff, 2.6);
    key.position.set(3, 4, 6);
    scene.add(key);
    const rim = new THREE.PointLight(accent, 30, 12, 2);
    rim.position.set(0, 1, 2.5);
    scene.add(rim);

    const rig = new THREE.Group();
    rig.scale.setScalar(small ? 0.78 : 1);
    scene.add(rig);

    // "4" rakamı: üç düz parçadan tüp (köşelerde küre)
    const R = 0.2;
    const digitMat = keep(new THREE.MeshPhysicalMaterial({ color: primary.clone().lerp(white, 0.55), roughness: 0.14, metalness: 0.2, clearcoat: 1, clearcoatRoughness: 0.06 }));
    const cap = keep(new THREE.SphereGeometry(R, 24, 24));
    const four = () => {
        const g = new THREE.Group();
        const pts = [[0.35, -1.25], [0.35, 1.2], [-0.75, -0.25], [0.75, -0.25]].map(([x, y]) => new THREE.Vector3(x, y, 0));
        [[pts[0], pts[1]], [pts[1], pts[2]], [pts[2], pts[3]]].forEach(([a, b]) => {
            g.add(new THREE.Mesh(keep(new THREE.TubeGeometry(new THREE.LineCurve3(a, b), 8, R, 24, false)), digitMat));
        });
        pts.forEach((p) => {
            const s = new THREE.Mesh(cap, digitMat);
            s.position.copy(p);
            g.add(s);
        });
        rig.add(g);
        return g;
    };
    const left = four();
    const right = four();
    left.position.x = -2.35;
    right.position.x = 2.35;

    // Büyüteç: altın çerçeve, cam mercek, sap
    const lens = new THREE.Group();
    rig.add(lens);
    const gold = keep(new THREE.MeshPhysicalMaterial({ color: accent, roughness: 0.18, metalness: 0.75, clearcoat: 1, emissive: accent, emissiveIntensity: 0.1 }));
    const glass = keep(new THREE.MeshPhysicalMaterial({ color: 0xffffff, roughness: 0.02, metalness: 0, transmission: 0.9, thickness: 0.4, transparent: true, opacity: 0.35, clearcoat: 1 }));
    lens.add(new THREE.Mesh(keep(new THREE.TorusGeometry(0.95, 0.16, 24, 96)), gold));
    lens.add(new THREE.Mesh(keep(new THREE.CircleGeometry(0.9, 64)), glass));
    const handle = new THREE.Mesh(keep(new THREE.CylinderGeometry(0.15, 0.19, 1.25, 28)), keep(new THREE.MeshPhysicalMaterial({ color: primary, roughness: 0.3, clearcoat: 1 })));
    handle.position.set(0.98, -0.98, 0);
    handle.rotation.z = Math.PI / 4;
    lens.add(handle);
    const collar = new THREE.Mesh(keep(new THREE.CylinderGeometry(0.2, 0.2, 0.18, 28)), gold);
    collar.position.set(0.72, -0.72, 0);
    collar.rotation.z = Math.PI / 4;
    lens.add(collar);
    // Mercekteki parıltı
    const glint = new THREE.Mesh(keep(new THREE.TorusGeometry(0.62, 0.03, 8, 48, Math.PI * 0.45)), keep(new THREE.MeshBasicMaterial({ color: 0xffffff, transparent: true, opacity: 0.7 })));
    glint.rotation.z = Math.PI * 0.55;
    glint.position.z = 0.05;
    lens.add(glint);

    // Süzülen belge sayfaları
    const sheetGeo = keep(new THREE.PlaneGeometry(0.62, 0.88));
    const texA = keep(documentTexture(accent.getStyle(), true, primary.getStyle()));
    const texB = keep(documentTexture(accent.getStyle(), false));
    const sheets = [[-3.6, 1.6, -1.6], [3.7, 1.7, -1.2], [-3.2, -1.7, -0.6], [3.4, -1.6, -1.8], [-0.6, 2.2, -2.4], [1.2, -2.3, -2]].map(([x, y, z], i) => {
        const m = new THREE.Mesh(sheetGeo, keep(new THREE.MeshStandardMaterial({ map: i % 2 ? texB : texA, roughness: 0.6, side: THREE.DoubleSide, transparent: true, opacity: 0.92 })));
        m.position.set(x, y, z);
        m.userData = { x, y, phase: i * 1.3, spin: (i % 2 ? -1 : 1) * (0.2 + i * 0.05) };
        rig.add(m);
        return m;
    });

    const sparks = createSparks(rig, keep, { max: 240, size: 0.08 });
    const waves = createWaves(rig, keep, accent.clone().lerp(white, 0.25), { pool: 4 });
    const burst = new THREE.Color('#fff7e0');
    const facing = new THREE.Quaternion();

    let pulse = 1.2;
    let twirl = 1;
    root.addEventListener('click', () => {
        if (stage.reduced) {
            return;
        }
        twirl = 0;
        sparks.emit(lens.position, 46, accent, { speed: 2.4, up: 0.4, life: 1.1, spread: 0.7 });
        sparks.emit(lens.position, 22, burst, { speed: 1.5, up: 0.2, life: 0.8, spread: 0.4 });
        waves.spawn(lens.position.clone(), 2.6, facing, 1);
    });

    stage.onFrame((dt, t) => {
        rig.rotation.y = lerp(rig.rotation.y, pointer.x * 0.35, 0.06);
        rig.rotation.x = lerp(rig.rotation.x, -pointer.y * 0.2, 0.06);

        // Rakamlar hafifçe salınır (sırayla)
        left.position.y = Math.sin(t * 1.2) * 0.1;
        right.position.y = Math.sin(t * 1.2 + 1.6) * 0.1;
        left.rotation.y = Math.sin(t * 0.7) * 0.25;
        right.rotation.y = Math.sin(t * 0.7 + 1.2) * 0.25;

        // Büyüteç tarar: sekiz çizer, fareye doğru çekilir
        const sx = Math.sin(t * 0.8) * 0.5 + pointer.x * 0.45;
        const sy = Math.sin(t * 1.6) * 0.22 + pointer.y * 0.3 + 0.1;
        lens.position.x = lerp(lens.position.x, sx, 0.08);
        lens.position.y = lerp(lens.position.y, sy, 0.08);
        twirl = Math.min(1, twirl + dt / 1.1);
        lens.rotation.z = Math.sin(t * 0.8) * 0.18;
        lens.rotation.y = Math.sin(t * 0.5) * 0.35 + easeInOut(twirl) * Math.PI * 2;
        glint.material.opacity = 0.45 + Math.sin(t * 2.4) * 0.25;

        // Belirli aralıklarla tarama dalgası
        pulse -= dt;
        if (pulse <= 0 && !stage.reduced) {
            pulse = 2.6;
            waves.spawn(lens.position.clone(), 1.9, facing, 1.3);
            sparks.emit(lens.position, 10, accent, { speed: 0.9, up: 0.1, life: 0.9, spread: 0.9 });
        }

        sheets.forEach((s) => {
            const { x, y, phase, spin } = s.userData;
            s.position.x = x + Math.sin(t * 0.4 + phase) * 0.15;
            s.position.y = y + Math.sin(t * 0.7 + phase) * 0.22;
            s.rotation.z = Math.sin(t * 0.5 + phase) * 0.3;
            s.rotation.y = t * spin;
        });
        rim.position.x = lens.position.x;
        sparks.update(dt);
        waves.update(dt);
    });
    stage.start();
}
