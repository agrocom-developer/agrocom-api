// Comportamiento del átomo `date` (resources/views/components/atoms/date.blade.php).
// Progressive enhancement: el <input type="date"> nativo es el control real
// (name, required, min, max, value, envío del formulario); este script arma
// al lado un botón-disparador + diálogo con calendario propio y oculta
// visualmente el nativo. Si este archivo no carga, el nativo (con el
// selector del navegador) queda como único control — visible y usable.
//
// Patrón WAI-ARIA APG "Date Picker Dialog": diálogo modal (role="dialog",
// aria-modal) que contiene una grilla (role="grid") con foco itinerante
// ("roving tabindex") — un único día por vez lleva tabindex="0", el resto
// -1, y la navegación con flechas mueve el foco DOM real (no hay
// aria-activedescendant acá, a diferencia de atoms/select: con una grilla de
// 2 dimensiones el foco real es más simple de razonar que simularlo).
//
// Todas las fechas se manejan como `Date` construidos con
// `new Date(anio, mesIndice0, dia)` (medianoche LOCAL) — nunca
// `new Date('YYYY-MM-DD')`, que Date interpreta como UTC y puede correrse un
// día al formatear en una zona horaria con offset negativo. Es el único
// punto de contacto con la API de fechas de JS; todo lo demás son
// operaciones sobre esos objetos o strings ISO.
//
// Sin dependencias externas — mismo criterio que atoms/input.js y atoms/select.js.

function pad(numero) {
    return String(numero).padStart(2, '0');
}

function hoy() {
    const ahora = new Date();
    return new Date(ahora.getFullYear(), ahora.getMonth(), ahora.getDate());
}

function parsearISO(iso) {
    if (!iso) {
        return null;
    }

    const partes = iso.split('-');
    if (partes.length !== 3) {
        return null;
    }

    const [anio, mes, dia] = partes.map(Number);
    return new Date(anio, mes - 1, dia);
}

function formatearISO(fecha) {
    return `${fecha.getFullYear()}-${pad(fecha.getMonth() + 1)}-${pad(fecha.getDate())}`;
}

function formatearVisual(fecha) {
    return `${pad(fecha.getDate())}/${pad(fecha.getMonth() + 1)}/${fecha.getFullYear()}`;
}

function mismoDia(a, b) {
    return Boolean(a) && Boolean(b)
        && a.getFullYear() === b.getFullYear()
        && a.getMonth() === b.getMonth()
        && a.getDate() === b.getDate();
}

function sumarDias(fecha, delta) {
    return new Date(fecha.getFullYear(), fecha.getMonth(), fecha.getDate() + delta);
}

function sumarMeses(fecha, delta) {
    const diaObjetivo = fecha.getDate();
    const base = new Date(fecha.getFullYear(), fecha.getMonth() + delta, 1);
    const diasEnDestino = new Date(base.getFullYear(), base.getMonth() + 1, 0).getDate();
    return new Date(base.getFullYear(), base.getMonth(), Math.min(diaObjetivo, diasEnDestino));
}

// Domingo=0 en JS; el panel arranca la semana en lunes, así que la columna 0
// de la grilla es el lunes. Único lugar donde se hace esta conversión.
function indiceColumnaLunes(fecha) {
    return (fecha.getDay() + 6) % 7;
}

