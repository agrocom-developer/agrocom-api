/**
 * Contactos dinámicos del formulario de cliente (HU-22, tarea 33): agregar y
 * quitar filas de `contactos[]` sin recargar la página. JS vanilla — clona
 * el `<template>` que ya trae el partial `_contacto-fila.blade.php` con el
 * placeholder `__INDICE__` en cada `name`, y lo reemplaza por el próximo
 * índice libre. No hay reindexado al quitar una fila: PHP arma igual el
 * array de `contactos` aunque los índices numéricos queden con huecos.
 *
 * Guard de presencia en el DOM (mismo criterio que `login.js`): en cualquier
 * página sin `[data-ag-clientes-form]` este módulo no hace nada.
 */
document.addEventListener('DOMContentLoaded', () => {
    const formulario = document.querySelector('[data-ag-clientes-form]');
    if (!formulario) return;

    const contenedor = formulario.querySelector('[data-ag-contactos]');
    const lista = formulario.querySelector('[data-ag-contactos-lista]');
    const plantilla = formulario.querySelector('[data-ag-contacto-template]');
    const botonAgregar = formulario.querySelector('[data-ag-contactos-agregar]');

    if (!contenedor || !lista || !plantilla || !botonAgregar) return;

    let proximoIndice = lista.querySelectorAll('[data-ag-contacto-fila]').length;

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
        const botonQuitar = evento.target.closest('[data-ag-contacto-quitar]');
        if (!botonQuitar) return;

        botonQuitar.closest('[data-ag-contacto-fila]')?.remove();
    });
});
