/**
 * organisms/topbar.js — Lógica de presentación para organisms/topbar.blade.php
 * Maneja el logout vía fetch POST contra /logout (endpoint real de backend).
 *
 * Patrón: delegación de eventos vanilla (sin framework), cero dependencias externas.
 * Inicialización guardada por DOMContentLoaded — no se ejecuta si no está presente.
 */

// Pedido directo del usuario (28/8/2026): el popover de notificaciones/menú de
// usuario quedaba invisible al abrirse — recortado por `overflow: hidden` de
// `.ag-topbar` (evita que su propio contenido rompa el alto fijo de 62px) y de
// `.ag-panel` (contiene el layout de tres niveles), ambos ancestros del
// `.dropdown-menu`. Bootstrap posiciona el menú con Popper en `position:
// absolute` respecto de esos ancestros por defecto — cualquiera de los dos
// `overflow: hidden` lo recorta apenas se abre. La solución NO es quitar esos
// `overflow: hidden` (existen por un motivo real, contener el layout fijo) sino
// pre-instanciar el Dropdown con `popperConfig: { strategy: 'fixed' }`: pasa a
// posicionarse respecto del viewport, fuera de cualquier contexto de recorte.
// Se pre-instancia ANTES de que la data-api de Bootstrap cree su propia
// instancia por defecto al primer click — `Dropdown.getOrCreateInstance()`
// (que usa la data-api internamente) encuentra esta instancia ya configurada.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.ag-topbar [data-bs-toggle="dropdown"]').forEach((el) => {
        // window.bootstrap: ver comentario en app.js ("Bootstrap components
        // are now available globally via window") — mismo criterio que el
        // resto del proyecto para no re-importar el paquete por archivo.
        new window.bootstrap.Dropdown(el, { popperConfig: { strategy: 'fixed' } });
    });

    const logoutButton = document.querySelector('[data-ag-logout]');

    if (!logoutButton) {
        return; // Esta página no tiene el botón de logout
    }

    logoutButton.addEventListener('click', async function (e) {
        e.preventDefault();

        try {
            const response = await fetch('/logout', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content,
                },
            });

            if (response.ok) {
                // Logout exitoso — redirigir a /login
                window.location.href = '/login';
            } else {
                console.error('Logout failed:', response.status);
                // En caso de error, mantener en la página actual
                // (el usuario puede reintentar)
            }
        } catch (error) {
            console.error('Logout error:', error);
            // En caso de error de red, mantener en la página actual
        }
    });
});
