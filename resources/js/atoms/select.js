// Comportamiento del átomo `select` (resources/views/components/atoms/select.blade.php).
// Progressive enhancement: el <select> nativo es el control real (name,
// required, value, envío del formulario); este script arma al lado un
// combobox accesible y lo oculta visualmente. Si este archivo no carga, el
// nativo queda como único control — visible y usable.
//
// Un solo elemento enfocable durante toda la interacción: el
// `[data-ag-select-trigger]` (role="combobox"). Nunca se mueve el foco a un
// hijo, así `aria-activedescendant` siempre tiene un dueño inequívoco. El
// texto tipeado (búsqueda o type-ahead) se captura por `keydown` sobre ese
// mismo elemento y se refleja en una fila decorativa dentro del listbox
// (buscable) o simplemente mueve la opción activa (no buscable).
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

    const buscable = root.dataset.agSelectBuscable === '1';
    const etiquetaBuscar = root.dataset.labelSearch || '';
    const etiquetaSinResultados = root.dataset.labelNoResults || '';
    const etiquetaLimpiar = root.dataset.labelClear || '';
    const etiquetaPlaceholder = root.dataset.labelPlaceholder || '';
    const idPrefijoOpcion = `${listbox.id}-opt-`;

    let opciones = [];
    let filtradas = [];
    let indiceActivo = -1;
    let abierto = false;
    let buffer = '';
    let temporizadorBuffer = null;

    function leerOpciones() {
        opciones = Array.from(nativo.querySelectorAll('option'))
            .filter((opt) => !opt.disabled)
            .map((opt) => ({ value: opt.value, label: opt.textContent ?? '' }));
    }

    function etiquetaSeleccionActual() {
        const seleccionada = opciones.find((opt) => opt.value === nativo.value);
        return seleccionada ? seleccionada.label : null;
    }

    function actualizarValorMostrado() {
        const etiqueta = etiquetaSeleccionActual();
        valorSpan.textContent = etiqueta ?? etiquetaPlaceholder;
        valorSpan.classList.toggle('ag-select__value--placeholder', !etiqueta);

        const puedeLimpiar = Boolean(etiqueta) && !nativo.required;
        if (botonLimpiar) {
            botonLimpiar.hidden = !puedeLimpiar;
            botonLimpiar.setAttribute('aria-hidden', puedeLimpiar ? 'false' : 'true');
            botonLimpiar.tabIndex = -1;
        }
    }

    function actualizarActiveDescendant() {
        if (indiceActivo >= 0 && filtradas[indiceActivo]) {
            trigger.setAttribute('aria-activedescendant', `${idPrefijoOpcion}${indiceActivo}`);
        } else {
            trigger.removeAttribute('aria-activedescendant');
        }
    }

    function pintarListbox() {
        listbox.innerHTML = '';

        if (buscable) {
            const fila = document.createElement('li');
            fila.className = 'ag-select__search-row';
            fila.setAttribute('role', 'presentation');
            fila.append(crearIcono('search'));
            const texto = document.createElement('span');
            texto.textContent = buffer || etiquetaBuscar;
            fila.append(texto);
            listbox.append(fila);
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

        const termino = normalizar(buffer);
        filtradas = termino === '' ? opciones : opciones.filter((opt) => normalizar(opt.label).includes(termino));
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
        pintarListbox();
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
        listbox.hidden = true;
        listbox.innerHTML = '';
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
        if (nativo.required || nativo.value === '') {
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
        const indice = opciones.findIndex((opt) => normalizar(opt.label).startsWith(termino));

        if (indice === -1) {
            return;
        }

        indiceActivo = indice;
        pintarListbox();
    }

    function manejarTipeo(caracter) {
        if (buscable) {
            buffer += caracter;
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
                if (buscable && abierto && buffer.length > 0) {
                    evento.preventDefault();
                    buffer = buffer.slice(0, -1);
                    filtrar();
                    pintarListbox();
                } else if (!abierto) {
                    limpiar();
                }
                return;
            default:
                if (evento.key.length === 1 && !evento.ctrlKey && !evento.altKey && !evento.metaKey) {
                    if (!abierto) {
                        abrir();
                    }
                    manejarTipeo(evento.key);
                }
        }
    });

    trigger.addEventListener('focusout', (evento) => {
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

    // Selects dependientes (p. ej. campaña según cliente en contratos-form.js,
    // subrubro según rubro en gastos-form.js) filtran el nativo marcando
    // `<option>` disabled/hidden desde afuera. `opciones` es una foto tomada
    // acá arriba: sin este observer, esa foto queda vieja y el combobox
    // mostraría opciones que el formulario ya descartó.
    const observador = new MutationObserver(() => {
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
