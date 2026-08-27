/**
 * Organism: login-form (resources/views/components/organisms/login-form.blade.php)
 *
 * Comportamiento de tabs: ingreso / recuperar acceso.
 *
 * - Click en un tab activa ese tab y le da foco.
 * - Click en "¿Olvidaste?" / "Volver al ingreso" cambia de tab y mueve el foco
 *   al tab correspondiente.
 * - ArrowRight/ArrowLeft en los tabs navega entre ellos (roving tabindex,
 *   patrón estándar de ARIA tabs).
 * - Solo existe si hay `.ag-login-form__tabs` en la página (guard para no
 *   romper otras páginas que carguen `app.js`).
 *
 * IMPORTANTE: No hay `<form>` en el panel "recuperar", así que el selector
 * `document.querySelector('[data-ag-login-form] form')` de
 * resources/js/pages/login.js sigue resolviendo a UN ÚNICO form (el panel
 * de ingreso). Esto es deliberado para que ese JS no tenga que tocar nada.
 */

document.addEventListener('DOMContentLoaded', () => {
    const tabsContainer = document.querySelector('.ag-login-form__tabs');
    if (!tabsContainer) return;

    const tabs = Array.from(tabsContainer.querySelectorAll('[role="tab"]'));
    const paneles = Array.from(document.querySelectorAll('[data-ag-login-panel]'));
    const switchButtons = Array.from(document.querySelectorAll('[data-ag-login-switch-tab]'));

    /**
     * Activa un tab dado su nombre (nombre del atributo data-ag-login-panel).
     */
    function activarTab(nombre) {
        tabs.forEach((tab) => {
            const esEste = tab.getAttribute('aria-controls') === `panel-${nombre}`;

            if (esEste) {
                tab.setAttribute('aria-selected', 'true');
                tab.setAttribute('tabindex', '0');
            } else {
                tab.setAttribute('aria-selected', 'false');
                tab.setAttribute('tabindex', '-1');
            }
        });

        paneles.forEach((panel) => {
            const esEste = panel.getAttribute('data-ag-login-panel') === nombre;

            if (esEste) {
                panel.removeAttribute('hidden');
            } else {
                panel.setAttribute('hidden', '');
            }
        });
    }

    /**
     * Click en un tab: activa ese tab y le da foco.
     */
    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const panelId = tab.getAttribute('aria-controls');
            const nombre = panelId.replace('panel-', '');
            activarTab(nombre);
            tab.focus();
        });
    });

    /**
     * Click en "¿Olvidaste?" / "Volver al ingreso": cambia de tab y mueve el
     * foco al tab correspondiente para mantener la coherencia navegacional
     * (el foco sigue siendo en el árbol de tabs, no en un botón que ya no es
     * relevante).
     */
    switchButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const nombre = btn.getAttribute('data-ag-login-switch-tab');
            activarTab(nombre);

            // Buscar el tab correspondiente y darle foco
            const tabCorrespondiente = tabs.find(
                (tab) => tab.getAttribute('aria-controls') === `panel-${nombre}`
            );
            if (tabCorrespondiente) {
                tabCorrespondiente.focus();
            }
        });
    });

    /**
     * Navegación con ArrowRight/ArrowLeft en los tabs (roving tabindex).
     */
    tabs.forEach((tab, index) => {
        tab.addEventListener('keydown', (e) => {
            let sigTab = null;

            if (e.key === 'ArrowRight') {
                e.preventDefault();
                // Ir al siguiente tab (wrap-around al final)
                sigTab = index < tabs.length - 1 ? tabs[index + 1] : tabs[0];
            } else if (e.key === 'ArrowLeft') {
                e.preventDefault();
                // Ir al anterior (wrap-around al inicio)
                sigTab = index > 0 ? tabs[index - 1] : tabs[tabs.length - 1];
            }

            if (sigTab) {
                const panelId = sigTab.getAttribute('aria-controls');
                const nombre = panelId.replace('panel-', '');
                activarTab(nombre);
                sigTab.focus();
            }
        });
    });
});
