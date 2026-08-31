/**
 * shared/color-tokens.js — resolución de tokens `--ag-color-*`/`--ag-font-*`
 * a valores CSS reales, para consumidores que no pueden simplemente escribir
 * `var(--ag-color-x)` en una hoja de estilos (ApexCharts, Leaflet: piden un
 * string de color ya resuelto en JS, nunca un hex hardcodeado — CLAUDE.md
 * invariante 11).
 *
 * `getComputedStyle(documentElement).getPropertyValue('--ag-color-x')` NO
 * alcanza para tokens que usan `color-mix()`: varios navegadores devuelven el
 * texto tal cual está escrito, no el color mezclado. Aplicar el token a una
 * propiedad real (`color`) de un elemento y leer `getComputedStyle().color`
 * fuerza la resolución completa a `rgb()`.
 */

let auxiliar = null;

export function leerColorToken(nombreToken) {
    auxiliar ??= (() => {
        const el = document.createElement('span');
        el.style.display = 'none';
        document.body.appendChild(el);
        return el;
    })();

    auxiliar.style.color = `var(${nombreToken})`;

    return getComputedStyle(auxiliar).color;
}

export function leerColoresTokens(nombresTokens) {
    return nombresTokens.map(leerColorToken);
}

export function leerTokenCrudo(nombreToken) {
    return getComputedStyle(document.documentElement).getPropertyValue(nombreToken).trim();
}
