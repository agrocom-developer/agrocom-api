/**
 * Contactos dinámicos del formulario de cliente (HU-22, tarea 33): agregar y
 * quitar filas de `contactos[]` sin recargar la página. JS vanilla — clona
 * el `<template>` que ya trae el partial `_contacto-fila.blade.php` con el
 * placeholder `__INDICE__` en cada `name`, y lo reemplaza por el próximo
 * índice libre. No hay reindexado al quitar una fila: PHP arma igual el
 * array de `contactos` aunque los índices numéricos queden con huecos.
 *
 * También sincroniza dos campos condicionales (tarea "resumen de cliente"),
 * ambos con su estado inicial calculado server-side (sin parpadeo) y
 * sincronizados por JS — mejora progresiva, sin JS quedan en el estado que
 * calculó el servidor:
 * - "Nombre comercial": solo si `tipo_persona = juridica`. Alterna la CLASE
 *   `ag-clientes-form__campo-reservado` (`visibility: hidden`), no la
 *   propiedad `hidden` — la celda del grid se reserva vacía en vez de
 *   liberarse, para que "Razón social" no la ocupe en su lugar (ver
 *   comentario en _formulario.blade.php).
 * - "Especificá el tipo" de un contacto: solo si esa fila tiene `tipo =
 *   otro`. Por delegación de eventos sobre `contenedor` (no un listener por
 *   fila): las filas se clonan dinámico, un listener fijado una sola vez al
 *   cargar la página nunca se enteraría de una fila nueva.
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

    if (contenedor && lista && plantilla && botonAgregar) {
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

        contenedor.addEventListener('change', (evento) => {
            if (!evento.target.matches('[data-ag-contacto-tipo]')) return;

            const fila = evento.target.closest('[data-ag-contacto-fila]');
            const campoTipoOtro = fila?.querySelector('[data-ag-contacto-tipo-otro]');
            if (campoTipoOtro) {
                campoTipoOtro.hidden = evento.target.value !== 'otro';
            }
        });
    }

    const selectTipoPersona = formulario.querySelector('#tipo_persona');
    const campoNombreComercial = formulario.querySelector('[data-ag-nombre-comercial]');

    if (selectTipoPersona && campoNombreComercial) {
        selectTipoPersona.addEventListener('change', () => {
            campoNombreComercial.classList.toggle('ag-clientes-form__campo-reservado', selectTipoPersona.value !== 'juridica');
        });
    }
});
