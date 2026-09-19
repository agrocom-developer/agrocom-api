/**
 * Dinámicas del formulario de contrato (HU-23, tarea 34; ampliado tarea
 * "contratos-lotes", 16/9/2026; rediseño modal de lotes, sept/2026). JS
 * vanilla, sin frameworks nuevos.
 *
 * Propiedades: multi-select mostrado como pills (chip `.ag-badge`, tarea
 * "contratos-lotes"). Elegir una propiedad del select, o clickear una pill
 * ya agregada, abre `#ag-modal-lotes-propiedad` con TODOS los lotes de esa
 * propiedad — tildados los que ya están en la lista apilada del contrato.
 * Nada cambia en el contrato hasta "Guardar selección" (`guardarSeleccionModal`):
 * cerrar el modal por la X/Cancelar/fondo descarta los cambios. El ícono de
 * cerrar de la pill saca la propiedad ENTERA (con todos sus lotes) del
 * contrato — `quitarGrupoDeLista`.
 *
 * Horario por lote (16/9/2026, reemplazo completo de com_contrato_ventanas):
 * cada lote tiene su propio rango horario (nullable, string `H:i`). Arranca
 * como "día completo" (sin inputs visibles) con un botón "Personalizar
 * horario" que despliega dos campos. Se guarda en `lotes[N][hora_inicio]` y
 * `lotes[N][hora_fin]`. El índice N es un contador monotónico
 * (`contadorIndiceLote`, nunca se reutiliza) para que agregar/quitar lotes
 * en cualquier orden no choque índices entre filas.
 *
 * "Personalizar horario" y "Quitar" de una fila de la lista apilada se
 * manejan por DELEGACIÓN sobre `listaApilada` (un solo listener), no por
 * listener individual al crear cada fila — así funcionan igual para las
 * filas que ya vienen renderizadas por el servidor (modo edición) que para
 * las que arma este JS al guardar una selección del modal.
 *
 * SessionStorage (guardar/restaurar el estado del formulario al navegar a
 * "Crear cliente/propiedad/lote" y volver, clave `ag_contrato_borrador`).
 *
 * Al volver, lo recién creado llega por la URL (`cliente_id`, `propiedad_id`,
 * `lote_id`, ver el prop `retorno` de `molecules/boton-volver`) y se deja ya
 * seleccionado en `restaurarBorrador()`. Cada id se valida contra lo que este
 * formulario ofrece (clientes del select, propiedades del cliente elegido,
 * lotes de esa propiedad no ocupados en la campaña elegida): uno que no
 * corresponde —ajeno, borrado, inventado— se ignora sin tocar nada más.
 *
 * Guard de presencia en el DOM (mismo criterio que `login.js`): en cualquier
 * página sin `[data-ag-contratos-form]` este módulo no hace nada.
 */
import { initTimeRanges } from '../atoms/time-range.js';

