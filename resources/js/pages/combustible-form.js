/**
 * Recarga la página de alta de combustible cuando cambia el equipo o la
 * fecha (tarea 73, HU-50): el `<select>` de recurso solo puede poblarse
 * server-side (`CombustibleController::create()`, vía
 * `LecturaEquipoTrabajo::recursosAFecha()`) — no hay catálogo estático que
 * filtrar en cliente, a diferencia del rubro/subrubro de
 * `gastos-form.js`. Mismo patrón de recarga con `fecha` en la query string
 * que ya usa la ficha de equipo del panel de Personal.
 *
 * Escucha `change` sobre los controles NATIVOS (`<select>`/`<input>`): tanto
 * si el JS de progressive enhancement de `atoms/select`/`atoms/date` cargó
 * como si no, ambos escriben `.value` en el control nativo y disparan
 * `change` ahí (ver el docblock de esos átomos).
 *
 * Guard de presencia en el DOM (mismo criterio que `gastos-form.js`): en
 * cualquier página sin `[data-ag-combustible-form]` este módulo no hace
 * nada.
 */
document.addEventListener('DOMContentLoaded', () => {
    const formulario = document.querySelector('[data-ag-combustible-form]');
    if (!formulario) return;

    const selectEquipo = formulario.querySelector('[data-ag-combustible-equipo]');
    const inputFecha = formulario.querySelector('[data-ag-combustible-fecha]');
    const urlCrear = formulario.dataset.agCombustibleCreateUrl;
    if (!selectEquipo || !inputFecha || !urlCrear) return;

    const recargar = () => {
        const parametros = new URLSearchParams();

        if (selectEquipo.value) parametros.set('equipo_trabajo_id', selectEquipo.value);
        if (inputFecha.value) parametros.set('fecha', inputFecha.value);

        window.location.href = `${urlCrear}?${parametros.toString()}`;
    };

    selectEquipo.addEventListener('change', recargar);
    inputFecha.addEventListener('change', recargar);
});
