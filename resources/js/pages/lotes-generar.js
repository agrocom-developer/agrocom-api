/**
 * Lotes en bloque: "Crear Lotes" (HU-72 reconstruida, 16/9/2026) y "Editar en
 * bloque" (19/9/2026). Las dos pantallas arman el mismo formulario
 * (`propiedades/_lotes-bloque-formulario.blade.php`) — un solo juego de
 * valores que se aplica a todos los lotes, sin filas dinámicas — y comparten
 * este módulo. Hace tres cosas:
 *
 * 1. El mismo toggle "limpio" → grado de obstáculos que `lotes-form.js`.
 * 2. La ayuda en vivo de las hectáreas por lote, con el patrón de "Adelanto
 *    Solicitado" de `contratos-form.js`: las plantillas de texto viajan en
 *    `data-plantilla-ayuda*` del propio input, se recalcula al escribir y la
 *    ayuda alterna `ag-input__help--accent` (la sugerencia: superficie de la
 *    propiedad ÷ total de lotes) con `ag-input__help--alert` (lo escrito, por
 *    el total de lotes, supera esa superficie). Solo informa, no bloquea.
 * 3. Solo en "editar": al bajar la cantidad avisa qué lotes se quitan (los
 *    últimos creados) y pide confirmación en el modal del panel antes de enviar.
 *
 * Las hectáreas se manejan como centésimas enteras (BigInt) y no como
 * `Number`: son DECIMAL, nunca float (invariante 6 de CLAUDE.md). La
 * sugerencia se trunca, no se redondea, para que repartirla nunca supere la
 * superficie (200 ha entre 3 lotes son 66,66 — no 66,67, que sumaría 200,01).
 *
 * Guard de presencia en el DOM (mismo criterio que `login.js`): en
 * cualquier página sin `[data-ag-lotes-generar-form]` este módulo no hace
 * nada.
 */

const MAXIMO_CODIGOS_VISIBLES = 6;

/** "1500.5" → 150050n. Lee dígitos, no números: `null` si no es un decimal positivo. */
function aCentesimas(texto) {
    const coincidencia = /^\s*(\d+)(?:\.(\d*))?\s*$/.exec(texto ?? '');

    if (!coincidencia) {
        return null;
    }

    return BigInt(coincidencia[1]) * 100n + BigInt(`${coincidencia[2] ?? ''}00`.slice(0, 2));
}