document.addEventListener('DOMContentLoaded', () => {
    const formulario = document.querySelector('[data-ag-contratos-form]');
    if (!formulario) return;

    // Memento de navegación (17/9/2026): mismos dos query params que ya arma
    // el `action-href` server-side de "Crear cliente" — acá los necesita JS
    // porque "Crear propiedad"/"Crear lote" arman su URL en runtime (cascade
    // cliente→propiedad, ver `abrirModalLotes()` más abajo).
    const urlOrigen = formulario.dataset.urlOrigen || window.location.href;
    const etiquetaOrigen = formulario.dataset.etiquetaOrigen || '';
    const queryOrigen = () => `volver_a=${encodeURIComponent(urlOrigen)}&volver_texto=${encodeURIComponent(etiquetaOrigen)}`;

    // ===== PROPIEDADES Y LOTES (estrategia 'a': datos embebidos) =====
    const selectCliente = formulario.querySelector('[name="cliente_id"]');
    const selectPropiedad = formulario.querySelector('[data-ag-propiedades-select]');
    const accionCrearPropiedad = selectPropiedad?.closest('.ag-select')?.querySelector('.ag-select__action') || null;
    const contenedorPropiedadesPills = formulario.querySelector('[data-ag-propiedades-pills]');
    const listaApilada = formulario.querySelector('[data-ag-lotes-lista-apilada]');
    const tablaLotes = formulario.querySelector('[data-ag-lotes-tabla]');
    const contenedorGrupos = formulario.querySelector('[data-ag-lotes-agrupados]');
    const scriptDatos = formulario.querySelector('[data-ag-propiedades-lotes]');

    const modalLotesEl = formulario.querySelector('[data-ag-modal-lotes]');
    const modalLotesTitulo = formulario.querySelector('[data-ag-modal-lotes-titulo]');
    const modalCrearLoteSlot = formulario.querySelector('[data-ag-modal-crear-lote-slot]');
    const modalLotesLista = formulario.querySelector('[data-ag-modal-lotes-lista]');
    const modalLotesAgotado = formulario.querySelector('[data-ag-modal-lotes-agotado]');
    const modalLotesGuardarBtn = formulario.querySelector('[data-ag-modal-lotes-guardar]');
    const bsModal = modalLotesEl ? bootstrap.Modal.getOrCreateInstance(modalLotesEl) : null;
    const selectCampania = formulario.querySelector('[name="campania_id"]');

    // Modal informativo del contrato en conflicto por lote (tarea
    // "contrato-lotes-conflicto", 18/9/2026) — JSON embebido indexado por
    // `lote_id`, mismo criterio de parseo defensivo que `propiedadesYLotes`.
    const modalConflictoEl = formulario.querySelector('[data-ag-modal-conflicto-lote]');
    const bsModalConflicto = modalConflictoEl ? bootstrap.Modal.getOrCreateInstance(modalConflictoEl) : null;
    const scriptConflictos = formulario.querySelector('[data-ag-conflictos-lotes]');
    let conflictosPorLote = {};
    if (scriptConflictos) {
        try {
            conflictosPorLote = JSON.parse(scriptConflictos.textContent) || {};
        } catch (e) {
            console.error('Error al parsear conflictos de lotes JSON:', e);
        }
    }

    const urlCrearLote = modalLotesEl?.dataset.urlCrearLote || '';
    const textoSinLotes = modalLotesEl?.dataset.textoSinLotes || '';
    const textoCrearLote = modalLotesEl?.dataset.textoCrearLote || '';
    const textoSeleccionarTodos = modalLotesEl?.dataset.textoSeleccionarTodos || '';
    const textoColCodigo = modalLotesEl?.dataset.textoColCodigo || '';
    const textoColHectareas = modalLotesEl?.dataset.textoColHectareas || '';
    const textoColDesnivel = modalLotesEl?.dataset.textoColDesnivel || '';
    const textoColLimpieza = modalLotesEl?.dataset.textoColLimpieza || '';
    const textoQuitarLote = listaApilada?.dataset.textoQuitarLote || '';
    const textoQuitarPropiedadPrefijo = contenedorPropiedadesPills?.dataset.textoQuitar || '';

    // Estado global accesible para restaurarBorrador
    let propiedadesSeleccionadas = new Set();
    let propiedadesYLotes = {};
    // Contador monotónico de índice de fila — nunca se reutiliza, ni al
    // quitar y volver a agregar el mismo lote (evita choques de `name`).
    let contadorIndiceLote = listaApilada?.querySelectorAll('[data-lote-id]').length || 0;

    const crearIcono = (nombre, size = 'sm', claseExtra = '') => {
        const span = document.createElement('span');
        span.className = `material-symbols-rounded ag-icon ag-icon--${size}${claseExtra ? ` ${claseExtra}` : ''}`;
        span.setAttribute('aria-hidden', 'true');
        span.textContent = nombre;
        return span;
    };

    const crearBotonAccion = (icono, texto) => {
        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'ag-button ag-button--text ag-button--sm';
        boton.appendChild(crearIcono(icono));
        const label = document.createElement('span');
        label.className = 'ag-button__label';
        label.textContent = texto;
        boton.appendChild(label);
        return boton;
    };

    const crearInputHidden = (name, value, dataAttr) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        if (dataAttr) input.setAttribute(dataAttr, '');
        return input;
    };

    // ===== Lista apilada: fila de lote (Código | Hectáreas | Día completo |
    // Horario | Acciones). Un lote recién agregado desde el modal arranca en
    // "día completo" (sin horario propio, mismo criterio que regía antes a
    // nivel de todo el contrato) — el checkbox habilita/deshabilita el par
    // de inputs de hora vía delegación (ver más abajo, cubre esta fila y las
    // que ya vienen renderizadas por el servidor en modo edición). =====
    const crearFilaLote = (loteId, loteData) => {
        const fila = document.createElement('div');
        fila.className = 'ag-contratos-form__lote-row';
        fila.setAttribute('data-lote-id', loteId);

        const indiceGlobal = contadorIndiceLote++;
        const inputLoteId = crearInputHidden(`lotes[${indiceGlobal}][lote_id]`, loteId);

        const codigo = document.createElement('strong');
        codigo.className = 'ag-contratos-form__lote-code';
        codigo.textContent = loteData.codigo;

        const hectareas = document.createElement('span');
        hectareas.className = 'ag-contratos-form__lote-hectareas';
        hectareas.textContent = `${Number(loteData.hectareas).toFixed(2)} ha`;

        const celdaDiaCompleto = document.createElement('label');
        celdaDiaCompleto.className = 'ag-contratos-form__lote-dia-completo-celda';
        const checkDiaCompleto = document.createElement('input');
        checkDiaCompleto.type = 'checkbox';
        checkDiaCompleto.className = 'ag-checkbox-group__input';
        checkDiaCompleto.checked = true;
        checkDiaCompleto.setAttribute('data-ag-lote-dia-completo', loteId);
        const boxDiaCompleto = document.createElement('span');
        boxDiaCompleto.className = 'ag-checkbox-group__box';
        boxDiaCompleto.setAttribute('aria-hidden', 'true');
        boxDiaCompleto.appendChild(crearIcono('check', 'sm', 'ag-checkbox-group__check'));
        celdaDiaCompleto.append(checkDiaCompleto, boxDiaCompleto);

        // Horario del lote: se clona el molde que trae el servidor
        // (`<template data-ag-time-range-molde>`), con el índice de esta fila en
        // los `name` y en el `id`, y se inicializa el componente. Nace
        // deshabilitado: el lote arranca en "día completo".
        const celdaRango = document.createElement('div');
        celdaRango.className = 'ag-contratos-form__lote-rango-horas';
        const molde = formulario.querySelector('template[data-ag-time-range-molde]');
        if (molde) {
            celdaRango.innerHTML = molde.innerHTML.replaceAll('__INDICE__', String(indiceGlobal));
            initTimeRanges(celdaRango);
        }

        const botonQuitar = crearBotonAccion('delete', textoQuitarLote);
        botonQuitar.setAttribute('data-ag-lote-quitar', '');
        botonQuitar.classList.add('ag-contratos-form__lote-quitar-btn');

        fila.append(inputLoteId, codigo, hectareas, celdaDiaCompleto, celdaRango, botonQuitar);
        return fila;
    };

    const quitarGrupoDeLista = (propiedadId) => {
        const grupo = contenedorGrupos?.querySelector(`[data-ag-lote-grupo="propiedad-${propiedadId}"]`);
        grupo?.remove();
    };

    /**
     * Reconcilia los lotes marcados en el modal con la lista apilada: agrega
     * los nuevos, quita los desmarcados, y NO TOCA los que ya estaban (para
     * no perder su horario personalizado ni el índice de su fila).
     */
    const sincronizarLotesDePropiedad = (propiedadId, propiedad, idsSeleccionados) => {
        if (!contenedorGrupos) return;

        let grupo = contenedorGrupos.querySelector(`[data-ag-lote-grupo="propiedad-${propiedadId}"]`);

        if (grupo) {
            grupo.querySelectorAll('[data-lote-id]').forEach((fila) => {
                if (!idsSeleccionados.has(fila.getAttribute('data-lote-id'))) {
                    fila.remove();
                }
            });
        }

        if (idsSeleccionados.size === 0) {
            grupo?.remove();
            return;
        }

        if (!grupo) {
            grupo = document.createElement('div');
            grupo.setAttribute('data-ag-lote-grupo', `propiedad-${propiedadId}`);
            grupo.className = 'ag-contratos-form__lote-group';

            const titulo = document.createElement('h4');
            titulo.className = 'ag-contratos-form__lote-group-title';
            titulo.textContent = propiedad.nombre;

            const contenedorLotes = document.createElement('div');
            contenedorLotes.className = 'ag-contratos-form__lote-group-items';
            contenedorLotes.setAttribute('data-ag-lote-contenedor', '');

            grupo.appendChild(titulo);
            grupo.appendChild(contenedorLotes);
            contenedorGrupos.appendChild(grupo);
        }

        const contenedorLotes = grupo.querySelector('[data-ag-lote-contenedor]');

        idsSeleccionados.forEach((loteId) => {
            if (contenedorLotes.querySelector(`[data-lote-id="${loteId}"]`)) {
                return; // ya estaba: no se toca (preserva horario)
            }
            const loteData = propiedad.lotes.find((l) => String(l.id) === loteId);
            if (loteData) {
                contenedorLotes.appendChild(crearFilaLote(loteId, loteData));
            }
        });
    };

    // ¿El lote ya está comprometido en OTRO contrato vigente de la campaña
    // elegida en este formulario? (`ocupado_en_campanias`, ver
    // `ContratosController::propiedadesYLotesPorCliente()`). Sin campaña
    // elegida todavía no hay nada que excluir. Lo usan el modal de lotes y el
    // regreso desde "Crear lote".
    const loteOcupadoEnCampaniaActual = (lote) => {
        const campaniaActual = selectCampania?.value ? String(selectCampania.value) : '';
        if (!campaniaActual) return false;
        return (lote.ocupado_en_campanias || []).map(String).includes(campaniaActual);
    };

    // Muestra la tabla de lotes agregados solo si hay al menos un grupo —
    // evita el cascarón vacío (solo encabezado) en un contrato nuevo.
    const actualizarVisibilidadTablaLotes = () => {
        if (!tablaLotes || !contenedorGrupos) return;
        tablaLotes.toggleAttribute('hidden', contenedorGrupos.children.length === 0);
    };

    // ===== Pills de propiedades seleccionadas =====
    const renderizarPills = () => {
        actualizarVisibilidadTablaLotes();

        if (!contenedorPropiedadesPills) return;

        contenedorPropiedadesPills.innerHTML = '';

        if (propiedadesSeleccionadas.size === 0) return;

        const clienteId = selectCliente?.value;
        if (!clienteId) return;

        const propiedades = propiedadesYLotes[clienteId] || {};

        Array.from(propiedadesSeleccionadas).forEach((propiedadId) => {
            const propiedad = propiedades[propiedadId];
            if (!propiedad) return;

            const cantidadLotes = contenedorGrupos?.querySelectorAll(
                `[data-ag-lote-grupo="propiedad-${propiedadId}"] [data-lote-id]`,
            ).length || 0;

            const pill = document.createElement('span');
            pill.className = 'ag-badge ag-badge--success ag-contratos-form__propiedad-pill';
            pill.setAttribute('role', 'button');
            pill.setAttribute('tabindex', '0');

            const label = document.createElement('span');
            label.className = 'ag-badge__label';
            label.textContent = cantidadLotes > 0 ? `${propiedad.nombre} (${cantidadLotes})` : propiedad.nombre;
            pill.appendChild(label);

            const botonQuitar = document.createElement('button');
            botonQuitar.type = 'button';
            botonQuitar.className = 'ag-contratos-form__propiedad-pill-close';
            botonQuitar.setAttribute('aria-label', `${textoQuitarPropiedadPrefijo} ${propiedad.nombre}`.trim());
            botonQuitar.appendChild(crearIcono('close'));
            botonQuitar.addEventListener('click', (e) => {
                e.stopPropagation();
                propiedadesSeleccionadas.delete(propiedadId);
                quitarGrupoDeLista(propiedadId);
                renderizarPills();
            });
            pill.appendChild(botonQuitar);

            const abrir = () => abrirModalLotes(propiedadId);
            pill.addEventListener('click', abrir);
            pill.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    abrir();
                }
            });

            contenedorPropiedadesPills.appendChild(pill);
        });
    };

    // ===== Modal de lotes de una propiedad =====
    function sincronizarSeleccionarTodosModal() {
        const checkboxes = modalLotesLista.querySelectorAll('[data-ag-modal-lote-checkbox]');
        const checkTodos = modalLotesLista.querySelector('[data-ag-modal-seleccionar-todos]');
        if (!checkTodos) return;

        const todosMarcados = Array.from(checkboxes).every((cb) => cb.checked);
        const algunosMarcados = Array.from(checkboxes).some((cb) => cb.checked);

        checkTodos.checked = todosMarcados && checkboxes.length > 0;
        checkTodos.indeterminate = algunosMarcados && !todosMarcados;
    }

    const abrirModalLotes = (propiedadId) => {
        if (!modalLotesEl || !bsModal) return;

        const clienteId = selectCliente?.value;
        if (!clienteId) return;

        const propiedad = (propiedadesYLotes[clienteId] || {})[propiedadId];
        if (!propiedad) return;

        modalLotesEl.setAttribute('data-propiedad-id-actual', propiedadId);
        if (modalLotesTitulo) {
            modalLotesTitulo.textContent = propiedad.nombre;
        }

        const idsYaEnContrato = new Set(
            Array.from(
                contenedorGrupos?.querySelectorAll(`[data-ag-lote-grupo="propiedad-${propiedadId}"] [data-lote-id]`) || [],
            ).map((el) => el.getAttribute('data-lote-id')),
        );

        modalLotesLista.innerHTML = '';

        // Acción "Crear lote", siempre visible arriba de la tabla (con o sin
        // lotes cargados todavía) — antes solo existía dentro del estado
        // vacío, pedido del usuario para poder sumar lotes sin cerrar el
        // modal y repetir el flujo desde cero. Botón real (no link de texto,
        // pedido explícito: "adentro de un modal no hace falta que sea un
        // btn link"), mismos tokens naranja/acento que el resto de los
        // botones "crear nuevo" del formulario (`ag-button--accent`).
        const accionCrearLote = document.createElement('a');
        accionCrearLote.href = '#';
        accionCrearLote.className = 'ag-button ag-button--accent ag-button--sm ag-contratos-form__modal-crear-lote';
        accionCrearLote.setAttribute('data-ag-link-accent', '');
        accionCrearLote.appendChild(crearIcono('add', 'sm', 'ag-button__icon'));
        const etiquetaCrearLote = document.createElement('span');
        etiquetaCrearLote.className = 'ag-button__label';
        etiquetaCrearLote.textContent = textoCrearLote;
        accionCrearLote.appendChild(etiquetaCrearLote);
        accionCrearLote.addEventListener('click', (e) => {
            e.preventDefault();
            guardarBorrador();
            window.location.href = `${urlCrearLote}?propiedad_id=${propiedadId}&${queryOrigen()}`;
        });
        if (modalCrearLoteSlot) {
            modalCrearLoteSlot.innerHTML = '';
            modalCrearLoteSlot.appendChild(accionCrearLote);
        } else {
            modalLotesLista.appendChild(accionCrearLote);
        }

        const lotes = propiedad.lotes || [];

        // Exclusión por conflicto (tarea "contrato-lotes-conflicto",
        // 18/9/2026): un lote ya comprometido en OTRO contrato vigente de la
        // MISMA campaña elegida en este formulario no se ofrece para elegir
        // — salvo que ya esté en el contrato que se está editando (ese sigue
        // apareciendo tildado, como siempre). Sin campaña elegida todavía,
        // no hay nada que excluir.
        const lotesVisibles = lotes.filter((lote) => idsYaEnContrato.has(String(lote.id)) || !loteOcupadoEnCampaniaActual(lote));

        // "Todos ocupados": la propiedad SÍ tiene lotes, pero ninguno queda
        // disponible para esta campaña — distinto del caso de abajo (la
        // propiedad no tiene ningún lote cargado en el catálogo).
        if (modalLotesAgotado) {
            modalLotesAgotado.hidden = !(lotes.length > 0 && lotesVisibles.length === 0);
        }

        if (lotes.length === 0) {
            modalLotesLista.hidden = false;
            const vacio = document.createElement('p');
            vacio.className = 'ag-contratos-form__lotes-empty-text';
            vacio.textContent = textoSinLotes;
            modalLotesLista.appendChild(vacio);
        } else if (lotesVisibles.length === 0) {
            modalLotesLista.hidden = true;
        } else {
            modalLotesLista.hidden = false;
            const tabla = document.createElement('div');
            tabla.className = 'ag-contratos-form__modal-tabla';

            const crearCeldaCheckbox = (esSeleccionarTodos, loteId) => {
                const celda = document.createElement('label');
                celda.className = 'ag-contratos-form__modal-checkbox-celda';

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'ag-checkbox-group__input';
                if (esSeleccionarTodos) {
                    checkbox.setAttribute('data-ag-modal-seleccionar-todos', '');
                    checkbox.setAttribute('aria-label', textoSeleccionarTodos);
                } else {
                    checkbox.value = loteId;
                    checkbox.setAttribute('data-ag-modal-lote-checkbox', '');
                }

                const box = document.createElement('span');
                box.className = 'ag-checkbox-group__box';
                box.setAttribute('aria-hidden', 'true');
                box.appendChild(crearIcono('check', 'sm', 'ag-checkbox-group__check'));

                celda.append(checkbox, box);
                return { celda, checkbox };
            };

            const head = document.createElement('div');
            head.className = 'ag-contratos-form__modal-tabla-head';
            const { celda: celdaTodos, checkbox: checkTodos } = crearCeldaCheckbox(true);
            checkTodos.addEventListener('change', () => {
                modalLotesLista.querySelectorAll('[data-ag-modal-lote-checkbox]').forEach((cb) => {
                    cb.checked = checkTodos.checked;
                });
            });
            const colCodigo = document.createElement('span');
            colCodigo.textContent = textoColCodigo;
            const colHectareas = document.createElement('span');
            colHectareas.textContent = textoColHectareas;
            const colDesnivel = document.createElement('span');
            colDesnivel.textContent = textoColDesnivel;
            const colLimpieza = document.createElement('span');
            colLimpieza.textContent = textoColLimpieza;
            head.append(celdaTodos, colCodigo, colHectareas, colDesnivel, colLimpieza);
            tabla.appendChild(head);

            lotesVisibles.forEach((lote) => {
                const fila = document.createElement('div');
                fila.className = 'ag-contratos-form__modal-tabla-fila';

                const { celda: celdaCheck, checkbox } = crearCeldaCheckbox(false, lote.id);
                checkbox.checked = idsYaEnContrato.has(String(lote.id));
                checkbox.addEventListener('change', sincronizarSeleccionarTodosModal);

                const celdaCodigo = document.createElement('strong');
                celdaCodigo.textContent = lote.codigo;

                const celdaHectareas = document.createElement('span');
                celdaHectareas.textContent = `${Number(lote.hectareas).toFixed(2)} ha`;

                const celdaDesnivel = document.createElement('span');
                celdaDesnivel.textContent = lote.desnivel_label || '—';
                celdaDesnivel.classList.toggle('ag-contratos-form__modal-tabla-vacio', !lote.desnivel_label);

                const celdaLimpieza = document.createElement('span');
                celdaLimpieza.textContent = lote.limpieza_label || '—';
                celdaLimpieza.classList.toggle('ag-contratos-form__modal-tabla-vacio', !lote.limpieza_label);

                fila.append(celdaCheck, celdaCodigo, celdaHectareas, celdaDesnivel, celdaLimpieza);

                // Clickear cualquier parte de la fila marca/desmarca — salvo
                // la propia celda de checkbox, que ya lo hace nativo (label
                // asociado) y duplicaría el toggle si también la contamos acá.
                fila.addEventListener('click', (e) => {
                    if (e.target.closest('.ag-contratos-form__modal-checkbox-celda')) return;
                    checkbox.checked = !checkbox.checked;
                    checkbox.dispatchEvent(new Event('change'));
                });

                tabla.appendChild(fila);
            });

            modalLotesLista.appendChild(tabla);
            sincronizarSeleccionarTodosModal();
        }

        bsModal.show();
    };

    const guardarSeleccionModal = () => {
        const propiedadId = modalLotesEl?.getAttribute('data-propiedad-id-actual');
        if (!propiedadId) return;

        const clienteId = selectCliente?.value;
        const propiedad = (propiedadesYLotes[clienteId] || {})[propiedadId];
        if (!propiedad) return;

        const idsSeleccionados = new Set(
            Array.from(modalLotesLista.querySelectorAll('[data-ag-modal-lote-checkbox]:checked')).map((cb) => cb.value),
        );

        sincronizarLotesDePropiedad(propiedadId, propiedad, idsSeleccionados);
        renderizarPills();
        bsModal.hide();
    };

    modalLotesGuardarBtn?.addEventListener('click', guardarSeleccionModal);

    // ===== Día completo, horario y quitar (delegación — cubre filas del
    // servidor y del JS con un solo listener cada una) =====
    listaApilada?.addEventListener('change', (e) => {
        const checkDiaCompleto = e.target.closest('[data-ag-lote-dia-completo]');
        if (!checkDiaCompleto) return;

        // "Día completo" deshabilita y vacía el horario de la fila. Basta con
        // tocar los inputs nativos: el componente `atoms/time-range` observa su
        // `disabled` y se vuelve a leer solo.
        const fila = checkDiaCompleto.closest('.ag-contratos-form__lote-row');
        fila?.querySelectorAll('.ag-time-range__native').forEach((input) => {
            input.disabled = checkDiaCompleto.checked;
            if (checkDiaCompleto.checked) input.value = '';
        });

        // Al destildar, el foco pasa al horario (sin abrirlo) para que se cargue
        // enseguida. Va en un `setTimeout`: el componente habilita su disparador al
        // observar el cambio de `disabled`, que ocurre después de este oyente.
        if (!checkDiaCompleto.checked) {
            setTimeout(() => fila?.querySelector('[data-ag-time-range-trigger]')?.focus(), 0);
        }
    });

    listaApilada?.addEventListener('click', (e) => {
        const botonQuitar = e.target.closest('[data-ag-lote-quitar]');
        if (botonQuitar) {
            e.preventDefault();
            const fila = botonQuitar.closest('[data-lote-id]');
            const grupo = fila?.closest('[data-ag-lote-grupo]');
            fila?.remove();
            if (grupo && !grupo.querySelector('[data-lote-id]')) {
                grupo.remove();
            }
            renderizarPills();
            return;
        }

        // Modal informativo del contrato en conflicto (tarea
        // "contrato-lotes-conflicto", 18/9/2026) — pinta los campos desde el
        // JSON embebido, sin pedirle nada al servidor.
        const botonVerConflicto = e.target.closest('[data-ag-lote-conflicto-ver]');
        if (botonVerConflicto && modalConflictoEl && bsModalConflicto) {
            e.preventDefault();
            const conflicto = conflictosPorLote[botonVerConflicto.dataset.loteIdConflicto];
            if (!conflicto) return;

            const setTexto = (selector, valor) => {
                const el = modalConflictoEl.querySelector(selector);
                if (el) el.textContent = valor;
            };

            setTexto('[data-ag-conflicto-cliente]', conflicto.cliente);
            setTexto('[data-ag-conflicto-propiedades]', conflicto.propiedades);
            setTexto('[data-ag-conflicto-vigencia]', conflicto.vigencia);
            setTexto('[data-ag-conflicto-monto]', conflicto.monto_total);

            const badgeEstado = modalConflictoEl.querySelector('[data-ag-conflicto-estado]');
            if (badgeEstado) {
                Array.from(badgeEstado.classList)
                    .filter((clase) => clase.startsWith('ag-badge--'))
                    .forEach((clase) => badgeEstado.classList.remove(clase));
                badgeEstado.classList.add(`ag-badge--${conflicto.estado_variant}`);
                const label = badgeEstado.querySelector('.ag-badge__label');
                if (label) label.textContent = conflicto.estado_label;
            }

            const botonEditar = modalConflictoEl.querySelector('[data-ag-conflicto-editar]');
            if (botonEditar) botonEditar.setAttribute('href', conflicto.editar_url);

            // Lotes compartidos entre este contrato y el de arriba (pedido
            // explícito, 18/9/2026: "no me sirve de mucho solo los datos del
            // contrato" — hace falta ver QUÉ lotes chocan, no solo con quién).
            // Filas armadas a mano con las mismas clases de `x-molecules.index-table`
            // (`ag-index-table__row`, ver index-table.css) — el componente Blade
            // ya puso la cabecera y el `--ag-index-table-columns` en el
            // contenedor ancestro, las filas heredan esa custom property
            // aunque vivan un nivel más adentro (`data-ag-conflicto-lotes`).
            const contenedorLotesConflicto = modalConflictoEl.querySelector('[data-ag-conflicto-lotes]');
            if (contenedorLotesConflicto) {
                contenedorLotesConflicto.innerHTML = '';
                (conflicto.lotes_en_conflicto || []).forEach((lote) => {
                    const fila = document.createElement('div');
                    fila.className = 'ag-index-table__row';
                    fila.setAttribute('role', 'row');

                    const celdaCodigo = document.createElement('span');
                    celdaCodigo.setAttribute('role', 'cell');
                    celdaCodigo.textContent = lote.codigo;

                    const celdaPropiedad = document.createElement('span');
                    celdaPropiedad.setAttribute('role', 'cell');
                    celdaPropiedad.textContent = lote.propiedad;

                    const celdaHectareas = document.createElement('span');
                    celdaHectareas.setAttribute('role', 'cell');
                    celdaHectareas.textContent = lote.hectareas;

                    fila.append(celdaCodigo, celdaPropiedad, celdaHectareas);
                    contenedorLotesConflicto.appendChild(fila);
                });
            }

            bsModalConflicto.show();
        }
    });

    // Configurar eventos solo si tenemos los elementos necesarios
    if (selectCliente && selectPropiedad && contenedorPropiedadesPills && scriptDatos) {
        try {
            propiedadesYLotes = JSON.parse(scriptDatos.textContent) || {};
        } catch (e) {
            console.error('Error al parsear propiedades/lotes JSON:', e);
        }

        /**
         * Actualizar select de propiedad cuando cambia el cliente.
         * Llena el <select> oculto con las propiedades del cliente actual.
         */
        const actualizarSelectPropiedad = () => {
            const clienteId = selectCliente.value;
            const propiedades = propiedadesYLotes[clienteId] || {};

            selectPropiedad.innerHTML = `<option value="">${selectPropiedad.getAttribute('placeholder') || ''}</option>`;
            selectPropiedad.disabled = !clienteId;

            Object.entries(propiedades).forEach(([propiedadId, propiedad]) => {
                const option = document.createElement('option');
                option.value = propiedadId;
                option.textContent = propiedad.nombre;
                selectPropiedad.appendChild(option);
            });

            if (accionCrearPropiedad) {
                if (clienteId) {
                    const urlCrear = accionCrearPropiedad.href.split('?')[0];
                    accionCrearPropiedad.href = `${urlCrear}?cliente_id=${clienteId}&${queryOrigen()}`;
                    accionCrearPropiedad.removeAttribute('hidden');
                } else {
                    accionCrearPropiedad.setAttribute('hidden', '');
                }
            }

            propiedadesSeleccionadas.clear();
            renderizarPills();
        };

        selectCliente.addEventListener('change', actualizarSelectPropiedad);

        selectPropiedad.addEventListener('change', () => {
            const propiedadId = selectPropiedad.value;
            if (!propiedadId) return;

            propiedadesSeleccionadas.add(propiedadId);
            selectPropiedad.value = '';
            renderizarPills();
            abrirModalLotes(propiedadId);
        });

        if (selectCliente.value) {
            actualizarSelectPropiedad();

            // Sembrar pills desde los grupos ya renderizados por el servidor
            // (modo edición) — actualizarSelectPropiedad() los vació arriba
            // porque asume "cambio de cliente"; en la carga inicial el
            // cliente no cambió, ya trae lotes de antes.
            contenedorGrupos?.querySelectorAll('[data-ag-lote-grupo]').forEach((grupo) => {
                const coincidencia = grupo.getAttribute('data-ag-lote-grupo')?.match(/^propiedad-(\d+)$/);
                if (coincidencia) propiedadesSeleccionadas.add(coincidencia[1]);
            });
            renderizarPills();
        }
    }

    // ===== SESSIONSTORAGE (guarda/restaura estado formulario) =====
    // Guardar borrador en sessionStorage antes de navegar (tarea "contratos-lotes", punto 5)
    const linksAltaRapida = formulario.querySelectorAll('[data-ag-link-accent]');
    linksAltaRapida.forEach((link) => {
        link.addEventListener('click', () => {
            guardarBorrador();
        });
    });

    function guardarBorrador() {
        const datos = new FormData(formulario);
        const bor = {};

        datos.forEach((value, key) => {
            if (key.startsWith('lotes[')) {
                if (!bor[key]) bor[key] = [];
                bor[key].push(value);
            } else if (!bor[key]) {
                bor[key] = value;
            }
        });

        sessionStorage.setItem('ag_contrato_borrador', JSON.stringify(bor));
    }

    function restaurarBorrador() {
        // Leer URL primero para permitir que los query params ganen
        const urlParams = new URLSearchParams(window.location.search);
        const clienteIdUrl = urlParams.get('cliente_id');
        const propiedadIdUrl = urlParams.get('propiedad_id');
        const loteIdUrl = urlParams.get('lote_id');

        const clienteAntes = selectCliente?.value;

        const bor = sessionStorage.getItem('ag_contrato_borrador');
        if (bor) {
            try {
                const datos = JSON.parse(bor);
                Object.entries(datos).forEach(([key, value]) => {
                    if (Array.isArray(value)) {
                        value.forEach((v) => {
                            const input = formulario.querySelector(`[name="${key}"]`);
                            if (input) input.value = v;
                        });
                    } else {
                        const input = formulario.querySelector(`[name="${key}"]`);
                        if (input) {
                            if (input.type === 'checkbox') {
                                input.checked = value === 'on' || value === '1';
                            } else {
                                input.value = value;
                            }
                        }
                    }
                });

                sessionStorage.removeItem('ag_contrato_borrador');
            } catch (e) {
                console.error('Error al restaurar borrador:', e);
            }
        }

        // El borrador pudo cambiar el cliente sin avisarle al resto del
        // formulario (propiedades, pills, etiqueta del select): se avisa acá.
        if (selectCliente && selectCliente.value !== clienteAntes) {
            selectCliente.dispatchEvent(new Event('change'));
        }

        // Aplicar valores de URL (ganan sobre el borrador). Cada id se acepta
        // solo si es una opción real de lo que este formulario ofrece.
        const opcionDelSelect = (select, valor) => Boolean(valor)
            && Array.from(select?.options || []).some((opcion) => opcion.value === valor);

        // Volver de "Crear cliente": queda elegido el cliente nuevo. Solo se
        // avisa del cambio si de verdad cambió — un `change` vacía las
        // propiedades elegidas, y con el mismo cliente no hay por qué.
        if (clienteIdUrl && !urlParams.has('_limpiar_cliente') && opcionDelSelect(selectCliente, clienteIdUrl)
            && selectCliente.value !== clienteIdUrl) {
            selectCliente.value = clienteIdUrl;
            selectCliente.dispatchEvent(new Event('change'));

            // Los lotes que ya estaban cargados (edición de un contrato) eran
            // de propiedades del cliente anterior: no valen para el nuevo.
            contenedorGrupos?.querySelectorAll('[data-ag-lote-grupo]').forEach((grupo) => grupo.remove());
            renderizarPills();
        }

        // Volver de "Crear propiedad" (con o sin lote): agregar la propiedad
        // y, si ya viene con un lote recién creado, sumarlo directo a la
        // lista apilada (mismo resultado que tildarlo en el modal y guardar).
        // La propiedad tiene que ser del cliente elegido y el lote de esa
        // propiedad y libre en la campaña elegida — si no, se ignoran.
        if (propiedadIdUrl && selectCliente && selectCliente.value) {
            const propiedad = (propiedadesYLotes[selectCliente.value] || {})[propiedadIdUrl];

            if (propiedad) {
                propiedadesSeleccionadas.add(propiedadIdUrl);

                const lote = loteIdUrl ? (propiedad.lotes || []).find((l) => String(l.id) === loteIdUrl) : null;
                if (lote && !loteOcupadoEnCampaniaActual(lote)) {
                    const idsActuales = new Set(
                        Array.from(
                            contenedorGrupos?.querySelectorAll(`[data-ag-lote-grupo="propiedad-${propiedadIdUrl}"] [data-lote-id]`) || [],
                        ).map((el) => el.getAttribute('data-lote-id')),
                    );
                    idsActuales.add(loteIdUrl);
                    sincronizarLotesDePropiedad(propiedadIdUrl, propiedad, idsActuales);
                }

                renderizarPills();
            }
        }
    }

    // Restaurar al cargar
    restaurarBorrador();

    // Guardar al enviar (limpiar después)
    formulario.addEventListener('submit', () => {
        sessionStorage.removeItem('ag_contrato_borrador');
    });

    // ===== Adelanto Solicitado: valor estimado a cobrar + % en vivo (tarea
    // "adelanto-calculado", 18/9/2026) — puramente informativo, sin bloqueo
    // de guardado nuevo. Replica en JS los mismos 3 factores de
    // `CrearContrato::calcularMontoTotal()`/`ActualizarContrato` (hectáreas ×
    // aplicaciones × precio por hectárea) solo para el preview en vivo — el
    // valor real que se guarda lo sigue recalculando siempre el servidor con
    // `Brick\Math\BigDecimal` (invariante 6): acá `Number` alcanza porque
    // este cálculo nunca se persiste. El 100% del valor estimado es el techo
    // MATEMÁTICO del adelanto (no se puede cobrar más de lo que vale el
    // contrato) — no hay ningún porcentaje de negocio fijo involucrado.
    const inputHectareas = formulario.querySelector('[name="hectareas_contratadas"]');
    const inputAplicaciones = formulario.querySelector('[name="aplicaciones_previstas"]');
    const inputPrecioHa = formulario.querySelector('[name="precio_ha"]');
    const inputAdelanto = formulario.querySelector('[name="adelanto_monto"]');
    const campoValorEstimado = formulario.querySelector('[data-ag-valor-estimado]');
    const ayudaAdelanto = document.getElementById('adelanto_monto-help');

    if (inputHectareas && inputAplicaciones && inputPrecioHa && inputAdelanto && campoValorEstimado && ayudaAdelanto) {
        const plantillaAyuda = inputAdelanto.dataset.plantillaAyuda || '';
        const plantillaAyudaMaximo = inputAdelanto.dataset.plantillaAyudaMaximo || '';

        const formatearMonto = (valor) => valor.toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        const recalcularAdelanto = () => {
            const hectareas = Number(inputHectareas.value) || 0;
            const aplicaciones = Number(inputAplicaciones.value) || 0;
            const precioHa = Number(inputPrecioHa.value) || 0;
            const adelanto = Number(inputAdelanto.value) || 0;

            const valorEstimado = hectareas * aplicaciones * precioHa;
            campoValorEstimado.value = formatearMonto(valorEstimado);

            const porcentaje = valorEstimado > 0 ? (adelanto / valorEstimado) * 100 : 0;
            const alLimite = porcentaje >= 100;

            ayudaAdelanto.textContent = (alLimite ? plantillaAyudaMaximo : plantillaAyuda).replace(':porcentaje', formatearMonto(porcentaje));
            ayudaAdelanto.classList.toggle('ag-input__help--accent', !alLimite);
            ayudaAdelanto.classList.toggle('ag-input__help--alert', alLimite);
        };

        [inputHectareas, inputAplicaciones, inputPrecioHa, inputAdelanto].forEach((input) => {
            input.addEventListener('input', recalcularAdelanto);
        });

        recalcularAdelanto();
    }
});
