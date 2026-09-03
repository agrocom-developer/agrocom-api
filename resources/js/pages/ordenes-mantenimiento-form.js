/**
 * Dos comportamientos independientes del panel de órdenes de mantenimiento
 * (HU-37, tarea 53), cada uno guardado por la presencia de su propio marcador
 * en el DOM (mismo criterio que el resto de `resources/js/pages/`): en
 * cualquier página sin esos marcadores, el bloque correspondiente no hace
 * nada.
 *
 * 1. Alta (ordenes/create.blade.php): muestra/oculta el select de equipo que
 *    corresponde al `equipo_tipo` elegido (`dron`/`vehiculo`) — ambos
 *    comparten `name="equipo_id"`, así que solo el visible/habilitado viaja
 *    en el POST (un campo `disabled` no se envía). Es presentación, no
 *    validación — el servidor revalida en `CrearOrdenMantenimientoRequest`,
 *    un POST manual podría enviar cualquier combinación igual. Mismo patrón
 *    que `stock-movimiento-form.js` (tarea 52).
 *
 * 2. Cierre (ordenes/edit.blade.php): agregar/quitar líneas de
 *    `repuestos[]` sin recargar la página. Mismo patrón que
 *    `contratos-form.js` (tarea 34): clona el `<template>` que ya trae el
 *    partial `_repuesto-linea.blade.php` con el placeholder `__INDICE__` en
 *    cada `name`, y lo reemplaza por el próximo índice libre. Sin
 *    reindexado al quitar una fila: PHP arma igual el array de `repuestos`
 *    aunque los índices numéricos queden con huecos.
 */
document.addEventListener('DOMContentLoaded', () => {
    const selectEquipoTipo = document.querySelector('[data-ag-orden-equipo-tipo]');
    const camposEquipo = Array.from(document.querySelectorAll('[data-ag-orden-campo]'));

    if (selectEquipoTipo && camposEquipo.length > 0) {
        const aplicarVisibilidad = () => {
            const tipo = selectEquipoTipo.value;

            camposEquipo.forEach((campo) => {
                const visible = campo.dataset.agOrdenCampo === tipo;

                campo.hidden = !visible;
                campo.querySelectorAll('select, input').forEach((control) => {
                    control.disabled = !visible;
                });
            });
        };

        selectEquipoTipo.addEventListener('change', aplicarVisibilidad);
        aplicarVisibilidad();
    }

    const contenedor = document.querySelector('[data-ag-repuestos]');
    const lista = document.querySelector('[data-ag-repuestos-lista]');
    const plantilla = document.querySelector('[data-ag-repuestos-template]');
    const botonAgregar = document.querySelector('[data-ag-repuestos-agregar]');

    if (!contenedor || !lista || !plantilla || !botonAgregar) return;

    let proximoIndice = lista.querySelectorAll('[data-ag-repuesto-fila]').length;

    botonAgregar.addEventListener('click', () => {
        const html = plantilla.innerHTML.replaceAll('__INDICE__', String(proximoIndice));
        proximoIndice += 1;

        const envoltorio = document.createElement('div');
        envoltorio.innerHTML = html.trim();

        const fila = envoltorio.firstElementChild;
        if (fila) {
            lista.appendChild(fila);
        }
    });

    contenedor.addEventListener('click', (evento) => {
        const botonQuitar = evento.target.closest('[data-ag-repuesto-quitar]');
        if (!botonQuitar) return;

        botonQuitar.closest('[data-ag-repuesto-fila]')?.remove();
    });
});
