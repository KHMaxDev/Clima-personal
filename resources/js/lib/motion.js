import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

let registered = false;

/**
 * Registra los plugins de GSAP una sola vez (el registro repetido lanza avisos).
 */
export function registerGsap() {
    if (registered) {
        return { gsap, ScrollTrigger };
    }

    gsap.registerPlugin(ScrollTrigger);
    registered = true;

    return { gsap, ScrollTrigger };
}

/**
 * Respeta la preferencia del sistema de reducir movimiento.
 */
export function prefersReducedMotion() {
    if (typeof window === 'undefined' || !window.matchMedia) {
        return false;
    }

    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * Marca los elementos [data-reveal] dentro de un contenedor como visibles.
 * Se usa como respaldo accesible cuando no se anima con GSAP.
 */
export function revealAll(container) {
    if (!container) {
        return;
    }

    container.querySelectorAll('[data-reveal]').forEach((element) => {
        element.classList.add('is-revealed');
    });
}

export { gsap, ScrollTrigger };
