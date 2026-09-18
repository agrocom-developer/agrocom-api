// Comportamiento de molecules/view-toggle (resources/views/components/
// molecules/view-toggle.blade.php): alterna qué panel de resultados se ve
// (lista/grilla) sin ninguna recarga — ambos ya están en el DOM, esto solo
// mueve el atributo `hidden` y guarda la preferencia. Mismo criterio de
// delegación de eventos que atoms/input.js, sin dependencias.
//
// El apagado del parpadeo al CARGAR la página (si `localStorage` difiere
// del `current` que sirvió el servidor) no vive acá: ese script es inline,
// en la propia página, justo después de los paneles — un módulo cargado por
// <script type="module"> (como este) siempre corre después del primer
// pintado, igual que ya documenta atoms/tema-inicial.blade.php para el
// mismo problema con el tema claro/oscuro.

document.addEventListener('click', (event) => {
    const boton = event.target.closest('[data-ag-vista-btn]');

    if (!boton) {
        return;
    }

    const grupo = boton.closest('[data-ag-view-toggle]');

    if (!grupo) {
        return;
    }

    aplicarVista(grupo, boton.dataset.agVistaBtn);
});

/**
 * @param {HTMLElement} grupo
 * @param {string} vista
 */
function aplicarVista(grupo, vista) {
    grupo.querySelectorAll('[data-ag-vista-btn]').forEach((boton) => {
        const activo = boton.dataset.agVistaBtn === vista;
        boton.classList.toggle('is-active', activo);
        boton.setAttribute('aria-pressed', String(activo));
    });

    const contenedor = document.getElementById(grupo.dataset.resultados || '');

    if (contenedor) {
        contenedor.querySelectorAll('[data-ag-vista-panel]').forEach((panel) => {
            panel.hidden = panel.dataset.agVistaPanel !== vista;
        });
    }

    try {
        localStorage.setItem(grupo.dataset.storageKey, vista);
    } catch (e) {
        // Almacenamiento no disponible (modo privado, cuota): la vista
        // igual cambia para esta carga, solo no sobrevive a la próxima.
    }
}
