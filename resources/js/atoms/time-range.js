/**
 * Átomo: time-range (resources/views/components/atoms/time-range.blade.php)
 * Rango horario en una sola casilla, con un popup de reloj anclado a ella.
 *
 * Mejora progresiva, igual que `atoms/date`: los dos `<input type="time">`
 * nativos son los controles reales (tienen `name`, se envían con el
 * formulario). Acá se ocultan, se muestra el disparador y, al confirmar, se
 * escribe en los nativos `H:i` en 24 horas más los eventos `input` y `change`.
 * Sin Popover API no se mejora nada: quedan los dos nativos.
 *
 * El popup es un popover (`popover="auto"`): capa superior sin recortes, cierra
 * al hacer clic afuera o con Esc, y no oscurece la pantalla. Lo abre el propio
 * disparador (`popovertarget`); acá se prepara el contenido en `beforetoggle` y
 * se ubica al abrirse, debajo de la casilla o encima si no entra.
 *
 * El reloj es de 12 horas y cada hora lleva su «a. m.»/«p. m.» a la vista: un
 * dial de 1 a 12 solo no dice si 3:00 es de la madrugada o de la tarde. Elegir
 * una hora pasa sola a sus minutos, luego a la hora de fin y a sus minutos; y
 * mientras no se toque a mano, el período de la hora de fin se elige solo para
 * que quede después del inicio (10:00 a. m. → 3 = 3:00 p. m.). El modo de texto
 * acepta «6:30 a. m.», «6 pm» o «18:30».
 *
 * Los textos llegan del Blade por `data-label-*` (ADR 0013); acá no hay ninguno
 * escrito. El color y la tipografía, todo por token en
 * resources/css/components/time-range.css.
 */

const SVG = 'http://www.w3.org/2000/svg';
const NBSP = ' ';

// Geometría del dial, en unidades del viewBox (200 × 200).
const CENTRO = 100;
const RADIO_FONDO = 94;
const RADIO_NUMEROS = 72;
const RADIO_SELECTOR = 15;

const PASOS = ['ih', 'im', 'fh', 'fm'];

const pad = (numero) => String(numero).padStart(2, '0');

/** `H:i` o `H:i:s` → `{h, m}` en 24 h; `null` si no es una hora válida. */
const leerHora24 = (texto) => {
    const coincidencia = /^(\d{1,2}):(\d{2})/.exec(String(texto ?? '').trim());
    if (!coincidencia) return null;

    const h = Number(coincidencia[1]);
    const m = Number(coincidencia[2]);

    return h <= 23 && m <= 59 ? { h, m } : null;
};

/**
 * Lo que se teclea: `6:30 a. m.`, `6 pm`, `06:30`, `18:30`, `0630`. Con «a. m.»/
 * «p. m.» la hora es de reloj de 12; sin él, de 24. → `{h, m}` en 24 h o `null`.
 */
const leerTecleada = (texto) => {
    const coincidencia = /^\s*(\d{1,2})(?::?(\d{2}))?\s*(?:([ap])\.?\s*m\.?)?\s*$/i.exec(String(texto ?? ''));
    if (!coincidencia) return null;

    let h = Number(coincidencia[1]);
    const m = coincidencia[2] === undefined ? 0 : Number(coincidencia[2]);

    if (m > 59) return null;

    if (coincidencia[3]) {
        if (h < 1 || h > 12) return null;
        h = (h % 12) + (coincidencia[3].toLowerCase() === 'p' ? 12 : 0);
    } else if (h > 23) {
        return null;
    }

    return { h, m };
};

/** 12 h + período → 24 h. */
const a24 = (h12, periodo) => (h12 % 12) + (periodo === 'pm' ? 12 : 0);

/** 24 h → hora de reloj de 12 (1–12) y su período. */
const de24 = (h) => ({ h12: h % 12 === 0 ? 12 : h % 12, periodo: h >= 12 ? 'pm' : 'am' });

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

