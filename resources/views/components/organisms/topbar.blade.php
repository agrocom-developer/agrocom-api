{{--
    Organism: topbar (docs/diseno/sistema_diseno_panel.md §4.7 — quinta
    vuelta, header del layout de tres niveles, maqueta 4a)

    62px de alto fijo (`flex:0 0 62px`, overflow hidden), agrupado en DOS
    bloques explícitos (sexta vuelta parte 2 — `.ag-topbar__left`/`__right`,
    pedido explícito del 28/8/2026: dejar de depender solo del `flex:1` del
    buscador para separar los grupos):
    - IZQUIERDA (`.ag-topbar__left`, cede el espacio sobrante): breadcrumb
      "Módulo › Vista" · buscador global (alto 36px, el ÚNICO elemento que
      cede espacio dentro del bloque: `flex:1 1 300px; min-width:220px;
      max-width:400px`, atajo ⌘K).
    - DERECHA (`.ag-topbar__right`, ancho fijo): usuario (con el ROL ACTIVO
      visible bajo el nombre) · toggle de tema (segmented, molecule
      theme-toggle) · campana con badge · selector de período · chip de
      campaña activa — en ESE orden exacto, pedido explícito.
    Todo salvo el buscador lleva `flex:0 0 auto; white-space:nowrap` — ver
    topbar.css. En tablet (<1200) el header se compacta: breadcrumb, campaña
    y período se ocultan (maqueta 5a). En móvil (<768) este header entero se
    reemplaza por organisms/mobile-topbar.

    El buscador y el selector de período son demo visual (sin backend
    todavía); la campana muestra las notificaciones que pasa el llamador; el
    menú de usuario tiene "Cambiar de rol" (link a la pantalla de selección,
    `?cambiar=1`) y "Cerrar sesión" (data-ag-logout → organisms/topbar.js).

    Props:
    - moduloLabel / vistaActual (nullable string): breadcrumb, ya traducidos.
    - campana (nullable string): texto del chip de campaña activa (demo).
    - periodo (nullable string): texto del selector de período (demo).
    - notifications (list, default []): `{icon, title, time, unread}` ya
      resueltos por el llamador. Lista vacía = estado vacío del popover.
    - activeRoleLabel (nullable string): nombre LEGIBLE del rol activo
      (PresentadorRol), mono uppercase bajo el nombre.
    - userName (nullable string).
    - cambiarRolHref (nullable string): null con un solo rol (no se muestra).
--}}
@props([
    'moduloLabel' => null,
    'vistaActual' => null,
    'campana' => null,
    'periodo' => null,
    'notifications' => [],
    'activeRoleLabel' => null,
    'userName' => null,
    'cambiarRolHref' => null,
])

@php
    $iniciales = collect(preg_split('/\s+/', trim((string) $userName)))
        ->filter()
        ->map(fn ($palabra) => mb_strtoupper(mb_substr($palabra, 0, 1)))
        ->take(2)
        ->implode('');
    $notificacionesSinLeer = collect($notifications)->filter(fn ($n) => (bool) data_get($n, 'unread', false))->count();
@endphp

<header {{ $attributes->class(['ag-topbar']) }}>
    <div class="ag-topbar__left">
        @if ($moduloLabel)
            <div class="ag-topbar__breadcrumb">
                <span>{{ $moduloLabel }}</span>
                @if ($vistaActual)
                    <x-atoms.icon name="chevron_right" size="sm" class="ag-topbar__breadcrumb-sep" />
                    <span class="ag-topbar__breadcrumb-current">{{ $vistaActual }}</span>
                @endif
            </div>
        @endif

        <label class="ag-topbar__search">
            <x-atoms.icon name="search" size="sm" class="ag-topbar__search-icon" />
            <input
                type="search"
                class="ag-topbar__search-input"
                placeholder="{{ __('ui.header.buscador_placeholder') }}"
                aria-label="{{ __('ui.header.buscador_aria') }}"
            >
            <kbd class="ag-topbar__search-kbd" aria-hidden="true">{{ __('ui.header.atajo_buscador') }}</kbd>
        </label>
    </div>

    <div class="ag-topbar__right">
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
                    <span class="ag-topbar__user-id">
                        <span class="ag-topbar__user-name">{{ $userName }}</span>
                        @if ($activeRoleLabel)
                            <span class="ag-topbar__user-role">
                                <span class="visually-hidden">{{ __('seguridad.rol.badge_activo') }}:</span>
                                {{ $activeRoleLabel }}
                            </span>
                        @endif
                    </span>
                </button>

                <ul class="dropdown-menu dropdown-menu-end ag-user-menu">
                    @if ($cambiarRolHref)
                        <li>
                            <a href="{{ $cambiarRolHref }}" class="dropdown-item ag-user-menu__item">
                                <x-atoms.icon name="swap_horiz" size="sm" class="ag-user-menu__icon" />
                                {{ __('seguridad.rol.switch_trigger') }}
                            </a>
                        </li>
                    @endif
                    <li>
                        <button
                            type="button"
                            class="dropdown-item ag-user-menu__item ag-user-menu__logout"
                            data-ag-logout
                        >
                            <x-atoms.icon name="logout" size="sm" class="ag-user-menu__icon" />
                            {{ __('ui.topbar.logout') }}
                        </button>
                    </li>
                </ul>
            </div>
        @endif

        <x-molecules.theme-toggle />

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
                <h2 class="ag-popover__title">{{ __('ui.topbar.notifications') }}</h2>

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

        @if ($periodo)
            <button type="button" class="ag-topbar__period" title="{{ __('ui.header.periodo') }}">
                <x-atoms.icon name="calendar_month" size="sm" />
                {{ $periodo }}
                <x-atoms.icon name="expand_more" size="sm" class="ag-topbar__period-chevron" />
            </button>
        @endif

        @if ($campana)
            <span class="ag-topbar__campaign" title="{{ __('ui.header.campana_activa') }}">
                <span class="ag-topbar__campaign-dot" aria-hidden="true"></span>
                {{ $campana }}
            </span>
        @endif
    </div>
</header>
