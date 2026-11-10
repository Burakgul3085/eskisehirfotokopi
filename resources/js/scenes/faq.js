/**
 * SSS sahnesi: cilalı 3D soru işareti (yazı tipi gerekmez: eğri boyunca tüp + nokta), çevresinde dönen yörünge ve
 * süzülen cam küreler. Fare sahneyi çevirir; soru işareti yavaşça salınır.
 */
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';
import { THREE, createStage, cssColor, lerp } from './kit.js';

export function mount(root) {
    const stage = createStage(root, { fov: 32, z: 7.2 });
    if (!stage) {
        return;
    }
    const { renderer, scene, pointer, keep } = stage;
    const primary = cssColor('--color-primary', '#0f766e');
    const accent = cssColor('--color-accent', '#f59e0b');

    const pmrem = keep(new THREE.PMREMGenerator(renderer));
    scene.environment = keep(pmrem.fromScene(new RoomEnvironment(), 0.04).texture);
    scene.add(new THREE.AmbientLight(0xffffff, 0.4));
    const key = new THREE.DirectionalLight(0xffffff, 2.4);
    key.position.set(3, 4, 5);
    scene.add(key);
    const rim = new THREE.PointLight(accent, 22, 10, 2);
    rim.position.set(-2.5, 1.5, -1.5);
    scene.add(rim);

    const rig = new THREE.Group();
    scene.add(rig);
    const mark = new THREE.Group();
    rig.add(mark);

    // Soru işaretinin kancası ve sapı
    const curve = new THREE.CatmullRomCurve3([
        new THREE.Vector3(-0.62, 0.62, 0),
        new THREE.Vector3(-0.5, 1.05, 0.05),
        new THREE.Vector3(0, 1.28, 0.08),
        new THREE.Vector3(0.52, 1.05, 0.05),
        new THREE.Vector3(0.6, 0.58, 0),
        new THREE.Vector3(0.32, 0.2, -0.04),
        new THREE.Vector3(0.03, -0.02, 0),
        new THREE.Vector3(0, -0.4, 0),
    ]);
    const R = 0.19;
    const gloss = keep(new THREE.MeshPhysicalMaterial({ color: primary, roughness: 0.16, metalness: 0.15, clearcoat: 1, clearcoatRoughness: 0.08 }));
    const goldMat = keep(new THREE.MeshPhysicalMaterial({ color: accent, roughness: 0.2, metalness: 0.6, clearcoat: 1, emissive: accent, emissiveIntensity: 0.12 }));
    mark.add(new THREE.Mesh(keep(new THREE.TubeGeometry(curve, 120, R, 28, false)), gloss));
    const cap = keep(new THREE.SphereGeometry(R, 28, 28));
    [curve.getPoint(0), curve.getPoint(1)].forEach((p) => {
        const s = new THREE.Mesh(cap, gloss);
        s.position.copy(p);
        mark.add(s);
    });
    const dot = new THREE.Mesh(keep(new THREE.SphereGeometry(0.24, 32, 32)), goldMat);
    dot.position.set(0, -0.92, 0);
    mark.add(dot);
    mark.position.y = -0.12;

    // Yörünge halkası ve üzerindeki küçük küreler
    const orbit = new THREE.Group();
    orbit.rotation.set(1.15, 0.2, 0.3);
    rig.add(orbit);
    const ringMat = keep(new THREE.MeshBasicMaterial({ color: primary.clone().lerp(new THREE.Color('#ffffff'), 0.4), transparent: true, opacity: 0.45 }));
    orbit.add(new THREE.Mesh(keep(new THREE.TorusGeometry(1.75, 0.008, 8, 200)), ringMat));
    const beads = [0, 1, 2].map((i) => {
        const b = new THREE.Mesh(keep(new THREE.SphereGeometry(0.07 - i * 0.012, 20, 20)), i === 0 ? goldMat : gloss);
        orbit.add(b);
        return b;
    });

    // Süzülen yarı saydam küreler
    const glass = keep(new THREE.MeshPhysicalMaterial({ color: 0xffffff, roughness: 0.05, metalness: 0, transparent: true, opacity: 0.22, clearcoat: 1 }));
    const bubbles = [[-1.6, 0.9, -0.8, 0.32], [1.55, -0.7, -0.4, 0.24], [1.3, 1.25, -1.4, 0.18], [-1.35, -1.05, 0.2, 0.14]].map(([x, y, z, r], i) => {
        const m = new THREE.Mesh(keep(new THREE.SphereGeometry(r, 32, 32)), glass);
        m.position.set(x, y, z);
        m.userData = { y, phase: i * 1.4 };
        rig.add(m);
        return m;
    });

    stage.onFrame((dt, t) => {
        rig.rotation.y = lerp(rig.rotation.y, pointer.x * 0.5, 0.08);
        rig.rotation.x = lerp(rig.rotation.x, -pointer.y * 0.25, 0.08);
        mark.rotation.y = Math.sin(t * 0.6) * 0.45;
        mark.rotation.z = Math.sin(t * 0.4) * 0.06;
        mark.position.y = -0.12 + Math.sin(t * 1.1) * 0.08;
        dot.position.y = -0.92 + Math.sin(t * 1.1 - 0.6) * 0.05;
        orbit.rotation.z = t * 0.25;
        beads.forEach((b, i) => {
            const a = t * (0.7 + i * 0.15) + i * 2.1;
            b.position.set(Math.cos(a) * 1.75, Math.sin(a) * 1.75, 0);
        });
        bubbles.forEach((b) => (b.position.y = b.userData.y + Math.sin(t * 0.8 + b.userData.phase) * 0.12));
    });
    stage.start();
}
