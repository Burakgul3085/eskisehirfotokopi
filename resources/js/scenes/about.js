/**
 * Hakkımızda sahnesi: marka işaretinin (iki sayfa ve "E") kalın, cilalı 3D hâli; çevresinde yavaşça süzülen iki
 * kitap ve iki belge sayfası, arkada sönük ışık noktaları. Fare sahneyi hafifçe çevirir; tıklayınca işaret bir
 * tur döner ve kıvılcım saçar. Abartısız: yavaş hareketler, az nesne.
 */
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';
import { THREE, createSparks, createStage, cssColor, documentTexture, dotTexture, easeInOut, lerp } from './kit.js';

// SVG işaretinin (viewBox 0..40, y aşağı) sahne birimine çevrilmesi: merkez (20,20), 10 birim = 1
const P = (x, y) => [(x - 20) / 10, -(y - 20) / 10];

/** Köşeleri yuvarlatılmış dikdörtgen; cut > 0 ise sağ üst köşe çapraz kesik (katlanmış sayfa) */
function pageShape(x, y, w, h, r, cut = 0) {
    const [x0, y0] = P(x, y);
    const [x1, y1] = P(x + w, y + h);
    const rr = r / 10;
    const c = cut / 10;
    const s = new THREE.Shape();
    s.moveTo(x0 + rr, y1);
    s.lineTo(x1 - rr, y1);
    s.quadraticCurveTo(x1, y1, x1, y1 + rr);
    if (c) {
        s.lineTo(x1, y0 - c);
        s.lineTo(x1 - c, y0);
    } else {
        s.lineTo(x1, y0 - rr);
        s.quadraticCurveTo(x1, y0, x1 - rr, y0);
    }
    s.lineTo(x0 + rr, y0);
    s.quadraticCurveTo(x0, y0, x0, y0 - rr);
    s.lineTo(x0, y1 + rr);
    s.quadraticCurveTo(x0, y1, x0 + rr, y1);
    return s;
}

