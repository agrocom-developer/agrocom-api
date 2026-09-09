/**
 * organisms/role-selection.js — pantalla de selección de rol (quinta
 * vuelta, maqueta 5c; pages/seleccionar-rol.blade.php).
 *
 * - Single-select entre tarjetas role-card (radiogroup): click o teclado.
 * - Navegable con teclado (consigna 5c): flechas mueven la selección
 *   (roving tabindex, patrón WAI-ARIA de radio group), Enter/Espacio
 *   confirman.
 * - El botón "Continuar como <rol>" actualiza su texto con el rol elegido
 *   (plantilla con placeholder __ROL__, ya traducida — este JS no inventa
 *   copy).
 * - Confirmar postea a POST /panel/rol-activo con `id_role` + `recordar`
 *   (checkbox "Entrar siempre con este rol") y navega a la URL que el
 *   servidor devuelve en `destino` (primer ítem visible del menú del rol
 *   recién elegido, tarea 62 — nunca un `/panel/dashboard` fijo: un rol sin
 *   permiso de dashboard aterrizaría en un 403). El `data-url-dashboard` del
 *   contenedor queda solo como respaldo si la respuesta no trajera `destino`.
 *
 * Patrón: vanilla + data-attributes, cero framework — mismo criterio que
 * organisms/topbar.js.
 */

document.addEventListener('DOMContentLoaded', () => {
    const raiz = document.querySelector('[data-ag-role-select]');
    if (!raiz) return;

    const grupo = raiz.querySelector('[data-ag-role-group]');
    const btnContinuar = raiz.querySelector('[data-ag-role-continuar]');
    const labelContinuar = raiz.querySelector('[data-ag-role-continuar-label]');
    const plantillaBoton = raiz.querySelector('[data-ag-role-boton-template]');
    const checkboxRecordar = raiz.querySelector('[data-ag-role-recordar]');
    const errorEl = raiz.querySelector('[data-ag-role-error]');

    // Sin roles asignados: no hay grupo ni botón — nada que wirear.
    if (!grupo || !btnContinuar) return;

    const tarjetas = Array.from(grupo.querySelectorAll('[data-ag-role-id]'));
    if (tarjetas.length === 0) return;

    function seleccionada() {
        return tarjetas.find((t) => t.getAttribute('aria-checked') === 'true') ?? tarjetas[0];
    }

    function seleccionar(tarjeta, enfocar = false) {
        tarjetas.forEach((t) => {
            const activa = t === tarjeta;
            t.setAttribute('aria-checked', String(activa));
            t.classList.toggle('is-selected', activa);
            t.tabIndex = activa ? 0 : -1;
        });

        if (labelContinuar && plantillaBoton) {
            labelContinuar.textContent = plantillaBoton.content.textContent
                .replace('__ROL__', tarjeta.getAttribute('data-ag-role-nombre') ?? '');
        }

        if (enfocar) tarjeta.focus();
    }

    tarjetas.forEach((tarjeta) => {
        tarjeta.addEventListener('click', () => seleccionar(tarjeta));

        tarjeta.addEventListener('keydown', (event) => {
            const indice = tarjetas.indexOf(tarjeta);

            if (event.key === 'ArrowDown' || event.key === 'ArrowRight') {
                event.preventDefault();
                seleccionar(tarjetas[(indice + 1) % tarjetas.length], true);
            } else if (event.key === 'ArrowUp' || event.key === 'ArrowLeft') {
                event.preventDefault();
                seleccionar(tarjetas[(indice - 1 + tarjetas.length) % tarjetas.length], true);
            } else if (event.key === 'Enter') {
                event.preventDefault();
                confirmar();
            }
            // Espacio: comportamiento nativo del botón (click) → seleccionar.
        });
    });

    // Estado inicial coherente (el servidor ya preseleccionó una tarjeta).
    seleccionar(seleccionada());

    async function confirmar() {
        const tarjeta = seleccionada();
        if (!tarjeta) return;

        btnContinuar.disabled = true;
        if (errorEl) errorEl.hidden = true;

        try {
            const respuesta = await fetch(raiz.getAttribute('data-accion'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content,
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    id_role: parseInt(tarjeta.getAttribute('data-ag-role-id'), 10),
                    recordar: Boolean(checkboxRecordar?.checked),
                }),
            });

            if (respuesta.ok) {
                const datos = await respuesta.json();
                window.location.href = datos.destino || raiz.getAttribute('data-url-dashboard');
                return;
            }

            btnContinuar.disabled = false;
            if (errorEl) {
                errorEl.textContent = errorEl.getAttribute('data-mensaje-error') ?? '';
                errorEl.hidden = false;
            }
        } catch {
            btnContinuar.disabled = false;
            if (errorEl) {
                errorEl.textContent = errorEl.getAttribute('data-mensaje-red') ?? '';
                errorEl.hidden = false;
            }
        }
    }

    btnContinuar.addEventListener('click', confirmar);
});
