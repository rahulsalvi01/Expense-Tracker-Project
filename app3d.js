

// Initialize Three.js Scene for 3D Background
const canvas = document.getElementById('bg-canvas');

// Scene, Camera, Renderer
const scene = new THREE.Scene();
// No fog for a clear look, or subtle fog matching theme
const isLight = document.body.classList.contains('light-mode');
scene.fog = new THREE.FogExp2(isLight ? 0xf8fafc : 0x0a0a16, 0.001);

const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
camera.position.z = 30;

const renderer = new THREE.WebGLRenderer({ canvas: canvas, alpha: true, antialias: true });
renderer.setSize(window.innerWidth, window.innerHeight);
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 1.5));

// Particles
const particlesGeometry = new THREE.BufferGeometry();
const particlesCount = 700;
const posArray = new Float32Array(particlesCount * 3);

for(let i = 0; i < particlesCount * 3; i++) {
    // Spread particles over a large area
    posArray[i] = (Math.random() - 0.5) * 100;
}

particlesGeometry.setAttribute('position', new THREE.BufferAttribute(posArray, 3));

// Custom particle material (slight purple/blue tint)
const particlesMaterial = new THREE.PointsMaterial({
    size: 0.15,
    color: 0x8b5cf6, // Purple accent matching CSS
    transparent: true,
    opacity: 0.8,
    blending: THREE.AdditiveBlending
});

// Create the mesh and add to scene
const particlesMesh = new THREE.Points(particlesGeometry, particlesMaterial);
scene.add(particlesMesh);

// Add some larger floating geometric shapes
const shapes = [];
const geometryTypes = [
    new THREE.IcosahedronGeometry(1, 0),
    new THREE.OctahedronGeometry(1, 0),
    new THREE.TetrahedronGeometry(1, 0)
];

const shapeMaterial = new THREE.MeshBasicMaterial({
    color: 0x3b82f6, // Blue accent
    wireframe: true,
    transparent: true,
    opacity: 0.15
});

for(let i = 0; i < 15; i++) {
    const geom = geometryTypes[Math.floor(Math.random() * geometryTypes.length)];
    const mesh = new THREE.Mesh(geom, shapeMaterial);
    
    mesh.position.x = (Math.random() - 0.5) * 60;
    mesh.position.y = (Math.random() - 0.5) * 60;
    mesh.position.z = (Math.random() - 0.5) * 40 - 10; // Keep behind UI
    
    mesh.rotation.x = Math.random() * Math.PI;
    mesh.rotation.y = Math.random() * Math.PI;
    
    scene.add(mesh);
    shapes.push({
        mesh: mesh,
        rotSpeedX: (Math.random() - 0.5) * 0.01,
        rotSpeedY: (Math.random() - 0.5) * 0.01,
        moveSpeedY: (Math.random() - 0.5) * 0.02
    });
}

// Mouse interaction
let mouseX = 0;
let mouseY = 0;
let targetX = 0;
let targetY = 0;

const windowHalfX = window.innerWidth / 2;
const windowHalfY = window.innerHeight / 2;

document.addEventListener('mousemove', (event) => {
    mouseX = (event.clientX - windowHalfX);
    mouseY = (event.clientY - windowHalfY);
});

// Animation Loop
const clock = new THREE.Clock();

function animate() {
    requestAnimationFrame(animate);
    
    const elapsedTime = clock.getElapsedTime();

    // Rotate particle system slowly
    particlesMesh.rotation.y = elapsedTime * 0.05;
    particlesMesh.rotation.x = elapsedTime * 0.02;

    // Ease target rotation towards mouse position (parallax)
    targetX = mouseX * 0.001;
    targetY = mouseY * 0.001;
    
    particlesMesh.rotation.y += 0.05 * (targetX - particlesMesh.rotation.y);
    particlesMesh.rotation.x += 0.05 * (targetY - particlesMesh.rotation.x);

    // Animate larger shapes
    shapes.forEach(shape => {
        shape.mesh.rotation.x += shape.rotSpeedX;
        shape.mesh.rotation.y += shape.rotSpeedY;
        shape.mesh.position.y += Math.sin(elapsedTime * 2 + shape.mesh.position.x) * 0.01;
    });

    renderer.render(scene, camera);
}

animate();

// Handle Resize
window.addEventListener('resize', () => {
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
});

// Mobile Sidebar Navigation Logic (Removed in favor of bottom nav)
