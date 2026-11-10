/**
 * Alt bilgi sahnesi: dalgalanan ışık noktalarından bir zemin (kâğıt yüzeyi gibi). Dalga GPU'da hesaplanır
 * (gölgelendirici), fare üzerinde gezinince noktalarda halka şeklinde bir kabarma oluşur. Renk: tema renginden
 * vurgu rengine, yüksekliğe göre.
 */
import { THREE, createStage, cssColor, lerp } from './kit.js';

const VERTEX = `
uniform float uTime;
uniform vec2 uMouse;
uniform float uSize;
varying float vHeight;
varying float vFade;
void main() {
    vec3 p = position;
    float w = sin(p.x * 0.55 + uTime * 0.9) * 0.35 + cos(p.z * 0.7 + uTime * 0.7) * 0.28 + sin((p.x + p.z) * 0.3 + uTime * 0.5) * 0.22;
    float d = distance(p.xz, uMouse);
    w += exp(-d * d * 0.35) * 0.9 * (0.6 + 0.4 * sin(d * 3.0 - uTime * 4.0));
    p.y += w;
    vHeight = w;
    vec4 mv = modelViewMatrix * vec4(p, 1.0);
    vFade = smoothstep(26.0, 6.0, -mv.z);
    gl_PointSize = uSize * (8.0 / -mv.z);
    gl_Position = projectionMatrix * mv;
}`;

const FRAGMENT = `
uniform vec3 uLow;
uniform vec3 uHigh;
varying float vHeight;
varying float vFade;
void main() {
    vec2 c = gl_PointCoord - 0.5;
    float a = smoothstep(0.5, 0.1, length(c));
    vec3 col = mix(uLow, uHigh, smoothstep(-0.3, 0.9, vHeight));
    gl_FragColor = vec4(col, a * vFade * (0.35 + smoothstep(-0.2, 1.0, vHeight) * 0.65));
}`;

export function mount(root) {
    const stage = createStage(root, { fov: 45, z: 9, maxDpr: 1.5, pointerHost: root.closest('footer') || root });
    if (!stage) {
        return;
    }
    const { scene, camera, pointer, keep, renderer, small } = stage;
    renderer.toneMapping = THREE.NoToneMapping;
    camera.position.set(0, 3.2, 9);
    camera.lookAt(0, 0, -2);

    const cols = small ? 70 : 150;
    const rows = small ? 26 : 44;
    const pos = new Float32Array(cols * rows * 3);
    let k = 0;
    for (let i = 0; i < cols; i++) {
        for (let j = 0; j < rows; j++) {
            pos[k++] = (i / (cols - 1) - 0.5) * 34;
            pos[k++] = 0;
            pos[k++] = (j / (rows - 1)) * -20 + 4;
        }
    }
    const geo = keep(new THREE.BufferGeometry());
    geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
    const uniforms = {
        uTime: { value: 0 },
        uMouse: { value: new THREE.Vector2(99, 99) },
        uSize: { value: small ? 7 : 9 },
        uLow: { value: cssColor('--color-primary', '#0f766e').lerp(new THREE.Color('#ffffff'), 0.15) },
        uHigh: { value: cssColor('--color-accent', '#f59e0b') },
    };
    const mat = keep(new THREE.ShaderMaterial({ vertexShader: VERTEX, fragmentShader: FRAGMENT, uniforms, transparent: true, depthWrite: false, blending: THREE.AdditiveBlending }));
    const points = new THREE.Points(geo, mat);
    scene.add(points);

    // Fare → dalga düzlemindeki nokta
    const ray = new THREE.Raycaster();
    const plane = new THREE.Plane(new THREE.Vector3(0, 1, 0), 0);
    const hit = new THREE.Vector3();
    const mouse = new THREE.Vector2(99, 99);

    stage.onFrame((dt, t) => {
        uniforms.uTime.value = t;
        if (pointer.lengthSq() > 0.0001) {
            ray.setFromCamera(pointer, camera);
            if (ray.ray.intersectPlane(plane, hit)) {
                mouse.set(lerp(mouse.x === 99 ? hit.x : mouse.x, hit.x, 0.15), lerp(mouse.y === 99 ? hit.z : mouse.y, hit.z, 0.15));
            }
        }
        uniforms.uMouse.value.copy(mouse);
        points.rotation.y = Math.sin(t * 0.05) * 0.05;
    });
    stage.start();
}
