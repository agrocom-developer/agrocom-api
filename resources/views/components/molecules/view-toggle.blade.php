{{--
    Molecule: view-toggle (17/9/2026 — alterna lista/grilla en un listado)
    Dos botones ícono (lista/grilla), cada uno un link GET normal a la MISMA
    ruta del listado con `?vista=lista|grilla` — sin JS propio, mismo
    criterio "sin Livewire" del resto del panel: cambiar de vista es una
    navegación de servidor, no un estado de cliente.

    Props:
    - action (requerido): URL base del listado (`route('panel.x.index')`).
    - current ('lista'|'grilla', requerido): vista activa — decide qué botón
      lleva `aria-pressed="true"` y la clase `is-active`.
    - query (array, default []): resto de filtros/búsqueda a preservar en el
      link (p. ej. `request()->except('vista')`) — el llamador arma el
      array; este componente no conoce nombres de campos de ningún dominio
      (DIP, §3 de la guía de pantalla).
    - listLabel/gridLabel (nullable): texto accesible de cada botón, ya
      traducido — sin pasarlos, caen en `ui.tabla.vista_lista`/`vista_grilla`.
--}}
@props([
    'action',
    'current',
    'query' => [],
    'listLabel' => null,
    'gridLabel' => null,
])

<div {{ $attributes->class(['ag-view-toggle']) }} role="group" aria-label="{{ __('ui.tabla.vista_grupo') }}">
    <a
        href="{{ $action.'?'.http_build_query([...$query, 'vista' => 'lista']) }}"
        class="ag-view-toggle__btn {{ $current === 'lista' ? 'is-active' : '' }}"
        aria-pressed="{{ $current === 'lista' ? 'true' : 'false' }}"
        title="{{ $listLabel ?? __('ui.tabla.vista_lista') }}"
    >
        <x-atoms.icon name="view_list" size="sm" />
    </a>
    <a
        href="{{ $action.'?'.http_build_query([...$query, 'vista' => 'grilla']) }}"
        class="ag-view-toggle__btn {{ $current === 'grilla' ? 'is-active' : '' }}"
        aria-pressed="{{ $current === 'grilla' ? 'true' : 'false' }}"
        title="{{ $gridLabel ?? __('ui.tabla.vista_grilla') }}"
    >
        <x-atoms.icon name="grid_view" size="sm" />
    </a>
</div>
