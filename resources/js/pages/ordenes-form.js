/**
 * Formulario de Órdenes de Aplicación (reforma Entrega 1, 18/9/2026):
 * Ahora la orden cubre TODOS los lotes del contrato (no se elige cada uno).
 * El número de aplicación lo calcula el servidor (correlativo).
 *
 * 1. Selección de contrato: pinta resumen (cliente, propiedades, aplicaciones)
 *    y autoselecciona contacto si es único.
 * 2. Lista de lotes de solo lectura (código + propiedad + hectáreas).
 * 3. Tipo → categoría de insumo: filtro de presentación.
 * 4. Mostrar/ocultar campos de dosis según categoría elegida.
 */

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

    // ===== Selección de contrato + resumen + lista de lotes de solo lectura =====
    const selectContrato = formulario.querySelector('[data-ag-orden-contrato]');
    const scriptDatos = formulario.querySelector('[data-ag-datos-contrato]');
    const resumenContrato = formulario.querySelector('[data-ag-resumen-contrato]');
    const contactoWrap = formulario.querySelector('[data-ag-contacto-wrap]');
    const selectContacto = formulario.querySelector('[data-ag-orden-contacto]');

    if (!selectContrato || !scriptDatos) return;

    const datosContrato = JSON.parse(scriptDatos.textContent || '{}');

    // Número de aplicación y lista de lotes (solo lectura): la orden cubre TODOS
    // los lotes del contrato, así que al elegir contrato se repintan tal cual
    // vienen. Los textos con plural llegan del Blade por data-* (el JS no traduce).
    const nroAplicacion = formulario.querySelector('[data-ag-nro-aplicacion]');
    const seccionLotes = formulario.querySelector('[data-ag-lotes-seccion]');
    const tablaLotes = formulario.querySelector('[data-ag-lotes-tabla]');
    const vacioLotes = formulario.querySelector('[data-ag-lotes-vacio]');
    const formatoHectareas = new Intl.NumberFormat('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

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

            [
                [lote.codigo, ''],
                [lote.propiedad, ''],
                [formatoHectareas.format(Number(lote.hectareas)), 'ag-ordenes__mono'],
            ].forEach(([texto, clase]) => {
                const celda = document.createElement('span');
                celda.setAttribute('role', 'cell');
                if (clase) celda.className = clase;
                celda.textContent = texto;
                fila.appendChild(celda);
            });

            tablaLotes.appendChild(fila);
        });

        tablaLotes.toggleAttribute('hidden', lotes.length === 0);
        if (vacioLotes) vacioLotes.toggleAttribute('hidden', lotes.length > 0);

        const contador = seccionLotes?.querySelector('.ag-section-head__count');
        if (contador) {
            const clave = lotes.length === 0 ? 'textoLotesCero' : (lotes.length === 1 ? 'textoLotesUno' : 'textoLotesVarios');
            contador.textContent = (seccionLotes.dataset[clave] || '').replace(':cantidad', String(lotes.length));
        }
    };

    const pintarContratoYLotes = (contratoId, { resetearSeleccion }) => {
        const datos = datosContrato[contratoId];

        pintarNroAplicacion(datos);
        pintarLotes(datos?.lotes || []);

        if (!datos) {
            if (resumenContrato) resumenContrato.setAttribute('hidden', '');
            if (selectContacto) {
                Array.from(selectContacto.querySelectorAll('option'))
                    .filter((opcion) => opcion.value !== '')
                    .forEach((opcion) => {
                        opcion.hidden = true;
                        opcion.disabled = true;
                    });
                selectContacto.value = '';
                selectContacto.dispatchEvent(new Event('change', { bubbles: true }));
            }
            return;
        }

        // Pinta resumen
        if (resumenContrato) {
            resumenContrato.removeAttribute('hidden');
            const clienteEl = resumenContrato.querySelector('[data-ag-cliente-nombre]');
            if (clienteEl) clienteEl.textContent = datos.cliente;

            const propiedadesEl = resumenContrato.querySelector('[data-ag-propiedades-nombres]');
            if (propiedadesEl) propiedadesEl.textContent = datos.propiedades.join(', ') || '—';

            const aplicacionesEl = resumenContrato.querySelector('[data-ag-aplicaciones-previstas]');
            if (aplicacionesEl) {
                aplicacionesEl.textContent = String(datos.aplicaciones_previstas);
            }

            const hectareasEl = resumenContrato.querySelector('[data-ag-hectareas-contratadas]');
            if (hectareasEl) hectareasEl.textContent = `${datos.hectareas_contratadas} ha`;

            const fechaInicioEl = resumenContrato.querySelector('[data-ag-fecha-inicio]');
            if (fechaInicioEl) fechaInicioEl.textContent = datos.fecha_inicio;

            const fechaFinEl = resumenContrato.querySelector('[data-ag-fecha-fin]');
            if (fechaFinEl) fechaFinEl.textContent = datos.fecha_fin || fechaFinEl.dataset.textoSinDefinir || '—';

            const logoEl = resumenContrato.querySelector('[data-ag-cliente-logo]');
            if (logoEl) {
                logoEl.src = datos.logo_url || logoEl.dataset.logoPlaceholder;
            }
        }

        // Filtro de contactos (solo del cliente de este contrato)
        if (selectContacto) {
            const idsDelCliente = new Set((datos.contactos || []).map((c) => String(c.id)));
            let valorSigueVisible = false;

            Array.from(selectContacto.querySelectorAll('option'))
                .filter((opcion) => opcion.value !== '')
                .forEach((opcion) => {
                    const visible = idsDelCliente.has(opcion.value);
                    opcion.hidden = !visible;
                    opcion.disabled = !visible;
                    if (visible && opcion.value === selectContacto.value) {
                        valorSigueVisible = true;
                    }
                });

            if (resetearSeleccion && !valorSigueVisible) {
                selectContacto.value = '';
            }

            // Autoselecciona si queda uno solo
            const contactoTieneError = contactoWrap?.hasAttribute('data-tiene-error');
            if (!contactoTieneError && datos.contactos && datos.contactos.length === 1) {
                selectContacto.value = String(datos.contactos[0].id);
            }

            selectContacto.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };

    selectContrato.addEventListener('change', () => {
        pintarContratoYLotes(parseInt(selectContrato.value, 10), { resetearSeleccion: true });
    });

    // Carga inicial: si ya hay un contrato (edición, o redisplay tras error)
    if (selectContrato.value) {
        pintarContratoYLotes(parseInt(selectContrato.value, 10), { resetearSeleccion: false });
    }
});
