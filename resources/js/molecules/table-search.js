// Comportamiento de molecules/table-search (resources/views/components/
// molecules/table-search.blade.php): autoenvía el formulario tras una pausa
// de tipeo en vez de depender de Enter. Sin librería de terceros (ADR 0002:
// una que agregara esto —tipo DataTables— trae su propio jQuery, su propia
// <table>, y su propio tema de colores que pelea con el theming claro/
// oscuro) — alcanza con un debounce chico, mismo criterio sin-dependencias
// que atoms/input.js.
//
// Enter sigue funcionando solo: es un <input> dentro de un <form>, el
// navegador ya lo envía sin JS.

const DEMORA_MS = 400;
const temporizadores = new WeakMap();

document.addEventListener('input', (event) => {
    const campo = event.target.closest('.ag-table-search__input');

    if (!campo) {
        return;
    }

    const formulario = campo.closest('form');

    if (!formulario) {
        return;
    }

    clearTimeout(temporizadores.get(campo));
    temporizadores.set(campo, setTimeout(() => formulario.requestSubmit(), DEMORA_MS));
});
