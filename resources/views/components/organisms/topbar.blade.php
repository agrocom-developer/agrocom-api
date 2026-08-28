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

    Jerarquía visual (mockup de dashboard, segunda ronda de HU-02): el lado
    derecho se agrupa en dos bloques separados por un divisor —
    "utilidades" (notificaciones + tema) vs. "identidad" (rol + usuario) —
    para que no se lea como una fila plana de íconos sueltos.

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
    - notifications (list, default []): cada ítem `{icon, title, time,
      unread}` ya resuelto/traducido por el llamador — este organism no
      inventa contenido, solo lo presenta. `icon` es un nombre de Material
      Symbols (default "notifications" si falta); `title`/`time` son texto
      plano; `unread` (bool) pinta el ítem con fondo tenue y suma al
      contador del badge de la campana. Con la lista vacía, el popover
      muestra el estado vacío (`ui.topbar.no_notifications`) — degrada bien
      sin datos, no se oculta el disparador.
--}}
@props([
    'roles' => [],
    'rolActivoId' => null,
    'activeRoleLabel' => null,
    'userName' => null,
    'sidebarId' => 'ag-sidebar',
    'notifications' => [],
])

@php
    $tieneVariosRoles = count($roles) > 1;
    $iniciales = collect(preg_split('/\s+/', trim((string) $userName)))
        ->filter()
        ->map(fn ($palabra) => mb_strtoupper(mb_substr($palabra, 0, 1)))
        ->take(2)
        ->implode('');
    $notificacionesSinLeer = collect($notifications)->filter(fn ($n) => (bool) data_get($n, 'unread', false))->count();
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
        <div class="ag-topbar__group">
            <div class="dropdown ag-topbar__notifications">
                <button
                    type="button"
                    class="ag-topbar__icon-btn ag-topbar__notifications-trigger"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                    aria-label="{{ __('ui.topbar.notifications') }}"
                >
                    <x-atoms.icon name="notifications" size="sm" />
                    @if ($notificacionesSinLeer > 0)
                        <span class="ag-topbar__notifications-count" aria-hidden="true">{{ $notificacionesSinLeer > 9 ? '9+' : $notificacionesSinLeer }}</span>
                    @endif
                </button>

                <div class="dropdown-menu dropdown-menu-end ag-notifications-popover">
                    <h2 class="ag-role-popover__title">{{ __('ui.topbar.notifications') }}</h2>

                    @if (count($notifications) === 0)
                        <p class="ag-notifications-popover__empty">{{ __('ui.topbar.no_notifications') }}</p>
                    @else
                        <ul class="ag-notification-list">
                            @foreach ($notifications as $notification)
                                <li class="ag-notification-item {{ data_get($notification, 'unread') ? 'is-unread' : '' }}">
                                    <span class="ag-notification-item__icon" aria-hidden="true">
                                        <x-atoms.icon :name="data_get($notification, 'icon', 'notifications')" size="sm" />
                                    </span>
                                    <span class="ag-notification-item__body">
                                        <span class="ag-notification-item__title">{{ data_get($notification, 'title') }}</span>
                                        <span class="ag-notification-item__time">{{ data_get($notification, 'time') }}</span>
                                    </span>
                                    @if (data_get($notification, 'unread'))
                                        <span class="ag-notification-item__dot" aria-hidden="true"></span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <x-molecules.theme-toggle />
        </div>

        @if ($activeRoleLabel || $tieneVariosRoles || $userName)
            <span class="ag-topbar__divider" aria-hidden="true"></span>
        @endif

        <div class="ag-topbar__group">
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
                <div class="dropdown ag-topbar__user-menu">
                    <button
                        type="button"
                        class="ag-topbar__user"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                        aria-label="{{ $userName }}"
                    >
                        <span class="ag-topbar__avatar" aria-hidden="true">{{ $iniciales ?: '?' }}</span>
                        <span class="ag-topbar__user-name">{{ $userName }}</span>
                    </button>

                    <ul class="dropdown-menu dropdown-menu-end ag-user-menu">
                        <li>
                            <button
                                type="button"
                                class="dropdown-item ag-user-menu__logout"
                                data-ag-logout
                            >
                                <x-atoms.icon name="logout" size="sm" class="ag-user-menu__logout-icon" />
                                {{ __('ui.topbar.logout') }}
                            </button>
                        </li>
                    </ul>
                </div>
            @endif
        </div>
    </div>
</header>
