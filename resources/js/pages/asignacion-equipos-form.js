/**
 * Formulario de reparto en bloque de una orden vigente (HU-92, tarea 107):
 * repetible ANIDADO — N equipos (`data-ag-equipo-fila`), cada uno con su
 * propia lista repetible de lotes+hectáreas (`data-ag-lote-equipo-fila`).
 * Mismo patrón vanilla que `campos-form.js` (clonar `<template>`, reemplazar
 * el índice literal), aplicado dos veces: una vez para el nivel equipo, y
 * una vez POR CADA equipo-fila (existente o recién clonada) para su propio
 * nivel de lotes.
 *
 * Guard de presencia en el DOM (mismo criterio que `login.js`): en cualquier
 * página sin `[data-ag-asignacion-equipos-form]` este módulo no hace nada.
 */
document.addEventListener('DOMContentLoaded', () => {
    const formulario = document.querySelector('[data-ag-asignacion-equipos-form]');
    if (!formulario) return;

    const listaEquipos = formulario.querySelector('[data-ag-equipos-lista]');
    const plantillaEquipo = formulario.querySelector('[data-ag-equipo-template]');
    const botonAgregarEquipo = formulario.querySelector('[data-ag-equipos-agregar]');

    if (!listaEquipos || !plantillaEquipo || !botonAgregarEquipo) return;

    let proximoIndiceEquipo = listaEquipos.querySelectorAll('[data-ag-equipo-fila]').length;

    /** Instala el repetible de lotes de UN equipo-fila (nueva o ya renderizada por el servidor). */
    function instalarLotesDeEquipo(equipoFila) {
        const listaLotes = equipoFila.querySelector('[data-ag-equipo-lotes-lista]');
        const plantillaLote = equipoFila.querySelector('[data-ag-equipo-lote-template]');
        const botonAgregarLote = equipoFila.querySelector('[data-ag-equipo-lote-agregar]');

        if (!listaLotes || !plantillaLote || !botonAgregarLote) return;

        let proximoIndiceLote = listaLotes.querySelectorAll('[data-ag-lote-equipo-fila]').length;

        botonAgregarLote.addEventListener('click', () => {
            const html = plantillaLote.innerHTML.replaceAll('__INDICE_LOTE__', String(proximoIndiceLote));
            proximoIndiceLote += 1;

            const envoltorio = document.createElement('div');
            envoltorio.innerHTML = html.trim();

            const fila = envoltorio.firstElementChild;
            if (fila) listaLotes.appendChild(fila);
        });

        listaLotes.addEventListener('click', (evento) => {
            const botonQuitar = evento.target.closest('[data-ag-lote-equipo-quitar]');
            if (!botonQuitar) return;

            botonQuitar.closest('[data-ag-lote-equipo-fila]')?.remove();
        });
    }

    // Equipos ya renderizados por el servidor (el primero, por defecto; o
    // los que trae `old()` tras un error de validación).
    listaEquipos.querySelectorAll('[data-ag-equipo-fila]').forEach(instalarLotesDeEquipo);

    botonAgregarEquipo.addEventListener('click', () => {
        const html = plantillaEquipo.innerHTML.replaceAll('__INDICE_EQUIPO__', String(proximoIndiceEquipo));
        proximoIndiceEquipo += 1;

        const envoltorio = document.createElement('div');
        envoltorio.innerHTML = html.trim();

        const fila = envoltorio.firstElementChild;
        if (fila) {
            listaEquipos.appendChild(fila);
            instalarLotesDeEquipo(fila);
        }
    });

    listaEquipos.addEventListener('click', (evento) => {
        const botonQuitar = evento.target.closest('[data-ag-equipo-quitar]');
        if (!botonQuitar) return;

        botonQuitar.closest('[data-ag-equipo-fila]')?.remove();
    });
});
