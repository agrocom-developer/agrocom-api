{{--
    Organism: module-sidebar (quinta vuelta — nivel 2 del layout, maqueta 4a;
    sexta vuelta parte 2: collapse/expand)
    Sidebar de ítems del MÓDULO ACTIVO: cabecera con botón de colapso +
    nombre en serif + línea de descripción, lista de ítems (molecules/
    menu-item: ícono + label + badge de pendientes) y, al pie, "Cambiar de
    rol". 252px (72px colapsado), superficie clara con borde derecho;
    visible solo ≥1200px (en tablet los ítems pasan a la banda de píldoras
    de panel-layout; en móvil, al drawer).

    El collapse/expand es JS propio (resources/js/organisms/module-sidebar.js,
    mismo patrón de delegación de eventos que theme-toggle.js): togglea
    `.is-collapsed` en `.ag-module-sidebar` y persiste el estado en
    localStorage (UI transitoria del cliente, no una preferencia de usuario
    server-side como el tema — sobrevive la navegación completa entre
    páginas de este panel multi-página).

    Props:
    - modulo (nullable array): `{label, descripcion, items: [{label, icono,
      href, active, badge, badgeTitle}]}` ya normalizado por panel-layout.
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
            <button
                type="button"
                class="ag-module-sidebar__toggle"
                data-ag-sidebar-toggle
                data-label-collapse="{{ __('ui.sidebar.collapse') }}"
                data-label-expand="{{ __('ui.sidebar.expand') }}"
                aria-expanded="true"
                aria-controls="ag-module-sidebar-nav"
                aria-label="{{ __('ui.sidebar.collapse') }}"
            >
                <x-atoms.icon name="menu" size="sm" />
            </button>

            <div class="ag-module-sidebar__heading">
                <h2 class="ag-module-sidebar__title">{{ __($modulo['label']) }}</h2>
                @if ($modulo['descripcion'])
                    <p class="ag-module-sidebar__desc">{{ __($modulo['descripcion']) }}</p>
                @endif
            </div>
        </div>

        <nav class="ag-module-sidebar__nav" id="ag-module-sidebar-nav" aria-label="{{ __($modulo['label']) }}">
            @foreach ($modulo['items'] as $index => $item)
                <x-molecules.menu-item
                    :label="$item['label']"
                    :icon="$item['icono']"
                    :href="$item['href']"
                    :active="$item['active']"
                    :badge="$item['badge']"
                    :badge-title="$item['badgeTitle'] ?? null"
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
