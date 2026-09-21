/**
 * Siembra de una propiedad (`propiedades/siembra.blade.php`, 21/9/2026). La
 * siembra se carga por SECTORES: un cultivo, su etapa y sus fechas, más los
 * lotes que lo comparten. Este módulo hace cuatro cosas:
 *
 * 1. Cambiar de cliente, de propiedad o de campaña recarga la pantalla (un GET
 *    nuevo). Los selects viajan como `*_ver`, que el servidor ignora: lo que
 *    se guarda es siempre la propiedad de la ruta y el `campania_id` oculto.
 * 2. Agregar y quitar sectores (clona el `<template>` de la página y avisa a
 *    los átomos `select` y `date` para que se inicialicen en el nodo nuevo).
 * 3. Elegir los lotes de un sector en el modal de la página. Un lote va en un
 *    solo sector: el modal ofrece los que siguen libres más los que el sector
 *    ya tiene. Los lotes elegidos viajan en UN campo oculto por sector, con
 *    los ids separados por coma (mil lotes como mil campos chocan con
 *    `max_input_vars` de PHP).
 * 4. Mantener a la vista cuántos lotes quedan libres.
 *
 * Pensado para propiedades de mil lotes: la selección del modal vive en un
 * `Set` y solo se dibujan las 40 casillas de la página que se ve, en cuatro
 * columnas que se leen de arriba abajo; «Marcar todos» actúa sobre todos los
 * lotes que el sector puede elegir, no solo esa página.
 *
 * Las hectáreas se suman como centésimas enteras (BigInt), nunca como
 * `Number`: son DECIMAL (invariante 6 de CLAUDE.md).
 *
 * Guard de presencia en el DOM (mismo criterio que `lotes-generar.js`): sin
 * `[data-ag-siembra-form]` este módulo no hace nada.
 */
import { crearPaginador } from '../shared/paginador-cliente.js';

const LOTES_POR_PAGINA = 40;
const FICHAS_VISIBLES = 14;

/** "60.5" → 6050n. */
function aCentesimas(texto) {
    const coincidencia = /^\s*(\d+)(?:\.(\d*))?\s*$/.exec(texto ?? '');

    return coincidencia ? BigInt(coincidencia[1]) * 100n + BigInt(`${coincidencia[2] ?? ''}00`.slice(0, 2)) : 0n;
}