/** 12550n → "125,50". El mismo formato que la ayuda de "Adelanto Solicitado". */
function formatearHectareas(centesimas) {
    return (Number(centesimas) / 100).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/** Reemplaza cada `:marca` de la plantilla — sin `String.replace`, que interpreta `$&` de un valor. */
function sustituir(plantilla, valores) {
    return Object.entries(valores).reduce((texto, [marca, valor]) => texto.split(marca).join(valor), plantilla ?? '');
}

function inicializarSwitchLimpio(formulario) {
    const switchLimpio = formulario.querySelector('[data-ag-lote-limpio]');
    const envoltorioGrado = formulario.querySelector('[data-ag-lote-grado-obstaculos-wrap]');

    if (!switchLimpio || !envoltorioGrado) return;

    const textoLabel = switchLimpio.closest('.ag-switch__control')?.querySelector('.ag-switch__label');
    const textoSi = switchLimpio.dataset.agLoteLimpioTextoSi;
    const textoNo = switchLimpio.dataset.agLoteLimpioTextoNo;

    switchLimpio.addEventListener('change', () => {
        envoltorioGrado.hidden = switchLimpio.checked;
        if (textoLabel) {
            textoLabel.textContent = switchLimpio.checked ? textoSi : textoNo;
        }
    });
}

function inicializarAyudaHectareas(formulario) {
    const cantidad = formulario.querySelector('[data-ag-lotes-cantidad]');
    const hectareas = formulario.querySelector('[data-ag-lotes-hectareas]');
    const ayuda = hectareas ? document.getElementById(`${hectareas.id}-help`) : null;
    const superficie = aCentesimas(formulario.dataset.agLotesSuperficie);

    // Sin superficie cargada en la propiedad no hay nada que repartir: queda
    // el texto del servidor, que dice que hace falta cargarla.
    if (!cantidad || !hectareas || !ayuda || superficie === null || superficie <= 0n) return;

    const base = Number.parseInt(formulario.dataset.agLotesBase ?? '0', 10) || 0;
    const textoInicial = ayuda.textContent;
    const { plantillaAyuda, plantillaAyudaUno, plantillaAyudaExceso, plantillaAyudaExcesoUno } = hectareas.dataset;

    const pintar = (texto, alerta) => {
        ayuda.textContent = texto;
        ayuda.classList.toggle('ag-input__help--accent', !alerta);
        ayuda.classList.toggle('ag-input__help--alert', alerta);
    };

    const recalcular = () => {
        const pedidos = Number.parseInt(cantidad.value, 10);

        if (!Number.isInteger(pedidos) || pedidos < 1) {
            pintar(textoInicial, false);
            return;
        }

        const lotes = base + pedidos;
        const sugerido = superficie / BigInt(lotes);
        const escrito = aCentesimas(hectareas.value);
        const total = escrito === null ? null : escrito * BigInt(lotes);
        const excede = total !== null && total > superficie;

        let plantilla = lotes === 1 ? plantillaAyudaUno : plantillaAyuda;
        if (excede) {
            plantilla = lotes === 1 ? plantillaAyudaExcesoUno : plantillaAyudaExceso;
        }

        pintar(sustituir(plantilla, {
            ':hectareas': formatearHectareas(excede ? escrito : sugerido),
            ':sugerido': formatearHectareas(sugerido),
            ':superficie': formatearHectareas(superficie),
            ':lotes': String(lotes),
            ':total': total === null ? '' : formatearHectareas(total),
        }), excede);
    };

    [cantidad, hectareas].forEach((entrada) => entrada.addEventListener('input', recalcular));

    recalcular();
}

function inicializarAvisoDeBajas(formulario) {
    const aviso = formulario.querySelector('[data-ag-lotes-quitar-aviso]');
    const textoAviso = aviso?.querySelector('[data-ag-lotes-quitar-texto]');
    const cantidad = formulario.querySelector('[data-ag-lotes-cantidad]');

    if (!aviso || !textoAviso || !cantidad) return;

    let codigos = [];
    try {
        codigos = JSON.parse(formulario.dataset.agLotesCodigos ?? '[]');
    } catch {
        return;
    }

    if (codigos.length === 0) return;

    const { agLotesQuitarVarios, agLotesQuitarUno, agLotesQuitarMas } = formulario.dataset;

    // Los códigos vienen en orden de alta: los últimos son los que se quitan.
    const sobrantes = () => {
        const pedidos = Number.parseInt(cantidad.value, 10);

        return Number.isInteger(pedidos) && pedidos >= 1 && pedidos < codigos.length ? codigos.slice(pedidos) : [];
    };

    const redactar = (quitar) => {
        const visibles = quitar.slice(0, MAXIMO_CODIGOS_VISIBLES);
        const resto = quitar.length - visibles.length;
        const lista = resto > 0
            ? `${visibles.join(', ')} ${sustituir(agLotesQuitarMas, { ':cantidad': String(resto) })}`
            : visibles.join(', ');

        return sustituir(quitar.length === 1 ? agLotesQuitarUno : agLotesQuitarVarios, {
            ':cantidad': String(quitar.length),
            ':codigos': lista,
        });
    };

    const actualizar = () => {
        const quitar = sobrantes();

        aviso.hidden = quitar.length === 0;
        if (quitar.length > 0) {
            textoAviso.textContent = redactar(quitar);
        }
    };

    cantidad.addEventListener('input', actualizar);
    actualizar();

    // Confirmación con el modal del panel (no `confirm()` nativo). El botón
    // "Confirmar" del modal es un submit del mismo form: al reconocerlo por
    // `submitter` se deja pasar y el envío sigue.
    const modal = document.getElementById(formulario.dataset.agLotesModalQuitar ?? '');
    const Modal = window.bootstrap?.Modal;

    if (!modal || !Modal) return;

    const mensaje = modal.querySelector('.ag-confirm-modal__message');
    const notaFija = mensaje?.textContent ?? '';

    formulario.addEventListener('submit', (evento) => {
        const quitar = sobrantes();

        if (quitar.length === 0 || (evento.submitter && modal.contains(evento.submitter))) return;

        evento.preventDefault();
        if (mensaje) {
            mensaje.textContent = `${redactar(quitar)} ${notaFija}`;
        }
        Modal.getOrCreateInstance(modal).show();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const formulario = document.querySelector('[data-ag-lotes-generar-form]');
    if (!formulario) return;

    inicializarSwitchLimpio(formulario);
    inicializarAyudaHectareas(formulario);
    inicializarAvisoDeBajas(formulario);
});
