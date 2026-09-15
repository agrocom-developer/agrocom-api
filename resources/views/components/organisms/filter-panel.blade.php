{{--
    Organism: filter-panel (`.ag-filter-panel`)
    Botón "Filtros" (outline, con contador) que despliega un panel con TODOS
    los campos de un listado — reemplaza al form `.ag-filtros` que antes
    quedaba siempre visible debajo del buscador. Un solo patrón para toda
    pantalla, tenga 1 filtro (Usuarios) o 6 (Bitácora): antes, una página con
    muchos filtros terminaba con una lista vertical larga porque cada campo
    ocupa su propia línea al envolver — acá siempre son 2 columnas dentro del
    panel, sin importar cuántos campos traiga.

    Es organism (no molecule) por el mismo criterio que `topbar`: orquesta
    átomos de campo + botones y tiene JS propio (el dropdown de Bootstrap,
    pre-instanciado con `strategy: fixed` en resources/js/app.js — el mismo
    fix que ya necesitaron notifications-menu/user-menu para no recortarse
    contra el `overflow` de `.ag-panel__content`).

    `data-bs-auto-close="outside"` en el trigger: el default de Bootstrap
    (`true`) cierra el dropdown con CUALQUIER click, esté dentro o fuera del
    menú — sin este atributo, clickear el combobox de `atoms/select` o abrir
    el calendario de `atoms/date` cierra el panel entero antes de poder
    elegir nada (pedido directo del usuario, 15/9/2026).

    El buscador (molecules/table-search) NO vive acá adentro: sigue siendo
    un componente aparte, siempre visible, con su propio auto-submit por
    debounce. Este panel es solo para los campos que necesitan submit
    explícito (select/date/input).

    "Limpiar filtros" vive AFUERA del panel, al lado del botón trigger —
    mismo patrón que el link "Limpiar" de molecules/table-search (reusa su
    misma clase `.ag-table-search__clear`), para poder limpiar sin necesidad
    de abrir el panel primero (pedido directo del usuario, 15/9/2026).

    Props:
    - action (string, requerido): URL del listado.
    - activeCount (int, default 0): cantidad de filtros con valor — quien
      arma la página ya calcula `$hayFiltrosActivos`, este es el mismo
      cálculo pero contando cuántos, no si hay alguno. El badge y el link
      "Limpiar" solo se pintan si es mayor a 0.
    - triggerLabel/applyLabel/clearLabel (nullable): textos ya traducidos.
      Sin pasarlos, caen en las claves genéricas de `ui.tabla.*` (mismo
      chrome en toda pantalla — no hace falta redefinirlas por dominio).

    El slot es el contenido del form: los `<x-atoms.select>`/`<x-atoms.date>`/
    `<x-atoms.input>` que cada página ya arma, más cualquier `<input type="hidden">`
    que necesite preservar (p. ej. el término de búsqueda `q`, para no perderlo
    al aplicar un filtro). Van dentro de `.ag-form-section__body` (el grid de
    2 columnas de molecules/form-section) por composición, no un grid nuevo
    — mismo criterio que ya usan las filas repetibles de formulario (ver
    docs/diseno/guia_pantalla_panel.md §6.3.2).
--}}
@props([
    'action',
    'activeCount' => 0,
    'triggerLabel' => null,
    'applyLabel' => null,
    'clearLabel' => null,
])

<div {{ $attributes->class(['ag-filter-panel-wrap']) }}>
    <div class="dropdown ag-filter-panel">
        <button
            type="button"
            class="ag-button ag-button--outline ag-button--md ag-filter-panel__trigger"
            data-bs-toggle="dropdown"
            data-bs-auto-close="outside"
            aria-expanded="false"
        >
            <x-atoms.icon name="filter_list" size="sm" class="ag-button__icon" />
            <span class="ag-button__label">{{ $triggerLabel ?? __('ui.tabla.filtros_boton') }}</span>
            @if ($activeCount > 0)
                <x-atoms.badge variant="accent" class="ag-filter-panel__count">{{ $activeCount }}</x-atoms.badge>
            @endif
        </button>

        <form method="GET" action="{{ $action }}" class="dropdown-menu dropdown-menu-end ag-filter-panel__menu">
            <div class="ag-form-section__body ag-filter-panel__grid">
                {{ $slot }}
            </div>

            <div class="ag-filter-panel__actions">
                <x-atoms.button type="submit" variant="outline" size="md" icon="search">
                    {{ $applyLabel ?? __('ui.tabla.filtros_aplicar') }}
                </x-atoms.button>
            </div>
        </form>
    </div>

    @if ($activeCount > 0)
        <a href="{{ $action }}" class="ag-table-search__clear">{{ $clearLabel ?? __('ui.tabla.filtros_limpiar') }}</a>
    @endif
</div>
