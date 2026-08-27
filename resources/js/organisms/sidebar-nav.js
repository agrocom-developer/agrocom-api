// Colapso a icon-rail del sidebar en desktop
// (resources/views/components/organisms/sidebar-nav.blade.php). Es la única
// pieza de JS propia de este organism: el offcanvas de mobile y el dropdown
// del selector de rol son componentes nativos de Bootstrap (cargados
// globalmente en resources/js/app.js) — el icon-rail no existe en Bootstrap.
//
// Persiste la preferencia en localStorage: es una comodidad de navegador
// (no perder el colapso al navegar entre páginas), NO la preferencia de
// tema/idioma por usuario de `sec_user_preferencia` (esa la conecta Livewire
// más adelante). Mismo patrón de delegación de eventos que
// resources/js/atoms/input.js.

const CLAVE_ALMACENAMIENTO = 'agrocom:sidebar-collapsed';

function aplicarEstado(sidebar, boton, colapsado) {
    sidebar.classList.toggle('is-collapsed', colapsado);
    boton.setAttribute('aria-label', colapsado ? boton.dataset.labelExpand : boton.dataset.labelCollapse);
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-ag-sidebar-collapse-toggle]').forEach((boton) => {
        const sidebar = boton.closest('[data-ag-sidebar]');

        if (!sidebar) {
            return;
        }

        let colapsadoGuardado = false;

        try {
            colapsadoGuardado = window.localStorage.getItem(CLAVE_ALMACENAMIENTO) === '1';
        } catch {
            // localStorage puede no estar disponible (navegación privada
            // estricta, etc.) — se degrada a "siempre expandido", sin romper.
        }

        aplicarEstado(sidebar, boton, colapsadoGuardado);

        boton.addEventListener('click', () => {
            const colapsado = !sidebar.classList.contains('is-collapsed');
            aplicarEstado(sidebar, boton, colapsado);

            try {
                window.localStorage.setItem(CLAVE_ALMACENAMIENTO, colapsado ? '1' : '0');
            } catch {
                // Ver comentario de arriba.
            }
        });
    });
});
