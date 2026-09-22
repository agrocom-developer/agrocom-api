/**
 * Formulario de Órdenes de Aplicación (reforma Entrega 1, 18/9/2026):
 * Ahora la orden cubre TODOS los lotes del contrato (no se elige cada uno).
 * El número de aplicación lo calcula el servidor (correlativo).
 *
 * 1. Selección de contrato (alta): repinta la tarjeta del contrato (cliente,
 *    propiedades, aplicaciones, hectáreas y fechas), los contactos y los lotes.
 *    En edición el contrato es fijo y el servidor ya pintó todo ese bloque; acá
 *    solo se pagina la lista de lotes.
 * 2. Contactos: el `<select>` solo tiene los del cliente del contrato elegido —
 *    se arma con los que trae `datosContrato`, nunca con los de otros clientes—
 *    y se autoselecciona si es único.
 * 3. Lista de lotes de solo lectura (código, propiedad, cultivo con su etapa,
 *    terreno y hectáreas), de 20 en 20 con el paginador de la tabla de lotes
 *    del contrato.
 * 4. Tipo → categoría de insumo: filtro de presentación.
 * 5. Mostrar/ocultar campos de dosis según categoría elegida.
 */

import { paginarFilas } from '../shared/paginador-cliente.js';

const LOTES_POR_PAGINA = 20;