function inicializar(root) {
    const nativo = root.querySelector('.ag-date__native');
    const trigger = root.querySelector('[data-ag-date-trigger]');
    const valorSpan = trigger?.querySelector('[data-ag-date-value]');
    const botonLimpiar = root.querySelector('[data-ag-date-clear]');
    const dialogo = root.querySelector('[data-ag-date-dialog]');
    const heading = root.querySelector('[data-ag-date-heading]');
    const grid = root.querySelector('[data-ag-date-grid]');
    const botonPrevMes = root.querySelector('[data-ag-date-prev-month]');
    const botonNextMes = root.querySelector('[data-ag-date-next-month]');
    const botonHoy = root.querySelector('[data-ag-date-today]');

    if (!nativo || !trigger || !valorSpan || !dialogo || !heading || !grid || !botonPrevMes || !botonNextMes) {
        return;
    }

    const meses = (root.dataset.labelMeses || '').split(',');
    const diasCortos = (root.dataset.labelDias || '').split(',');
    const diasCompletos = (root.dataset.labelDiasCompletos || '').split(',');
    const etiquetaPlaceholder = root.dataset.labelPlaceholder || '';

    let abierto = false;
    let vistaAnio;
    let vistaMes;
    let enfocado = null;

    function minimo() {
        return nativo.min ? parsearISO(nativo.min) : null;
    }

    function maximo() {
        return nativo.max ? parsearISO(nativo.max) : null;
    }

    function valorActual() {
        return nativo.value ? parsearISO(nativo.value) : null;
    }

    function limitarRango(fecha) {
        const min = minimo();
        const max = maximo();
        if (min && fecha < min) {
            return min;
        }
        if (max && fecha > max) {
            return max;
        }
        return fecha;
    }

    function fueraDeRango(fecha) {
        const min = minimo();
        const max = maximo();
        return (min && fecha < min) || (max && fecha > max);
    }

    function actualizarValorMostrado() {
        const actual = valorActual();
        valorSpan.textContent = actual ? formatearVisual(actual) : etiquetaPlaceholder;
        valorSpan.classList.toggle('ag-date__value--placeholder', !actual);

        const puedeLimpiar = Boolean(actual) && !nativo.required;
        if (botonLimpiar) {
            botonLimpiar.hidden = !puedeLimpiar;
            botonLimpiar.setAttribute('aria-hidden', puedeLimpiar ? 'false' : 'true');
        }
    }

    function pintarEncabezado() {
        heading.textContent = `${meses[vistaMes] ?? ''} ${vistaAnio}`;
    }

    function pintarGrid() {
        grid.innerHTML = '';

        const thead = document.createElement('thead');
        const filaEncabezado = document.createElement('tr');
        filaEncabezado.setAttribute('role', 'row');
        diasCortos.forEach((diaCorto, indice) => {
            const th = document.createElement('th');
            th.setAttribute('role', 'columnheader');
            th.setAttribute('scope', 'col');
            th.setAttribute('abbr', diasCompletos[indice] ?? diaCorto);
            th.textContent = diaCorto;
            filaEncabezado.append(th);
        });
        thead.append(filaEncabezado);
        grid.append(thead);

        const primerDiaMes = new Date(vistaAnio, vistaMes, 1);
        const totalDias = new Date(vistaAnio, vistaMes + 1, 0).getDate();
        const columnaInicio = indiceColumnaLunes(primerDiaMes);
        const totalCeldas = Math.ceil((columnaInicio + totalDias) / 7) * 7;
        const seleccionada = valorActual();
        const fechaHoy = hoy();

        const tbody = document.createElement('tbody');

        for (let fila = 0; fila < totalCeldas / 7; fila += 1) {
            const filaEl = document.createElement('tr');
            filaEl.setAttribute('role', 'row');

            for (let columna = 0; columna < 7; columna += 1) {
                const indice = (fila * 7) + columna;
                const diaMes = indice - columnaInicio + 1;
                const celdaEl = document.createElement('td');
                celdaEl.setAttribute('role', 'gridcell');

                if (diaMes < 1 || diaMes > totalDias) {
                    celdaEl.setAttribute('aria-hidden', 'true');
                    filaEl.append(celdaEl);
                    continue;
                }

                const fecha = new Date(vistaAnio, vistaMes, diaMes);
                const deshabilitada = fueraDeRango(fecha);
                const esHoy = mismoDia(fecha, fechaHoy);
                const esSeleccionada = mismoDia(fecha, seleccionada);
                const esEnfocada = mismoDia(fecha, enfocado);

                const boton = document.createElement('button');
                boton.type = 'button';
                boton.className = [
                    'ag-date__day',
                    esHoy ? 'ag-date__day--hoy' : '',
                    esSeleccionada ? 'ag-date__day--seleccionado' : '',
                ].filter(Boolean).join(' ');
                boton.textContent = String(diaMes);
                boton.tabIndex = esEnfocada ? 0 : -1;
                boton.dataset.fecha = formatearISO(fecha);
                boton.setAttribute(
                    'aria-label',
                    `${diasCompletos[indiceColumnaLunes(fecha)] ?? ''}, ${diaMes} de ${(meses[vistaMes] ?? '').toLowerCase()} de ${vistaAnio}`,
                );

                if (esSeleccionada) {
                    boton.setAttribute('aria-selected', 'true');
                }
                if (esHoy) {
                    boton.setAttribute('aria-current', 'date');
                }

                if (deshabilitada) {
                    boton.disabled = true;
                    boton.setAttribute('aria-disabled', 'true');
                } else {
                    boton.addEventListener('click', () => seleccionar(fecha));
                }

                celdaEl.append(boton);
                filaEl.append(celdaEl);
            }

            tbody.append(filaEl);
        }

        grid.append(tbody);
    }

    function enfocarCeldaActual() {
        if (!enfocado) {
            return;
        }
        const iso = formatearISO(enfocado);
        const boton = grid.querySelector(`[data-fecha="${iso}"]`);
        boton?.focus();
    }

    function mostrarMes(fecha) {
        vistaAnio = fecha.getFullYear();
        vistaMes = fecha.getMonth();
        pintarEncabezado();
        pintarGrid();
    }

    function moverFocoA(fecha) {
        enfocado = limitarRango(fecha);
        const cambiaMes = enfocado.getFullYear() !== vistaAnio || enfocado.getMonth() !== vistaMes;

        if (cambiaMes) {
            mostrarMes(enfocado);
        } else {
            pintarGrid();
        }

        enfocarCeldaActual();
    }

    function abrir() {
        if (nativo.disabled || abierto) {
            return;
        }

        abierto = true;
        enfocado = valorActual() || limitarRango(hoy());
        mostrarMes(enfocado);

        if (botonHoy) {
            botonHoy.disabled = fueraDeRango(hoy());
        }

        trigger.setAttribute('aria-expanded', 'true');
        dialogo.hidden = false;
        enfocarCeldaActual();
    }

    function cerrar({ enfocarDisparador = false } = {}) {
        if (!abierto) {
            return;
        }

        abierto = false;
        trigger.setAttribute('aria-expanded', 'false');
        dialogo.hidden = true;

        if (enfocarDisparador) {
            trigger.focus();
        }
    }

    function seleccionar(fecha) {
        const iso = formatearISO(fecha);
        if (nativo.value !== iso) {
            nativo.value = iso;
            nativo.dispatchEvent(new Event('change', { bubbles: true }));
        }

        actualizarValorMostrado();
        cerrar({ enfocarDisparador: true });
    }

    function limpiar() {
        if (nativo.required || nativo.value === '') {
            return;
        }

        nativo.value = '';
        nativo.dispatchEvent(new Event('change', { bubbles: true }));
        actualizarValorMostrado();
        cerrar({ enfocarDisparador: true });
    }

    function atraparFoco(evento) {
        const focables = Array.from(dialogo.querySelectorAll('button:not([disabled])'))
            .filter((el) => el.tabIndex !== -1);

        if (focables.length === 0) {
            return;
        }

        const primero = focables[0];
        const ultimo = focables[focables.length - 1];

        if (evento.shiftKey && document.activeElement === primero) {
            evento.preventDefault();
            ultimo.focus();
        } else if (!evento.shiftKey && document.activeElement === ultimo) {
            evento.preventDefault();
            primero.focus();
        }
    }

    dialogo.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape') {
            evento.preventDefault();
            cerrar({ enfocarDisparador: true });
            return;
        }

        if (evento.key === 'Tab') {
            atraparFoco(evento);
            return;
        }

        if (!evento.target.matches('.ag-date__day')) {
            return;
        }

        switch (evento.key) {
            case 'ArrowRight':
                evento.preventDefault();
                moverFocoA(sumarDias(enfocado, 1));
                return;
            case 'ArrowLeft':
                evento.preventDefault();
                moverFocoA(sumarDias(enfocado, -1));
                return;
            case 'ArrowDown':
                evento.preventDefault();
                moverFocoA(sumarDias(enfocado, 7));
                return;
            case 'ArrowUp':
                evento.preventDefault();
                moverFocoA(sumarDias(enfocado, -7));
                return;
            case 'Home':
                evento.preventDefault();
                moverFocoA(sumarDias(enfocado, -indiceColumnaLunes(enfocado)));
                return;
            case 'End':
                evento.preventDefault();
                moverFocoA(sumarDias(enfocado, 6 - indiceColumnaLunes(enfocado)));
                return;
            case 'PageUp':
                evento.preventDefault();
                moverFocoA(sumarMeses(enfocado, evento.shiftKey ? -12 : -1));
                return;
            case 'PageDown':
                evento.preventDefault();
                moverFocoA(sumarMeses(enfocado, evento.shiftKey ? 12 : 1));
                return;
            case 'Enter':
            case ' ':
                evento.preventDefault();
                if (!evento.target.disabled) {
                    seleccionar(enfocado);
                }
                return;
            default:
        }
    });

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

        if (evento.key === 'ArrowDown' && !abierto) {
            evento.preventDefault();
            abrir();
            return;
        }

        if ((evento.key === 'Backspace' || evento.key === 'Delete') && !abierto) {
            evento.preventDefault();
            limpiar();
        }
    });

    document.addEventListener('click', (evento) => {
        if (abierto && !root.contains(evento.target)) {
            cerrar();
        }
    });

    botonPrevMes.addEventListener('click', () => moverFocoA(sumarMeses(enfocado, -1)));
    botonNextMes.addEventListener('click', () => moverFocoA(sumarMeses(enfocado, 1)));

    botonHoy?.addEventListener('click', () => seleccionar(limitarRango(hoy())));

    botonLimpiar?.addEventListener('click', (evento) => {
        evento.stopPropagation();
        limpiar();
    });

    nativo.classList.add('ag-date__native--enhanced');
    nativo.setAttribute('aria-hidden', 'true');
    nativo.tabIndex = -1;
    trigger.hidden = false;
    actualizarValorMostrado();
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-ag-date]').forEach(inicializar);
});
