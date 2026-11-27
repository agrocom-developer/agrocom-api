/**
 * shared/color-tokens.js — lee los tokens CSS del panel ya resueltos, para los
 * lienzos (canvas) y las librerías de JS que piden colores y medidas literales y
 * no entienden `var(--ag-…)`. El token se aplica a un elemento oculto y se lee su
 * valor calculado: así se respeta el tema vigente y los `color-mix()` salen
 * mezclados. Nada del panel declara un color propio en JS; todo pasa por acá.
 */

let sonda = null;
let lienzoDeColor = null;

function obtenerSonda() {
    if (sonda === null || !sonda.isConnected) {
        sonda = document.createElement('span');
        sonda.setAttribute('aria-hidden', 'true');
        sonda.style.cssText = 'position:absolute;visibility:hidden;pointer-events:none;width:0;height:0;overflow:hidden;';
        document.body.appendChild(sonda);
    }

    return sonda;
}

/** El valor calculado de una propiedad CSS al aplicarle un token (`--ag-…`). */
function calcular(propiedad, token) {
    const el = obtenerSonda();
    el.style[propiedad] = `var(${token})`;
    const valor = getComputedStyle(el)[propiedad];
    el.style[propiedad] = '';

    return valor;
}

/**
 * El color de un token como `rgb()`/`rgba()`. Los navegadores devuelven los
 * `color-mix()` en formatos como `color(srgb …)` que ni el canvas de las librerías
 * ni sus analizadores de color aceptan siempre: se pinta un píxel y se lee de vuelta.
 */
export function leerColorToken(token) {
    const calculado = calcular('color', token);

    if (lienzoDeColor === null) {
        lienzoDeColor = document.createElement('canvas');
        lienzoDeColor.width = 1;
        lienzoDeColor.height = 1;
    }
    const ctx = lienzoDeColor.getContext('2d', { willReadFrequently: true });
    ctx.clearRect(0, 0, 1, 1);
    ctx.fillStyle = calculado;
    ctx.fillRect(0, 0, 1, 1);
    const [r, g, b, a] = ctx.getImageData(0, 0, 1, 1).data;

    // Se serializa el resultado de leer el token; no es un color propio.
    const canales = a === 255 ? [r, g, b] : [r, g, b, (a / 255).toFixed(3)];
    const funcion = a === 255 ? 'rgb' : 'rgba';

    return `${funcion}(${canales.join(', ')})`;
}

/** Una medida (`--ag-space-*`, `--ag-font-size-*`, `--ag-radius-*`) en píxeles. */
export function leerMedidaToken(token, propiedad = 'width') {
    const px = Number.parseFloat(calcular(propiedad, token));

    return Number.isFinite(px) ? px : 0;
}

/** Una familia tipográfica (`--ag-font-family-*`), lista para el `font` de un canvas. */
export function leerFamiliaToken(token) {
    return calcular('fontFamily', token);
}

/** Un peso de fuente (`--ag-font-weight-*`) como número. */
export function leerPesoToken(token) {
    return Number.parseInt(calcular('fontWeight', token), 10) || 400;
}

/** La duración, en milisegundos, de un token de transición (`--ag-transition-*`). */
export function leerDuracionToken(token) {
    const el = obtenerSonda();
    el.style.transition = `var(${token})`;
    const duracion = getComputedStyle(el).transitionDuration.split(',')[0].trim();
    el.style.transition = '';
    const valor = Number.parseFloat(duracion);

    if (!Number.isFinite(valor)) {
        return 0;
    }

    return duracion.endsWith('ms') ? valor : valor * 1000;
}
