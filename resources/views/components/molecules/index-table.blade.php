{{--
    Molecule: index-table (arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md)

    Envuelve la tabla de un listado: contenedor con borde/radio/superficie,
    fila de cabecera (`role="row"`, columnas en mayúscula muted) y filas de
    datos con hover, colapsando a una columna por fila en mobile (<992px,
    ocultando índice y cabecera). Lo único que cambia de una pantalla a otra
    es cuántas columnas tiene y de qué ancho — el resto (borde, tipografía,
    hover, colapso responsive) es siempre igual.

    No compone otros componentes del catálogo: la cabecera y las filas son
    contenido del llamador (que suele incluir `atoms/badge` u
    `organisms/row-actions` dentro de una celda) — mismo criterio que
    `molecules/form-section` con sus campos, por eso molecule y no organism.

    Nace de clientes/propiedades/campanias/lotes/contratos.css, que
    declaraban la misma tabla con distinto nombre de clase
    (`.ag-clientes__tabla`/`__head`/`__fila`, etc.) letra por letra, incluido
    el mismo colapso mobile — ver docs/diseno/guia_pantalla_panel.md §6.2.

    Props:
    - columns (requerido): valor crudo de `grid-template-columns` para la
      cabecera y cada fila (p. ej. `'3rem 2fr 1fr 1fr var(--ag-row-actions-width)'`)
      — la única medida propia de cada pantalla. Viaja como custom property
      inline (`--ag-index-table-columns`), mismo mecanismo que ya usa
      `molecules/progress-meter` para su `--ag-progress-meter-percent`.

    Slots:
    - head (requerido): columnheaders (`<span role="columnheader">...`) — la
      clase `ag-index-table__indice`/`ag-index-table__acciones-head` va en el
      `<span>` que corresponda.
    - (default): filas, una por `@foreach` del llamador
      (`<div class="ag-index-table__row" role="row">...</div>`).
--}}
@props([
    'columns',
])

<div {{ $attributes->class(['ag-index-table']) }} role="table" style="--ag-index-table-columns: {{ $columns }}">
    <div class="ag-index-table__head" role="row">
        {{ $head }}
    </div>

    {{ $slot }}
</div>
