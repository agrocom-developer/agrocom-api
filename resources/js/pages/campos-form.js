/**
 * Manejo del formulario de campo (HU-24, tarea 35; actualizado ADR 0018):
 *
 * 1. Filtra el `<select>` de propiedad según el cliente elegido (ADR 0018:
 *    el campo ahora cuelga de una propiedad, no directo de un cliente) —
 *    mismo patrón que cliente→propiedad en `lotes-form.js`. Es presentación,
 *    no validación: el servidor (`CrearCampoRequest`) valida `propiedad_id`
 *    contra `com_propiedades` sin importar qué cliente esté seleccionado.
 *
 * 2. Lotes dinámicos: agregar y quitar filas de `lotes[]` sin recargar la
 *    página. Mismo patrón que `resources/js/pages/clientes-form.js` (tarea 33)
 *    — JS vanilla, clona el `<template>` que ya trae el partial
 *    `_lote-fila.blade.php` con el placeholder `__INDICE__` en cada `name`,
 *    y lo reemplaza por el próximo índice libre. No hay reindexado al quitar
 *    una fila: PHP arma igual el array de `lotes` aunque los índices
 *    numéricos queden con huecos.
 *
 * Guard de presencia en el DOM (mismo criterio que `login.js`): en cualquier
 * página sin `[data-ag-campos-form]` este módulo no hace nada.
 *
 * El perímetro de cada lote lo dibuja `organisms/lote-mapa-editor.js` sobre un
 * mapa satelital; acá solo se emite `agrocom:lote-agregado` cuando se clona una
 * fila, para que ese módulo instancie el mapa de la fila nueva.
 */
document.addEventListener('DOMContentLoaded', () => {
    const formulario = document.querySelector('[data-ag-campos-form]');
    if (!formulario) return;

    // Cascade cliente → propiedad
    const selectCliente = formulario.querySelector('[data-ag-campo-cliente]');
    const selectPropiedad = formulario.querySelector('[data-ag-campo-propiedad]');

    if (selectCliente && selectPropiedad) {
        const mapaClientePropiedad = JSON.parse(selectPropiedad.dataset.mapaClientePropiedad || '{}');
        const opciones = Array.from(selectPropiedad.querySelectorAll('option')).filter((opcion) => opcion.value !== '');

        const aplicarFiltro = () => {
            const clienteId = selectCliente.value;
            let valorSigueVisible = false;

            opciones.forEach((opcion) => {
                const visible = clienteId === '' || String(mapaClientePropiedad[opcion.value]) === clienteId;
                opcion.hidden = !visible;
                opcion.disabled = !visible;
                if (visible && opcion.value === selectPropiedad.value) {
                    valorSigueVisible = true;
                }
            });

            if (!valorSigueVisible) {
                selectPropiedad.value = '';
            }
        };

        selectCliente.addEventListener('change', aplicarFiltro);
        aplicarFiltro();
    }

    const contenedor = formulario.querySelector('[data-ag-lotes]');
    const lista = formulario.querySelector('[data-ag-lotes-lista]');
    const plantilla = formulario.querySelector('[data-ag-lote-template]');
    const botonAgregar = formulario.querySelector('[data-ag-lotes-agregar]');

    if (!contenedor || !lista || !plantilla || !botonAgregar) return;

    let proximoIndice = lista.querySelectorAll('[data-ag-lote-fila]').length;

    botonAgregar.addEventListener('click', () => {
        const html = plantilla.innerHTML.replaceAll('__INDICE__', String(proximoIndice));
        proximoIndice += 1;

        const envoltorio = document.createElement('div');
        envoltorio.innerHTML = html.trim();

        const fila = envoltorio.firstElementChild;
        if (fila) {
            lista.appendChild(fila);

            // El editor de mapa del lote (organisms/lote-mapa-editor.js) se
            // carga por import() dinámico y no observa el DOM: se le avisa de
            // la fila nueva para que instancie su Leaflet. Se emite siempre,
            // aunque el chunk todavía no haya cargado — en ese caso el editor
            // recorre el DOM al inicializarse y la encuentra igual.
            document.dispatchEvent(new CustomEvent('agrocom:lote-agregado', { detail: { fila } }));
        }
    });

    contenedor.addEventListener('click', (evento) => {
        const botonQuitar = evento.target.closest('[data-ag-lote-quitar]');
        if (!botonQuitar) return;

        botonQuitar.closest('[data-ag-lote-fila]')?.remove();
    });
});
