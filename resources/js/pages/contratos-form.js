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
 * El contrato solo dice QUÉ lotes entran (21/9/2026): el día completo y el
 * horario de cada lote se cargan en la orden de trabajo, no acá. Cada fila
 * manda `lotes[N][lote_id]`; N es un contador monotónico
 * (`contadorIndiceLote`, nunca se reutiliza) para que agregar/quitar lotes en
 * cualquier orden no choque índices entre filas. Las filas de cada propiedad
 * van en el orden en que el servidor manda sus lotes (natural por código: L1,
 * L2, … L10 — `Lote::scopeOrdenadosPorCodigo()`), se agreguen cuando se agreguen.
 *
 * Arriba de la tabla, un aviso informativo compara las hectáreas que suman los
 * lotes elegidos con las hectáreas contratadas (`actualizarResumenHectareas`):
 * orienta, no bloquea el guardado.
 *
 * "Quitar" de una fila se maneja por DELEGACIÓN sobre `listaApilada` (un solo
 * listener), no por listener individual al crear cada fila — así funciona
 * igual para las filas que ya vienen renderizadas por el servidor (modo
 * edición) que para las que arma este JS al guardar una selección del modal.
 *
 * Borrador (guardar/restaurar el formulario al navegar a "Crear
 * cliente/propiedad/lote" y volver): los campos los guarda y repone
 * `shared/borrador-formulario.js` —switches, casillas y radios incluidos—; este
 * módulo suma lo suyo, que no es un campo: qué propiedades y qué lotes estaban
 * elegidos. Reponerlo nunca pisa con un valor vacío lo que el formulario ya trae
 * —p. ej. la campaña activa que el alta ofrece elegida (19/9/2026).
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
import { descartarBorrador, guardarBorrador as guardarBorradorCompartido, leerBorrador, restaurarCampos } from '../shared/borrador-formulario.js';
import { crearPaginador } from '../shared/paginador-cliente.js';

// Lotes por página, en el modal de una propiedad y en la tabla del contrato.
const LOTES_POR_PAGINA = 20;

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
    const modalLotesSeleccionados = formulario.querySelector('[data-ag-modal-lotes-seleccionados]');
    const contenedorPaginadorModal = formulario.querySelector('[data-ag-modal-paginador]');
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
    const urlSiembra = modalLotesEl?.dataset.urlSiembra || '';
    const textoSiembra = modalLotesEl?.dataset.textoSiembra || '';
    const textoSeleccionarTodos = modalLotesEl?.dataset.textoSeleccionarTodos || '';
    const textoColCodigo = modalLotesEl?.dataset.textoColCodigo || '';
    const textoColHectareas = modalLotesEl?.dataset.textoColHectareas || '';
    const textoColDesnivel = modalLotesEl?.dataset.textoColDesnivel || '';
    const textoColLimpieza = modalLotesEl?.dataset.textoColLimpieza || '';
    const textoColCultivo = modalLotesEl?.dataset.textoColCultivo || '';
    const textoSinEtapa = modalLotesEl?.dataset.textoSinEtapa || '';
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

    // ===== Lista apilada: fila de lote (Código | Propiedad | Hectáreas |
    // Acciones). La propiedad va en su columna, no en una banda por grupo. Las
    // hectáreas viajan también en `data-hectareas`: de ahí las suma el aviso de
    // arriba de la tabla, igual para estas filas que para las del servidor. =====
    const crearFilaLote = (loteId, loteData, nombrePropiedad) => {
        const fila = document.createElement('div');
        fila.className = 'ag-contratos-form__lote-row';
        fila.setAttribute('data-lote-id', loteId);
        fila.setAttribute('data-hectareas', String(loteData.hectareas));

        const indiceGlobal = contadorIndiceLote++;
        const inputLoteId = crearInputHidden(`lotes[${indiceGlobal}][lote_id]`, loteId);

        const codigo = document.createElement('strong');
        codigo.className = 'ag-contratos-form__lote-code';
        codigo.textContent = loteData.codigo;

        const propiedad = document.createElement('span');
        propiedad.className = 'ag-contratos-form__lote-propiedad';
        propiedad.textContent = nombrePropiedad;

        const hectareas = document.createElement('span');
        hectareas.className = 'ag-contratos-form__lote-hectareas';
        hectareas.textContent = `${formatearHectareas(centesimas(loteData.hectareas))} ha`;

        const botonQuitar = crearBotonAccion('delete', textoQuitarLote);
        botonQuitar.setAttribute('data-ag-lote-quitar', '');
        botonQuitar.setAttribute('aria-label', textoQuitarLote);
        botonQuitar.classList.add('ag-contratos-form__lote-quitar-btn');

        const acciones = document.createElement('div');
        acciones.className = 'ag-contratos-form__lote-acciones';
        acciones.appendChild(botonQuitar);

        fila.append(inputLoteId, codigo, propiedad, hectareas, acciones);
        return fila;
    };

    const quitarGrupoDeLista = (propiedadId) => {
        const grupo = contenedorGrupos?.querySelector(`[data-ag-lote-grupo="propiedad-${propiedadId}"]`);
        grupo?.remove();
    };

    /**
     * Reconcilia los lotes marcados en el modal con la lista apilada: agrega
     * los nuevos, quita los desmarcados, y NO TOCA los que ya estaban (para
     * no perder el índice de su fila). Al final reacomoda las filas en el orden
     * de `propiedad.lotes` (natural por código): un L3 que se suma después de
     * L10 queda en su lugar, no al final.
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

            const contenedorLotes = document.createElement('div');
            contenedorLotes.className = 'ag-contratos-form__lote-group-items';
            contenedorLotes.setAttribute('data-ag-lote-contenedor', '');

            grupo.appendChild(contenedorLotes);
            contenedorGrupos.appendChild(grupo);
        }

        const contenedorLotes = grupo.querySelector('[data-ag-lote-contenedor]');

        idsSeleccionados.forEach((loteId) => {
            if (contenedorLotes.querySelector(`[data-lote-id="${loteId}"]`)) {
                return; // ya estaba: no se toca
            }
            const loteData = propiedad.lotes.find((l) => String(l.id) === loteId);
            if (loteData) {
                contenedorLotes.appendChild(crearFilaLote(loteId, loteData, propiedad.nombre));
            }
        });

        // `appendChild` de un nodo que ya está lo MUEVE: recorrer en el orden
        // del servidor deja las filas ordenadas sin recrear ninguna. Una fila
        // cuyo lote ya no figura en el catálogo se queda donde estaba, arriba.
        propiedad.lotes.forEach((lote) => {
            const fila = contenedorLotes.querySelector(`:scope > [data-lote-id="${lote.id}"]`);
            if (fila) contenedorLotes.appendChild(fila);
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

    // Paginación de la tabla de lotes (20 por página, contando de corrido a
    // través de los grupos de propiedad). Se ocultan las filas de otras páginas
    // con `hidden`, no se quitan: sus campos siguen en el formulario y se envían.
    // Al cargar, si alguna fila trae un error de validación se abre la
    // página donde está, para que no quede escondido.
    const contenedorPaginadorLotes = formulario.querySelector('[data-ag-lotes-paginador]');
    const paginadorLotes = contenedorPaginadorLotes
        ? crearPaginador(contenedorPaginadorLotes, { porPagina: LOTES_POR_PAGINA, alCambiar: () => mostrarPaginaLotes() })
        : null;
    let primeraCargaTabla = true;

    const filasDeLotes = () => Array.from(contenedorGrupos?.querySelectorAll('.ag-contratos-form__lote-row') ?? []);

    function mostrarPaginaLotes() {
        if (!paginadorLotes || !contenedorGrupos) return;

        const [desde, hasta] = paginadorLotes.rango();
        filasDeLotes().forEach((fila, indice) => {
            fila.hidden = indice < desde || indice >= hasta;
        });
    }

    const paginarTablaLotes = () => {
        if (!paginadorLotes) return;

        const filas = filasDeLotes();
        let pagina = paginadorLotes.pagina();

        if (primeraCargaTabla) {
            const conError = filas.findIndex((fila) => fila.querySelector('.ag-input__error'));
            if (conError >= 0) pagina = Math.floor(conError / LOTES_POR_PAGINA) + 1;
            primeraCargaTabla = false;
        }

        paginadorLotes.actualizar(filas.length, pagina);
        mostrarPaginaLotes();
    };

    // ===== Aviso de hectáreas: lo que suman los lotes elegidos contra las
    // hectáreas contratadas (21/9/2026). Informativo: sirve para notar que se
    // eligieron lotes de más o de menos, no impide guardar. Se suma en
    // centésimas enteras para que 0,1 + 0,2 no aparezca como 0,30000000000000004;
    // nada de esto se persiste (invariante 6: lo que vale es lo del servidor). =====
    const resumenHectareas = formulario.querySelector('[data-ag-lotes-resumen]');
    const textoResumenHectareas = resumenHectareas?.querySelector('[data-ag-lotes-resumen-texto]');
    const inputHectareasContratadas = formulario.querySelector('[name="hectareas_contratadas"]');

    const centesimas = (valor) => Math.round((Number(valor) || 0) * 100);
    const formatearHectareas = (enCentesimas) => (enCentesimas / 100)
        .toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // El contador de la cabecera de la sección («5 lotes») lo pinta el servidor
    // al cargar; acá se mantiene al día al agregar o quitar lotes.
    const contadorSeccionLotes = resumenHectareas?.closest('.ag-form-section')?.querySelector('.ag-section-head__count');

    const actualizarResumenHectareas = () => {
        if (!resumenHectareas || !textoResumenHectareas) return;

        const filas = filasDeLotes();
        if (contadorSeccionLotes) {
            contadorSeccionLotes.textContent = (resumenHectareas.dataset.textoContador || '').replace(':cantidad', String(filas.length));
        }
        resumenHectareas.toggleAttribute('hidden', filas.length === 0);
        if (filas.length === 0) return;

        const suma = filas.reduce((total, fila) => total + centesimas(fila.getAttribute('data-hectareas')), 0);
        const contratadas = centesimas(inputHectareasContratadas?.value);

        let comparacion = 'sinContratadas';
        if (contratadas > 0) {
            comparacion = suma === contratadas ? 'igual' : (suma > contratadas ? 'mas' : 'menos');
        }

        const datos = resumenHectareas.dataset;
        const plantillaLotes = filas.length === 1 ? datos.textoLotesUno : datos.textoLotesVarios;
        const plantillaComparacion = {
            sinContratadas: datos.textoSinContratadas,
            igual: datos.textoIgual,
            mas: datos.textoMas,
            menos: datos.textoMenos,
        }[comparacion];
        const plantillaResaltado = { mas: datos.textoMasResaltado, menos: datos.textoMenosResaltado }[comparacion] ?? '';

        const completar = (plantilla) => (plantilla || '')
            .replace(':cantidad', String(filas.length))
            .replace(':suma', formatearHectareas(suma))
            .replace(':contratadas', formatearHectareas(contratadas))
            .replace(':diferencia', formatearHectareas(Math.abs(suma - contratadas)));

        // Solo la diferencia («50,00 ha menos») va resaltada: la frase se parte
        // en `:resaltado` y esa parte entra como elemento, nunca como HTML.
        const [antes, despues = ''] = `${plantillaLotes} ${plantillaComparacion}`.split(':resaltado');
        textoResumenHectareas.replaceChildren(completar(antes));
        if (plantillaResaltado) {
            const resaltado = document.createElement('strong');
            resaltado.className = 'ag-contratos-form__lotes-resumen-diferencia';
            resaltado.textContent = completar(plantillaResaltado);
            textoResumenHectareas.append(resaltado, completar(despues));
        }
    };

    inputHectareasContratadas?.addEventListener('input', actualizarResumenHectareas);

    // Muestra la tabla de lotes agregados solo si hay al menos un grupo —
    // evita el cascarón vacío (solo encabezado) en un contrato nuevo.
    const actualizarVisibilidadTablaLotes = () => {
        if (!tablaLotes || !contenedorGrupos) return;
        tablaLotes.toggleAttribute('hidden', contenedorGrupos.children.length === 0);
        paginarTablaLotes();
        actualizarResumenHectareas();
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

    // «12 de 33 lotes seleccionados»: con la lista en páginas, lo marcado puede
    // estar en una que no se ve.
    function actualizarContadorModal() {
        if (!modalLotesSeleccionados) return;

        const checkboxes = modalLotesLista.querySelectorAll('[data-ag-modal-lote-checkbox]');
        const marcados = modalLotesLista.querySelectorAll('[data-ag-modal-lote-checkbox]:checked').length;

        modalLotesSeleccionados.textContent = checkboxes.length === 0
            ? ''
            : (modalLotesEl?.dataset.textoSeleccionados ?? '').replace(':cantidad', String(marcados)).replace(':total', String(checkboxes.length));
    }

    // Paginación del modal: se ocultan las filas de otras páginas (`hidden`),
    // no se quitan — lo marcado en ellas se guarda igual.
    let filasModal = [];
    const paginadorModal = contenedorPaginadorModal
        ? crearPaginador(contenedorPaginadorModal, {
            porPagina: LOTES_POR_PAGINA,
            alCambiar: () => mostrarPaginaModal(),
        })
        : null;

    function mostrarPaginaModal() {
        if (!paginadorModal) return;

        const [desde, hasta] = paginadorModal.rango();
        filasModal.forEach((fila, indice) => {
            fila.hidden = indice < desde || indice >= hasta;
        });
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
        filasModal = [];
        paginadorModal?.actualizar(0);
        actualizarContadorModal();

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
        // «Registrar siembra»: mismo viaje de ida y vuelta que «Crear lote»
        // (borrador + memento), a la siembra de ESTA propiedad y con la campaña
        // elegida en el formulario. Es donde la siembra entra en el flujo: acá se
        // decide qué lotes van juntos, y la columna «Cultivo y etapa» sale de ahí.
        // Sin permiso de editar la propiedad el Blade no manda la URL y no se dibuja.
        let accionSiembra = null;
        if (urlSiembra && (propiedad.lotes || []).length > 0) {
            accionSiembra = document.createElement('a');
            accionSiembra.href = '#';
            accionSiembra.className = 'ag-button ag-button--outline ag-button--sm';
            accionSiembra.appendChild(crearIcono('eco', 'sm', 'ag-button__icon'));
            const etiquetaSiembra = document.createElement('span');
            etiquetaSiembra.className = 'ag-button__label';
            etiquetaSiembra.textContent = textoSiembra;
            accionSiembra.appendChild(etiquetaSiembra);
            accionSiembra.addEventListener('click', (e) => {
                e.preventDefault();
                guardarBorrador();
                const campania = selectCampania?.value ? `campania_id=${encodeURIComponent(selectCampania.value)}&` : '';
                window.location.href = `${urlSiembra.replace('__PROPIEDAD__', propiedadId)}?${campania}${queryOrigen()}`;
            });
        }

        if (modalCrearLoteSlot) {
            modalCrearLoteSlot.innerHTML = '';
            if (accionSiembra) modalCrearLoteSlot.appendChild(accionSiembra);
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
            // «Todos» son los de la propiedad entera, no solo los de la página que se ve.
            checkTodos.addEventListener('change', () => {
                modalLotesLista.querySelectorAll('[data-ag-modal-lote-checkbox]').forEach((cb) => {
                    cb.checked = checkTodos.checked;
                });
                actualizarContadorModal();
            });
            const colCodigo = document.createElement('span');
            colCodigo.textContent = textoColCodigo;
            const colHectareas = document.createElement('span');
            colHectareas.textContent = textoColHectareas;
            const colDesnivel = document.createElement('span');
            colDesnivel.textContent = textoColDesnivel;
            const colLimpieza = document.createElement('span');
            colLimpieza.textContent = textoColLimpieza;
            const colCultivo = document.createElement('span');
            colCultivo.textContent = textoColCultivo;
            head.append(celdaTodos, colCodigo, colHectareas, colCultivo, colDesnivel, colLimpieza);
            tabla.appendChild(head);

            lotesVisibles.forEach((lote) => {
                const fila = document.createElement('div');
                fila.className = 'ag-contratos-form__modal-tabla-fila';

                const { celda: celdaCheck, checkbox } = crearCeldaCheckbox(false, lote.id);
                checkbox.checked = idsYaEnContrato.has(String(lote.id));
                checkbox.addEventListener('change', () => {
                    sincronizarSeleccionarTodosModal();
                    actualizarContadorModal();
                });

                const celdaCodigo = document.createElement('strong');
                celdaCodigo.textContent = lote.codigo;

                const celdaHectareas = document.createElement('span');
                celdaHectareas.textContent = `${formatearHectareas(centesimas(lote.hectareas))} ha`;

                // Cultivo y etapa del lote EN LA CAMPAÑA ELEGIDA en el formulario
                // (`siembras`, ver `ContratosController::propiedadesYLotesPorCliente()`):
                // es lo que dice qué lotes van juntos en un contrato. Sin campaña
                // elegida o sin siembra registrada, la celda queda vacía («—»).
                const siembra = (lote.siembras || {})[selectCampania?.value || ''];
                const celdaCultivo = document.createElement('span');
                celdaCultivo.textContent = siembra ? `${siembra.cultivo} · ${siembra.etapa_label || textoSinEtapa}` : '—';
                celdaCultivo.classList.toggle('ag-contratos-form__modal-tabla-vacio', !siembra);

                const celdaDesnivel = document.createElement('span');
                celdaDesnivel.textContent = lote.desnivel_label || '—';
                celdaDesnivel.classList.toggle('ag-contratos-form__modal-tabla-vacio', !lote.desnivel_label);

                const celdaLimpieza = document.createElement('span');
                celdaLimpieza.textContent = lote.limpieza_label || '—';
                celdaLimpieza.classList.toggle('ag-contratos-form__modal-tabla-vacio', !lote.limpieza_label);

                fila.append(celdaCheck, celdaCodigo, celdaHectareas, celdaCultivo, celdaDesnivel, celdaLimpieza);

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

            filasModal = Array.from(tabla.querySelectorAll('.ag-contratos-form__modal-tabla-fila'));
            paginadorModal?.actualizar(filasModal.length, 1);
            mostrarPaginaModal();
            actualizarContadorModal();
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

    // ===== Quitar y ver conflicto (delegación — cubre filas del servidor y
    // del JS con un solo listener) =====
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

    // ===== BORRADOR (guarda/restaura el formulario al salir a un alta rápida) =====
    // Los campos van por `shared/borrador-formulario.js`; acá se suma lo que no
    // es un campo: el cliente con el que se eligieron las propiedades, y qué
    // lotes de cada una estaban en la tabla. Se guarda al salir por cualquier
    // enlace de alta rápida —los del servidor y el «Crear lote» del modal.
    formulario.addEventListener('click', (e) => {
        if (e.target.closest('[data-ag-link-accent]')) guardarBorrador();
    });

    function guardarBorrador() {
        const lotesPorPropiedad = {};
        propiedadesSeleccionadas.forEach((propiedadId) => {
            lotesPorPropiedad[propiedadId] = [];
        });
        contenedorGrupos?.querySelectorAll('[data-ag-lote-grupo]').forEach((grupo) => {
            const coincidencia = grupo.getAttribute('data-ag-lote-grupo')?.match(/^propiedad-(\d+)$/);
            if (!coincidencia) return;
            lotesPorPropiedad[coincidencia[1]] = Array.from(grupo.querySelectorAll('[data-lote-id]'))
                .map((fila) => fila.getAttribute('data-lote-id'));
        });

        guardarBorradorCompartido(formulario, { clienteId: selectCliente?.value ?? '', lotesPorPropiedad });
    }

    /**
     * Devuelve a la tabla las propiedades y los lotes que tenía, si el cliente
     * sigue siendo el mismo (los de otro cliente no valen). Cada id se valida
     * contra lo que este formulario ofrece HOY: una propiedad o un lote que ya
     * no está, o que entretanto quedó comprometido en otro contrato de la
     * campaña, se ignora. `sincronizarLotesDePropiedad` no toca las filas que
     * ya vinieron del servidor (edición) y saca las que se habían quitado.
     */
    function restaurarLotesDelBorrador(extra) {
        if (!extra || typeof extra !== 'object' || !selectCliente?.value) return;
        if (String(extra.clienteId ?? '') !== selectCliente.value) return;

        const propiedades = propiedadesYLotes[selectCliente.value] || {};
        const lotesPorPropiedad = extra.lotesPorPropiedad && typeof extra.lotesPorPropiedad === 'object' ? extra.lotesPorPropiedad : {};

        // Una propiedad que el borrador ya no trae se había quitado entera.
        contenedorGrupos?.querySelectorAll('[data-ag-lote-grupo]').forEach((grupo) => {
            const coincidencia = grupo.getAttribute('data-ag-lote-grupo')?.match(/^propiedad-(\d+)$/);
            if (coincidencia && !(coincidencia[1] in lotesPorPropiedad)) grupo.remove();
        });
        propiedadesSeleccionadas.clear();

        Object.entries(lotesPorPropiedad).forEach(([propiedadId, loteIds]) => {
            const propiedad = propiedades[propiedadId];
            if (!propiedad || !Array.isArray(loteIds)) return;

            const yaEnTabla = new Set(
                Array.from(
                    contenedorGrupos?.querySelectorAll(`[data-ag-lote-grupo="propiedad-${propiedadId}"] [data-lote-id]`) || [],
                ).map((fila) => fila.getAttribute('data-lote-id')),
            );
            const validos = new Set(loteIds.map(String).filter((loteId) => {
                const lote = (propiedad.lotes || []).find((l) => String(l.id) === loteId);
                return lote && (yaEnTabla.has(loteId) || !loteOcupadoEnCampaniaActual(lote));
            }));

            propiedadesSeleccionadas.add(propiedadId);
            sincronizarLotesDePropiedad(propiedadId, propiedad, validos);
        });

        renderizarPills();
    }

    function restaurarBorrador() {
        // Leer URL primero para permitir que los query params ganen
        const urlParams = new URLSearchParams(window.location.search);
        const clienteIdUrl = urlParams.get('cliente_id');
        const propiedadIdUrl = urlParams.get('propiedad_id');
        const loteIdUrl = urlParams.get('lote_id');

        const clienteAntes = selectCliente?.value;

        // De un solo uso y propio de ESTA ruta (el alta y la edición de cada
        // contrato tienen cada una el suyo): un «Nuevo cliente» que no volvió
        // por su botón no deja un contrato a medias en la edición de OTRO.
        // `cliente_id` se avisa aparte, más abajo: mueve propiedades y pills.
        const borrador = leerBorrador();
        if (borrador) {
            restaurarCampos(formulario, borrador.campos, { silenciar: (control) => control.name === 'cliente_id' });
        }

        // El borrador pudo cambiar el cliente sin avisarle al resto del
        // formulario (propiedades, pills, etiqueta del select): se avisa acá.
        if (selectCliente && selectCliente.value !== clienteAntes) {
            selectCliente.dispatchEvent(new Event('change'));
        }

        // Con el cliente ya en su lugar (su `change` vacía las propiedades
        // elegidas), vuelven las propiedades y los lotes que había.
        if (borrador) restaurarLotesDelBorrador(borrador.extra);

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

    // Al enviar, el borrador ya no hace falta.
    formulario.addEventListener('submit', descartarBorrador);

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
