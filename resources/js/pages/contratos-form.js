/**
 * Ventanas horarias dinámicas del formulario de contrato (HU-23, tarea 34):
 * agregar y quitar filas de `ventanas[]` sin recargar la página. Mismo patrón
 * que `resources/js/pages/clientes-form.js` (tarea 33) — JS vanilla, clona el
 * `<template>` que ya trae el partial `_ventana-fila.blade.php` con el
 * placeholder `__INDICE__` en cada `name`, y lo reemplaza por el próximo
 * índice libre. No hay reindexado al quitar una fila: PHP arma igual el
 * array de `ventanas` aunque los índices numéricos queden con huecos.
 *
 * Guard de presencia en el DOM (mismo criterio que `login.js`): en cualquier
 * página sin `[data-ag-contratos-form]` este módulo no hace nada.
 */
document.addEventListener('DOMContentLoaded', () => {
    const formulario = document.querySelector('[data-ag-contratos-form]');
    if (!formulario) return;

    const contenedor = formulario.querySelector('[data-ag-ventanas]');
    const lista = formulario.querySelector('[data-ag-ventanas-lista]');
    const plantilla = formulario.querySelector('[data-ag-ventana-template]');
    const botonAgregar = formulario.querySelector('[data-ag-ventanas-agregar]');

    if (!contenedor || !lista || !plantilla || !botonAgregar) return;

    let proximoIndice = lista.querySelectorAll('[data-ag-ventana-fila]').length;

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
        const botonQuitar = evento.target.closest('[data-ag-ventana-quitar]');
        if (!botonQuitar) return;

        botonQuitar.closest('[data-ag-ventana-fila]')?.remove();
    });
});
