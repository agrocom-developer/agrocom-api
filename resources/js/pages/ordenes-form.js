/**
 * Manejo del formulario de orden de aplicación:
 *
 * 1. Lotes dinámicos (HU-92, tarea 107): agregar y quitar filas de `lotes[]`
 *    sin recargar la página. Mismo patrón vanilla que
 *    `resources/js/pages/campos-form.js` (clona el `<template>` que ya trae
 *    el partial `_lote-orden-fila.blade.php` con el placeholder `__INDICE__`,
 *    sin reindexar al quitar una fila).
 *
 * 2. Tipo → categoría de insumo (HU-79, tarea 110): el usuario elige primero
 *    "Tipo" (Sólido/Líquido, campo de PRESENTACIÓN, sin `name` validado por el
 *    server — nunca se envía como tal) y ese valor filtra el `<select>` de
 *    "Categoría de insumo" (mismo patrón cliente→propiedad de
 *    `campos-form.js`, vía `data-mapa-categoria-insumo-tipo`, id→tipo). El
 *    tipo elegido también decide si se ve "Litros por hectárea" o "Kilos por
 *    vuelo"; al ocultar uno se limpia su valor — es presentación, no la única
 *    guarda: el servidor (`CrearOrdenRequest::validarCampoSegunCategoriaInsumo()`)
 *    exige el que corresponda según la categoría que de verdad llegó al POST,
 *    sin importar qué haya mostrado el JS.
 *
 * 3. Contrato → lotes (consistencia de negocio: el contrato es el QUIÉN, la
 *    orden es el CÓMO — no se arma una orden del contrato del cliente A con
 *    un lote del cliente B): a diferencia de tipo→categoría, acá el
 *    `<select>` dependiente se repite una vez por fila y las filas se crean
 *    dinámicamente, así que no alcanza `filtrarPorValorPadre()` (pensada para
 *    UN dependiente fijo) — `aplicarFiltroLotesPorContrato()` recorre TODAS
 *    las filas presentes cada vez que se llama, y se llama de nuevo al
 *    agregar una fila nueva. Mismo respaldo servidor que el resto: es
 *    presentación, `withValidator()` la exige igual.
 *
 * Guard de presencia en el DOM (mismo criterio que `login.js`): en cualquier
 * página sin `[data-ag-ordenes-form]` este módulo no hace nada.
 */
/**
 * Filtra las `<option>` de `selectDependiente` según el valor de
 * `selectPadre`, usando `mapa` (valor de la opción => valor del padre). Mismo
 * patrón que `filtrarPorCliente()` de `campos-form.js`, generalizado al
 * nombre del padre (acá es "tipo", no "cliente").
 *
 * @param {HTMLSelectElement} selectPadre
 * @param {HTMLSelectElement} selectDependiente
 * @param {Record<string, string>} mapa
 */
function filtrarPorValorPadre(selectPadre, selectDependiente, mapa) {
    const opciones = Array.from(selectDependiente.querySelectorAll('option')).filter((opcion) => opcion.value !== '');

    const aplicarFiltro = () => {
        const valorPadre = selectPadre.value;
        let valorSigueVisible = false;

        opciones.forEach((opcion) => {
            const visible = valorPadre === '' || mapa[opcion.value] === valorPadre;
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

    selectPadre.addEventListener('change', aplicarFiltro);
    aplicarFiltro();
}

document.addEventListener('DOMContentLoaded', () => {
    const formulario = document.querySelector('[data-ag-ordenes-form]');
    if (!formulario) return;

    const selectTipoInsumo = formulario.querySelector('[data-ag-orden-tipo-insumo]');
    const selectCategoriaInsumo = formulario.querySelector('[data-ag-orden-categoria-insumo]');

    if (selectTipoInsumo && selectCategoriaInsumo) {
        const mapaCategoriaTipo = JSON.parse(selectCategoriaInsumo.dataset.mapaCategoriaInsumoTipo || '{}');
        filtrarPorValorPadre(selectTipoInsumo, selectCategoriaInsumo, mapaCategoriaTipo);

        const campoLiquido = formulario.querySelector('[data-ag-orden-campo-tipo="liquido"]');
        const campoSolido = formulario.querySelector('[data-ag-orden-campo-tipo="solido"]');

        const mostrarSegunTipo = () => {
            const tipo = selectTipoInsumo.value;

            if (campoLiquido) {
                campoLiquido.hidden = tipo !== 'liquido';
                if (tipo !== 'liquido') campoLiquido.querySelector('input').value = '';
            }

            if (campoSolido) {
                campoSolido.hidden = tipo !== 'solido';
                if (tipo !== 'solido') campoSolido.querySelector('input').value = '';
            }
        };

        selectTipoInsumo.addEventListener('change', mostrarSegunTipo);
        mostrarSegunTipo();
    }

    const contenedor = formulario.querySelector('[data-ag-orden-lotes]');
    const lista = formulario.querySelector('[data-ag-orden-lotes-lista]');
    const plantilla = formulario.querySelector('[data-ag-orden-lote-template]');
    const botonAgregar = formulario.querySelector('[data-ag-orden-lotes-agregar]');

    if (!contenedor || !lista || !plantilla || !botonAgregar) return;

    const selectContrato = formulario.querySelector('[data-ag-orden-contrato]');
    let aplicarFiltroLotesPorContrato = () => {};

    if (selectContrato) {
        const mapaContratoCliente = JSON.parse(selectContrato.dataset.mapaContratoCliente || '{}');
        const mapaLoteCliente = JSON.parse(contenedor.dataset.mapaLoteCliente || '{}');

        aplicarFiltroLotesPorContrato = () => {
            const clienteId = mapaContratoCliente[selectContrato.value];

            contenedor.querySelectorAll('select[name$="[lote_id]"]').forEach((selectLote) => {
                const opciones = Array.from(selectLote.querySelectorAll('option')).filter((opcion) => opcion.value !== '');
                let valorSigueVisible = false;

                opciones.forEach((opcion) => {
                    const visible = clienteId === undefined || String(mapaLoteCliente[opcion.value]) === String(clienteId);
                    opcion.hidden = !visible;
                    opcion.disabled = !visible;
                    if (visible && opcion.value === selectLote.value) valorSigueVisible = true;
                });

                if (!valorSigueVisible) selectLote.value = '';
            });
        };

        selectContrato.addEventListener('change', aplicarFiltroLotesPorContrato);
        aplicarFiltroLotesPorContrato();
    }

    let proximoIndice = lista.querySelectorAll('[data-ag-orden-lote-fila]').length;

    botonAgregar.addEventListener('click', () => {
        const html = plantilla.innerHTML.replaceAll('__INDICE__', String(proximoIndice));
        proximoIndice += 1;

        const envoltorio = document.createElement('div');
        envoltorio.innerHTML = html.trim();

        const fila = envoltorio.firstElementChild;
        if (fila) {
            lista.appendChild(fila);
            aplicarFiltroLotesPorContrato();
        }
    });

    contenedor.addEventListener('click', (evento) => {
        const botonQuitar = evento.target.closest('[data-ag-orden-lote-quitar]');
        if (!botonQuitar) return;

        botonQuitar.closest('[data-ag-orden-lote-fila]')?.remove();
    });
});
