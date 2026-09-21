/**
 * Borrador de formulario: lo que ya se cargó en un formulario no se pierde al
 * salir por un botón de alta rápida (el `action-href` de `atoms/select`, o
 * cualquier enlace con `data-ag-link-accent`) a crear el objeto que falta —un
 * cliente, una propiedad, una cuadrilla— y volver.
 *
 * Se guarda al hacer clic en ese enlace y se repone al volver a la MISMA ruta;
 * es de un solo uso (se borra al leerlo y al enviar el formulario). Vive en
 * `sessionStorage`: si no está disponible, solo se pierde la comodidad.
 *
 * Cada control se guarda según lo que es, no según lo que mandaría el envío:
 *
 * - switch y casilla (`checkbox`): si está tildado o no, uno por uno. Un
 *   `FormData` no sirve para esto: la casilla sin tildar no viaja, y el
 *   `<input type="hidden" value="0">` que la acompaña con su mismo nombre sí
 *   —al reponer por nombre se escribía sobre el oculto y el switch volvía
 *   siempre apagado (21/9/2026).
 * - `radio`: cuál del grupo quedó elegido.
 * - todo lo demás (texto, número, fecha, `range`, select, textarea): su valor.
 *
 * Los ocultos no se guardan ni se pisan: los arma el servidor o el JS de la
 * pantalla, que es quien sabe reponerlos (p. ej. las filas de lotes del
 * contrato, ver `contratos-form.js`).
 *
 * Todo formulario con un enlace de alta rápida lo tiene solo, sin hacer nada.
 * Una pantalla que además necesita guardar algo propio se declara con
 * `data-ag-borrador="propio"` en el `<form>` y usa `guardarBorrador()` /
 * `leerBorrador()` / `restaurarCampos()` desde su módulo.
 */

const PREFIJO_CLAVE = 'ag_borrador:';
const SELECTOR_ENLACE = '[data-ag-link-accent]';
const TIPOS_QUE_NO_SE_GUARDAN = new Set(['hidden', 'file', 'password', 'submit', 'button', 'reset', 'image']);

const claveDe = (ruta) => `${PREFIJO_CLAVE}${ruta}`;

/** Controles con nombre que el borrador guarda, en el orden del formulario. */
function controlesDe(formulario) {
    return Array.from(formulario.elements).filter((control) => control.name
        && !TIPOS_QUE_NO_SE_GUARDAN.has(control.type)
        && control.tagName !== 'BUTTON'
        && control.tagName !== 'FIELDSET');
}

/**
 * Foto de los campos del formulario.
 *
 * @returns {{valores: Object<string, string[]>, marcados: Object<string, boolean>, elegidos: Object<string, string|null>}}
 */
export function capturarCampos(formulario) {
    const campos = { valores: {}, marcados: {}, elegidos: {} };

    controlesDe(formulario).forEach((control) => {
        if (control.type === 'checkbox') {
            // Las casillas de un grupo comparten nombre: se distinguen por valor.
            campos.marcados[`${control.name}::${control.value}`] = control.checked;
        } else if (control.type === 'radio') {
            if (control.checked) campos.elegidos[control.name] = control.value;
            else if (!(control.name in campos.elegidos)) campos.elegidos[control.name] = null;
        } else if (control.type === 'select-multiple') {
            campos.valores[control.name] = Array.from(control.selectedOptions).map((opcion) => opcion.value);
        } else if (!control.disabled) {
            // Varios campos con el mismo nombre (`telefonos[]`): por posición.
            (campos.valores[control.name] ??= []).push(control.value);
        }
    });

    return campos;
}

const avisarCambio = (control) => {
    control.dispatchEvent(new Event('input', { bubbles: true }));
    control.dispatchEvent(new Event('change', { bubbles: true }));
};

/**
 * Devuelve a cada campo lo que tenía. Tres cuidados con los campos de valor:
 * uno vacío no pisa lo que el formulario ya trae (p. ej. la campaña activa que
 * el alta ofrece elegida); un select solo acepta una opción que este formulario
 * ofrece; y después de escribir se avisa al campo (`input`/`change`) para que
 * los controles propios —el select con su etiqueta, la fecha con su disparador,
 * un total calculado— se repinten en vez de quedar mostrando otra cosa.
 *
 * `silenciar(control)` deja sin aviso a un campo cuyo `change` hace algo más
 * que repintar (en contratos, cambiar el cliente vacía propiedades y lotes): la
 * pantalla lo avisa después, cuando le conviene.
 */
