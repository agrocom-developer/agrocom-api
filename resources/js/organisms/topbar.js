/**
 * organisms/topbar.js — Lógica de presentación para organisms/topbar.blade.php
 * Maneja el logout vía fetch POST contra /logout (endpoint real de backend).
 *
 * Patrón: delegación de eventos vanilla (sin framework), cero dependencias externas.
 * Inicialización guardada por DOMContentLoaded — no se ejecuta si no está presente.
 */

// La pre-instanciación de dropdowns con `strategy: 'fixed'` (necesaria para
// que no se recorten contra el `overflow` de `.ag-topbar`/`.ag-panel`, ver
// el pedido directo del usuario del 28/8/2026) vive desde el 15/9/2026 en
// resources/js/app.js — dejó de ser exclusiva del header cuando
// organisms/filter-panel y organisms/row-actions sumaron sus propios
// dropdowns, así que un solo `DOMContentLoaded` cubre a todos en vez de que
// cada organism repita su propio `querySelectorAll('[data-bs-toggle="dropdown"]')`.
document.addEventListener('DOMContentLoaded', function () {
    // `querySelectorAll`, no `querySelector` (fix 11/9/2026): topbar y
    // mobile-topbar están los dos en el DOM a la vez, cada uno con su
    // propio botón de logout — con `querySelector` (uno solo) el de mobile
    // nunca se wireaba, quedaba muerto al tacto.
    document.querySelectorAll('[data-ag-logout]').forEach((logoutButton) => {
        // Defaults preservan el comportamiento histórico del panel interno; el
        // portal del cliente (HU-41, tarea 55) reusa este mismo botón/script con
        // `data-ag-logout-url="/portal/logout"` y `data-ag-logout-redirect="/portal/login"`
        // (guard `cliente`, sin selector de rol al volver a loguearse).
        const logoutUrl = logoutButton.dataset.agLogoutUrl || '/logout';
        const redirectUrl = logoutButton.dataset.agLogoutRedirect || '/login';

        logoutButton.addEventListener('click', async function (e) {
            e.preventDefault();

            try {
                const response = await fetch(logoutUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });

                if (response.ok) {
                    window.location.href = redirectUrl;
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
});

// Atajo ⌘K / Ctrl+K: enfoca el buscador del header (9/9/2026, cuando el
// buscador dejó de ser maqueta). El `<kbd>` del campo viene anunciando el
// atajo desde la quinta vuelta del diseño y hasta ahora no hacía nada.
//
// Solo ENFOCA — no busca: buscar lo hace el submit del form (Enter), así que
// esto sigue andando aunque el JS falle en cargar.
document.addEventListener('keydown', (event) => {
    if (event.key?.toLowerCase() !== 'k' || !(event.metaKey || event.ctrlKey)) {
        return;
    }

    const buscador = document.querySelector('[data-ag-buscador]');

    if (!buscador) {
        return; // pantalla sin header de panel (login, portal)
    }

    event.preventDefault();
    buscador.focus();
    buscador.select();
});
