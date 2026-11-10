/**
 * Belge bandı sahnesi: soldaki deste tarayıcı ışığıyla taranır, kopyası havada dönerek sağdaki desteye uçar.
 * Kopyalar dönüşümlü siyah-beyaz ve renkli; sağ deste dolunca sönüp yeniden başlar.
 */
import { THREE, createStage, cssColor, documentTexture, easeInOut, lerp } from './kit.js';

const SHEET = { w: 1.08, h: 1.53 };
const GAP = 0.016;

export function mount(root) {
    const stage = createStage(root, { fov: 30, z: 7.0 });
    if (!stage) {
        return;
    }
    const { scene, camera, pointer, keep, small } = stage;
    const primary = cssColor('--color-primary', '#0f766e');
    const accent = cssColor('--color-accent', '#f59e0b');
    const accentHex = '#' + accent.getHexString();
    const primaryHex = '#' + primary.getHexString();

    scene.add(new THREE.HemisphereLight(0xffffff, 0x0f172a, 1.6));
    const key = new THREE.DirectionalLight(0xffffff, 2.2);
    key.position.set(2, 4, 5);
    scene.add(key);
    const glowLight = new THREE.PointLight(accent, 14, 6, 2);
    glowLight.position.set(-1.2, 0.6, 1.2);
    scene.add(glowLight);

    const rig = new THREE.Group();
    rig.position.set(small ? 0 : 0.15, small ? -0.15 : -0.1, 0);
    scene.add(rig);

    const geo = keep(new THREE.PlaneGeometry(SHEET.w, SHEET.h));
    const texBw = keep(documentTexture(accentHex, false));
    const texColor = keep(documentTexture(accentHex, true, primaryHex));
    const matBw = keep(new THREE.MeshStandardMaterial({ map: texBw, roughness: 0.75, side: THREE.DoubleSide }));
    const matColor = keep(new THREE.MeshStandardMaterial({ map: texColor, roughness: 0.75, side: THREE.DoubleSide }));
    const blank = keep(new THREE.MeshStandardMaterial({ color: 0xf8fafc, roughness: 0.85, side: THREE.DoubleSide }));

    // Desteler: kâğıtlar arkaya yatık, hafif dağınık
    const makeStack = (x, rotZ) => {
        const g = new THREE.Group();
        g.position.set(x, -0.25, 0);
        g.rotation.set(-0.72, x * 0.12, rotZ);
        rig.add(g);
        return g;
    };
    const stackA = makeStack(-1.3, 0.1);
    const stackB = makeStack(1.35, -0.14);
    stackA.updateMatrix();
    stackB.updateMatrix();

    for (let i = 0; i < 9; i++) {
        const m = new THREE.Mesh(geo, i === 8 ? matBw : blank);
        m.position.set((Math.sin(i * 2.1) * 0.025), (Math.cos(i * 1.7) * 0.02), i * GAP);
        m.rotation.z = Math.sin(i * 1.3) * 0.03;
        stackA.add(m);
    }
    const bSheets = [];

    // Tarayıcı ışığı: üst kâğıt boyunca gidip gelen parlak çizgi + yumuşak hale
    const beamMat = keep(new THREE.MeshBasicMaterial({ color: accent, transparent: true, opacity: 0.95, blending: THREE.AdditiveBlending, depthWrite: false }));
    const haloMat = keep(new THREE.MeshBasicMaterial({ color: accent, transparent: true, opacity: 0.22, blending: THREE.AdditiveBlending, depthWrite: false }));
    const beam = new THREE.Mesh(keep(new THREE.PlaneGeometry(SHEET.w * 1.08, 0.025)), beamMat);
    const halo = new THREE.Mesh(keep(new THREE.PlaneGeometry(SHEET.w * 1.2, 0.26)), haloMat);
    beam.position.z = halo.position.z = 9 * GAP + 0.01;
    stackA.add(beam, halo);

    // Arkada yavaşça dönen ince halkalar (derinlik)
    const ringMat = keep(new THREE.MeshBasicMaterial({ color: primary.clone().lerp(new THREE.Color('#ffffff'), 0.35), transparent: true, opacity: 0.28 }));
    const rings = [2.2, 2.9].map((r, i) => {
        const ring = new THREE.Mesh(keep(new THREE.TorusGeometry(r, 0.008, 8, 160)), ringMat);
        ring.position.set(0, 0, -2);
        ring.rotation.set(1.2 + i * 0.25, 0.3 * i, 0);
        rig.add(ring);
        return ring;
    });
    const dotMat = keep(new THREE.MeshStandardMaterial({ color: accent, emissive: accent, emissiveIntensity: 0.9 }));
    const orbiters = [0, 1, 2].map((i) => {
        const s = new THREE.Mesh(keep(new THREE.SphereGeometry(0.05 + i * 0.015, 20, 20)), dotMat);
        rig.add(s);
        return s;
    });

    // Uçan kopyalar
    const flights = [];
    let copyIndex = 0;
    let nextCopy = 0.8;
    const spinAxis = new THREE.Vector3(0, 1, 0);
    const qSpin = new THREE.Quaternion();
    const topA = () => new THREE.Vector3(0, 0, 9 * GAP + 0.02).applyMatrix4(stackA.matrix);
    const topB = () => new THREE.Vector3(0, 0, (bSheets.length + 1) * GAP).applyMatrix4(stackB.matrix);

    const spawn = () => {
        const colorful = copyIndex++ % 2 === 1;
        const m = new THREE.Mesh(geo, (colorful ? matColor : matBw).clone());
        m.material.transparent = true;
        keep(m.material);
        rig.add(m);
        const from = topA();
        const to = topB();
        const ctrl = from.clone().lerp(to, 0.5).add(new THREE.Vector3(0, 1.7, 0.9));
        flights.push({ m, t: 0, from, to, ctrl, qFrom: stackA.quaternion.clone(), qTo: stackB.quaternion.clone(), colorful });
        glowLight.intensity = 30;
    };

    const land = (f) => {
        rig.remove(f.m);
        const m = new THREE.Mesh(geo, f.colorful ? matColor : matBw);
        m.position.set(Math.sin(bSheets.length * 2.3) * 0.03, Math.cos(bSheets.length * 1.9) * 0.025, (bSheets.length + 1) * GAP);
        m.rotation.z = Math.sin(bSheets.length * 1.1) * 0.05;
        stackB.add(m);
        bSheets.push(m);
        if (bSheets.length > 7) {
            // Kayan deste: en alttaki kâğıt kalkar, kalanlar bir kat iner
            stackB.remove(bSheets.shift());
            bSheets.forEach((s, i) => (s.position.z = (i + 1) * GAP));
        }
    };

    stage.onFrame((dt, t) => {
        rig.rotation.y = lerp(rig.rotation.y, pointer.x * 0.35, 0.08);
        rig.rotation.x = lerp(rig.rotation.x, -pointer.y * 0.18, 0.08);
        rig.position.y = (small ? -0.15 : -0.1) + Math.sin(t * 0.9) * 0.05;

        // Tarama ışığı: üst kâğıt boyunca ileri geri
        const sweep = Math.sin(t * 1.6);
        beam.position.y = halo.position.y = sweep * SHEET.h * 0.46;
        beamMat.opacity = 0.75 + Math.abs(Math.cos(t * 1.6)) * 0.25;
        glowLight.intensity = lerp(glowLight.intensity, 12, 0.05);
        glowLight.position.y = 0.2 + sweep * 0.5;

        rings.forEach((r, i) => (r.rotation.z = t * (0.08 + i * 0.05)));
        orbiters.forEach((o, i) => {
            const a = t * (0.5 + i * 0.22) + i * 2.1;
            o.position.set(Math.cos(a) * (2.0 + i * 0.4), Math.sin(a * 1.3) * 0.7 + 0.3, Math.sin(a) * 1.1 - 1);
        });

        nextCopy -= dt;
        if (nextCopy <= 0 && flights.length < 2) {
            spawn();
            nextCopy = 1.9;
        }
        for (let k = flights.length - 1; k >= 0; k--) {
            const f = flights[k];
            f.t += dt / 1.6;
            const u = easeInOut(Math.min(1, f.t));
            const a = f.from.clone().lerp(f.ctrl, u);
            const b = f.ctrl.clone().lerp(f.to, u);
            f.m.position.copy(a.lerp(b, u));
            f.m.quaternion.copy(f.qFrom).slerp(f.qTo, u);
            qSpin.setFromAxisAngle(spinAxis, u * Math.PI * 2);
            f.m.quaternion.premultiply(qSpin);
            f.m.scale.setScalar(1 + Math.sin(u * Math.PI) * 0.12);
            f.m.material.opacity = Math.min(1, f.t * 6);
            if (f.t >= 1) {
                land(f);
                flights.splice(k, 1);
            }
        }
    });

    camera.lookAt(0, 0, 0);
    stage.start(() => {
        // Hareketi azalt: sağ deste birkaç kopyayla dolu dursun
        for (let i = 0; i < 4; i++) {
            land({ m: new THREE.Object3D(), colorful: i % 2 === 1 });
        }
    });
}
