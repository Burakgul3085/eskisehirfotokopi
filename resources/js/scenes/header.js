/**
 * Header 3D sahnesi: header boyunca dalgalanıp kendi ekseninde kıvrılan iki ipek ışık şeridi (GPU'da, gölgelendirici)
 * ve aralarında sağdan sola süzülen küçük belge yaprakları. Fare şeridi yakın noktasından hafifçe kaldırır.
 * Yalnız geniş ekranda çalışır; kare hızı sınırlıdır, sayfa boştayken yüklenir.
 */
import { THREE, createStage, cssColor, documentTexture, lerp } from './kit.js';

const FOV = 30;
const CAM_Z = 6;

const VERTEX = /* glsl */ `
    uniform float uTime;
    uniform float uPhase;
    uniform float uAmp;
    uniform vec2 uMouse;
    varying vec2 vUv;
    varying float vFacing;
    void main() {
        vUv = uv;
        vec3 p = position;
        float x = p.x; // -0.5 .. 0.5 (genişlik ölçekle verilir)
        float wave = sin(x * 7.0 + uTime * 0.55 + uPhase) * 0.6 + sin(x * 15.0 - uTime * 0.8 + uPhase * 2.0) * 0.18;
        float twist = sin(x * 4.5 + uTime * 0.35 + uPhase) * 1.35;
        float c = cos(twist);
        float s = sin(twist);
        vec3 q = vec3(p.x, p.y * c, p.y * s);
        float near = exp(-pow((x - uMouse.x * 0.5) * 6.0, 2.0));
        q.y += wave * uAmp + near * (0.25 + uMouse.y * 0.2);
        vFacing = c;
        gl_Position = projectionMatrix * modelViewMatrix * vec4(q, 1.0);
    }
`;

const FRAGMENT = /* glsl */ `
    uniform vec3 uA;
    uniform vec3 uB;
    uniform float uOpacity;
    uniform float uTime;
    varying vec2 vUv;
    varying float vFacing;
    void main() {
        vec3 col = mix(uA, uB, smoothstep(0.05, 0.95, vUv.x + sin(uTime * 0.2) * 0.1));
        float across = smoothstep(0.0, 0.3, vUv.y) * smoothstep(1.0, 0.7, vUv.y);
        float ends = smoothstep(0.0, 0.1, vUv.x) * smoothstep(1.0, 0.9, vUv.x);
        float threads = 0.7 + 0.3 * sin(vUv.y * 70.0);
        float sheen = 0.5 + 0.5 * abs(vFacing);
        gl_FragColor = vec4(col * (0.8 + 0.35 * sheen), uOpacity * across * ends * threads * sheen);
        #include <colorspace_fragment>
    }
`;

export function mount(root) {
    if (!window.matchMedia('(min-width: 1024px)').matches) {
        return;
    }
    const build = () => setup(root);
    'requestIdleCallback' in window ? requestIdleCallback(build, { timeout: 3000 }) : setTimeout(build, 1200);
}

function setup(root) {
    let view = { w: 20, h: 3 };
    let ribbons = []; // ilk boyutlandırma createStage içinde çağrılır
    const stage = createStage(root, {
        fov: FOV, z: CAM_Z, maxDpr: 1.5, fps: 30,
        onResize: (w, h) => {
            const hh = Math.tan(THREE.MathUtils.degToRad(FOV / 2)) * CAM_Z;
            view = { w: hh * 2 * (w / h), h: hh * 2 };
            ribbons.forEach((r) => (r.scale.x = view.w * 1.15));
        },
    });
    if (!stage) {
        return;
    }
    const { scene, pointer, keep } = stage;
    const primary = cssColor('--color-primary', '#0f766e');
    const accent = cssColor('--color-accent', '#f59e0b');

    // ---- İpek şeritler ----
    const geo = keep(new THREE.PlaneGeometry(1, 1, 260, 16));
    ribbons = [
        { phase: 0, amp: 0.55, y: 0.25, h: 1.25, opacity: 0.6, a: primary, b: accent },
        { phase: 2.4, amp: 0.45, y: -0.35, h: 0.9, opacity: 0.42, a: accent, b: primary },
    ].map((o, i) => {
        const mat = keep(new THREE.ShaderMaterial({
            vertexShader: VERTEX,
            fragmentShader: FRAGMENT,
            transparent: true,
            depthWrite: false,
            side: THREE.DoubleSide,
            uniforms: {
                uTime: { value: 0 }, uPhase: { value: o.phase }, uAmp: { value: o.amp },
                uMouse: { value: new THREE.Vector2() }, uOpacity: { value: o.opacity },
                uA: { value: o.a.clone() }, uB: { value: o.b.clone() },
            },
        }));
        const m = new THREE.Mesh(geo, mat);
        m.scale.set(view.w * 1.15, o.h, 1);
        m.position.set(0, o.y, -0.5 - i * 0.6);
        scene.add(m);
        return m;
    });
    // ---- Süzülen belge yaprakları ----
    const accentHex = '#' + accent.getHexString();
    const primaryHex = '#' + primary.getHexString();
    const sheetGeo = keep(new THREE.PlaneGeometry(0.3, 0.42));
    const mats = [false, true].map((colorful) => keep(new THREE.MeshBasicMaterial({
        map: keep(documentTexture(accentHex, colorful, primaryHex)), transparent: true, opacity: 0.92, side: THREE.DoubleSide,
    })));
    const sheets = Array.from({ length: 7 }, (_, i) => {
        const m = new THREE.Mesh(sheetGeo, mats[i % 3 === 1 ? 1 : 0]);
        m.userData = {
            u: i / 7 + Math.random() * 0.05,          // yatay konum (0..1, kayar)
            speed: 0.008 + Math.random() * 0.006,
            y: lerp(-0.9, 0.9, Math.random()),
            z: lerp(-1.2, 0.6, Math.random()),
            spin: lerp(0.3, 0.7, Math.random()) * (Math.random() < 0.5 ? -1 : 1),
            ph: Math.random() * 6.28,
        };
        scene.add(m);
        return m;
    });

    const mouse = new THREE.Vector2();
    stage.onFrame((dt, t) => {
        mouse.lerp(pointer, 0.1);
        ribbons.forEach((r) => {
            r.material.uniforms.uTime.value = t;
            r.material.uniforms.uMouse.value.copy(mouse);
        });
        for (const s of sheets) {
            const d = s.userData;
            d.u -= d.speed * dt * 2;
            if (d.u < -0.08) {
                d.u = 1.08;
            }
            s.position.set((d.u - 0.5) * view.w, d.y + Math.sin(t * 0.9 + d.ph) * 0.18, d.z);
            s.rotation.set(Math.sin(t * 0.5 + d.ph) * 0.6, t * d.spin + d.ph, Math.sin(t * 0.7 + d.ph) * 0.3);
        }
    });
    stage.start();
}
