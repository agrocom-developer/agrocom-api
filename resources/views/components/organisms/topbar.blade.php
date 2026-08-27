{{--
    Organism: topbar (docs/diseno/sistema_diseno_panel.md §4.7)
    Composición: hamburguesa (mobile) + nombre de usuario + badge del rol
    activo + theme-toggle propio + botón que abre el popover de cambio de rol
    (dropdown nativo de Bootstrap, anclado a ESTE trigger — independiente del
    que vive en el pie de organisms/sidebar-nav, cada uno con su propio
    Popper). Es donde vive, visualmente, "cambiar de rol activo sin volver a
    loguearse" (CA de HU-02) — el disparador, no la lógica: la acción real
    (POST /panel/rol-activo) la implementa `frontend`.

    Lleva SU PROPIO theme-toggle (no solo el del pie del sidebar): en mobile,
    con el sidebar cerrado, es la única forma de cambiar de tema sin abrir el
    menú.

    Props:
    - roles (list, default []): igual forma que sidebar-nav. El botón de
      cambio de rol solo se muestra con 2+.
    - rolActivoId (nullable).
    - activeRoleLabel (nullable string): `sec_role.name` del rol activo, ya
      resuelto por el llamador (dominio, no se traduce — ADR 0013 punto 3).
    - userName (nullable string): nombre completo, para el avatar (iniciales)
      y el texto junto a él.
    - sidebarId (default "ag-sidebar"): debe coincidir con el `id` que se le
      pasó a `<x-organisms.sidebar-nav>` para que el botón hamburguesa abra
      el offcanvas correcto.
--}}
@props([
    'roles' => [],
    'rolActivoId' => null,
    'activeRoleLabel' => null,
    'userName' => null,
    'sidebarId' => 'ag-sidebar',
])

@php
    $tieneVariosRoles = count($roles) > 1;
    $iniciales = collect(preg_split('/\s+/', trim((string) $userName)))
        ->filter()
        ->map(fn ($palabra) => mb_strtoupper(mb_substr($palabra, 0, 1)))
        ->take(2)
        ->implode('');
@endphp

<header {{ $attributes->class(['ag-topbar']) }}>
    <button
        type="button"
        class="ag-topbar__hamburger"
        data-bs-toggle="offcanvas"
        data-bs-target="#{{ $sidebarId }}"
        aria-controls="{{ $sidebarId }}"
        aria-label="{{ __('ui.sidebar.open') }}"
    >
        <x-atoms.icon name="menu" />
    </button>

    <span class="ag-topbar__spacer"></span>

    <div class="ag-topbar__right">
        <x-molecules.theme-toggle />

        @if ($activeRoleLabel)
            <span class="ag-role-badge">
                <span class="visually-hidden">{{ __('seguridad.rol.badge_activo') }}:</span>
                {{ $activeRoleLabel }}
            </span>
        @endif

        @if ($tieneVariosRoles)
            {{-- `.ag-role-popover`/`.ag-role-list`: ver topbar.css. --}}
            <div class="dropdown ag-topbar__role-switch">
                <button
                    type="button"
                    class="ag-topbar__icon-btn"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                    aria-label="{{ __('seguridad.rol.switch_trigger') }}"
                >
                    <x-atoms.icon name="swap_horiz" size="sm" />
                </button>

                <div class="dropdown-menu dropdown-menu-end ag-role-popover">
                    <h2 class="ag-role-popover__title">{{ __('seguridad.rol.switch_titulo') }}</h2>
                    <livewire:panel.role-switcher :roles="$roles" :rol-activo-id="$rolActivoId" />
                </div>
            </div>
        @endif

        @if ($userName)
            <span class="ag-topbar__user">
                <span class="ag-topbar__avatar" aria-hidden="true">{{ $iniciales ?: '?' }}</span>
                <span class="ag-topbar__user-name">{{ $userName }}</span>
            </span>
        @endif
    </div>
</header>
