/**
 * Manejo del formulario de orden de aplicación (HU-92, tarea 107): lotes
 * dinámicos — agregar y quitar filas de `lotes[]` sin recargar la página.
 * Mismo patrón vanilla que `resources/js/pages/campos-form.js` (clona el
 * `<template>` que ya trae el partial `_lote-orden-fila.blade.php` con el
 * placeholder `__INDICE__`, sin reindexar al quitar una fila).
 *
 * Guard de presencia en el DOM (mismo criterio que `login.js`): en cualquier
 * página sin `[data-ag-ordenes-form]` este módulo no hace nada.
 */
document.addEventListener('DOMContentLoaded', () => {
    const formulario = document.querySelector('[data-ag-ordenes-form]');
    if (!formulario) return;

    const contenedor = formulario.querySelector('[data-ag-orden-lotes]');
    const lista = formulario.querySelector('[data-ag-orden-lotes-lista]');
    const plantilla = formulario.querySelector('[data-ag-orden-lote-template]');
    const botonAgregar = formulario.querySelector('[data-ag-orden-lotes-agregar]');

    if (!contenedor || !lista || !plantilla || !botonAgregar) return;

    let proximoIndice = lista.querySelectorAll('[data-ag-orden-lote-fila]').length;

    botonAgregar.addEventListener('click', () => {
        const html = plantilla.innerHTML.replaceAll('__INDICE__', String(proximoIndice));
        proximoIndice += 1;

        const envoltorio = document.createElement('div');
        envoltorio.innerHTML = html.trim();

        const fila = envoltorio.firstElementChild;
        if (fila) lista.appendChild(fila);
    });

    contenedor.addEventListener('click', (evento) => {
        const botonQuitar = evento.target.closest('[data-ag-orden-lote-quitar]');
        if (!botonQuitar) return;

        botonQuitar.closest('[data-ag-orden-lote-fila]')?.remove();
    });
});
