// Comportamiento del átomo `select` (resources/views/components/atoms/select.blade.php).
// Progressive enhancement: el <select> nativo es el control real (name,
// required, value, envío del formulario); este script arma al lado un
// combobox accesible y lo oculta visualmente. Si este archivo no carga, el
// nativo queda como único control — visible y usable.
//
// Select NO buscable (8 opciones o menos): un solo elemento enfocable durante
// toda la interacción, el `[data-ag-select-trigger]` (role="combobox"); el
// texto tipeado (type-ahead) se captura por `keydown` sobre ese elemento y
// mueve la opción activa.
//
// Select BUSCABLE: al abrir, el foco pasa a un `<input>` real dentro del
// desplegable (19/9/2026). Antes era una fila decorativa sin foco: pulsarla no
// hacía nada, el placeholder no se iba, no había teclado en el celular y ni el
// pegado ni los acentos compuestos entraban. Ese input es el dueño de
// `aria-activedescendant` mientras el desplegable está abierto; al cerrar, el
// foco vuelve al trigger.
//
// Sin dependencias externas — mismo criterio que atoms/input.js.

const UMBRAL_REINICIO_TYPEAHEAD_MS = 500;

function normalizar(texto) {
    return texto
        .toLocaleLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();
}

// Búsqueda por PALABRAS (19/9/2026): cada palabra escrita tiene que aparecer en
// la etiqueta, en cualquier orden y posición — "cotoca santa" encuentra
// "Cotoca - Santa Cruz". Sin distinguir mayúsculas ni acentos (`normalizar`).
function palabrasDeBusqueda(texto) {
    return normalizar(texto).split(/\s+/).filter(Boolean);
}

function coincide(etiquetaNormalizada, palabras) {
    return palabras.every((palabra) => etiquetaNormalizada.includes(palabra));
}

function crearIcono(nombre, claseExtra) {
    const icono = document.createElement('span');
    icono.className = `material-symbols-rounded ag-icon ag-icon--sm${claseExtra ? ` ${claseExtra}` : ''}`;
    icono.setAttribute('aria-hidden', 'true');
    icono.textContent = nombre;
    return icono;
}

