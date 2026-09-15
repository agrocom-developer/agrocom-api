{{--
    Molecule: menu-item (docs/diseno/sistema_diseno_panel.md §4.3)
    El ítem hoja del menú (ver organisms/collapsible-menu-group para el caso
    con submenú). Encaja directo con la forma de `ItemMenu`
    (App\Dominios\Seguridad\Aplicacion\ItemMenu): `label` es la CLAVE de
    traducción sin resolver (se resuelve acá vía `__()`, nunca antes —
    ADR 0013, docblock de ItemMenu), `icon`/`href` ya vienen resueltos por
    quien arma el árbol (sidebar-nav/collapsible-menu-group no llaman a
    `route()` ni conocen `sec_menu`).

    El elemento raíz (`<a>`/`<button>`) siempre lleva tooltip de Bootstrap
    con el label resuelto (`data-bs-toggle="tooltip"`, `data-bs-placement="right"`
    — el sidebar vive a la izquierda), pedido explícito del 28/8/2026: con
    `module-sidebar` colapsado (`.is-collapsed`, ver module-sidebar.css) el
    `__label`/`__badge` se ocultan y el ítem queda solo-ícono — sin esto no
    había forma de saber a qué ítem corresponde cada ícono sin expandir.
    Irrelevante-pero-inofensivo cuando el sidebar está expandido (el label ya
    es visible).

    Props:
    - label (requerido): clave de traducción (p. ej. "seguridad.menu.usuarios").
    - icon (nullable): nombre de ícono Material Symbols.
    - href (nullable): URL ya resuelta. Sin ella, renderiza <button> (grupo
      sin acción propia, o placeholder).
    - active (bool, default false): estado visual + `aria-current`.
    - badge (nullable, string|int): SOLO el número/contador corto que se
      pinta en el pill (p. ej. "3", "12") — nunca la frase completa.
    - badgeTitle (nullable string): frase completa del contador (p. ej.
      "Hoy · 3"), se resuelve como tooltip nativo de Bootstrap
      (`data-bs-toggle="tooltip"`, inicializado globalmente en app.js) sobre
      el badge — sin ella el badge no lleva tooltip.
    - permission (nullable string): código `sec_permission`, informativo —
      esta molécula no consulta `sec_*`, el filtrado ya ocurrió antes
      (App\Dominios\Seguridad\Aplicacion\ObtenerMenuPorRolActivo).
    - staggerIndex (nullable int): posición del ítem en su lista, para el
      efecto de aparición escalonada (ver menu-item.css) — puramente visual,
      quien arma la lista (sidebar-nav/collapsible-menu-group) lo pasa como
      el índice del `@foreach`.
--}}
@props([
    'label',
    'icon' => null,
    'href' => null,
    'active' => false,
    'badge' => null,
    'badgeTitle' => null,
    'permission' => null,
    'staggerIndex' => null,
])

@php
    $resolvedLabel = __($label);
    $classes = ['ag-menu-item', $active ? 'is-active' : ''];
    $style = $staggerIndex !== null ? "--ag-menu-item-index: {$staggerIndex}" : null;
@endphp

@if ($href)
    <a
        href="{{ $href }}"
        {{ $attributes->class($classes) }}
        @if ($style) style="{{ $style }}" @endif
        @if ($active) aria-current="page" @endif
        @if ($permission) data-ag-permission="{{ $permission }}" @endif
        data-bs-toggle="tooltip"
        data-bs-placement="right"
        data-bs-title="{{ $resolvedLabel }}"
    >
        @if ($icon)
            <x-atoms.icon :name="$icon" size="md" class="ag-menu-item__icon" />
        @endif
        <span class="ag-menu-item__label">{{ $resolvedLabel }}</span>
        @if ($badge !== null)
            <span
                class="ag-menu-item__badge"
                @if ($badgeTitle)
                    data-bs-toggle="tooltip"
                    data-bs-placement="right"
                    data-bs-title="{{ $badgeTitle }}"
                @endif
            >{{ $badge }}</span>
        @endif
    </a>
@else
    <button
        type="button"
        {{ $attributes->class($classes) }}
        @if ($style) style="{{ $style }}" @endif
        @if ($active) aria-current="true" @endif
        @if ($permission) data-ag-permission="{{ $permission }}" @endif
        data-bs-toggle="tooltip"
        data-bs-placement="right"
        data-bs-title="{{ $resolvedLabel }}"
    >
        @if ($icon)
            <x-atoms.icon :name="$icon" size="md" class="ag-menu-item__icon" />
        @endif
        <span class="ag-menu-item__label">{{ $resolvedLabel }}</span>
        @if ($badge !== null)
            <span
                class="ag-menu-item__badge"
                @if ($badgeTitle)
                    data-bs-toggle="tooltip"
                    data-bs-placement="right"
                    data-bs-title="{{ $badgeTitle }}"
                @endif
            >{{ $badge }}</span>
        @endif
    </button>
@endif
