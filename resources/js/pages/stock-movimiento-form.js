/**
 * Muestra/oculta los campos que no aplican a todos los tipos de movimiento
 * de stock (HU-36, tarea 52): `base_destino_id` (solo traslado), `sentido`
 * (solo ajuste), `costo_unitario` (solo compra) y `motivo` (ajuste y
 * traslado). Es presentación, no validación — el servidor revalida las
 * obligatoriedades condicionales en `RegistrarMovimientoRequest`, un POST
 * manual podría enviar cualquier combinación igual. Mismo criterio de
 * "guard de presencia en el DOM" que `gastos-form.js`.
 */
document.addEventListener('DOMContentLoaded', () => {
    const formulario = document.querySelector('[data-ag-movimiento-form]');
    if (!formulario) return;

    const selectTipo = formulario.querySelector('[data-ag-movimiento-tipo]');
    const camposCondicionales = Array.from(formulario.querySelectorAll('[data-ag-movimiento-campo]'));
    if (!selectTipo || camposCondicionales.length === 0) return;

    const aplicarVisibilidad = () => {
        const tipo = selectTipo.value;

        camposCondicionales.forEach((campo) => {
            const tiposQueLoMuestran = campo.dataset.agMovimientoCampo.split(' ');
            const visible = tiposQueLoMuestran.includes(tipo);

            campo.hidden = !visible;
            campo.querySelectorAll('input, select').forEach((control) => {
                control.disabled = !visible;
            });
        });
    };

    selectTipo.addEventListener('change', aplicarVisibilidad);
    aplicarVisibilidad();
});