function inicializar(root) {
    const nativo = root.querySelector('.ag-select__native');
    const trigger = root.querySelector('[data-ag-select-trigger]');
    const valorSpan = trigger?.querySelector('[data-ag-select-value]');
    const botonLimpiar = trigger?.querySelector('[data-ag-select-clear]');
    const listbox = root.querySelector('[data-ag-select-listbox]');

    if (!nativo || !trigger || !valorSpan || !listbox) {
        return;
    }

    // Un mismo select no se arma dos veces (ver `ag:select:inicializar` abajo).
    if (root.dataset.agSelectListo === '1') {
        return;
    }
    root.dataset.agSelectListo = '1';

    const buscable = root.dataset.agSelectBuscable === '1';
    const etiquetaBuscar = root.dataset.labelSearch || '';
    const etiquetaSinResultados = root.dataset.labelNoResults || '';
    const etiquetaLimpiar = root.dataset.labelClear || '';
    const etiquetaPlaceholder = root.dataset.labelPlaceholder || '';
    const idPrefijoOpcion = `${listbox.id}-opt-`;

    // Fila de búsqueda: se crea UNA vez y se conserva entre repintados de las
    // opciones — si se reconstruyera en cada tecla, el input perdería el foco.
    let filaBusqueda = null;
    let inputBusqueda = null;

    if (buscable) {
        filaBusqueda = document.createElement('li');
        filaBusqueda.className = 'ag-select__search-row';
        filaBusqueda.setAttribute('role', 'presentation');
        filaBusqueda.append(crearIcono('search'));

        inputBusqueda = document.createElement('input');
        inputBusqueda.type = 'text';
        inputBusqueda.className = 'ag-select__search-input';
        inputBusqueda.placeholder = etiquetaBuscar;
        inputBusqueda.autocomplete = 'off';
        inputBusqueda.spellcheck = false;
        inputBusqueda.setAttribute('role', 'searchbox');
        inputBusqueda.setAttribute('aria-label', etiquetaBuscar);
        inputBusqueda.setAttribute('aria-controls', listbox.id);
        inputBusqueda.setAttribute('aria-autocomplete', 'list');
        filaBusqueda.append(inputBusqueda);

        // Un clic en el ícono o en el margen de la fila no debe sacar el foco
        // del input: el `focusout` lo tomaría como "salió del combobox" y
        // cerraría el desplegable.
        filaBusqueda.addEventListener('mousedown', (evento) => {
            if (evento.target !== inputBusqueda) {
                evento.preventDefault();
                inputBusqueda.focus();
            }
        });
    }

    let opciones = [];
    let filtradas = [];
    let indiceActivo = -1;
    let abierto = false;
    let buffer = '';
    let temporizadorBuffer = null;

    function leerOpciones() {
        opciones = Array.from(nativo.querySelectorAll('option'))
            .filter((opt) => !opt.disabled)
            .map((opt) => {
                const label = opt.textContent ?? '';

                // `normalizada` se calcula acá, una sola vez: con ~365 municipios
                // normalizar en cada tecla sería trabajo repetido.
                return { value: opt.value, label, normalizada: normalizar(label) };
            });
    }

    function etiquetaSeleccionActual() {
        const seleccionada = opciones.find((opt) => opt.value === nativo.value);
        return seleccionada ? seleccionada.label : null;
    }

    // Un select obligatorio no ofrece la «×»: vaciarlo lo dejaría inválido. Salvo
    // que la página lo pida con `data-ag-select-clearable` — un obligatorio donde
    // quitar lo elegido es parte de la carga (la cuadrilla del Equipo 1 de la
    // Orden de Trabajo libera esa cuadrilla para otro equipo).
    function seLimpia() {
        return !nativo.required || nativo.dataset.agSelectClearable !== undefined;
    }

    function actualizarValorMostrado() {
        const etiqueta = etiquetaSeleccionActual();
        valorSpan.textContent = etiqueta ?? etiquetaPlaceholder;
        valorSpan.classList.toggle('ag-select__value--placeholder', !etiqueta);

        const puedeLimpiar = Boolean(etiqueta) && seLimpia();
        if (botonLimpiar) {
            botonLimpiar.hidden = !puedeLimpiar;
            botonLimpiar.setAttribute('aria-hidden', puedeLimpiar ? 'false' : 'true');
            botonLimpiar.tabIndex = -1;
        }
    }

    function actualizarActiveDescendant() {
        // Lo tiene quien tiene el foco: el input de búsqueda si hay, si no el trigger.
        const dueno = inputBusqueda ?? trigger;

        if (indiceActivo >= 0 && filtradas[indiceActivo]) {
            dueno.setAttribute('aria-activedescendant', `${idPrefijoOpcion}${indiceActivo}`);
        } else {
            dueno.removeAttribute('aria-activedescendant');
        }
    }

    // Quita las opciones pintadas, conservando la fila de búsqueda (y su foco).
    function limpiarOpciones() {
        Array.from(listbox.children).forEach((hijo) => {
            if (hijo !== filaBusqueda) {
                hijo.remove();
            }
        });
    }

    function pintarListbox() {
        limpiarOpciones();

        if (filaBusqueda && filaBusqueda.parentNode !== listbox) {
            listbox.prepend(filaBusqueda);
        }

        if (filtradas.length === 0) {
            const vacio = document.createElement('li');
            vacio.className = 'ag-select__empty';
            vacio.setAttribute('role', 'presentation');
            vacio.textContent = etiquetaSinResultados;
            listbox.append(vacio);
            actualizarActiveDescendant();
            return;
        }

        filtradas.forEach((opt, indice) => {
            const li = document.createElement('li');
            const seleccionada = opt.value === nativo.value;
            li.id = `${idPrefijoOpcion}${indice}`;
            li.setAttribute('role', 'option');
            li.setAttribute('aria-selected', seleccionada ? 'true' : 'false');
            li.dataset.indice = String(indice);
            li.className = [
                'ag-select__option',
                indice === indiceActivo ? 'ag-select__option--active' : '',
                seleccionada ? 'ag-select__option--selected' : '',
            ]
                .filter(Boolean)
                .join(' ');

            const texto = document.createElement('span');
            texto.textContent = opt.label;
            li.append(texto);

            if (seleccionada) {
                li.append(crearIcono('check', 'ag-select__option-check'));
            }

            li.addEventListener('mousedown', (evento) => {
                evento.preventDefault();
                seleccionar(opt);
            });

            listbox.append(li);
        });

        actualizarActiveDescendant();

        const activo = filtradas[indiceActivo] ? listbox.querySelector(`#${CSS.escape(`${idPrefijoOpcion}${indiceActivo}`)}`) : null;
        activo?.scrollIntoView({ block: 'nearest' });
    }

    function filtrar() {
        if (!buscable) {
            filtradas = opciones;
            return;
        }

        const palabras = palabrasDeBusqueda(buffer);
        filtradas = palabras.length === 0 ? opciones : opciones.filter((opt) => coincide(opt.normalizada, palabras));
        indiceActivo = filtradas.length > 0 ? 0 : -1;
    }

    function abrir() {
        if (nativo.disabled || abierto) {
            return;
        }

        abierto = true;
        buffer = '';
        filtrar();

        if (!buscable) {
            const actual = opciones.findIndex((opt) => opt.value === nativo.value);
            indiceActivo = actual >= 0 ? actual : filtradas.length > 0 ? 0 : -1;
        }

        trigger.setAttribute('aria-expanded', 'true');
        listbox.hidden = false;

        if (inputBusqueda) {
            inputBusqueda.value = '';
        }

        pintarListbox();
        inputBusqueda?.focus({ preventScroll: true });
    }

    function cerrar() {
        if (!abierto) {
            return;
        }

        abierto = false;
        buffer = '';
        indiceActivo = -1;
        trigger.setAttribute('aria-expanded', 'false');
        trigger.removeAttribute('aria-activedescendant');
        inputBusqueda?.removeAttribute('aria-activedescendant');
        listbox.hidden = true;
        limpiarOpciones();

        if (inputBusqueda) {
            inputBusqueda.value = '';
        }
    }

    function seleccionar(opcion) {
        if (nativo.value !== opcion.value) {
            nativo.value = opcion.value;
            nativo.dispatchEvent(new Event('change', { bubbles: true }));
        }

        actualizarValorMostrado();
        cerrar();
        trigger.focus();
    }

    function limpiar() {
        if (!seLimpia() || nativo.value === '') {
            return;
        }

        nativo.value = '';
        nativo.dispatchEvent(new Event('change', { bubbles: true }));
        actualizarValorMostrado();
        cerrar();
        trigger.focus();
    }

    function moverActivo(delta) {
        if (filtradas.length === 0) {
            return;
        }

        const siguiente = Math.min(Math.max(indiceActivo + delta, 0), filtradas.length - 1);
        if (siguiente === indiceActivo) {
            return;
        }

        indiceActivo = siguiente;
        pintarListbox();
    }

    function saltarAPrimeraCoincidencia(caracterBuffer) {
        const termino = normalizar(caracterBuffer);
        const indice = opciones.findIndex((opt) => opt.normalizada.startsWith(termino));

        if (indice === -1) {
            return;
        }

        indiceActivo = indice;
        pintarListbox();
    }

    function manejarTipeo(caracter) {
        if (buscable) {
            // `abrir()` ya pasó el foco al input: el carácter que abrió el
            // desplegable se vuelca ahí como primer texto de la búsqueda.
            inputBusqueda.value += caracter;
            buffer = inputBusqueda.value;
            filtrar();
            pintarListbox();
            return;
        }

        clearTimeout(temporizadorBuffer);
        buffer += caracter;
        temporizadorBuffer = setTimeout(() => {
            buffer = '';
        }, UMBRAL_REINICIO_TYPEAHEAD_MS);
        saltarAPrimeraCoincidencia(buffer);
    }

    trigger.addEventListener('click', () => {
        if (nativo.disabled) {
            return;
        }

        if (abierto) {
            cerrar();
        } else {
            abrir();
        }
    });

    trigger.addEventListener('keydown', (evento) => {
        if (nativo.disabled) {
            return;
        }

        switch (evento.key) {
            case 'ArrowDown':
                evento.preventDefault();
                abierto ? moverActivo(1) : abrir();
                return;
            case 'ArrowUp':
                evento.preventDefault();
                abierto ? moverActivo(-1) : abrir();
                return;
            case 'Home':
                if (abierto) {
                    evento.preventDefault();
                    indiceActivo = filtradas.length > 0 ? 0 : -1;
                    pintarListbox();
                }
                return;
            case 'End':
                if (abierto) {
                    evento.preventDefault();
                    indiceActivo = filtradas.length > 0 ? filtradas.length - 1 : -1;
                    pintarListbox();
                }
                return;
            case 'Enter':
                evento.preventDefault();
                if (!abierto) {
                    abrir();
                } else if (indiceActivo >= 0 && filtradas[indiceActivo]) {
                    seleccionar(filtradas[indiceActivo]);
                } else {
                    cerrar();
                }
                return;
            case ' ':
                if (buscable && abierto) {
                    manejarTipeo(' ');
                    evento.preventDefault();
                    return;
                }
                evento.preventDefault();
                if (!abierto) {
                    abrir();
                } else if (indiceActivo >= 0 && filtradas[indiceActivo]) {
                    seleccionar(filtradas[indiceActivo]);
                }
                return;
            case 'Escape':
                if (abierto) {
                    evento.preventDefault();
                    cerrar();
                }
                return;
            case 'Tab':
                cerrar();
                return;
            case 'Backspace':
            case 'Delete':
                // Con el desplegable abierto y búsqueda, el foco está en el input
                // y borra por su cuenta; acá solo llega el trigger.
                if (!abierto) {
                    limpiar();
                }
                return;
            default:
                if (evento.key.length === 1 && !evento.ctrlKey && !evento.altKey && !evento.metaKey) {
                    if (buscable) {
                        // El foco se muda al input dentro de `abrir()`: sin esto el
                        // carácter se insertaría ahí por segunda vez.
                        evento.preventDefault();
                    }

                    if (!abierto) {
                        abrir();
                    }
                    manejarTipeo(evento.key);
                }
        }
    });

    if (inputBusqueda) {
        inputBusqueda.addEventListener('input', () => {
            buffer = inputBusqueda.value;
            filtrar();
            pintarListbox();
        });

        // Home/End quedan para el cursor del texto; el resto navega la lista.
        inputBusqueda.addEventListener('keydown', (evento) => {
            switch (evento.key) {
                case 'ArrowDown':
                    evento.preventDefault();
                    moverActivo(1);
                    return;
                case 'ArrowUp':
                    evento.preventDefault();
                    moverActivo(-1);
                    return;
                case 'Enter':
                    // Sin `preventDefault` el Enter enviaría el formulario entero.
                    evento.preventDefault();
                    if (indiceActivo >= 0 && filtradas[indiceActivo]) {
                        seleccionar(filtradas[indiceActivo]);
                    } else {
                        cerrar();
                    }
                    return;
                case 'Escape':
                    evento.preventDefault();
                    cerrar();
                    trigger.focus();
                    return;
                case 'Tab':
                    cerrar();
            }
        });
    }

    // En la raíz y no en el trigger: con búsqueda el foco vive en el input del
    // desplegable, y sus `focusout` no pasan por el trigger.
    root.addEventListener('focusout', (evento) => {
        if (!root.contains(evento.relatedTarget)) {
            cerrar();
        }
    });

    document.addEventListener('click', (evento) => {
        if (abierto && !root.contains(evento.target)) {
            cerrar();
        }
    });

    botonLimpiar?.addEventListener('click', (evento) => {
        evento.stopPropagation();
        limpiar();
    });

    if (etiquetaLimpiar && botonLimpiar) {
        botonLimpiar.setAttribute('aria-label', etiquetaLimpiar);
    }

    leerOpciones();
    nativo.classList.add('ag-select__native--enhanced');
    nativo.setAttribute('aria-hidden', 'true');
    nativo.tabIndex = -1;
    trigger.hidden = false;
    actualizarValorMostrado();

    // Selección hecha por otro script (`nativo.value = ...` + `change`, p. ej.
    // `contratos-form.js` al volver de crear un cliente): sin esto la etiqueta
    // visible se quedaba con el valor anterior. La selección que hace este
    // mismo combobox ya la refresca arriba; repetirlo acá es inofensivo.
    nativo.addEventListener('change', actualizarValorMostrado);

    // Selects dependientes (p. ej. campaña según cliente en contratos-form.js,
    // subrubro según rubro en gastos-form.js) filtran el nativo marcando
    // `<option>` disabled/hidden desde afuera. `opciones` es una foto tomada
    // acá arriba: sin este observer, esa foto queda vieja y el combobox
    // mostraría opciones que el formulario ya descartó.
    //
    // Safeguard (tarea "contratos-lotes"): cuando JS externo modifica el select
    // (p. ej. limpia innerHTML y agrega opciones nuevas), el observer dispara
    // múltiples veces. Nos aseguramos de que el nativo SIEMPRE mantenga la clase
    // que lo hace invisible (opacity: 0), incluso si algo lo quita por error.
    const observador = new MutationObserver(() => {
        if (!nativo.classList.contains('ag-select__native--enhanced')) {
            nativo.classList.add('ag-select__native--enhanced');
        }
        leerOpciones();
        actualizarValorMostrado();
        if (abierto) {
            filtrar();
            pintarListbox();
        }
    });
    observador.observe(nativo, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled', 'hidden'] });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-ag-select]').forEach(inicializar);
});

// Selects que llegan DESPUÉS de la carga — una fila clonada de un `<template>`
// (p. ej. «Agregar lote» en `ordenes-trabajo-form.js`). Quien la inserta avisa
// con este evento sobre el nodo nuevo; sin eso el select quedaba nativo, sin
// buscador y con otro aspecto que el resto del formulario.
document.addEventListener('ag:select:inicializar', (evento) => {
    const nodo = evento.target;
    if (!(nodo instanceof Element)) {
        return;
    }
    if (nodo.matches('[data-ag-select]')) {
        inicializar(nodo);
    }
    nodo.querySelectorAll('[data-ag-select]').forEach(inicializar);
});