export function restaurarCampos(formulario, campos, { silenciar = () => false } = {}) {
    if (!campos || typeof campos !== 'object') return;

    const valores = campos.valores ?? {};
    const marcados = campos.marcados ?? {};
    const elegidos = campos.elegidos ?? {};
    const posicion = {};

    controlesDe(formulario).forEach((control) => {
        let cambio = false;

        if (control.type === 'checkbox') {
            const clave = `${control.name}::${control.value}`;
            if (!(clave in marcados)) return;
            cambio = control.checked !== marcados[clave];
            control.checked = marcados[clave];
        } else if (control.type === 'radio') {
            if (!(control.name in elegidos) || elegidos[control.name] === null) return;
            const elegido = control.value === elegidos[control.name];
            cambio = elegido && !control.checked;
            control.checked = elegido;
        } else if (control.type === 'select-multiple') {
            if (!(control.name in valores)) return;
            Array.from(control.options).forEach((opcion) => {
                const elegida = valores[control.name].includes(opcion.value);
                cambio ||= opcion.selected !== elegida;
                opcion.selected = elegida;
            });
        } else {
            const indice = posicion[control.name] ?? 0;
            posicion[control.name] = indice + 1;

            const guardado = valores[control.name]?.[indice];
            if (guardado === undefined || guardado === null) return;

            const texto = String(guardado);
            if (texto === '' && control.value !== '') return;
            if (control.tagName === 'SELECT' && !Array.from(control.options).some((opcion) => opcion.value === texto)) return;
            if (control.value === texto) return;

            control.value = texto;
            cambio = true;
        }

        if (cambio && !silenciar(control)) avisarCambio(control);
    });
}

/**
 * Guarda el borrador de este formulario. `extra` es lo que la pantalla quiera
 * sumar por su cuenta (debe poder pasar por `JSON.stringify`).
 */
export function guardarBorrador(formulario, extra = null) {
    try {
        sessionStorage.setItem(
            claveDe(window.location.pathname),
            JSON.stringify({ campos: capturarCampos(formulario), extra }),
        );
    } catch {
        // Sin sessionStorage (modo privado estricto) solo se pierde la comodidad.
    }
}

/**
 * Lee el borrador de la ruta actual y lo borra: es de un solo uso, así no
 * reaparece en un alta posterior. La clave lleva la ruta, de modo que el alta y
 * la edición de cada registro tienen cada una el suyo y ninguno pisa a otro
 * —tampoco cuando se encadenan dos altas rápidas (contrato → propiedad → cliente).
 *
 * @returns {{campos: object, extra: any}|null}
 */
export function leerBorrador() {
    try {
        const clave = claveDe(window.location.pathname);
        const crudo = sessionStorage.getItem(clave);
        if (crudo === null) return null;

        sessionStorage.removeItem(clave);

        const borrador = JSON.parse(crudo);
        return borrador && typeof borrador === 'object' ? borrador : null;
    } catch {
        return null;
    }
}

export function descartarBorrador() {
    try {
        sessionStorage.removeItem(claveDe(window.location.pathname));
    } catch {
        // Nada que descartar.
    }
}

// Todo formulario con un enlace de alta rápida, salvo el que lleva el suyo.
document.addEventListener('DOMContentLoaded', () => {
    const formularios = Array.from(document.querySelectorAll('form'))
        .filter((formulario) => formulario.dataset.agBorrador !== 'propio' && formulario.querySelector(SELECTOR_ENLACE));

    if (formularios.length !== 1) {
        // Ninguno, o más de uno en la misma ruta (no se sabría de cuál es el borrador).
        return;
    }

    const [formulario] = formularios;

    const borrador = leerBorrador();
    if (borrador) restaurarCampos(formulario, borrador.campos);

    formulario.addEventListener('click', (evento) => {
        if (evento.target.closest(SELECTOR_ENLACE)) guardarBorrador(formulario);
    });

    formulario.addEventListener('submit', descartarBorrador);
});
