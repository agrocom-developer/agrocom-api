/**
 * Átomo: time-range (resources/views/components/atoms/time-range.blade.php)
 * Rango horario en una sola casilla, con un selector de reloj circular.
 *
 * Mejora progresiva, igual que `atoms/date`: los dos `<input type="time">`
 * nativos son los controles reales (tienen `name`, se envían con el
 * formulario). Acá se ocultan, se muestra el disparador y, al confirmar, se
 * escribe en los nativos `H:i` (24 h) más los eventos `input` y `change`.
 *
 * El selector es un `<dialog>` modal — foco atrapado, Esc cierra y fondo
 * inerte los da el navegador. Dentro:
 *   - cabecera "HH:MM – HH:MM" con cuatro segmentos (hora y minutos de inicio,
 *     hora y minutos de fin); el activo es el que edita el dial;
 *   - dial de 24 h: anillo exterior 12·1–11, interior 00·13–23; en minutos, un
 *     anillo con marcas cada 5 y precisión de 1 minuto al arrastrar;
 *   - al soltar el dial se pasa solo al segmento siguiente (hora → minutos →
 *     hora de fin → minutos de fin);
 *   - modo de texto "HH:MM": la alternativa accesible al dial;
 *   - Limpiar (vacía y cierra), Cancelar (descarta), Aceptar (confirma).
 *
 * Los textos llegan del Blade por `data-label-*` (ADR 0013); acá no hay
 * ninguno escrito. El color y la tipografía, todo por token en
 * resources/css/components/time-range.css.
 */

const SVG = 'http://www.w3.org/2000/svg';

// Geometría del dial, en unidades del viewBox (240 × 240).
const CENTRO = 120;
const RADIO_FONDO = 112;
const RADIO_EXTERIOR = 88;
const RADIO_INTERIOR = 56;
const RADIO_SELECTOR = 17;
// Por debajo de este radio un toque cae en el anillo interior (13–23 y 00).
const UMBRAL_ANILLOS = (RADIO_EXTERIOR + RADIO_INTERIOR) / 2;

const PASOS = ['ih', 'im', 'fh', 'fm'];

const pad = (numero) => String(numero).padStart(2, '0');

/** `H:i` o `H:i:s` → `{h, m}`; `null` si no es una hora válida de 24 h. */
const leerHora = (texto) => {
    const coincidencia = /^(\d{1,2}):(\d{2})/.exec(String(texto ?? '').trim());
    if (!coincidencia) return null;

    const h = Number(coincidencia[1]);
    const m = Number(coincidencia[2]);

    return h <= 23 && m <= 59 ? { h, m } : null;
};

/** Lo que se teclea en el modo de texto: acepta `6:00`, `06:00` y `0600`. */
const leerTecleada = (texto) => {
    const coincidencia = /^(\d{1,2}):?(\d{2})$/.exec(String(texto ?? '').trim());
    return coincidencia ? leerHora(`${coincidencia[1]}:${coincidencia[2]}`) : null;
};

const formatear = (h, m) => `${pad(h)}:${pad(m)}`;

const svg = (nombre, atributos = {}, texto = null) => {
    const nodo = document.createElementNS(SVG, nombre);
    Object.entries(atributos).forEach(([clave, valor]) => nodo.setAttribute(clave, String(valor)));
    if (texto !== null) nodo.textContent = texto;
    return nodo;
};

/** Punto del dial a `angulo` grados desde las 12 (sentido horario) y `radio` del centro. */
const punto = (angulo, radio) => {
    const rad = (angulo * Math.PI) / 180;
    return { x: CENTRO + radio * Math.sin(rad), y: CENTRO - radio * Math.cos(rad) };
};

/** Dónde queda una hora en el dial: 12 arriba en el anillo exterior, 00 arriba en el interior. */
const geometriaHora = (h) => {
    if (h === 0) return { angulo: 0, radio: RADIO_INTERIOR };
    if (h === 12) return { angulo: 0, radio: RADIO_EXTERIOR };
    if (h < 12) return { angulo: h * 30, radio: RADIO_EXTERIOR };

    return { angulo: (h - 12) * 30, radio: RADIO_INTERIOR };
};

