/**
 * Detalle de una orden de aplicación (`ordenes/show`): pagina la tabla de Lotes
 * de 20 en 20, con el mismo paginador que la tabla de lotes del contrato
 * (`shared/paginador-cliente.js`). Las filas ya vienen todas del servidor; las
 * de otras páginas se ocultan con `hidden`, no se quitan.
 *
 * Los textos del paginador llegan en el `data-*` del contenedor
 * (`[data-ag-lotes-detalle-paginador]`), armados por el Blade (el JS no traduce).
 */

import { paginarFilas } from '../shared/paginador-cliente.js';

const LOTES_POR_PAGINA = 20;

document.addEventListener('DOMContentLoaded', () => {
    const tabla = document.querySelector('[data-ag-lotes-detalle]');
    const contenedor = document.querySelector('[data-ag-lotes-detalle-paginador]');

    if (!tabla || !contenedor) return;

    paginarFilas(contenedor, {
        filas: () => Array.from(tabla.querySelectorAll('.ag-index-table__row')),
        porPagina: LOTES_POR_PAGINA,
    }).actualizar(1);
});
