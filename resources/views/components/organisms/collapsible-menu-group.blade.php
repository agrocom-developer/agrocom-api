{{--
    Organism: collapsible-menu-group (docs/diseno/sistema_diseno_panel.md §4.4)
    Orquesta varios `menu-item` (molecules) bajo una cabecera colapsable —
    combinación de moléculas con lógica de coordinación propia (abierto/
    cerrado), por eso es organism y no molecule.

    Base: el mecanismo de expandir/colapsar es el `collapse` NATIVO de
    Bootstrap 5.3 (`data-bs-toggle="collapse"`, JS ya cargado en
    resources/js/app.js) — catálogo §4.4, sin JS propio: Bootstrap gestiona
    `aria-expanded`/`.collapsed`/`.show` solo. Estética Material en los ítems
    internos (menu-item ya la trae).

    Props:
    - label (requerido): clave de traducción del grupo (se resuelve vía
      `__()`, mismo criterio que menu-item — ver su docblock).
    - icon (nullable): ícono del grupo.
    - items (requerido): lista de ítems hijo, cada uno con la forma de
      `ItemMenu` (o un array equivalente: label/icono|icon/ruta|href/active/
      badge/permission) — acepta ambas formas vía `data_get()`.
    - open (bool, default false): estado inicial. Se fuerza a `true` además
      si algún hijo está `active` (no se puede mostrar un grupo colapsado con
      la sección actual adentro, oculta).
    - id (nullable): id del contenedor colapsable; por defecto se deriva del
      `label` (slug) — pasalo explícito si dos grupos pudieran compartir
      label.
    - staggerIndex (nullable int): ver menu-item — se aplica a la cabecera
      del grupo (que también es un `.ag-menu-item`).
--}}
@props([
    'label',
    'icon' => null,
    'items' => [],
    'open' => false,
    'id' => null,
    'staggerIndex' => null,
])

@php
    $groupId = $id ?? 'ag-menu-group-'.\Illuminate\Support\Str::slug((string) $label);
    $resolvedLabel = __($label);
    $hasActiveChild = collect($items)->contains(fn ($item) => (bool) data_get($item, 'active', false));
    $isOpen = $open || $hasActiveChild;
    $style = $staggerIndex !== null ? "--ag-menu-item-index: {$staggerIndex}" : null;
@endphp

<div {{ $attributes->class(['ag-menu-group']) }}>
    <button
        type="button"
        class="ag-menu-item ag-menu-group__toggle {{ $isOpen ? '' : 'collapsed' }}"
        @if ($style) style="{{ $style }}" @endif
        data-bs-toggle="collapse"
        data-bs-target="#{{ $groupId }}"
        aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
        aria-controls="{{ $groupId }}"
    >
        @if ($icon)
            <x-atoms.icon :name="$icon" size="sm" class="ag-menu-item__icon" />
        @endif
        <span class="ag-menu-item__label">{{ $resolvedLabel }}</span>
        <x-atoms.icon name="expand_more" size="sm" class="ag-menu-group__chevron" />
    </button>

    <div class="collapse {{ $isOpen ? 'show' : '' }} ag-menu-group__items" id="{{ $groupId }}">
        @foreach ($items as $index => $item)
            <x-molecules.menu-item
                :label="data_get($item, 'label')"
                :icon="data_get($item, 'icono', data_get($item, 'icon'))"
                :href="data_get($item, 'ruta', data_get($item, 'href'))"
                :active="(bool) data_get($item, 'active', false)"
                :badge="data_get($item, 'badge')"
                :permission="data_get($item, 'permission')"
                :stagger-index="$index"
                class="ag-menu-group__item"
            />
        @endforeach
    </div>
</div>
