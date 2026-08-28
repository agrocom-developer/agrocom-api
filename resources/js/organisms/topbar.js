/**
 * organisms/topbar.js — Lógica de presentación para organisms/topbar.blade.php
 * Maneja el logout vía fetch POST contra /logout (endpoint real de backend).
 *
 * Patrón: delegación de eventos vanilla (sin framework), cero dependencias externas.
 * Inicialización guardada por DOMContentLoaded — no se ejecuta si no está presente.
 */

document.addEventListener('DOMContentLoaded', function () {
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
