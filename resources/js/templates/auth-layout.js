// Comportamiento del template `auth-layout`
// (resources/views/components/templates/auth-layout.blade.php): galería de 3
// imágenes de fondo con auto-avance + navegación manual por indicadores
// (rediseño de login, cuarta vuelta). Presentación pura, sin dependencias —
// mismo patrón de delegación de eventos que theme-toggle.js/login-form.js.
//
// Guard: solo corre si existe `[data-ag-auth-gallery]` en la página (login,
// selección de rol) — no rompe ninguna otra pantalla que cargue app.js.
//
// Reduced motion: el auto-avance (un timer que mueve contenido solo) se
// desactiva bajo `prefers-reduced-motion: reduce` — a diferencia del estado
// codificado en `transform` del thumb de otros componentes (que nunca se
// anula), acá no hay estado que preservar, es un movimiento automático que el
// usuario pidió reducir. La navegación manual (click en los dots) sigue
// funcionando igual, con un crossfade más corto (ver el media query
// equivalente en resources/css/components/auth-layout.css).

const INTERVALO_MS = 6000;

function iniciarGaleria(galeria) {
    const slides = Array.from(galeria.querySelectorAll('[data-ag-auth-slide]'));
    const dots = Array.from(galeria.querySelectorAll('[data-ag-auth-dot]'));

    if (slides.length < 2) {
        return;
    }

    let activo = Math.max(
        0,
        slides.findIndex((slide) => slide.classList.contains('is-active')),
    );
    let temporizador = null;

    function activar(indice) {
        activo = indice;

        slides.forEach((slide, i) => {
            const esEste = i === indice;
            slide.classList.toggle('is-active', esEste);
            slide.setAttribute('aria-hidden', String(!esEste));
        });

        dots.forEach((dot, i) => {
            const esEste = i === indice;
            dot.classList.toggle('is-active', esEste);
            dot.setAttribute('aria-current', String(esEste));
        });
    }

    function avanzar() {
        activar((activo + 1) % slides.length);
    }

    function reiniciarAutoAvance() {
        if (temporizador === null) {
            return;
        }

        window.clearInterval(temporizador);
        temporizador = window.setInterval(avanzar, INTERVALO_MS);
    }

    const prefiereMenosMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!prefiereMenosMovimiento) {
        temporizador = window.setInterval(avanzar, INTERVALO_MS);
    }

    dots.forEach((dot, indice) => {
        dot.addEventListener('click', () => {
            if (indice === activo) {
                return;
            }

            activar(indice);
            reiniciarAutoAvance();
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-ag-auth-gallery]').forEach(iniciarGaleria);
});
