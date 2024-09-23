import * as THREE from 'https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.module.js';

class StarGalaxyBackground {
    constructor() {
        this.scene = new THREE.Scene();
        this.camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
        this.renderer = new THREE.WebGLRenderer();
        this.stars = [];
        this.colorPalette = [0xFFFFFF, 0xFFD700, 0x00FFFF, 0xFF69B4, 0x32CD32, 0xFF4500];

        this.init();
    }

    init() {
        this.renderer.setSize(window.innerWidth, window.innerHeight);
        document.body.appendChild(this.renderer.domElement);

        this.camera.position.z = 5;

        this.createStars();

        window.addEventListener('resize', () => this.onWindowResize(), false);

        this.animate();
    }

    createStars() {
        const geometry = new THREE.BufferGeometry();
        const vertices = [];
        const colors = [];

        for (let i = 0; i < 10000; i++) {
            const x = (Math.random() - 0.5) * 2000;
            const y = (Math.random() - 0.5) * 2000;
            const z = (Math.random() - 0.5) * 2000;
            vertices.push(x, y, z);

            const color = new THREE.Color(this.colorPalette[Math.floor(Math.random() * this.colorPalette.length)]);
            colors.push(color.r, color.g, color.b);
        }

        geometry.setAttribute('position', new THREE.Float32BufferAttribute(vertices, 3));
        geometry.setAttribute('color', new THREE.Float32BufferAttribute(colors, 3));

        const material = new THREE.PointsMaterial({ size: 2, vertexColors: true, blending: THREE.AdditiveBlending });

        const stars = new THREE.Points(geometry, material);
        this.scene.add(stars);
        this.stars.push(stars);
    }

    animate() {
        requestAnimationFrame(() => this.animate());

        this.stars.forEach(star => {
            star.rotation.x += 0.0001;
            star.rotation.y += 0.0001;
        });

        this.renderer.render(this.scene, this.camera);
    }

    onWindowResize() {
        this.camera.aspect = window.innerWidth / window.innerHeight;
        this.camera.updateProjectionMatrix();
        this.renderer.setSize(window.innerWidth, window.innerHeight);
    }
}

const starGalaxyBackground = new StarGalaxyBackground();