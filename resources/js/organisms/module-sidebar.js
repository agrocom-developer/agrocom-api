/**
 * organisms/module-sidebar.js — Collapse/expand del sidebar de nivel 2
 * (resources/views/components/organisms/module-sidebar.blade.php).
 *
 * Patrón: delegación de eventos vanilla (sin framework), mismo criterio que
 * molecules/theme-toggle.js. Estado persistido en localStorage — es UI
 * transitoria del cliente (no una preferencia de usuario server-side como
 * el tema), pero como esta app es multi-página (cada navegación es una
 * carga completa, no SPA), sin persistencia el sidebar se re-expandiría en
 * cada click de menú.
 */

const STORAGE_KEY = 'agrocom:sidebar-collapsed';

function leerEstadoGuardado() {
    try {
        return window.localStorage.getItem(STORAGE_KEY) === '1';
    } catch (error) {
        return false; // localStorage no disponible (modo privado, etc.) — arranca expandido
    }
}

function guardarEstado(colapsado) {
    try {
        window.localStorage.setItem(STORAGE_KEY, colapsado ? '1' : '0');
    } catch (error) {
        // Fire-and-forget: el toggle igual funciona en esta carga, solo no persiste.
    }
}

function aplicarEstado(sidebar, boton, colapsado) {
    sidebar.classList.toggle('is-collapsed', colapsado);
    boton.setAttribute('aria-expanded', String(!colapsado));
    boton.setAttribute('aria-label', colapsado ? boton.dataset.labelExpand : boton.dataset.labelCollapse);
}

document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('.ag-module-sidebar');
    const boton = document.querySelector('[data-ag-sidebar-toggle]');

    if (!sidebar || !boton) {
        return; // Página sin sidebar de nivel 2 (rol sin módulo activo, etc.)
    }

    aplicarEstado(sidebar, boton, leerEstadoGuardado());
});

document.addEventListener('click', (event) => {
    const boton = event.target.closest('[data-ag-sidebar-toggle]');

    if (!boton) {
        return;
    }

    const sidebar = document.querySelector('.ag-module-sidebar');

    if (!sidebar) {
        return;
    }

    const siguiente = !sidebar.classList.contains('is-collapsed');
    aplicarEstado(sidebar, boton, siguiente);
    guardarEstado(siguiente);
});
