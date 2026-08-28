{{--
    Organism: module-sidebar (quinta vuelta — nivel 2 del layout, maqueta 4a)
    Sidebar de ítems del MÓDULO ACTIVO: cabecera con el nombre en serif +
    línea de descripción, lista de ítems (molecules/menu-item: ícono + label
    + badge de pendientes) y, al pie, "Cambiar de rol". 252px, superficie
    clara con borde derecho; visible solo ≥1200px (en tablet los ítems pasan
    a la banda de píldoras de panel-layout; en móvil, al drawer).

    Props:
    - modulo (nullable array): `{label, descripcion, items: [{label, icono,
      href, active, badge}]}` ya normalizado por panel-layout.
    - cambiarRolHref (nullable string): link a la pantalla de selección de
      rol (`?cambiar=1`) — null con un solo rol (no se muestra).
--}}
@props([
    'modulo' => null,
    'cambiarRolHref' => null,
])

@if ($modulo !== null)
    <aside class="ag-module-sidebar">
        <div class="ag-module-sidebar__header">
            <h2 class="ag-module-sidebar__title">{{ __($modulo['label']) }}</h2>
            @if ($modulo['descripcion'])
                <p class="ag-module-sidebar__desc">{{ __($modulo['descripcion']) }}</p>
            @endif
        </div>

        <nav class="ag-module-sidebar__nav" aria-label="{{ __($modulo['label']) }}">
            @foreach ($modulo['items'] as $index => $item)
                <x-molecules.menu-item
                    :label="$item['label']"
                    :icon="$item['icono']"
                    :href="$item['href']"
                    :active="$item['active']"
                    :badge="$item['badge']"
                    :stagger-index="$index"
                />
            @endforeach
        </nav>

        @if ($cambiarRolHref)
            <div class="ag-module-sidebar__footer">
                <a href="{{ $cambiarRolHref }}" class="ag-module-sidebar__role-link">
                    <x-atoms.icon name="swap_horiz" size="sm" />
                    <span>{{ __('seguridad.rol.switch_trigger') }}</span>
                </a>
            </div>
        @endif
    </aside>
@endif