/**
 * Filtra las `<option>` de `selectDependiente` según el valor de `selectPadre`,
 * usando `mapa` (valor de la opción => valor del padre).
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

    // ===== Filtro Tipo → Categoría de insumo =====
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
                if (tipo !== 'liquido') {
                    campoLiquido.setAttribute('hidden', '');
                    const input = campoLiquido.querySelector('input');
                    if (input) input.value = '';
                } else {
                    campoLiquido.removeAttribute('hidden');
                }
            }

            if (campoSolido) {
                if (tipo !== 'solido') {
                    campoSolido.setAttribute('hidden', '');
                    const input = campoSolido.querySelector('input');
                    if (input) input.value = '';
                } else {
                    campoSolido.removeAttribute('hidden');
                }
            }
        };

        selectCategoriaInsumo.addEventListener('change', mostrarSegunTipo);
        selectTipoInsumo.addEventListener('change', mostrarSegunTipo);
        mostrarSegunTipo();
    }

    // ===== Contrato + tarjeta del contrato + contactos + lista de lotes de solo lectura =====
    const scriptDatos = formulario.querySelector('[data-ag-datos-contrato]');
    if (!scriptDatos) return;

    const datosContrato = JSON.parse(scriptDatos.textContent || '{}');

    // En el alta el contrato es un `<select>`; en la edición, un campo oculto fijo.
    const selectContrato = formulario.querySelector('[data-ag-orden-contrato]');
    const contratoFijo = formulario.querySelector('[data-ag-orden-contrato-fijo]');
    const resumenContrato = formulario.querySelector('[data-ag-resumen-contrato]');
    const contactoWrap = formulario.querySelector('[data-ag-contacto-wrap]');
    const selectContacto = formulario.querySelector('[data-ag-orden-contacto]');

    // Número de aplicación y lista de lotes (solo lectura): la orden cubre TODOS
    // los lotes del contrato, así que al elegir contrato se repintan tal cual
    // vienen. Los textos con plural llegan del Blade por data-* (el JS no traduce).
    const nroAplicacion = formulario.querySelector('[data-ag-nro-aplicacion]');
    const seccionLotes = formulario.querySelector('[data-ag-lotes-seccion]');
    const tablaLotes = formulario.querySelector('[data-ag-lotes-tabla]');
    const vacioLotes = formulario.querySelector('[data-ag-lotes-vacio]');
    const contenedorPaginador = formulario.querySelector('[data-ag-lotes-paginador]');
    const formatoHectareas = new Intl.NumberFormat('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // Paginación de la tabla de lotes (20 por página): se ocultan las filas de otras
    // páginas con `hidden`, no se quitan.
    const filasDeLotes = () => Array.from(tablaLotes?.querySelectorAll('.ag-index-table__row') ?? []);
    const paginadorLotes = tablaLotes && contenedorPaginador
        ? paginarFilas(contenedorPaginador, { filas: filasDeLotes, porPagina: LOTES_POR_PAGINA })
        : null;

    const pintarNroAplicacion = (datos) => {
        if (!nroAplicacion) return;
        const nro = datos?.siguiente_nro;
        nroAplicacion.textContent = nro === undefined || nro === null
            ? '—'
            : (nroAplicacion.dataset.textoPlantilla || ':nro de :total')
                .replace(':nro', String(nro))
                .replace(':total', String(datos.aplicaciones_previstas));
    };

    const pintarLotes = (lotes) => {
        if (!tablaLotes) return;

        tablaLotes.querySelectorAll('.ag-index-table__row').forEach((fila) => fila.remove());

        lotes.forEach((lote) => {
            const fila = document.createElement('div');
            fila.className = 'ag-index-table__row';
            fila.setAttribute('role', 'row');

            // Mismas celdas que pinta el Blade: cada una es una lista de líneas
            // `[texto, esDetalle]`; con más de una, la celda las apila.
            const textos = tablaLotes.dataset;
            const terreno = [
                lote.desnivel_label ? [textos.textoDesnivel.replace(':valor', lote.desnivel_label), true] : null,
                lote.limpieza_label ? [textos.textoLimpieza.replace(':valor', lote.limpieza_label), true] : null,
            ].filter(Boolean);

            [
                [[[lote.codigo, false]], ''],
                [[[lote.propiedad, false]], ''],
                [lote.cultivo
                    ? [[lote.cultivo, false], [lote.etapa_label || textos.textoSinEtapa, true]]
                    : [[textos.textoSinCultivo, true]], 'ag-ordenes__celda-doble'],
                [terreno.length > 0 ? terreno : [[textos.textoSinTerreno, true]], 'ag-ordenes__celda-doble'],
                [[[formatoHectareas.format(Number(lote.hectareas)), false]], 'ag-ordenes__mono'],
            ].forEach(([lineas, clase]) => {
                const celda = document.createElement('span');
                celda.setAttribute('role', 'cell');
                if (clase) celda.className = clase;

                if (clase === 'ag-ordenes__celda-doble') {
                    lineas.forEach(([texto, esDetalle]) => {
                        const linea = document.createElement('span');
                        if (esDetalle) linea.className = 'ag-ordenes__celda-detalle';
                        linea.textContent = texto;
                        celda.appendChild(linea);
                    });
                } else {
                    celda.textContent = lineas[0][0];
                }

                fila.appendChild(celda);
            });

            tablaLotes.appendChild(fila);
        });

        tablaLotes.toggleAttribute('hidden', lotes.length === 0);
        if (vacioLotes) vacioLotes.toggleAttribute('hidden', lotes.length > 0);
        paginadorLotes?.actualizar(1);

        const contador = seccionLotes?.querySelector('.ag-section-head__count');
        if (contador) {
            const clave = lotes.length === 0 ? 'textoLotesCero' : (lotes.length === 1 ? 'textoLotesUno' : 'textoLotesVarios');
            contador.textContent = (seccionLotes.dataset[clave] || '').replace(':cantidad', String(lotes.length));
        }
    };

    // Contactos del cliente del contrato: se reemplazan las opciones del `<select>` por
    // las de ESTE contrato (`datos.contactos`, ya con su etiqueta traducida). Nunca hay
    // opciones de otros clientes, ni siquiera ocultas.
    const pintarContactos = (datos, { resetearSeleccion }) => {
        if (!selectContacto) return;

        const contactos = datos?.contactos || [];
        const valorPrevio = selectContacto.value;

        selectContacto.querySelectorAll('option').forEach((opcion) => {
            if (opcion.value !== '') opcion.remove();
        });

        contactos.forEach((contacto) => {
            const opcion = document.createElement('option');
            opcion.value = String(contacto.id);
            opcion.textContent = contacto.label;
            selectContacto.appendChild(opcion);
        });

        const sigueElegido = !resetearSeleccion && contactos.some((contacto) => String(contacto.id) === valorPrevio);
        selectContacto.value = sigueElegido ? valorPrevio : '';

        // Autoselecciona si el cliente tiene un solo contacto
        const contactoTieneError = contactoWrap?.hasAttribute('data-tiene-error');
        if (!sigueElegido && !contactoTieneError && contactos.length === 1) {
            selectContacto.value = String(contactos[0].id);
        }

        // Sin contrato elegido (alta) no hay de quién listar contactos.
        if (selectContrato) selectContacto.disabled = !datos;

        selectContacto.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const pintarResumen = (datos) => {
        if (!resumenContrato) return;

        if (!datos) {
            resumenContrato.setAttribute('hidden', '');
            return;
        }

        resumenContrato.removeAttribute('hidden');

        const poner = (selector, texto) => {
            const nodo = resumenContrato.querySelector(selector);
            if (nodo) nodo.textContent = texto;
        };

        poner('[data-ag-cliente-nombre]', datos.cliente);
        poner('[data-ag-propiedades-nombres]', datos.propiedades.join(', ') || '—');
        poner('[data-ag-aplicaciones-previstas]', String(datos.aplicaciones_previstas));
        poner('[data-ag-hectareas-contratadas]', `${datos.hectareas_contratadas} ha`);
        poner('[data-ag-fecha-inicio]', datos.fecha_inicio);
        poner('[data-ag-fecha-fin]', datos.fecha_fin || resumenContrato.querySelector('[data-ag-fecha-fin]')?.dataset.textoSinDefinir || '—');

        const logoEl = resumenContrato.querySelector('[data-ag-cliente-logo]');
        if (logoEl) logoEl.src = datos.logo_url || logoEl.dataset.logoPlaceholder;
    };

    const pintarContratoYLotes = (contratoId, { resetearSeleccion }) => {
        const datos = datosContrato[contratoId];

        pintarNroAplicacion(datos);
        pintarLotes(datos?.lotes || []);
        pintarResumen(datos);
        pintarContactos(datos, { resetearSeleccion });
    };

    if (selectContrato) {
        selectContrato.addEventListener('change', () => {
            pintarContratoYLotes(parseInt(selectContrato.value, 10), { resetearSeleccion: true });
        });

        // Carga inicial del alta: si ya hay un contrato (preseleccionado, o redisplay tras error)
        if (selectContrato.value) {
            pintarContratoYLotes(parseInt(selectContrato.value, 10), { resetearSeleccion: false });
        } else {
            paginadorLotes?.actualizar(1);
        }
    } else if (contratoFijo) {
        // Edición: el servidor ya pintó la tarjeta, los contactos del cliente y los lotes;
        // solo falta repartir los lotes en páginas.
        paginadorLotes?.actualizar(1);
    }
});
