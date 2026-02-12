/**
 * Link Page Studio - JavaScript
 * Particle system and animations
 */

// ─── Particles System ────────────────────────────────────────
const REPEL_RADIUS = 120;
const REPEL_STRENGTH = 60;

class ParticleSystem {
    constructor(container) {
        if (!container) return;

        this.container = container;
        this.particles = [];
        this.particleElements = [];
        this.mouse = { x: -9999, y: -9999 };
        this.rafId = null;

        this.init();
        this.bindEvents();
        this.animate();
    }

    init() {
        for (let i = 0; i < 35; i++) {
            const particle = {
                id: i,
                size: Math.random() * 3.5 + 1.5,
                baseX: Math.random() * 100,
                baseY: Math.random() * 100,
                duration: Math.random() * 15 + 10,
                delay: Math.random() * 10,
                opacity: Math.random() * 0.35 + 0.08,
                hue: 250 + Math.random() * 60,
            };

            this.particles.push(particle);

            const el = document.createElement('div');
            el.className = 'particle';
            el.style.width = `${particle.size}px`;
            el.style.height = `${particle.size}px`;
            el.style.left = `${particle.baseX}%`;
            el.style.top = `${particle.baseY}%`;
            el.style.opacity = particle.opacity;
            el.style.background = `hsl(${particle.hue} 40% 70%)`;
            el.style.animation = `float ${particle.duration}s ease-in-out ${particle.delay}s infinite`;

            this.container.appendChild(el);
            this.particleElements.push(el);
        }
    }

    bindEvents() {
        window.addEventListener('mousemove', (e) => {
            this.mouse.x = e.clientX;
            this.mouse.y = e.clientY;
        });

        // Handle touch events for mobile
        window.addEventListener('touchmove', (e) => {
            if (e.touches.length > 0) {
                this.mouse.x = e.touches[0].clientX;
                this.mouse.y = e.touches[0].clientY;
            }
        });

        // Reset mouse position when touch ends
        window.addEventListener('touchend', () => {
            this.mouse.x = -9999;
            this.mouse.y = -9999;
        });
    }

    animate() {
        this.particleElements.forEach((el, i) => {
            const p = this.particles[i];
            const rect = el.getBoundingClientRect();
            const cx = rect.left + rect.width / 2;
            const cy = rect.top + rect.height / 2;
            const dx = cx - this.mouse.x;
            const dy = cy - this.mouse.y;
            const dist = Math.sqrt(dx * dx + dy * dy);

            if (dist < REPEL_RADIUS) {
                const force = (1 - dist / REPEL_RADIUS) * REPEL_STRENGTH;
                const angle = Math.atan2(dy, dx);
                const tx = Math.cos(angle) * force;
                const ty = Math.sin(angle) * force;
                el.style.transform = `translate(${tx}px, ${ty}px) scale(${1 + (1 - dist / REPEL_RADIUS) * 0.8})`;
                el.style.opacity = `${Math.min(p.opacity * 3, 0.7)}`;
            } else {
                el.style.transform = 'translate(0, 0) scale(1)';
                el.style.opacity = `${p.opacity}`;
            }
        });

        this.rafId = requestAnimationFrame(() => this.animate());
    }

    destroy() {
        if (this.rafId) {
            cancelAnimationFrame(this.rafId);
        }
    }
}

// ─── Initialize ──────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const particlesContainer = document.getElementById('particles');
    if (particlesContainer) {
        new ParticleSystem(particlesContainer);
    }
});
