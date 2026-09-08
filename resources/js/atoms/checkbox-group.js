// Comportamiento del átomo `checkbox-group`
// (resources/views/components/atoms/checkbox-group.blade.php). A diferencia
// de atoms/select.js, acá NO se arma ningún control falso: las casillas ya
// son `<input type="checkbox">` reales, tabulables, con su propio `<label>`.
// Lo único que agrega este script es un filtro de texto que oculta/muestra
// filas de la lista — si no carga, la búsqueda ni se muestra (queda `hidden`
// de fábrica en el marcado) y la lista completa sigue visible y usable.
//
// Sin dependencias externas — mismo criterio que atoms/select.js.

function normalizar(texto) {
    return texto
        .toLocaleLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();
}

function inicializar(root) {
    const buscadorWrap = root.querySelector('[data-ag-checkbox-group-search-wrap]');
    const buscador = root.querySelector('[data-ag-checkbox-group-search]');
    const lista = root.querySelector('[data-ag-checkbox-group-list]');
    const vacio = root.querySelector('[data-ag-checkbox-group-empty]');

    if (!buscadorWrap || !buscador || !lista) {
        return;
    }

    const items = Array.from(lista.querySelectorAll('[data-ag-checkbox-group-item]')).map((item) => ({
        elemento: item,
        etiqueta: normalizar(item.querySelector('.ag-checkbox-group__option-label')?.textContent ?? ''),
    }));

    function filtrar() {
        const termino = normalizar(buscador.value);
        let visibles = 0;

        items.forEach(({ elemento, etiqueta }) => {
            const coincide = termino === '' || etiqueta.includes(termino);
            elemento.hidden = !coincide;
            if (coincide) {
                visibles += 1;
            }
        });

        if (vacio) {
            vacio.hidden = visibles > 0;
        }
    }

    buscador.addEventListener('input', filtrar);

    buscadorWrap.hidden = false;
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-ag-checkbox-group]').forEach(inicializar);
});