function pintarDial(dial, tipo, valor) {
    dial.replaceChildren();
    dial.append(svg('circle', { class: 'ag-time-range__dial-fondo', cx: CENTRO, cy: CENTRO, r: RADIO_FONDO }));

    if (valor !== null) {
        const { angulo, radio } = tipo === 'hora' ? geometriaHora(valor) : { angulo: valor * 6, radio: RADIO_EXTERIOR };
        const p = punto(angulo, radio);

        dial.append(svg('line', { class: 'ag-time-range__dial-aguja', x1: CENTRO, y1: CENTRO, x2: p.x, y2: p.y }));
        dial.append(svg('circle', { class: 'ag-time-range__dial-eje', cx: CENTRO, cy: CENTRO, r: 3 }));
        dial.append(svg('circle', { class: 'ag-time-range__dial-selector', cx: p.x, cy: p.y, r: RADIO_SELECTOR }));

        // Un minuto que no cae en una marca se señala con un punto: el número va en la cabecera.
        if (tipo === 'minuto' && valor % 5 !== 0) {
            dial.append(svg('circle', { class: 'ag-time-range__dial-punto', cx: p.x, cy: p.y, r: 3 }));
        }
    }

    const numero = (texto, angulo, radio, esInterior, elegido) => {
        const p = punto(angulo, radio);
        const clases = ['ag-time-range__dial-numero'];
        if (esInterior) clases.push('ag-time-range__dial-numero--interior');
        if (elegido) clases.push('ag-time-range__dial-numero--elegido');

        dial.append(svg('text', { class: clases.join(' '), x: p.x, y: p.y }, texto));
    };

    for (let i = 0; i < 12; i += 1) {
        if (tipo === 'hora') {
            const exterior = i === 0 ? 12 : i;
            const interior = i === 0 ? 0 : 12 + i;

            numero(pad(exterior), i * 30, RADIO_EXTERIOR, false, valor === exterior);
            numero(pad(interior), i * 30, RADIO_INTERIOR, true, valor === interior);
        } else {
            numero(pad(i * 5), i * 30, RADIO_EXTERIOR, false, valor === i * 5);
        }
    }
}

