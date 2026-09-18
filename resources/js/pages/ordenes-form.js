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
    const buscadorLotesWrap = formulario.querySelector('[data-ag-lotes-buscador-wrap]');
    const paginadoLotes = formulario.querySelector('[data-ag-lotes-paginado]');
    const vacioBuscador = formulario.querySelector('[data-ag-lotes-vacio]');
    const btnPaginaAnterior = formulario.querySelector('[data-ag-pagina-anterior]');
    const btnPaginaSiguiente = formulario.querySelector('[data-ag-pagina-siguiente]');
    const infoPagina = formulario.querySelector('[data-ag-pagina-info]');
    const selectNroAplicacion = formulario.querySelector('[data-ag-orden-nro-aplicacion]');
    const lotesSinContrato = formulario.querySelector('[data-ag-lotes-sin-contrato]');

    // Estado vacío "el contrato elegido no tiene ningún lote" (tarea
    // "contrato-lotes-conflicto", 18/9/2026) — distinto de `vacioBuscador`
    // (hay lotes, pero la búsqueda de texto no encuentra nada). El botón
    // "Editar contrato" arma su `href` en runtime con el memento de
    // navegación (mismo criterio que `contratos-form.js`).
    const vacioContratoSinLotes = formulario.querySelector('[data-ag-lotes-contrato-vacio]');
    const botonEditarContratoVacio = formulario.querySelector('[data-ag-lotes-contrato-vacio-editar]');
    const urlOrigen = formulario.dataset.urlOrigen || window.location.href;
    const etiquetaOrigen = formulario.dataset.etiquetaOrigen || '';
    const lotesContenido = formulario.querySelector('[data-ag-lotes-contenido]');
    const fechaEmisionAyuda = formulario.querySelector('[data-ag-fecha-emision-ayuda]');

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
     * Checkbox con el mismo marcado que `atoms/checkbox-group`
     * (checkbox-group.css): el `<input>` real va `position: absolute` e
     * invisible — lo que se ve es el `<span class="ag-checkbox-group__box">`
     * hermano (con el ícono de check adentro), mostrado vía `:checked ~ .box`
     * en CSS. Compartido entre la fila de cada lote y el "seleccionar
     * todos" del header (mismo criterio que `_modal-lotes` de Comercial).
     */
    const crearCheckboxCelda = () => {
        const label = document.createElement('label');
        label.className = 'ag-checkbox-group__option';
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.className = 'ag-checkbox-group__input';
        const caja = document.createElement('span');
        caja.className = 'ag-checkbox-group__box';
        caja.setAttribute('aria-hidden', 'true');
        const icono = document.createElement('span');
        icono.className = 'material-symbols-rounded ag-icon ag-icon--sm ag-checkbox-group__check';
        icono.textContent = 'check';
        caja.appendChild(icono);
        label.append(checkbox, caja);
        return { label, checkbox };
    };

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
            // "El contrato no tiene ningún lote" (tarea
            // "contrato-lotes-conflicto", 18/9/2026 — el caso real que
            // motivó todo este pedido) es distinto de "hay lotes, pero la
            // búsqueda de texto no encontró nada": solo el primero ofrece
            // ir a editar el contrato.
            if (lotes.length === 0) {
                if (vacioBuscador) vacioBuscador.hidden = true;
                if (vacioContratoSinLotes) {
                    vacioContratoSinLotes.hidden = false;
                    const editUrl = datosContrato[selectContrato?.value]?.contrato_edit_url;
                    if (botonEditarContratoVacio && editUrl) {
                        const separador = editUrl.includes('?') ? '&' : '?';
                        botonEditarContratoVacio.setAttribute(
                            'href',
                            `${editUrl}${separador}volver_a=${encodeURIComponent(urlOrigen)}&volver_texto=${encodeURIComponent(etiquetaOrigen)}`,
                        );
                    }
                }
            } else {
                if (vacioBuscador) vacioBuscador.hidden = false;
                if (vacioContratoSinLotes) vacioContratoSinLotes.hidden = true;
            }
            if (paginadoLotes) paginadoLotes.hidden = true;
            renderizarInputsHidden(lotes);
            return;
        }

        if (vacioBuscador) vacioBuscador.hidden = true;
        if (vacioContratoSinLotes) vacioContratoSinLotes.hidden = true;
        // Paginado solo si hay más de una página (+10 lotes filtrados) — con
        // 10 o menos no aporta nada mostrar "Anterior/1 de 1/Siguiente".
        if (paginadoLotes) paginadoLotes.hidden = lotesFiltrados.length <= lotesPerPage;

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

        // "Seleccionar todos" — actúa sobre TODO `lotesFiltrados` (todas las
        // páginas del filtro actual, no solo la visible): con un contrato
        // de 3000ha en 40-75 lotes, tildar de a uno por página no tendría
        // sentido. `checked`/`indeterminate` reflejan la selección real
        // aunque la mayoría de esas filas no estén dibujadas ahora mismo.
        const { label: labelTodos, checkbox: checkTodos } = crearCheckboxCelda();
        checkTodos.setAttribute('aria-label', textos.textoSeleccionarTodos || '');
        const marcados = lotesFiltrados.filter((l) => seleccion.has(l.lote_id)).length;
        checkTodos.checked = lotesFiltrados.length > 0 && marcados === lotesFiltrados.length;
        checkTodos.indeterminate = marcados > 0 && marcados < lotesFiltrados.length;
        checkTodos.addEventListener('change', () => {
            lotesFiltrados.forEach((lote) => {
                if (checkTodos.checked) {
                    seleccion.set(lote.lote_id, seleccion.get(lote.lote_id) ?? String(lote.hectareas));
                } else {
                    seleccion.delete(lote.lote_id);
                }
            });
            renderizarContenedor(lotes);
        });

        const headCells = [
            { elemento: labelTodos },
            { texto: textos.textoColCodigo || '' },
            { texto: textos.textoColPropiedad || '' },
            { texto: textos.textoColHectareasLote || '' },
            { texto: textos.textoColHectareasSolicitadas || '', ayuda: textos.textoColHectareasSolicitadasAyuda },
            { texto: textos.textoColDesnivel || '' },
            { texto: textos.textoColLimpieza || '' },
        ];
        headCells.forEach(({ texto, ayuda, elemento }) => {
            const th = headRow.insertCell();

            if (elemento) {
                th.className = 'ag-ordenes-form__lotes-tabla-checkbox';
                th.appendChild(elemento);
                return;
            }

            th.append(document.createTextNode(texto));

            // Tooltip Bootstrap (ya inicializado globalmente en app.js) — el
            // significado de "Hectáreas solicitadas" no es obvio a simple
            // vista (puede ser menos que el total del lote).
            if (ayuda) {
                const icono = document.createElement('span');
                icono.className = 'material-symbols-rounded ag-icon ag-icon--sm ag-ordenes-form__lotes-ayuda-icono';
                icono.textContent = 'info';
                icono.setAttribute('data-bs-toggle', 'tooltip');
                icono.setAttribute('data-bs-placement', 'top');
                icono.setAttribute('title', ayuda);
                icono.setAttribute('aria-hidden', 'true');
                th.appendChild(icono);
                if (window.bootstrap?.Tooltip) {
                    new window.bootstrap.Tooltip(icono);
                }
            }
        });

        // Body: filas de lotes (paginadas).
        const tbody = tabla.createTBody();
        lotesPagina.forEach((lote) => {
            const row = tbody.insertRow();
            row.className = 'ag-ordenes-form__lotes-tabla-fila';

            const estaSeleccionado = seleccion.has(lote.lote_id);

            const cellCheck = row.insertCell();
            cellCheck.className = 'ag-ordenes-form__lotes-tabla-checkbox';
            const { label: labelCheck, checkbox } = crearCheckboxCelda();
            checkbox.checked = estaSeleccionado;
            cellCheck.appendChild(labelCheck);

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
            // Sin contrato elegido no hay cliente de quién mostrar
            // contactos: el select queda visible pero sin ninguna opción
            // habilitada (nunca se oculta, ver docblock de más abajo).
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
            if (contenedorLotes) contenedorLotes.innerHTML = '';
            if (lotesSinContrato) lotesSinContrato.hidden = false;
            if (lotesContenido) lotesContenido.hidden = true;
            if (buscadorLotesWrap) buscadorLotesWrap.hidden = true;
            if (fechaEmisionAyuda) fechaEmisionAyuda.hidden = true;
            return;
        }

        // Hay contrato: se ve el buscador+tabla real, no la card de "elige
        // un contrato" (mostrar un buscador para una tabla sin universo de
        // datos confunde — pedido explícito del usuario 18/9/2026). Mismo
        // criterio si el contrato no tiene NINGÚN lote (tarea
        // "contrato-lotes-conflicto", 18/9/2026): un buscador sobre el
        // estado vacío "editar contrato" es igual de confuso.
        if (lotesSinContrato) lotesSinContrato.hidden = true;
        if (lotesContenido) lotesContenido.hidden = false;
        if (buscadorLotesWrap) buscadorLotesWrap.hidden = (datos.lotes || []).length === 0;

        // Ayuda de "Fecha de emisión": fecha de inicio del contrato
        // destacada, fecha de fin solo si el contrato la tiene (nullable).
        if (fechaEmisionAyuda) {
            fechaEmisionAyuda.hidden = false;
            const fechaInicioEl = fechaEmisionAyuda.querySelector('[data-ag-fecha-inicio-contrato]');
            if (fechaInicioEl) fechaInicioEl.textContent = datos.fecha_inicio;

            const fechaFinWrap = fechaEmisionAyuda.querySelector('[data-ag-fecha-fin-wrap]');
            const fechaFinEl = fechaEmisionAyuda.querySelector('[data-ag-fecha-fin-contrato]');
            if (fechaFinWrap) fechaFinWrap.hidden = !datos.fecha_fin;
            if (fechaFinEl) fechaFinEl.textContent = datos.fecha_fin || '';
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

            const hectareasEl = resumenContrato.querySelector('[data-ag-hectareas-contratadas]');
            if (hectareasEl) hectareasEl.textContent = `${datos.hectareas_contratadas} ha`;

            const fechaInicioEl = resumenContrato.querySelector('[data-ag-fecha-inicio]');
            if (fechaInicioEl) fechaInicioEl.textContent = datos.fecha_inicio;

            const fechaFinEl = resumenContrato.querySelector('[data-ag-fecha-fin]');
            if (fechaFinEl) fechaFinEl.textContent = datos.fecha_fin || fechaFinEl.dataset.textoSinDefinir || '—';

            // Logo del cliente — cae al placeholder (`data-logo-placeholder`,
            // ver _formulario.blade.php) si el cliente no tiene uno propio.
            const logoEl = resumenContrato.querySelector('[data-ag-cliente-logo]');
            if (logoEl) {
                logoEl.src = datos.logo_url || logoEl.dataset.logoPlaceholder;
            }
        }

        // Contacto: el `<select>` SIEMPRE queda visible (nunca se oculta,
        // pedido explícito del usuario 18/9/2026) — lo que cambia es qué
        // opciones puede elegir: solo los contactos DEL CLIENTE de este
        // contrato (`datos.contactos`, ya scopeado server-side), mismo
        // criterio de ocultar/deshabilitar `<option>` que ya usa
        // `filtrarPorValorPadre()` para tipo_insumo→categoría.
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

            // Cambio REAL de contrato (no la carga inicial): el contacto ya
            // elegido pertenecía a otro cliente, se limpia. En la carga
            // inicial (edición/redisplay) nunca se toca un valor ya
            // guardado, aunque `resetearSeleccion` sea false.
            if (resetearSeleccion && !valorSigueVisible) {
                selectContacto.value = '';
            }

            // Autoselecciona si queda uno solo disponible — salvo que el
            // campo ya venga con un error del servidor (`data-tiene-error`):
            // ahí se deja como está, para que el usuario vea qué falló.
            const contactoTieneError = contactoWrap?.hasAttribute('data-tiene-error');
            if (!contactoTieneError && datos.contactos && datos.contactos.length === 1) {
                selectContacto.value = String(datos.contactos[0].id);
            }

            selectContacto.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // Acota las opciones de "Número de aplicación" al total pactado del
        // contrato (1..aplicaciones_previstas) — nunca las borra del DOM
        // (así una edición con un valor ya guardado fuera de rango, p. ej.
        // tras una modificación del contrato, no pierde su opción real):
        // solo oculta/deshabilita las que exceden el pacto de ESTE contrato.
        if (selectNroAplicacion) {
            Array.from(selectNroAplicacion.querySelectorAll('option'))
                .filter((opcion) => opcion.value !== '')
                .forEach((opcion) => {
                    const numero = parseInt(opcion.value, 10);
                    const visible = !Number.isNaN(numero) && numero <= datos.aplicaciones_previstas;
                    opcion.hidden = !visible;
                    opcion.disabled = !visible;
                });
        }

        // Autoselecciona nro_aplicacion sugerido (SOLO en create(), NULL en edit()).
        if (selectNroAplicacion && datos.nro_aplicacion_sugerido !== null) {
            if (selectNroAplicacion.value === '' || selectNroAplicacion.value === '0') {
                selectNroAplicacion.value = datos.nro_aplicacion_sugerido;
            }
        }

        // Al elegir contrato (nunca en la carga inicial de edición/redisplay,
        // que respeta lo ya guardado/tipeado): todos los lotes arrancan
        // tildados con su hectáreas completa — pedido explícito del usuario
        // 18/9/2026, "el caso ideal es que entre todo en la orden, el
        // operario ve cuáles quita" — no lo contrario.
        if (resetearSeleccion) {
            seleccion.clear();
            (datos.lotes || []).forEach((lote) => {
                seleccion.set(lote.lote_id, String(lote.hectareas));
            });
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
