{{--
    Molecule: table-search (`.ag-table-search`)
    Buscador acotado a UN listado, para reemplazar la barra `.ag-filtros`
    tradicional cuando el único filtro real es una búsqueda por texto — vive
    pegado a la tabla (arriba a la derecha), no como franja aparte. Mismo
    lenguaje visual que el buscador global del header (`organisms/topbar`:
    ícono + campo + caja redondeada), pero es OTRO componente: el del header
    manda a `panel.buscar` (búsqueda global entre entidades), este envía un
    GET normal a la ruta del propio listado.

    Props:
    - action (string, requerido): URL del listado (`route('panel.x.index')`).
    - name (string, default "q"): nombre del parámetro GET.
    - value (string|null): valor aplicado, para dejar el campo con el texto
      tras el submit.
    - placeholder (string|null): ya traducido por el llamador.
    - clearLabel (string|null): ya traducido por el llamador. Con un `value`
      activo, se muestra un link "Limpiar" al lado para volver a la tabla
      sin filtrar — sin esta prop no se muestra (algunos llamadores prefieren
      resolver el "limpiar" por otro lado, p. ej. junto a más filtros).
--}}
@props([
    'action',
    'name' => 'q',
    'value' => null,
    'placeholder' => null,
    'clearLabel' => null,
])

<div class="ag-table-search-wrap">
    <form
        method="GET"
        action="{{ $action }}"
        role="search"
        {{ $attributes->class(['ag-table-search']) }}
    >
        <x-atoms.icon name="search" size="sm" class="ag-table-search__icon" />
        <input
            type="search"
            name="{{ $name }}"
            value="{{ $value }}"
            class="ag-table-search__input"
            placeholder="{{ $placeholder }}"
            autocomplete="off"
        >
    </form>

    @if ($clearLabel && $value !== null && $value !== '')
        <a href="{{ $action }}" class="ag-table-search__clear">{{ $clearLabel }}</a>
    @endif
</div>