function iniciar(raiz) {
    if (raiz.dataset.agTimeRangoListo === '1') return;

    const [nativoInicio, nativoFin] = raiz.querySelectorAll('.ag-time-range__native');
    const disparador = raiz.querySelector('[data-ag-time-range-trigger]');
    const valorVisible = raiz.querySelector('[data-ag-time-range-value]');
    const botonLimpiar = raiz.querySelector('[data-ag-time-range-clear]');
    const dialogo = raiz.querySelector('[data-ag-time-range-dialog]');
    const dial = raiz.querySelector('[data-ag-time-range-dial]');
    const reloj = raiz.querySelector('[data-ag-time-range-reloj]');
    const teclado = raiz.querySelector('[data-ag-time-range-teclado]');
    const aviso = raiz.querySelector('[data-ag-time-range-aviso]');
    const botonModo = raiz.querySelector('[data-ag-time-range-modo]');
    const iconoModo = botonModo?.querySelector('.ag-icon');
    const botonAceptar = raiz.querySelector('[data-accion="aceptar"]');
    const segmentos = raiz.querySelectorAll('[data-seg]');
    const camposTeclado = {
        inicio: raiz.querySelector('[data-teclado="inicio"]'),
        fin: raiz.querySelector('[data-teclado="fin"]'),
    };

    if (!nativoInicio || !nativoFin || !disparador || !valorVisible || !dialogo || !dial || !reloj || !teclado || !aviso || !botonModo || !botonAceptar) {
        return;
    }

    raiz.dataset.agTimeRangoListo = '1';

    const etiquetas = raiz.dataset;
    const nombrePaso = {
        ih: `${etiquetas.labelInicio}: ${etiquetas.labelHora}`,
        im: `${etiquetas.labelInicio}: ${etiquetas.labelMinutos}`,
        fh: `${etiquetas.labelFin}: ${etiquetas.labelHora}`,
        fm: `${etiquetas.labelFin}: ${etiquetas.labelMinutos}`,
    };

    let borrador = { ih: null, im: null, fh: null, fm: null };
    let paso = 'ih';
    let modoTeclado = false;
    let avisoManual = '';
    let arrastrando = false;

    const esMinutos = () => paso === 'im' || paso === 'fm';

    // ===== Disparador (lo que se ve en la casilla) =====

    const pintarDisparador = () => {
        const inicio = leerHora(nativoInicio.value);
        const fin = leerHora(nativoFin.value);
        const completo = inicio !== null && fin !== null;
        const deshabilitado = nativoInicio.disabled || nativoFin.disabled;

        valorVisible.textContent = completo ? `${formatear(inicio.h, inicio.m)} – ${formatear(fin.h, fin.m)}` : etiquetas.labelPlaceholder;
        valorVisible.classList.toggle('ag-time-range__value--placeholder', !completo);
        disparador.disabled = deshabilitado;
        raiz.classList.toggle('ag-time-range--deshabilitado', deshabilitado);

        if (botonLimpiar) botonLimpiar.hidden = !completo || deshabilitado;
    };

    // Mejora progresiva: los nativos se ocultan sin sacarlos del DOM.
    [nativoInicio, nativoFin].forEach((nativo) => {
        nativo.classList.add('ag-time-range__native--enhanced');
        nativo.tabIndex = -1;
    });
    disparador.hidden = false;
    pintarDisparador();

    // Cualquier código de la página puede deshabilitar el campo cambiando
    // `disabled` en los nativos (la tabla de lotes lo hace con "Día completo"):
    // se observa ese atributo y el disparador se vuelve a leer solo.
    const observador = new MutationObserver(pintarDisparador);
    [nativoInicio, nativoFin].forEach((nativo) => observador.observe(nativo, { attributes: true, attributeFilter: ['disabled'] }));

    // ===== Escritura en los nativos =====

    const escribir = (inicio, fin) => {
        if (nativoInicio.value !== inicio || nativoFin.value !== fin) {
            nativoInicio.value = inicio;
            nativoFin.value = fin;

            [nativoInicio, nativoFin].forEach((nativo) => {
                nativo.dispatchEvent(new Event('input', { bubbles: true }));
                nativo.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }

        pintarDisparador();
    };

    // ===== Validación del borrador =====

    const hayInicio = () => borrador.ih !== null && borrador.im !== null;
    const hayFin = () => borrador.fh !== null && borrador.fm !== null;
    const hayAlgo = () => PASOS.some((clave) => borrador[clave] !== null);
    const finAntesDelInicio = () => hayInicio() && hayFin() && borrador.fh * 60 + borrador.fm <= borrador.ih * 60 + borrador.im;

    /** Texto del error del borrador, o `null` si se puede confirmar. */
    const errorDelBorrador = () => {
        if (!hayAlgo()) return null;
        if (!hayInicio() || !hayFin()) return etiquetas.labelErrorAmbas;
        if (finAntesDelInicio()) return etiquetas.labelErrorOrden;

        return null;
    };

    // ===== Pintado del selector =====

    const pintar = () => {
        segmentos.forEach((boton) => {
            const clave = boton.dataset.seg;
            const activo = clave === paso && !modoTeclado;

            boton.textContent = borrador[clave] === null ? '--' : pad(borrador[clave]);
            boton.classList.toggle('ag-time-range__seg--activo', activo);
            boton.setAttribute('aria-pressed', String(activo));
        });

        const valor = borrador[paso];
        pintarDial(dial, esMinutos() ? 'minuto' : 'hora', valor);

        dial.setAttribute('aria-label', nombrePaso[paso]);
        dial.setAttribute('aria-valuemin', '0');
        dial.setAttribute('aria-valuemax', esMinutos() ? '59' : '23');
        if (valor === null) {
            dial.removeAttribute('aria-valuenow');
            dial.removeAttribute('aria-valuetext');
        } else {
            dial.setAttribute('aria-valuenow', String(valor));
            dial.setAttribute('aria-valuetext', pad(valor));
        }

        // Fin antes del inicio se avisa en vivo; lo demás, al intentar confirmar.
        aviso.textContent = avisoManual || (finAntesDelInicio() ? etiquetas.labelErrorOrden : '');
    };

    const avanzar = () => {
        const indice = PASOS.indexOf(paso);

        if (indice < PASOS.length - 1) {
            paso = PASOS[indice + 1];
            pintar();
        } else {
            botonAceptar.focus();
        }
    };

    const fijar = (valor) => {
        borrador[paso] = valor;
        avisoManual = '';

        // Elegida la hora, los minutos arrancan en 00 (como el selector de Android).
        if (paso === 'ih' && borrador.im === null) borrador.im = 0;
        if (paso === 'fh' && borrador.fm === null) borrador.fm = 0;

        pintar();
    };

    // ===== Dial: puntero =====

    const valorEn = (evento) => {
        const caja = dial.getBoundingClientRect();
        const dx = evento.clientX - (caja.left + caja.width / 2);
        const dy = evento.clientY - (caja.top + caja.height / 2);

        let angulo = (Math.atan2(dx, -dy) * 180) / Math.PI;
        if (angulo < 0) angulo += 360;

        if (esMinutos()) return Math.round(angulo / 6) % 60;

        const posicion = Math.round(angulo / 30) % 12;
        const distancia = (Math.hypot(dx, dy) * 240) / caja.width;
        const interior = distancia < UMBRAL_ANILLOS;

        if (interior) return posicion === 0 ? 0 : 12 + posicion;

        return posicion === 0 ? 12 : posicion;
    };

    dial.addEventListener('pointerdown', (evento) => {
        if (evento.pointerType === 'mouse' && evento.button !== 0) return;

        arrastrando = true;
        dial.setPointerCapture(evento.pointerId);
        dial.focus({ preventScroll: true });
        fijar(valorEn(evento));
        evento.preventDefault();
    });

    dial.addEventListener('pointermove', (evento) => {
        if (arrastrando) fijar(valorEn(evento));
    });

    dial.addEventListener('pointerup', (evento) => {
        if (!arrastrando) return;

        arrastrando = false;
        if (dial.hasPointerCapture(evento.pointerId)) dial.releasePointerCapture(evento.pointerId);
        avanzar();
    });

    dial.addEventListener('pointercancel', () => {
        arrastrando = false;
    });

    // ===== Dial: teclado =====

    dial.addEventListener('keydown', (evento) => {
        const maximo = esMinutos() ? 59 : 23;
        const salto = esMinutos() ? 5 : 1;
        const cambios = { ArrowRight: 1, ArrowUp: 1, ArrowLeft: -1, ArrowDown: -1, PageUp: salto, PageDown: -salto };

        if (evento.key === 'Enter' || evento.key === ' ') {
            evento.preventDefault();
            avanzar();
            return;
        }

        if (evento.key === 'Home' || evento.key === 'End') {
            evento.preventDefault();
            fijar(evento.key === 'Home' ? 0 : maximo);
            return;
        }

        if (!(evento.key in cambios)) return;

        evento.preventDefault();
        fijar((((borrador[paso] ?? 0) + cambios[evento.key]) % (maximo + 1) + maximo + 1) % (maximo + 1));
    });

    segmentos.forEach((boton) => {
        boton.addEventListener('click', () => {
            if (modoTeclado) ponerModo(false);
            paso = boton.dataset.seg;
            pintar();
            dial.focus({ preventScroll: true });
        });
    });

    // ===== Modo de texto =====

    const llenarTeclado = () => {
        camposTeclado.inicio.value = hayInicio() ? formatear(borrador.ih, borrador.im) : '';
        camposTeclado.fin.value = hayFin() ? formatear(borrador.fh, borrador.fm) : '';
    };

    /** Pasa lo tecleado al borrador. `false` (con aviso) si algo no es una hora válida. */
    const leerTeclado = () => {
        const inicio = camposTeclado.inicio.value.trim() === '' ? null : leerTecleada(camposTeclado.inicio.value);
        const fin = camposTeclado.fin.value.trim() === '' ? null : leerTecleada(camposTeclado.fin.value);
        const invalido = (camposTeclado.inicio.value.trim() !== '' && inicio === null) || (camposTeclado.fin.value.trim() !== '' && fin === null);

        if (invalido) {
            avisoManual = etiquetas.labelErrorFormato;
            pintar();
            return false;
        }

        borrador = { ih: inicio?.h ?? null, im: inicio?.m ?? null, fh: fin?.h ?? null, fm: fin?.m ?? null };
        avisoManual = '';

        return true;
    };

    function ponerModo(aTeclado) {
        if (aTeclado === modoTeclado) return;
        if (!aTeclado && !leerTeclado()) return;
        if (aTeclado) llenarTeclado();

        modoTeclado = aTeclado;
        reloj.hidden = aTeclado;
        teclado.hidden = !aTeclado;

        botonModo.setAttribute('aria-label', aTeclado ? etiquetas.labelUsarReloj : etiquetas.labelUsarTeclado);
        if (iconoModo) iconoModo.textContent = aTeclado ? 'schedule' : 'keyboard';

        pintar();
        (aTeclado ? camposTeclado.inicio : dial).focus({ preventScroll: true });
    }

    botonModo.addEventListener('click', () => ponerModo(!modoTeclado));

    Object.values(camposTeclado).forEach((campo) => {
        campo.addEventListener('keydown', (evento) => {
            if (evento.key === 'Enter') {
                evento.preventDefault();
                aceptar();
            }
        });
        campo.addEventListener('input', () => {
            avisoManual = '';
            aviso.textContent = '';
        });
    });

    // ===== Abrir, confirmar, limpiar =====

    const abrir = () => {
        if (nativoInicio.disabled) return;

        const inicio = leerHora(nativoInicio.value);
        const fin = leerHora(nativoFin.value);

        borrador = { ih: inicio?.h ?? null, im: inicio?.m ?? null, fh: fin?.h ?? null, fm: fin?.m ?? null };
        paso = 'ih';
        modoTeclado = false;
        avisoManual = '';
        reloj.hidden = false;
        teclado.hidden = true;
        botonModo.setAttribute('aria-label', etiquetas.labelUsarTeclado);
        if (iconoModo) iconoModo.textContent = 'keyboard';

        dialogo.showModal();
        pintar();
        dial.focus({ preventScroll: true });
    };

    function aceptar() {
        if (modoTeclado && !leerTeclado()) return;

        const error = errorDelBorrador();
        if (error) {
            avisoManual = error;
            pintar();
            return;
        }

        escribir(hayInicio() ? formatear(borrador.ih, borrador.im) : '', hayFin() ? formatear(borrador.fh, borrador.fm) : '');
        dialogo.close();
    }

    const vaciar = () => {
        escribir('', '');
        if (dialogo.open) dialogo.close();
    };

    disparador.addEventListener('click', abrir);
    botonLimpiar?.addEventListener('click', () => {
        vaciar();
        disparador.focus();
    });

    dialogo.querySelector('[data-accion="aceptar"]').addEventListener('click', aceptar);
    dialogo.querySelector('[data-accion="cancelar"]').addEventListener('click', () => dialogo.close());
    dialogo.querySelector('[data-accion="limpiar"]').addEventListener('click', vaciar);

    // Un clic en el fondo (el <dialog> mismo, no su contenido) descarta.
    dialogo.addEventListener('click', (evento) => {
        if (evento.target === dialogo) dialogo.close();
    });

    dialogo.addEventListener('close', () => {
        arrastrando = false;
        disparador.focus();
    });

    // API mínima para quien necesite releer el valor (p. ej. tras cambiarlo por código).
    raiz.agTimeRange = { refrescar: pintarDisparador };
}

/** Inicializa los campos de rango horario de `raiz`. Idempotente: se puede llamar tras clonar filas. */
export function initTimeRanges(raiz = document) {
    raiz.querySelectorAll('[data-ag-time-range]').forEach(iniciar);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initTimeRanges());
} else {
    initTimeRanges();
}