function pintarDial(dial, tipo, valor) {
    dial.replaceChildren();
    dial.append(svg('circle', { class: 'ag-time-range__dial-fondo', cx: CENTRO, cy: CENTRO, r: RADIO_FONDO }));

    if (valor !== null) {
        const p = punto(tipo === 'hora' ? (valor % 12) * 30 : valor * 6, RADIO_NUMEROS);

        dial.append(svg('line', { class: 'ag-time-range__dial-aguja', x1: CENTRO, y1: CENTRO, x2: p.x, y2: p.y }));
        dial.append(svg('circle', { class: 'ag-time-range__dial-eje', cx: CENTRO, cy: CENTRO, r: 2.5 }));
        dial.append(svg('circle', { class: 'ag-time-range__dial-selector', cx: p.x, cy: p.y, r: RADIO_SELECTOR }));

        // Un minuto que no cae en una marca se señala con un punto: el número va en la cabecera.
        if (tipo === 'minuto' && valor % 5 !== 0) {
            dial.append(svg('circle', { class: 'ag-time-range__dial-punto', cx: p.x, cy: p.y, r: 2.5 }));
        }
    }

    for (let i = 0; i < 12; i += 1) {
        const etiqueta = tipo === 'hora' ? (i === 0 ? 12 : i) : i * 5;
        const p = punto(i * 30, RADIO_NUMEROS);
        const clases = ['ag-time-range__dial-numero'];
        if (valor === etiqueta) clases.push('ag-time-range__dial-numero--elegido');

        dial.append(svg('text', { class: clases.join(' '), x: p.x, y: p.y }, tipo === 'hora' ? String(etiqueta) : pad(etiqueta)));
    }
}

