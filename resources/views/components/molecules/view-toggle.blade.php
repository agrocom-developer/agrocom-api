{{--
    Molecule: view-toggle (17/9/2026 — alterna lista/grilla de un listado;
    reescrito el mismo día a 100% client-side: la primera versión usaba dos
    links `?vista=lista|grilla` con recarga GET completa — pedido explícito
    de corregirlo, esa recarga se sentía como un parpadeo de página en cada
    click). Ahora: dos botones que alternan la visibilidad de dos bloques ya
    renderizados por el servidor (nunca los arma por JS — ambos existen
    siempre en el DOM, uno con `hidden`), y recuerdan la preferencia en
    `localStorage` — mismo mecanismo que ya usa `module-sidebar.js` para
    colapsar/expandir el sidebar (persistencia sin servidor, sin recarga).

    JS en `resources/js/molecules/view-toggle.js` (delegación de eventos,
    sin dependencias, mismo criterio que el resto del catálogo).

    Props:
    - current ('lista'|'grilla', default 'lista'): vista con la que el
      servidor arrancó (antes de que el JS aplique `localStorage`, si había
      una preferencia distinta guardada — ver el script inline que arma la
      página, evita el parpadeo al cargar).
    - resultsId (requerido): id del contenedor que agrupa los paneles
      `[data-ag-vista-panel="lista"|"grilla"]` a alternar.
    - storageKey (requerido): clave de `localStorage` para esta preferencia
      — cada listado con este toggle usa la suya (p. ej.
      "agrocom:ordenes:vista"), para no pisarse entre pantallas.
    - listLabel/gridLabel (nullable): texto accesible de cada botón, ya
      traducido — sin pasarlos, caen en `ui.tabla.vista_lista`/`vista_grilla`.
--}}
@props([
    'current' => 'lista',
    'resultsId',
    'storageKey',
    'listLabel' => null,
    'gridLabel' => null,
])

<div
    {{ $attributes->class(['ag-view-toggle']) }}
    role="group"
    aria-label="{{ __('ui.tabla.vista_grupo') }}"
    data-ag-view-toggle
    data-resultados="{{ $resultsId }}"
    data-storage-key="{{ $storageKey }}"
>
    <button
        type="button"
        class="ag-view-toggle__btn {{ $current === 'lista' ? 'is-active' : '' }}"
        data-ag-vista-btn="lista"
        aria-pressed="{{ $current === 'lista' ? 'true' : 'false' }}"
        title="{{ $listLabel ?? __('ui.tabla.vista_lista') }}"
    >
        <x-atoms.icon name="view_list" size="sm" />
    </button>
    <button
        type="button"
        class="ag-view-toggle__btn {{ $current === 'grilla' ? 'is-active' : '' }}"
        data-ag-vista-btn="grilla"
        aria-pressed="{{ $current === 'grilla' ? 'true' : 'false' }}"
        title="{{ $gridLabel ?? __('ui.tabla.vista_grilla') }}"
    >
        <x-atoms.icon name="grid_view" size="sm" />
    </button>
</div>