/** 12550n → "125,50". */
function formatearHectareas(centesimas) {
    return (Number(centesimas) / 100).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/** Reemplaza cada `:marca` de la plantilla — sin `String.replace`, que interpreta `$&` de un valor. */
function sustituir(plantilla, valores) {
    return Object.entries(valores).reduce((texto, [marca, valor]) => texto.split(marca).join(String(valor)), plantilla ?? '');
}

/**
 * Cliente, propiedad y campaña son selects que RECARGAN la pantalla: ninguno
 * viaja con el guardado. Elegir cliente lleva a la pantalla sin propiedad, con
 * las de ese cliente cargadas; elegir propiedad, a la siembra de esa propiedad;
 * elegir campaña, a la misma propiedad en esa campaña. El cultivo desde cuya
 * ficha se llegó acompaña cada salto. El memento de «Volver» vive en la sesión:
 * no hace falta llevarlo en la URL.
 */
function inicializarDestino(formulario) {
    const cliente = formulario.querySelector('[data-ag-siembra-cliente]');
    const propiedad = formulario.querySelector('[data-ag-siembra-propiedad]');
    const campania = formulario.querySelector('[data-ag-siembra-campania]');

    const ir = (ruta, parametros) => {
        const destino = new URL(ruta, window.location.origin);

        Object.entries({ ...parametros, cultivo_id: formulario.dataset.agSiembraCultivo }).forEach(([nombre, valor]) => {
            if (valor) destino.searchParams.set(nombre, valor);
        });

        window.location.assign(destino);
    };

    const aPropiedad = (propiedadId) => ir(
        formulario.dataset.agSiembraUrlPropiedad.replace('__PROPIEDAD__', propiedadId),
        { campania_id: campania?.value },
    );

    cliente?.addEventListener('change', () => {
        if (cliente.value) ir(formulario.dataset.agSiembraUrlCliente, { cliente_id: cliente.value, campania_id: campania?.value });
    });

    propiedad?.addEventListener('change', () => {
        if (propiedad.value) aPropiedad(propiedad.value);
    });

    campania?.addEventListener('change', () => {
        if (!campania.value) return;

        if (propiedad?.value) aPropiedad(propiedad.value);
        else ir(formulario.dataset.agSiembraUrlCliente, { cliente_id: cliente?.value, campania_id: campania.value });
    });
}

function inicializarSectores(formulario) {
    const contenedor = formulario.querySelector('[data-ag-siembra-sectores]');
    const molde = formulario.querySelector('[data-ag-siembra-sector-molde]');
    const datos = formulario.querySelector('[data-ag-siembra-lotes]');

    if (!contenedor || !molde || !datos) return;

    const textos = formulario.dataset;
    const lotes = JSON.parse(datos.textContent || '[]');
    const lotePorId = new Map(lotes.map((lote) => [String(lote.id), lote]));
    const avisoLibres = formulario.querySelector('[data-ag-siembra-libres]');
    const filaAgregar = formulario.querySelector('[data-ag-siembra-sector-agregar-fila]');
    let siguienteIndice = contenedor.querySelectorAll('[data-ag-siembra-sector]').length;

    const sectores = () => Array.from(contenedor.querySelectorAll('[data-ag-siembra-sector]'));
    const campoLotes = (sector) => sector.querySelector('[data-ag-siembra-sector-lotes]');
    const idsDe = (sector) => campoLotes(sector).value.split(',').filter((id) => lotePorId.has(id));

    function pintarSector(sector, numero) {
        const ids = idsDe(sector);
        const hectareas = ids.reduce((suma, id) => suma + aCentesimas(lotePorId.get(id).hectareas), 0n);

        sector.querySelector('[data-ag-siembra-sector-titulo]').textContent = sustituir(textos.textoSector, { ':numero': numero });
        sector.querySelector('[data-ag-siembra-sector-resumen]').textContent = ids.length === 0
            ? textos.textoSectorVacio
            : sustituir(ids.length === 1 ? textos.textoSectorResumenUno : textos.textoSectorResumen, { ':cantidad': ids.length, ':hectareas': formatearHectareas(hectareas) });

        const fichas = sector.querySelector('[data-ag-siembra-sector-fichas]');
        fichas.replaceChildren(...ids.slice(0, FICHAS_VISIBLES).map((id) => {
            const ficha = document.createElement('span');
            ficha.className = 'ag-siembra-form__ficha';

            const icono = document.createElement('span');
            icono.className = 'material-symbols-rounded ag-icon ag-icon--sm';
            icono.setAttribute('aria-hidden', 'true');
            icono.textContent = textos.iconoFicha;

            ficha.append(icono, lotePorId.get(id).codigo);
            return ficha;
        }));

        if (ids.length > FICHAS_VISIBLES) {
            const mas = document.createElement('span');
            mas.className = 'ag-siembra-form__fichas-mas';
            mas.textContent = sustituir(textos.textoFichasMas, { ':cantidad': ids.length - FICHAS_VISIBLES });
            fichas.append(mas);
        }

        fichas.hidden = ids.length === 0;
    }

    function pintarTodo() {
        const actuales = sectores();
        const ocupados = actuales.reduce((total, sector) => total + idsDe(sector).length, 0);

        actuales.forEach((sector, posicion) => pintarSector(sector, posicion + 1));

        if (avisoLibres) {
            avisoLibres.textContent = sustituir(textos.textoLibres, { ':libres': lotes.length - ocupados, ':total': lotes.length });
        }

        // Sin lotes libres no hay nada que poner en otro sector.
        if (filaAgregar) {
            filaAgregar.hidden = ocupados >= lotes.length;
        }
    }

    function agregarSector() {
        const envoltorio = document.createElement('div');
        envoltorio.innerHTML = molde.innerHTML.replace(/__INDICE__/g, String(siguienteIndice));
        siguienteIndice += 1;

        const sector = envoltorio.firstElementChild;
        contenedor.append(sector);
        sector.dispatchEvent(new CustomEvent('ag:select:inicializar', { bubbles: true }));
        sector.dispatchEvent(new CustomEvent('ag:date:inicializar', { bubbles: true }));
        pintarTodo();
        sector.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }

    const modal = crearModalLotes({
        lotes,
        /** Los lotes que ya están en OTRO sector no se ofrecen. */
        disponiblesPara: (sector) => {
            const ajenos = new Set(sectores().filter((otro) => otro !== sector).flatMap(idsDe));
            return lotes.filter((lote) => !ajenos.has(String(lote.id)));
        },
    });

    contenedor.addEventListener('click', (evento) => {
        const sector = evento.target.closest('[data-ag-siembra-sector]');
        if (!sector) return;

        if (evento.target.closest('[data-ag-siembra-sector-quitar]')) {
            sector.remove();
            // Un click sobre un nodo que ya no está no le llega al formulario:
            // la barra de acciones se entera por este `change`.
            formulario.dispatchEvent(new Event('change', { bubbles: true }));
            pintarTodo();
            return;
        }

        if (evento.target.closest('[data-ag-siembra-sector-elegir]')) {
            const numero = sectores().indexOf(sector) + 1;

            modal?.abrir({
                numero,
                disponibles: modal.disponiblesPara(sector),
                elegidos: idsDe(sector),
                alGuardar: (ids) => {
                    campoLotes(sector).value = ids.join(',');
                    formulario.dispatchEvent(new Event('change', { bubbles: true }));
                    pintarTodo();
                },
            });
        }
    });

    formulario.querySelector('[data-ag-siembra-sector-agregar]')?.addEventListener('click', agregarSector);
    pintarTodo();
}

/**
 * El modal «Elegir lotes». `abrir()` recibe qué lotes puede elegir el sector y
 * cuáles ya tiene; «Guardar selección» devuelve los ids elegidos EN EL ORDEN
 * del catálogo (el natural: L1, L2, … L10), no en el que se marcaron.
 */
function crearModalLotes({ lotes, disponiblesPara }) {
    const raiz = document.querySelector('[data-ag-siembra-modal]');
    if (!raiz || !window.bootstrap?.Modal) return null;

    const textos = raiz.dataset;
    const titulo = raiz.querySelector('[data-ag-siembra-modal-titulo]');
    const todos = raiz.querySelector('[data-ag-siembra-modal-todos]');
    const grilla = raiz.querySelector('[data-ag-siembra-modal-grilla]');
    const agotado = raiz.querySelector('[data-ag-siembra-modal-agotado]');
    const herramientas = raiz.querySelector('.ag-siembra-modal__herramientas');
    const seleccionados = raiz.querySelector('[data-ag-siembra-modal-seleccionados]');
    const molde = raiz.querySelector('[data-ag-siembra-modal-molde]');
    const ventana = window.bootstrap.Modal.getOrCreateInstance(raiz);

    // `visibles` es lo que el modal ofrece: hoy, todos los disponibles.
    let disponibles = [];
    let visibles = [];
    let elegidos = new Set();
    let alGuardar = () => {};
    let ultimoMarcado = null;

    const paginador = crearPaginador(raiz.querySelector('[data-ag-siembra-modal-paginador]'), {
        porPagina: LOTES_POR_PAGINA,
        alCambiar: () => pintarPagina(),
    });

    function pintarContador() {
        seleccionados.textContent = sustituir(textos.textoSeleccionados, { ':cantidad': elegidos.size, ':total': disponibles.length });
        todos.checked = visibles.length > 0 && visibles.every((lote) => elegidos.has(String(lote.id)));
        todos.indeterminate = !todos.checked && visibles.some((lote) => elegidos.has(String(lote.id)));
    }

    function pintarPagina() {
        const [desde, hasta] = paginador.rango();
        const pagina = visibles.slice(desde, hasta);

        // Columnas que se leen de arriba abajo (`grid-auto-flow: column`): los
        // lotes de la página se reparten en partes iguales, y la primera columna
        // trae los primeros. Cuántas columnas hay lo dice el CSS (4, o 2 en móvil).
        const columnas = Number(getComputedStyle(grilla).getPropertyValue('--ag-siembra-columnas')) || 4;
        grilla.style.setProperty('--ag-siembra-filas', String(Math.max(1, Math.ceil(pagina.length / columnas))));

        grilla.replaceChildren(...pagina.map((lote) => {
            const id = String(lote.id);
            const envoltorio = document.createElement('div');
            envoltorio.innerHTML = molde.innerHTML.replace(/__ID__/g, id);

            const ficha = envoltorio.firstElementChild;
            const casilla = ficha.querySelector('[data-ag-siembra-modal-lote]');
            const etiqueta = ficha.querySelector('.ag-checkbox__label');

            etiqueta.textContent = lote.codigo;
            const hectareas = document.createElement('span');
            hectareas.className = 'ag-siembra-modal__lote-hectareas';
            hectareas.textContent = sustituir(textos.textoHectareas, { ':cantidad': formatearHectareas(aCentesimas(lote.hectareas)) });
            etiqueta.append(hectareas);

            casilla.value = id;
            casilla.checked = elegidos.has(id);
            return ficha;
        }));

        pintarContador();
    }

    function pintarDesdeElPrincipio() {
        visibles = disponibles;

        const sinNadaQueElegir = disponibles.length === 0;
        agotado.hidden = !sinNadaQueElegir;
        herramientas.hidden = sinNadaQueElegir;
        grilla.hidden = sinNadaQueElegir;
        ultimoMarcado = null;

        paginador.actualizar(visibles.length, 1);
        pintarPagina();
    }

    // Mayús + clic marca (o desmarca) el tramo entre el lote anterior y este,
    // aunque cruce de página.
    // El clic cae en el rótulo y el navegador lo repite sobre la casilla, a
    // veces sin la tecla: la Mayús se anota en el primero y se usa en el segundo.
    let mayusPendiente = false;

    grilla.addEventListener('click', (evento) => {
        const casilla = evento.target.closest('[data-ag-siembra-modal-lote]');
        if (!casilla) {
            mayusPendiente = evento.shiftKey;
            return;
        }

        const conMayus = evento.shiftKey || mayusPendiente;
        mayusPendiente = false;
        const posicion = visibles.findIndex((lote) => String(lote.id) === casilla.value);

        if (conMayus && ultimoMarcado !== null && ultimoMarcado !== posicion) {
            const [desde, hasta] = [Math.min(ultimoMarcado, posicion), Math.max(ultimoMarcado, posicion)];
            visibles.slice(desde, hasta + 1).forEach((lote) => {
                if (casilla.checked) elegidos.add(String(lote.id));
                else elegidos.delete(String(lote.id));
            });
            ultimoMarcado = posicion;
            pintarPagina();
            return;
        }

        if (casilla.checked) elegidos.add(casilla.value);
        else elegidos.delete(casilla.value);

        ultimoMarcado = posicion;
        pintarContador();
    });

    todos.addEventListener('change', () => {
        visibles.forEach((lote) => {
            if (todos.checked) elegidos.add(String(lote.id));
            else elegidos.delete(String(lote.id));
        });
        pintarPagina();
    });

    raiz.querySelector('[data-ag-siembra-modal-guardar]').addEventListener('click', () => {
        alGuardar(lotes.map((lote) => String(lote.id)).filter((id) => elegidos.has(id)));
        ventana.hide();
    });

    return {
        disponiblesPara,
        abrir({ numero, disponibles: ofrecidos, elegidos: actuales, alGuardar: devolver }) {
            disponibles = ofrecidos;
            elegidos = new Set(actuales);
            alGuardar = devolver;
            titulo.textContent = sustituir(textos.textoTitulo, { ':numero': numero });
            pintarDesdeElPrincipio();
            ventana.show();
        },
    };
}

document.addEventListener('DOMContentLoaded', () => {
    const formulario = document.querySelector('[data-ag-siembra-form]');

    if (!formulario) return;

    inicializarDestino(formulario);
    inicializarSectores(formulario);
});