function iniciar(raiz) {
    if (raiz.dataset.agTimeRangoListo === '1') return;

    // Sin Popover API el componente no se mejora: quedan los dos inputs nativos.
    if (!('popover' in HTMLElement.prototype)) return;

    const [nativoInicio, nativoFin] = raiz.querySelectorAll('.ag-time-range__native');
    const disparador = raiz.querySelector('[data-ag-time-range-trigger]');
    const valorVisible = raiz.querySelector('[data-ag-time-range-value]');
    const botonLimpiar = raiz.querySelector('[data-ag-time-range-clear]');
    const popup = raiz.querySelector('[data-ag-time-range-popup]');
    const dial = raiz.querySelector('[data-ag-time-range-dial]');
    const reloj = raiz.querySelector('[data-ag-time-range-reloj]');
    const teclado = raiz.querySelector('[data-ag-time-range-teclado]');
    const aviso = raiz.querySelector('[data-ag-time-range-aviso]');
    const botonModo = raiz.querySelector('[data-ag-time-range-modo]');
    const iconoModo = botonModo?.querySelector('.ag-icon');
    const botonAceptar = raiz.querySelector('[data-accion="aceptar"]');
    const segmentos = raiz.querySelectorAll('[data-seg]');
    const opcionesPeriodo = raiz.querySelectorAll('[data-periodo]');
    const camposTeclado = {
        inicio: raiz.querySelector('[data-teclado="inicio"]'),
        fin: raiz.querySelector('[data-teclado="fin"]'),
    };

    if (!nativoInicio || !nativoFin || !disparador || !valorVisible || !popup || !dial || !reloj || !teclado || !aviso || !botonModo || !botonAceptar) {
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

    /** «6:30 a. m.» — espacios sin corte para que la hora y su período no se partan. */
    const formatear12 = (h24, m) => {
        const { h12, periodo } = de24(h24);
        const texto = `${h12}:${pad(m)} ${periodo === 'am' ? etiquetas.labelAm : etiquetas.labelPm}`;

        return texto.replaceAll(' ', NBSP);
    };

    const formatear24 = (h24, m) => `${pad(h24)}:${pad(m)}`;

    // Borrador de lo que se está eligiendo: horas en reloj de 12 (`ih`/`fh`, 1–12)
    // con su período (`pi`/`pf`), y minutos (`im`/`fm`). `pfManual`: si ya se tocó
    // a mano el período de la hora de fin, deja de elegirse solo.
    let est = { ih: null, im: null, pi: 'am', fh: null, fm: null, pf: 'am', pfManual: false };
    let paso = 'ih';
    let modoTeclado = false;
    let avisoManual = '';
    let arrastrando = false;
    let devolverFoco = false;

    const esMinutos = () => paso === 'im' || paso === 'fm';

    // ===== Disparador (lo que se ve en la casilla) =====

    const pintarDisparador = () => {
        const inicio = leerHora24(nativoInicio.value);
        const fin = leerHora24(nativoFin.value);
        const completo = inicio !== null && fin !== null;
        const deshabilitado = nativoInicio.disabled || nativoFin.disabled;

        valorVisible.textContent = completo ? `${formatear12(inicio.h, inicio.m)} – ${formatear12(fin.h, fin.m)}` : etiquetas.labelPlaceholder;
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

    const hayInicio = () => est.ih !== null && est.im !== null;
    const hayFin = () => est.fh !== null && est.fm !== null;
    const hayAlgo = () => PASOS.some((clave) => est[clave] !== null);
    const minutosDelDia = (h12, periodo, m) => a24(h12, periodo) * 60 + m;
    const finAntesDelInicio = () => hayInicio() && hayFin()
        && minutosDelDia(est.fh, est.pf, est.fm) <= minutosDelDia(est.ih, est.pi, est.im);

    /** Texto del error del borrador, o `null` si se puede confirmar. */
    const errorDelBorrador = () => {
        if (!hayAlgo()) return null;
        if (!hayInicio() || !hayFin()) return etiquetas.labelErrorAmbas;
        if (finAntesDelInicio()) return etiquetas.labelErrorOrden;

        return null;
    };

    /**
     * El período de la hora de fin se elige solo, mientras nadie lo toque: el
     * primero (a. m., y si no p. m.) con el que el fin queda después del inicio.
     * Con inicio 10:00 a. m., el fin «3» pasa a ser 3:00 p. m.
     */
    const reajustarPeriodoDeFin = () => {
        if (est.pfManual || est.fh === null) return;

        const inicio = est.ih !== null ? minutosDelDia(est.ih, est.pi, est.im ?? 0) : null;

        for (const periodo of ['am', 'pm']) {
            if (inicio === null || minutosDelDia(est.fh, periodo, est.fm ?? 0) > inicio) {
                est.pf = periodo;
                return;
            }
        }

        est.pf = 'pm'; // ninguno queda después: el aviso lo dice
    };

    // ===== Pintado del popup =====

    const pintar = () => {
        segmentos.forEach((boton) => {
            const clave = boton.dataset.seg;
            const activo = clave === paso && !modoTeclado;

            boton.textContent = est[clave] === null ? '--' : pad(est[clave]);
            boton.classList.toggle('ag-time-range__seg--activo', activo);
            boton.setAttribute('aria-pressed', String(activo));
        });

        opcionesPeriodo.forEach((opcion) => {
            const activa = est[opcion.dataset.periodo] === opcion.dataset.valor;

            opcion.classList.toggle('ag-time-range__periodo-opcion--activo', activa);
            opcion.setAttribute('aria-pressed', String(activa));
        });

        const valor = est[paso];
        pintarDial(dial, esMinutos() ? 'minuto' : 'hora', valor);

        dial.setAttribute('aria-label', nombrePaso[paso]);
        dial.setAttribute('aria-valuemin', esMinutos() ? '0' : '1');
        dial.setAttribute('aria-valuemax', esMinutos() ? '59' : '12');
        if (valor === null) {
            dial.removeAttribute('aria-valuenow');
            dial.removeAttribute('aria-valuetext');
        } else {
            dial.setAttribute('aria-valuenow', String(valor));
            dial.setAttribute('aria-valuetext', esMinutos() ? pad(valor) : String(valor));
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
        est[paso] = valor;
        avisoManual = '';

        // Elegida la hora, los minutos arrancan en 00 (como el selector de Android).
        if (paso === 'ih' && est.im === null) est.im = 0;
        if (paso === 'fh' && est.fm === null) est.fm = 0;

        reajustarPeriodoDeFin();
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
        const minutos = esMinutos();
        const salto = minutos ? 5 : 1;
        const cambios = { ArrowRight: 1, ArrowUp: 1, ArrowLeft: -1, ArrowDown: -1, PageUp: salto, PageDown: -salto };

        if (evento.key === 'Enter' || evento.key === ' ') {
            evento.preventDefault();
            avanzar();
            return;
        }

        if (evento.key === 'Home' || evento.key === 'End') {
            evento.preventDefault();
            fijar(evento.key === 'Home' ? (minutos ? 0 : 1) : (minutos ? 59 : 12));
            return;
        }

        if (!(evento.key in cambios)) return;

        evento.preventDefault();

        if (minutos) {
            fijar((((est[paso] ?? 0) + cambios[evento.key]) % 60 + 60) % 60);
        } else {
            fijar((((est[paso] ?? 12) - 1 + cambios[evento.key]) % 12 + 12) % 12 + 1);
        }
    });

    segmentos.forEach((boton) => {
        boton.addEventListener('click', () => {
            if (modoTeclado) ponerModo(false);
            paso = boton.dataset.seg;
            pintar();
            dial.focus({ preventScroll: true });
        });
    });

    // a. m. / p. m. de cada hora. Tocar el de la hora de fin deja de elegirlo solo.
    opcionesPeriodo.forEach((opcion) => {
        opcion.addEventListener('click', () => {
            est[opcion.dataset.periodo] = opcion.dataset.valor;

            if (opcion.dataset.periodo === 'pf') est.pfManual = true;
            else reajustarPeriodoDeFin();

            avisoManual = '';
            pintar();
        });
    });

    // ===== Modo de texto =====

    const llenarTeclado = () => {
        camposTeclado.inicio.value = hayInicio() ? formatear12(a24(est.ih, est.pi), est.im).replaceAll(NBSP, ' ') : '';
        camposTeclado.fin.value = hayFin() ? formatear12(a24(est.fh, est.pf), est.fm).replaceAll(NBSP, ' ') : '';
    };

    /** Pasa lo tecleado al borrador. `false` (con aviso) si algo no es una hora válida. */
    const leerTeclado = () => {
        const crudo = { inicio: camposTeclado.inicio.value.trim(), fin: camposTeclado.fin.value.trim() };
        const inicio = crudo.inicio === '' ? null : leerTecleada(crudo.inicio);
        const fin = crudo.fin === '' ? null : leerTecleada(crudo.fin);

        if ((crudo.inicio !== '' && inicio === null) || (crudo.fin !== '' && fin === null)) {
            avisoManual = etiquetas.labelErrorFormato;
            pintar();
            return false;
        }

        const d = (hora) => (hora === null ? null : de24(hora.h));

        est = {
            ih: d(inicio)?.h12 ?? null,
            im: inicio?.m ?? null,
            pi: d(inicio)?.periodo ?? 'am',
            fh: d(fin)?.h12 ?? null,
            fm: fin?.m ?? null,
            pf: d(fin)?.periodo ?? 'am',
            pfManual: fin !== null,
        };
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
        posicionar();
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

    // ===== Abrir, ubicar, confirmar, limpiar =====

    /** Carga lo que ya hay en los nativos como punto de partida del borrador. */
    const cargarBorrador = () => {
        const inicio = leerHora24(nativoInicio.value);
        const fin = leerHora24(nativoFin.value);
        const di = inicio ? de24(inicio.h) : null;
        const df = fin ? de24(fin.h) : null;

        est = {
            ih: di?.h12 ?? null,
            im: inicio?.m ?? null,
            pi: di?.periodo ?? 'am',
            fh: df?.h12 ?? null,
            fm: fin?.m ?? null,
            pf: df?.periodo ?? 'am',
            pfManual: fin !== null,
        };
        paso = 'ih';
        modoTeclado = false;
        avisoManual = '';
        reloj.hidden = false;
        teclado.hidden = true;
        botonModo.setAttribute('aria-label', etiquetas.labelUsarTeclado);
        if (iconoModo) iconoModo.textContent = 'keyboard';
    };

    /** Debajo de la casilla, o encima si abajo no entra; siempre dentro de la ventana. */
    function posicionar() {
        if (!popup.matches(':popover-open')) return;

        const margen = 8;
        const caja = disparador.getBoundingClientRect();
        const ancho = popup.offsetWidth;
        const alto = popup.offsetHeight;

        const izquierda = Math.min(Math.max(margen, caja.left), Math.max(margen, window.innerWidth - ancho - margen));
        let arriba = caja.bottom + 6;

        if (arriba + alto > window.innerHeight - margen) {
            arriba = Math.max(margen, caja.top - alto - 6);
        }

        popup.style.left = `${izquierda}px`;
        popup.style.top = `${arriba}px`;
    }

    // Se prepara el contenido ANTES de abrir (así entra ya pintado); si el campo
    // está deshabilitado (día completo) no se abre.
    popup.addEventListener('beforetoggle', (evento) => {
        if (evento.newState !== 'open') return;

        if (nativoInicio.disabled) {
            evento.preventDefault();
            return;
        }

        cargarBorrador();
        pintar();
    });

    popup.addEventListener('toggle', (evento) => {
        if (evento.newState === 'open') {
            disparador.setAttribute('aria-expanded', 'true');
            posicionar();
            dial.focus({ preventScroll: true });
            window.addEventListener('resize', posicionar);
            window.addEventListener('scroll', posicionar, true);
        } else {
            disparador.setAttribute('aria-expanded', 'false');
            window.removeEventListener('resize', posicionar);
            window.removeEventListener('scroll', posicionar, true);
            arrastrando = false;

            // El foco vuelve a la casilla cuando se cierra con un botón o con Esc;
            // no cuando se hizo clic en otro lado (ahí el foco ya es de esa otra cosa).
            if (devolverFoco) disparador.focus();
            devolverFoco = false;
        }
    });

    const cerrar = () => {
        devolverFoco = true;
        if (popup.matches(':popover-open')) popup.hidePopover();
    };

    popup.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape') devolverFoco = true;
    });

    // Con el teclado, salir del popup con Tab lo cierra (un popover no atrapa el foco).
    popup.addEventListener('focusout', (evento) => {
        const destino = evento.relatedTarget;

        if (destino && !popup.contains(destino) && destino !== disparador && popup.matches(':popover-open')) {
            popup.hidePopover();
        }
    });

    function aceptar() {
        if (modoTeclado && !leerTeclado()) return;

        const error = errorDelBorrador();
        if (error) {
            avisoManual = error;
            pintar();
            return;
        }

        escribir(
            hayInicio() ? formatear24(a24(est.ih, est.pi), est.im) : '',
            hayFin() ? formatear24(a24(est.fh, est.pf), est.fm) : '',
        );
        cerrar();
    }

    const vaciar = () => {
        escribir('', '');
        cerrar();
    };

    botonLimpiar?.addEventListener('click', () => {
        escribir('', '');
        disparador.focus();
    });

    popup.querySelector('[data-accion="aceptar"]').addEventListener('click', aceptar);
    popup.querySelector('[data-accion="cancelar"]').addEventListener('click', cerrar);
    popup.querySelector('[data-accion="limpiar"]').addEventListener('click', vaciar);

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
