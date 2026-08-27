{{--
    Organism: sidebar-nav (docs/diseno/sistema_diseno_panel.md §4.6)
    Composición: logo (colapsado/expandido) + menú (menu-item/collapsible-
    menu-group) + pie con theme-toggle y el disparador del selector de rol.

    Base: el colapso responsivo en MOBILE es el `offcanvas` NATIVO de
    Bootstrap 5.3 (`.offcanvas.offcanvas-lg`, breakpoint `lg` = 991.98px, JS
    ya cargado en resources/js/app.js) — catálogo §4.6/§2. El colapso a
    icon-rail en DESKTOP no existe en Bootstrap: se resuelve con la clase
    propia `.is-collapsed` + resources/js/organisms/sidebar-nav.js (la única
    pieza de JS propia de este organism). El popover de cambio de rol usa el
    `dropdown` nativo de Bootstrap (Popper incluido) — cada disparador
    (este pie Y el de organisms/topbar) abre SU PROPIO popover anclado a sí
    mismo, no uno compartido.

    No consulta `sec_*`: recibe el árbol de menú y los roles ya resueltos
    (ADR 0008 — los organisms son adaptadores delgados de presentación).

    Props:
    - menu (list): árbol ya filtrado para el rol activo, forma de
      `App\Dominios\Seguridad\Aplicacion\ItemMenu` (o array equivalente:
      label/icono|icon/ruta|href/hijos/active/badge/permission).
    - roles (list, default []): roles vivos del usuario, forma
      `{id, name, description}` — igual que responde SesionController::store.
      El disparador de "cambiar de rol" solo se muestra si hay 2+.
    - rolActivoId (nullable): id del rol activo, para marcar cuál está
      seleccionado en el popover.
    - id (default "ag-sidebar"): id del `<aside>` — organisms/topbar apunta
      su botón hamburguesa acá vía la prop `sidebarId`.
    - collapsed (bool, default false): estado inicial del icon-rail en
      desktop (el JS lo persiste en localStorage entre cargas de página,
      pura comodidad de navegador, no la preferencia de usuario del backend).
--}}
@props([
    'menu' => [],
    'roles' => [],
    'rolActivoId' => null,
    'id' => 'ag-sidebar',
    'collapsed' => false,
])

@php
    $tieneVariosRoles = count($roles) > 1;
@endphp

<aside
    {{ $attributes->class(['ag-sidebar', 'offcanvas', 'offcanvas-lg', 'offcanvas-start', $collapsed ? 'is-collapsed' : '']) }}
    tabindex="-1"
    id="{{ $id }}"
    aria-labelledby="{{ $id }}-label"
    data-ag-sidebar
>
    <div class="ag-sidebar__header">
        <span class="ag-sidebar__brand">
            <x-atoms.logo size="sm" />
            <span class="ag-sidebar__brand-name" id="{{ $id }}-label">{{ __('ui.logo.alt') }}</span>
        </span>

        <button
            type="button"
            class="ag-sidebar__collapse-btn"
            data-ag-sidebar-collapse-toggle
            data-label-collapse="{{ __('ui.sidebar.collapse') }}"
            data-label-expand="{{ __('ui.sidebar.expand') }}"
            aria-label="{{ __('ui.sidebar.collapse') }}"
        >
            <x-atoms.icon name="chevron_left" size="sm" />
        </button>

        <button
            type="button"
            class="ag-sidebar__close-btn"
            data-bs-dismiss="offcanvas"
            aria-label="{{ __('ui.sidebar.close') }}"
        >
            <x-atoms.icon name="close" size="sm" />
        </button>
    </div>

    <nav class="ag-menu" aria-label="{{ __('ui.logo.alt') }}">
        @foreach ($menu as $index => $item)
            @php($hijos = data_get($item, 'hijos', data_get($item, 'items', [])))

            @if (! empty($hijos))
                <x-organisms.collapsible-menu-group
                    :label="data_get($item, 'label')"
                    :icon="data_get($item, 'icono', data_get($item, 'icon'))"
                    :items="$hijos"
                    :stagger-index="$index"
                />
            @else
                <x-molecules.menu-item
                    :label="data_get($item, 'label')"
                    :icon="data_get($item, 'icono', data_get($item, 'icon'))"
                    :href="data_get($item, 'ruta', data_get($item, 'href'))"
                    :active="(bool) data_get($item, 'active', false)"
                    :badge="data_get($item, 'badge')"
                    :permission="data_get($item, 'permission')"
                    :stagger-index="$index"
                />
            @endif
        @endforeach
    </nav>

    <div class="ag-sidebar__footer">
        @if ($tieneVariosRoles)
            {{-- `.ag-role-popover`/`.ag-role-list` se definen en topbar.css
                 (mismo skin de dropdown-menu, compartido entre este pie y
                 organisms/topbar — no hace falta redeclararlo acá). --}}
            <div class="dropdown ag-sidebar__role-switch">
                <button
                    type="button"
                    class="ag-sidebar__footer-btn"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                >
                    <x-atoms.icon name="swap_horiz" size="sm" />
                    <span>{{ __('seguridad.rol.switch_trigger') }}</span>
                </button>

                <div class="dropdown-menu ag-role-popover">
                    <h2 class="ag-role-popover__title">{{ __('seguridad.rol.switch_titulo') }}</h2>
                    <livewire:panel.role-switcher :roles="$roles" :rol-activo-id="$rolActivoId" />
                </div>
            </div>
        @endif

        <x-molecules.theme-toggle :show-label="true" />
    </div>
</aside>
