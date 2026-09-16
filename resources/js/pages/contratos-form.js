/**
 * Ventanas horarias dinámicas del formulario de contrato (HU-23, tarea 34):
 * agregar y quitar filas de `ventanas[]` sin recargar la página. Mismo patrón
 * que `resources/js/pages/clientes-form.js` (tarea 33) — JS vanilla, clona el
 * `<template>` que ya trae el partial `_ventana-fila.blade.php` con el
 * placeholder `__INDICE__` en cada `name`, y lo reemplaza por el próximo
 * índice libre. No hay reindexado al quitar una fila: PHP arma igual el
 * array de `ventanas` aunque los índices numéricos queden con huecos.
 *
 * El interruptor "Día completo" (HU-47, tarea 70) es puro DOM, sin campo
 * propio que viaje al servidor (ADR 0015 punto 5 — no hay
 * `ventana_todo_el_dia` en la base, cero filas ya significa "día completo"):
 * encenderlo oculta la sección Y VACÍA la lista (nada de `ventanas[]` se
 * manda); apagarlo la muestra y, si está vacía, agrega una fila para no
 * dejar al usuario con el botón "Agregar ventana" como único camino.
 *
 * Sin filtro de campaña por cliente (ADR 0015, corregido el 15/9/2026): la
 * campaña es un catálogo compartido, todas están disponibles para cualquier
 * cliente.
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
    const interruptorDiaCompleto = formulario.querySelector('[data-ag-dia-completo]');

    if (!contenedor || !lista || !plantilla || !botonAgregar) return;

    let proximoIndice = lista.querySelectorAll('[data-ag-ventana-fila]').length;

    const agregarFila = () => {
        const html = plantilla.innerHTML.replaceAll('__INDICE__', String(proximoIndice));
        proximoIndice += 1;

        const envoltorio = document.createElement('div');
        envoltorio.innerHTML = html.trim();

        const fila = envoltorio.firstElementChild;
        if (fila) {
            lista.appendChild(fila);
        }
    };

    botonAgregar.addEventListener('click', agregarFila);

    contenedor.addEventListener('click', (evento) => {
        const botonQuitar = evento.target.closest('[data-ag-ventana-quitar]');
        if (!botonQuitar) return;

        botonQuitar.closest('[data-ag-ventana-fila]')?.remove();
    });

    if (interruptorDiaCompleto) {
        interruptorDiaCompleto.addEventListener('change', () => {
            if (interruptorDiaCompleto.checked) {
                lista.replaceChildren();
                contenedor.hidden = true;
            } else {
                contenedor.hidden = false;
                if (lista.querySelectorAll('[data-ag-ventana-fila]').length === 0) {
                    agregarFila();
                }
            }
        });
    }
});
