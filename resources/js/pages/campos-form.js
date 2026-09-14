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
 * 3. Generador de alta masiva (HU-72, tarea 88, solo en `create`, no existe
 *    en `edit`): "Generar lotes" REEMPLAZA la lista completa por N filas
 *    clonadas del mismo `<template>` de arriba (reusa `clonarFilaLote()`),
 *    con código provisorio "Lote 1".."Lote N" y la hectárea tipeada. Se
 *    reemplaza en vez de agregar porque el formulario siempre arranca con
 *    una fila vacía por defecto (el camino manual de un lote a la vez) — sin
 *    reemplazo, esa fila vacía quedaría pisando la validación del server.
 *    `cultivo_id`/`campania_id` son selects normales del formulario (no
 *    tocan `lotes[]`); el filtro cliente → campaña reusa el mismo patrón
 *    que cliente → propiedad, vía `filtrarPorCliente()`.
 *
 * Guard de presencia en el DOM (mismo criterio que `login.js`): en cualquier
 * página sin `[data-ag-campos-form]` este módulo no hace nada.
 *
 * El perímetro de cada lote lo dibuja `organisms/lote-mapa-editor.js` sobre un
 * mapa satelital; acá solo se emite `agrocom:lote-agregado` cuando se clona una
 * fila, para que ese módulo instancie el mapa de la fila nueva.
 */
/**
 * Filtra las `<option>` de `selectDependiente` según el valor de
 * `selectCliente`, usando `mapa` (valor de la opción => cliente_id). Mismo
 * patrón para cliente→propiedad y cliente→campaña: se limpia el valor
 * elegido si deja de ser visible con el nuevo cliente.
 *
 * @param {HTMLSelectElement} selectCliente
 * @param {HTMLSelectElement} selectDependiente
 * @param {Record<string, string|number>} mapa
 */
function filtrarPorCliente(selectCliente, selectDependiente, mapa) {
    const opciones = Array.from(selectDependiente.querySelectorAll('option')).filter((opcion) => opcion.value !== '');

    const aplicarFiltro = () => {
        const clienteId = selectCliente.value;
        let valorSigueVisible = false;

        opciones.forEach((opcion) => {
            const visible = clienteId === '' || String(mapa[opcion.value]) === clienteId;
            opcion.hidden = !visible;
            opcion.disabled = !visible;
            if (visible && opcion.value === selectDependiente.value) {
                valorSigueVisible = true;
            }
        });

        if (!valorSigueVisible) {
            selectDependiente.value = '';
        }
    };

    selectCliente.addEventListener('change', aplicarFiltro);
    aplicarFiltro();
}

document.addEventListener('DOMContentLoaded', () => {
    const formulario = document.querySelector('[data-ag-campos-form]');
    if (!formulario) return;

    // Cascade cliente → propiedad
    const selectCliente = formulario.querySelector('[data-ag-campo-cliente]');
    const selectPropiedad = formulario.querySelector('[data-ag-campo-propiedad]');

    if (selectCliente && selectPropiedad) {
        const mapaClientePropiedad = JSON.parse(selectPropiedad.dataset.mapaClientePropiedad || '{}');
        filtrarPorCliente(selectCliente, selectPropiedad, mapaClientePropiedad);
    }

    // Cascade cliente → campaña (generador de alta masiva, solo en create)
    const selectCampania = formulario.querySelector('[data-ag-generador-campania]');

    if (selectCliente && selectCampania) {
        const mapaClienteCampania = JSON.parse(selectCampania.dataset.mapaClienteCampania || '{}');
        filtrarPorCliente(selectCliente, selectCampania, mapaClienteCampania);
    }

    const contenedor = formulario.querySelector('[data-ag-lotes]');
    const lista = formulario.querySelector('[data-ag-lotes-lista]');
    const plantilla = formulario.querySelector('[data-ag-lote-template]');
    const botonAgregar = formulario.querySelector('[data-ag-lotes-agregar]');

    if (!contenedor || !lista || !plantilla || !botonAgregar) return;

    let proximoIndice = lista.querySelectorAll('[data-ag-lote-fila]').length;

    /**
     * Clona la plantilla de fila, la agrega a la lista y avisa al editor de
     * mapa. Compartido por "Agregar lote" y el generador de alta masiva.
     *
     * @returns {Element|null}
     */
    function clonarFilaLote() {
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

        return fila;
    }

    botonAgregar.addEventListener('click', () => {
        clonarFilaLote();
    });

    contenedor.addEventListener('click', (evento) => {
        const botonQuitar = evento.target.closest('[data-ag-lote-quitar]');
        if (!botonQuitar) return;

        botonQuitar.closest('[data-ag-lote-fila]')?.remove();
    });

    // Generador de alta masiva (HU-72, tarea 88): reemplaza la lista entera
    // por N filas nuevas — ver punto 3 del comentario de cabecera.
    const generador = formulario.querySelector('[data-ag-generador]');
    const inputCantidad = generador?.querySelector('[data-ag-generador-cantidad]');
    const inputHectareas = generador?.querySelector('[data-ag-generador-hectareas]');
    const botonGenerar = generador?.querySelector('[data-ag-generador-generar]');

    if (generador && inputCantidad && inputHectareas && botonGenerar) {
        botonGenerar.addEventListener('click', () => {
            const cantidad = Math.floor(Number(inputCantidad.value));
            const hectareas = inputHectareas.value.trim();

            if (!Number.isFinite(cantidad) || cantidad < 1 || hectareas === '') return;

            lista.innerHTML = '';
            proximoIndice = 0;

            for (let numero = 1; numero <= cantidad; numero += 1) {
                const fila = clonarFilaLote();
                if (!fila) continue;

                const inputCodigo = fila.querySelector('input[name$="[codigo]"]');
                const inputHectareasFila = fila.querySelector('input[name$="[hectareas]"]');

                if (inputCodigo) inputCodigo.value = `Lote ${numero}`;
                if (inputHectareasFila) inputHectareasFila.value = hectareas;
            }
        });
    }
});