export function mount(root) {
    const stage = createStage(root, { fov: 30, z: 9.2 });
    if (!stage) {
        return;
    }
    const { renderer, scene, pointer, keep, small } = stage;
    const primary = cssColor('--color-primary', '#0f766e');
    const accent = cssColor('--color-accent', '#f59e0b');
    const white = new THREE.Color('#ffffff');

    const pmrem = keep(new THREE.PMREMGenerator(renderer));
    scene.environment = keep(pmrem.fromScene(new RoomEnvironment(), 0.04).texture);
    scene.environmentIntensity = 0.45;
    renderer.toneMappingExposure = 0.85;
    scene.add(new THREE.AmbientLight(0xffffff, 0.2));
    const key = new THREE.DirectionalLight(0xffffff, 1.7);
    key.position.set(3, 4, 6);
    scene.add(key);
    const rim = new THREE.PointLight(accent, 18, 12, 2);
    rim.position.set(-3, 2, -2);
    scene.add(rim);

    const rig = new THREE.Group();
    scene.add(rig);

    // --- Marka işareti
    const mark = new THREE.Group();
    rig.add(mark);
    const extrude = (shape, depth, bevel = 0.06) => keep(new THREE.ExtrudeGeometry(shape, {
        depth, bevelEnabled: true, bevelThickness: bevel, bevelSize: bevel, bevelSegments: 4, curveSegments: 12,
    }));
    const backMat = keep(new THREE.MeshPhysicalMaterial({ color: accent, roughness: 0.28, metalness: 0.1, clearcoat: 1, clearcoatRoughness: 0.15 }));
    const frontMat = keep(new THREE.MeshPhysicalMaterial({ color: primary, roughness: 0.2, metalness: 0.08, clearcoat: 1, clearcoatRoughness: 0.08 }));
    const foldMat = keep(new THREE.MeshPhysicalMaterial({ color: primary.clone().lerp(white, 0.45), roughness: 0.3, clearcoat: 0.6 }));
    const inkMat = keep(new THREE.MeshPhysicalMaterial({ color: 0xffffff, roughness: 0.25, clearcoat: 1, emissive: 0xffffff, emissiveIntensity: 0.05 }));

    const back = new THREE.Mesh(extrude(pageShape(10, 3, 23.5, 30.5, 4), 0.32), backMat);
    back.position.z = -0.55;
    mark.add(back);
    const front = new THREE.Mesh(extrude(pageShape(4.5, 9, 24.5, 28, 4, 6.3), 0.34), frontMat);
    mark.add(front);
    // Katlanmış köşe: kesik köşeye oturan küçük üçgen
    const [fx0, fy0] = P(22.7, 9);
    const [fx1, fy1] = P(29, 15.3);
    const fold = new THREE.Shape();
    fold.moveTo(fx0, fy0);
    fold.lineTo(fx0, fy1);
    fold.lineTo(fx1, fy1);
    fold.closePath();
    const foldMesh = new THREE.Mesh(extrude(fold, 0.06, 0.03), foldMat);
    foldMesh.position.z = 0.42;
    mark.add(foldMesh);
    // "E" harfinin çubukları
    [[10, 19, 3, 13], [10, 19, 13, 3], [10, 24, 9.5, 3], [10, 29, 13, 3]].forEach(([x, y, w, h]) => {
        const bar = new THREE.Mesh(extrude(pageShape(x, y, w, h, 1.5), 0.08, 0.04), inkMat);
        bar.position.z = 0.42;
        mark.add(bar);
    });
    // Ağırlık merkezini ortala ve biraz küçült
    mark.position.set(0.2, -0.05, 0);
    mark.scale.setScalar(small ? 0.72 : 0.8);

    // --- Süzülen kitaplar ve belge sayfaları
    const floaters = [];
    const pagesMat = keep(new THREE.MeshStandardMaterial({ color: 0xf8fafc, roughness: 0.8 }));
    const book = (cover, x, y, z, rot) => {
        const g = new THREE.Group();
        const coverMat = keep(new THREE.MeshPhysicalMaterial({ color: cover, roughness: 0.35, clearcoat: 0.8 }));
        g.add(new THREE.Mesh(keep(new THREE.BoxGeometry(0.9, 1.2, 0.2)), coverMat));
        const pages = new THREE.Mesh(keep(new THREE.BoxGeometry(0.84, 1.12, 0.16)), pagesMat);
        pages.position.x = 0.05;
        g.add(pages);
        const spine = new THREE.Mesh(keep(new THREE.BoxGeometry(0.06, 1.2, 0.24)), coverMat);
        spine.position.x = -0.45;
        g.add(spine);
        g.position.set(x, y, z);
        g.rotation.set(...rot);
        g.userData = { y, rot: rot.slice(), phase: floaters.length * 1.7 };
        rig.add(g);
        floaters.push(g);
    };
    const sheet = (tex, x, y, z, rot) => {
        const mat = keep(new THREE.MeshStandardMaterial({ map: keep(tex), roughness: 0.7, side: THREE.DoubleSide }));
        const m = new THREE.Mesh(keep(new THREE.PlaneGeometry(0.85, 1.2)), mat);
        m.position.set(x, y, z);
        m.rotation.set(...rot);
        m.userData = { y, rot: rot.slice(), phase: floaters.length * 1.7 };
        rig.add(m);
        floaters.push(m);
    };
    const hex = (c) => `#${c.getHexString()}`;
    book(primary.clone().lerp(new THREE.Color('#000000'), 0.15), -2.35, 1.05, -0.6, [0.2, 0.55, -0.25]);
    book(accent, 2.45, -1.05, -0.3, [-0.15, -0.6, 0.3]);
    sheet(documentTexture(hex(accent), true, hex(primary)), 2.2, 1.35, -1.1, [0.1, -0.45, 0.18]);
    sheet(documentTexture(hex(accent)), -2.15, -1.25, -0.9, [-0.1, 0.5, -0.2]);

    // --- Arkada sönük ışık noktaları
    const dustCount = small ? 26 : 44;
    const dustPos = new Float32Array(dustCount * 3);
    for (let i = 0; i < dustCount; i++) {
        dustPos[i * 3] = (Math.random() - 0.5) * 7.5;
        dustPos[i * 3 + 1] = (Math.random() - 0.5) * 5;
        dustPos[i * 3 + 2] = -1.5 - Math.random() * 2.5;
    }
    const dustGeo = keep(new THREE.BufferGeometry());
    dustGeo.setAttribute('position', new THREE.BufferAttribute(dustPos, 3));
    const dust = new THREE.Points(dustGeo, keep(new THREE.PointsMaterial({
        size: 0.09, map: keep(dotTexture()), color: accent.clone().lerp(white, 0.5), transparent: true, opacity: 0.55, depthWrite: false, blending: THREE.AdditiveBlending,
    })));
    rig.add(dust);

    // --- Tıklama: tam tur ve kıvılcım
    const sparks = createSparks(rig, keep, { max: 160, size: 0.08 });
    let spin = 1;
    root.addEventListener('click', () => {
        if (stage.reduced || spin < 1) {
            return;
        }
        spin = 0;
        const c = new THREE.Vector3(0, 0, 0.6);
        sparks.emit(c, 46, accent, { speed: 2.4, up: 0.25, life: 1.1, spread: 0.7 });
        sparks.emit(c, 22, white, { speed: 1.5, up: 0.2, life: 0.8, spread: 0.5 });
    });

    stage.onFrame((dt, t) => {
        rig.rotation.y = lerp(rig.rotation.y, pointer.x * 0.35, 0.06);
        rig.rotation.x = lerp(rig.rotation.x, -pointer.y * 0.18, 0.06);
        spin = Math.min(1, spin + dt / 1.4);
        mark.rotation.y = Math.sin(t * 0.5) * 0.32 - 0.18 + easeInOut(spin) * Math.PI * 2;
        mark.rotation.x = Math.sin(t * 0.35) * 0.06;
        mark.position.y = -0.05 + Math.sin(t * 0.9) * 0.07;
        floaters.forEach((f) => {
            const { y, rot, phase } = f.userData;
            f.position.y = y + Math.sin(t * 0.7 + phase) * 0.12;
            f.rotation.x = rot[0] + Math.sin(t * 0.5 + phase) * 0.08;
            f.rotation.y = rot[1] + Math.sin(t * 0.4 + phase) * 0.12;
        });
        dust.rotation.y = t * 0.02;
        sparks.update(dt);
    });
    stage.start();
}
