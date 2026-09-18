/**
 * Manejo del formulario de orden de aplicación (reforma 18/9/2026):
 *
 * 1. Selección de contrato (nueva sección "Datos del contrato"): embebe JSON
 *    con datosContrato por contrato_id, pinta resumen (cliente, propiedades,
 *    aplicaciones) y autoselecciona contacto si es único.
 *
 * 2. Tabla de lotes (reemplaza el repetible anterior): desde
 *    datosContrato[contratoId].lotes, con checkbox, búsqueda y paginado
 *    (10 filas por página).
 *
 * 3. Autofill de nro_aplicacion sugerido (SOLO en create(), NULL en edit()).
 *
 * 4. Tipo → categoría de insumo: filtro pre-existente, se mantiene igual.
 *
 * 5. Mostrar/ocultar campos de dosis según categoría elegida.
 *
 * Guard de presencia en el DOM: si no hay `[data-ag-ordenes-form]`, no hace nada.
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

    // ===== Filtro Tipo → Categoría de insumo (pre-existente) =====
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

    // ===== Selección de contrato + resumen + tabla de lotes =====
    const selectContrato = formulario.querySelector('[data-ag-orden-contrato]');
    const scriptDatos = formulario.querySelector('[data-ag-datos-contrato]');
    const scriptLotesIniciales = formulario.querySelector('[data-ag-lotes-iniciales]');
    const resumenContrato = formulario.querySelector('[data-ag-resumen-contrato]');
    const contactoWrap = formulario.querySelector('[data-ag-contacto-wrap]');
    const selectContacto = formulario.querySelector('[data-ag-orden-contacto]');
    const tablaLotes = formulario.querySelector('[data-ag-tabla-lotes]');
    const contenedorLotes = formulario.querySelector('[data-ag-lotes-contenedor]');
    const buscadorLotes = formulario.querySelector('[data-ag-lotes-buscar]');
    const paginadoLotes = formulario.querySelector('[data-ag-lotes-paginado]');
    const vacioBuscador = formulario.querySelector('[data-ag-lotes-vacio]');
    const btnPaginaAnterior = formulario.querySelector('[data-ag-pagina-anterior]');
    const btnPaginaSiguiente = formulario.querySelector('[data-ag-pagina-siguiente]');
    const infoPagina = formulario.querySelector('[data-ag-pagina-info]');
    const selectNroAplicacion = formulario.querySelector('[data-ag-orden-nro-aplicacion]');

    if (!selectContrato || !scriptDatos) return;

    const datosContrato = JSON.parse(scriptDatos.textContent || '{}');
    const lotesIniciales = scriptLotesIniciales ? JSON.parse(scriptLotesIniciales.textContent || '[]') : [];

    let paginaActual = 1;
    const lotesPerPage = 10;
    let lotesFiltrados = [];

    // Única fuente de verdad de la selección: `lote_id` => hectáreas a
    // solicitar (string). Sobrevive a paginar/buscar (esas dos acciones
    // destruyen y vuelven a armar las filas de la tabla, pero SIEMPRE leen
    // el valor tildado/tipeado desde acá, nunca desde el DOM viejo) — así un
    // input ya editado no se pierde al cambiar de página. Sembrado una sola
    // vez desde `old('lotes')` (redisplay tras error) o desde los lotes ya
    // guardados de la orden en edición (`$lotesOrden`, ver `_formulario.blade.php`).
    const seleccion = new Map();
    lotesIniciales.forEach((lote) => {
        if (lote && lote.lote_id) {
            seleccion.set(parseInt(lote.lote_id, 10), String(lote.hectareas_solicitadas ?? ''));
        }
    });

    /**
     * Actualiza la vista: aplica buscador, paginado, renderiza tabla y form.
     */
    const actualizarVista = (lotes) => {
        const termino = (buscadorLotes?.value || '').toLowerCase();
        const palabrasClave = termino.split(/\s+/).filter(Boolean);

        // Filtra por búsqueda (código + propiedad, sin distinguir tildes/mayúsculas).
        lotesFiltrados = lotes.filter((lote) => {
            const codigo = (lote.codigo || '').toLowerCase();
            const propiedad = (lote.propiedad || '').toLowerCase();
            return palabrasClave.every((palabra) => codigo.includes(palabra) || propiedad.includes(palabra));
        });

        const totalPaginas = Math.max(1, Math.ceil(lotesFiltrados.length / lotesPerPage));
        paginaActual = Math.max(1, Math.min(paginaActual, totalPaginas));

        renderizarContenedor(lotes);
        renderizarPaginado();
    };

    /**
     * Renderiza el contenedor de lotes con la tabla y los inputs hidden.
     * Reconstruye el DOM de la página actual en cada llamada, pero SIEMPRE
     * lee/escribe el estado tildado desde `seleccion` (nunca desde el DOM
     * que está por destruir) — por eso paginar/buscar no pierde datos ya
     * cargados, aunque las filas en sí se recreen.
     */
    const renderizarContenedor = (lotes) => {
        if (!contenedorLotes) return;

        contenedorLotes.innerHTML = '';

        if (lotesFiltrados.length === 0) {
            if (vacioBuscador) vacioBuscador.hidden = false;
            if (paginadoLotes) paginadoLotes.hidden = true;
            renderizarInputsHidden(lotes);
            return;
        }

        if (vacioBuscador) vacioBuscador.hidden = true;
        if (paginadoLotes) paginadoLotes.hidden = false;

        // Tabla de lotes visibles en esta página.
        const inicio = (paginaActual - 1) * lotesPerPage;
        const fin = inicio + lotesPerPage;
        const lotesPagina = lotesFiltrados.slice(inicio, fin);

        const tabla = document.createElement('table');
        tabla.className = 'ag-ordenes-form__lotes-tabla';

        // Header.
        const head = tabla.createTHead();
        const headRow = head.insertRow();
        headRow.className = 'ag-ordenes-form__lotes-tabla-head';
        const textos = tablaLotes?.dataset || {};
        const headCells = [
            '',
            textos.textoColCodigo || '',
            textos.textoColPropiedad || '',
            textos.textoColHectareasLote || '',
            textos.textoColHectareasSolicitadas || '',
            textos.textoColDesnivel || '',
            textos.textoColLimpieza || '',
        ];
        headCells.forEach((text) => {
            const th = headRow.insertCell();
            th.textContent = text;
        });

        // Body: filas de lotes (paginadas).
        const tbody = tabla.createTBody();
        lotesPagina.forEach((lote) => {
            const row = tbody.insertRow();
            row.className = 'ag-ordenes-form__lotes-tabla-fila';

            const estaSeleccionado = seleccion.has(lote.lote_id);

            const cellCheck = row.insertCell();
            cellCheck.className = 'ag-ordenes-form__lotes-tabla-checkbox';
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.checked = estaSeleccionado;
            checkbox.className = 'ag-checkbox-group__input';
            cellCheck.appendChild(checkbox);

            // Código.
            const cellCodigo = row.insertCell();
            cellCodigo.textContent = lote.codigo;

            // Propiedad.
            const cellPropiedad = row.insertCell();
            cellPropiedad.textContent = lote.propiedad;

            // Hectáreas totales del lote (informativo, no editable).
            const cellHectareas = row.insertCell();
            cellHectareas.textContent = `${parseFloat(lote.hectareas).toFixed(2)} ha`;

            // Hectáreas a solicitar: editable, visible solo si está tildado
            // (mismo criterio que el repetible anterior — el usuario puede
            // pedir MENOS que el lote completo, no solo todo o nada).
            const cellHectareasSolicitadas = row.insertCell();
            const inputHectareas = document.createElement('input');
            inputHectareas.type = 'number';
            inputHectareas.step = '0.01';
            inputHectareas.min = '0.01';
            inputHectareas.max = String(lote.hectareas);
            inputHectareas.className = 'ag-input__control ag-ordenes-form__lotes-hectareas';
            inputHectareas.value = seleccion.get(lote.lote_id) ?? String(lote.hectareas);
            inputHectareas.hidden = !estaSeleccionado;
            inputHectareas.addEventListener('input', () => {
                if (checkbox.checked) {
                    seleccion.set(lote.lote_id, inputHectareas.value);
                    renderizarInputsHidden(lotes);
                }
            });
            cellHectareasSolicitadas.appendChild(inputHectareas);

            checkbox.addEventListener('change', () => {
                if (checkbox.checked) {
                    seleccion.set(lote.lote_id, inputHectareas.value || String(lote.hectareas));
                    inputHectareas.hidden = false;
                } else {
                    seleccion.delete(lote.lote_id);
                    inputHectareas.hidden = true;
                }
                renderizarInputsHidden(lotes);
            });

            // Desnivel.
            const cellDesnivel = row.insertCell();
            cellDesnivel.textContent = lote.desnivel_label || '—';

            // Limpieza.
            const cellLimpieza = row.insertCell();
            cellLimpieza.textContent = lote.limpieza_label || '—';
        });

        contenedorLotes.appendChild(tabla);
        renderizarInputsHidden(lotes);
    };

    /**
     * Renderiza los inputs hidden `lotes[N][lote_id]` y `lotes[N][hectareas_solicitadas]`
     * basados en `seleccion` — se llama tanto al tildar/destildar como al
     * editar el input de hectáreas, así el submit siempre refleja el último
     * valor, esté o no visible la fila en la página actual.
     */
    const renderizarInputsHidden = (lotes) => {
        let existente = tablaLotes?.querySelector('[data-ag-inputs-lotes-hidden]');
        if (!existente) {
            existente = document.createElement('div');
            existente.setAttribute('data-ag-inputs-lotes-hidden', '');
            existente.style.display = 'none';
            tablaLotes?.appendChild(existente);
        }

        existente.innerHTML = '';

        let indice = 0;
        seleccion.forEach((hectareas, loteId) => {
            const lote = lotes.find((l) => l.lote_id === loteId);
            if (!lote) return;

            const inputId = document.createElement('input');
            inputId.type = 'hidden';
            inputId.name = `lotes[${indice}][lote_id]`;
            inputId.value = loteId;

            const inputHectareas = document.createElement('input');
            inputHectareas.type = 'hidden';
            inputHectareas.name = `lotes[${indice}][hectareas_solicitadas]`;
            inputHectareas.value = hectareas;

            existente.appendChild(inputId);
            existente.appendChild(inputHectareas);

            indice++;
        });
    };

    /**
     * Renderiza los botones de paginado.
     */
    const renderizarPaginado = () => {
        if (!infoPagina) return;

        const totalPaginas = Math.max(1, Math.ceil(lotesFiltrados.length / lotesPerPage));
        infoPagina.textContent = `${paginaActual} / ${totalPaginas}`;

        if (btnPaginaAnterior) {
            btnPaginaAnterior.disabled = paginaActual === 1;
            btnPaginaAnterior.onclick = () => {
                paginaActual = Math.max(1, paginaActual - 1);
                renderizarContenedor(datosContrato[selectContrato.value]?.lotes || []);
                renderizarPaginado();
            };
        }

        if (btnPaginaSiguiente) {
            btnPaginaSiguiente.disabled = paginaActual === totalPaginas;
            btnPaginaSiguiente.onclick = () => {
                paginaActual = Math.min(totalPaginas, paginaActual + 1);
                renderizarContenedor(datosContrato[selectContrato.value]?.lotes || []);
                renderizarPaginado();
            };
        }
    };

    /**
     * Pinta resumen + contacto + tabla de lotes para un contrato. Se llama
     * tanto en un cambio REAL del `<select>` (el usuario elige otro
     * contrato — ahí sí se reinicia `seleccion`, porque el universo de
     * lotes cambió por completo) como en la carga inicial de la página
     * (edición, o redisplay tras un error de validación — ahí la
     * `seleccion` ya viene sembrada desde `lotesIniciales` y NO se toca).
     */
    const pintarContratoYLotes = (contratoId, { resetearSeleccion }) => {
        const datos = datosContrato[contratoId];

        if (!datos) {
            if (resumenContrato) resumenContrato.hidden = true;
            if (contactoWrap) contactoWrap.hidden = true;
            if (contenedorLotes) contenedorLotes.innerHTML = '';
            if (vacioBuscador) vacioBuscador.hidden = false;
            if (paginadoLotes) paginadoLotes.hidden = true;
            return;
        }

        // Pinta resumen.
        if (resumenContrato) {
            resumenContrato.hidden = false;
            const clienteEl = resumenContrato.querySelector('[data-ag-cliente-nombre]');
            if (clienteEl) clienteEl.textContent = datos.cliente;

            const propiedadesEl = resumenContrato.querySelector('[data-ag-propiedades-nombres]');
            if (propiedadesEl) propiedadesEl.textContent = datos.propiedades.join(', ') || '—';

            // El label "Aplicaciones pactadas" ya lo pone el <label> del
            // Blade (campo_contrato_aplicaciones) — acá solo va el número,
            // nunca la frase repetida (y nunca texto suelto en JS).
            const aplicacionesEl = resumenContrato.querySelector('[data-ag-aplicaciones-previstas]');
            if (aplicacionesEl) {
                aplicacionesEl.textContent = String(datos.aplicaciones_previstas);
            }
        }

        // Autoselecciona contacto si es único.
        if (selectContacto && datos.contactos && datos.contactos.length === 1) {
            selectContacto.value = datos.contactos[0].id;
            if (contactoWrap) contactoWrap.hidden = true;
        } else if (contactoWrap) {
            contactoWrap.hidden = false;
        }

        // Autoselecciona nro_aplicacion sugerido (SOLO en create(), NULL en edit()).
        if (selectNroAplicacion && datos.nro_aplicacion_sugerido !== null) {
            if (selectNroAplicacion.value === '' || selectNroAplicacion.value === '0') {
                selectNroAplicacion.value = datos.nro_aplicacion_sugerido;
            }
        }

        if (resetearSeleccion) {
            seleccion.clear();
        }

        paginaActual = 1;
        actualizarVista(datos.lotes || []);
    };

    selectContrato.addEventListener('change', () => {
        pintarContratoYLotes(parseInt(selectContrato.value, 10), { resetearSeleccion: true });
    });

    // Buscador de lotes.
    if (buscadorLotes) {
        buscadorLotes.addEventListener('input', () => {
            actualizarVista(datosContrato[selectContrato.value]?.lotes || []);
        });
    }

    // Carga inicial: si ya hay un contrato (edición, o redisplay tras
    // error), pinta todo SIN reiniciar `seleccion` — ya viene sembrada
    // desde `lotesIniciales`.
    if (selectContrato.value) {
        pintarContratoYLotes(parseInt(selectContrato.value, 10), { resetearSeleccion: false });
    }
});
