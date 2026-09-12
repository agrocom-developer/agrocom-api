{{--
    Organism: mobile-topbar (quinta vuelta — móvil <768px, maqueta 5b)
    Header oscuro (mismos tokens del riel, constantes entre temas):
    hamburguesa que abre organisms/module-drawer, logo, nombre de la app con
    `ROL · CAMPAÑA` en mono lima, campana con badge y avatar. Debajo, la
    banda de breadcrumb: ícono del módulo activo + "Módulo › Vista" + lupa.
    El contenido scrollea; este header no (flex:0 0 auto en panel-layout).

    Web vista en el móvil, no app: sin barra inferior ni botón flotante.
    Visible solo <768px — ver topbar.css.

    Corrección real (11/9/2026, reportado por el usuario mirando el panel en
    el celular): campana y avatar eran `<span>` decorativos —no `<button>`,
    sin `data-bs-toggle`— y la lupa era un ícono suelto sin acción. No había
    forma de ver notificaciones, entrar a "Mi perfil" NI DE CERRAR SESIÓN
    desde el celular, y tampoco de escribir una búsqueda. Los tres ahora son
    controles reales:
    - Campana y avatar: `molecules/notifications-menu` y `molecules/user-menu`
      (las mismas que usa `organisms/topbar` de escritorio, con
      `trigger-class`/`compact` para los tokens constantes del riel en vez
      de los del tema — ver el docblock de cada una).
    - Lupa: checkbox + `<label>` sin JavaScript (mismo truco que
      `molecules/file-field` para "Reemplazar"/"Quitar") — tildarlo oculta
      el breadcrumb y muestra una fila de búsqueda real (`<input
      type="search">` dentro de un `<form GET>` a `panel.buscar`, igual que
      el buscador de escritorio). Un segundo `<label>` del mismo checkbox,
      dentro de la fila de búsqueda, cierra. Sigue el mismo criterio de
      progressive enhancement que el resto del catálogo (`atoms/date`,
      `atoms/select`): funciona sin ejecutar una sola línea de JS.

    Props:
    - moduloIcono / moduloLabel / vistaActual: módulo activo (ya
      normalizado/traducido por panel-layout).
    - activeRoleLabel (nullable string): nombre legible del rol activo, única
      línea de meta bajo el breadcrumb desde que cayó el chip de campaña
      (9/9/2026): venía en `null` fijo por ADR 0015 punto 1 — la campaña es
      del cliente, no hay una "activa" de sesión que mostrar acá.
    - notifications (list): pasa entero a `molecules/notifications-menu`.
    - userName (nullable string): pasa a `molecules/user-menu`. Sin
      `cambiarRolHref` a propósito — `organisms/module-drawer` ya tiene su
      propio link de "Cambiar de rol" en el pie, duplicarlo acá sería un
      segundo camino a lo mismo.
    - drawerId: id del offcanvas de módulos que abre la hamburguesa.
--}}
@props([
    'moduloIcono' => null,
    'moduloLabel' => null,
    'vistaActual' => null,
    'activeRoleLabel' => null,
    'notifications' => [],
    'userName' => null,
    'drawerId' => 'ag-module-drawer',
])

@php
    $meta = trim((string) $activeRoleLabel);
    $buscadorId = 'ag-mobile-buscador-toggle';
@endphp

<div class="ag-mobile-topbar">
    <div class="ag-mobile-topbar__bar">
        <button
            type="button"
            class="ag-mobile-topbar__menu-btn"
            data-bs-toggle="offcanvas"
            data-bs-target="#{{ $drawerId }}"
            aria-controls="{{ $drawerId }}"
            aria-label="{{ __('ui.drawer.abrir') }}"
        >
            <x-atoms.icon name="menu" />
        </button>

        <img src="{{ asset('logo.png') }}" alt="" aria-hidden="true" class="ag-mobile-topbar__logo">

        <div class="ag-mobile-topbar__id">
            <span class="ag-mobile-topbar__app">{{ __('ui.logo.alt') }}</span>
            @if ($meta !== '')
                <span class="ag-mobile-topbar__meta">{{ $meta }}</span>
            @endif
        </div>

        <x-molecules.theme-toggle class="ag-mobile-topbar__theme-toggle" />

        <x-molecules.notifications-menu :notifications="$notifications" trigger-class="ag-mobile-topbar__bell-btn" />

        <x-molecules.user-menu :user-name="$userName" compact trigger-class="ag-mobile-topbar__avatar-btn" />
    </div>

    @if ($moduloLabel)
        {{-- Checkbox "visually hidden" (mismo criterio que
             `.ag-file-field__native`): sigue alcanzable por teclado, el
             `<label>` de al lado es solo el disparador visual/de mouse. --}}
        <input type="checkbox" id="{{ $buscadorId }}" class="ag-mobile-topbar__search-toggle">

        <div class="ag-mobile-topbar__crumb">
            @if ($moduloIcono)
                <x-atoms.icon :name="$moduloIcono" size="sm" class="ag-mobile-topbar__crumb-icon" />
            @endif
            <span>{{ $moduloLabel }}</span>
            @if ($vistaActual)
                <x-atoms.icon name="chevron_right" size="sm" class="ag-mobile-topbar__crumb-sep" />
                <span class="ag-mobile-topbar__crumb-current">{{ $vistaActual }}</span>
            @endif
            <span class="ag-mobile-topbar__crumb-spacer"></span>
            <label for="{{ $buscadorId }}" class="ag-mobile-topbar__crumb-search" aria-label="{{ __('ui.header.buscador_aria') }}">
                <x-atoms.icon name="search" size="sm" />
            </label>
        </div>

        <form
            method="GET"
            action="{{ route('panel.buscar') }}"
            class="ag-mobile-topbar__search-bar"
            role="search"
        >
            <x-atoms.icon name="search" size="sm" class="ag-mobile-topbar__search-icon" />
            <input
                type="search"
                name="q"
                value="{{ request()->routeIs('panel.buscar') ? request()->string('q') : '' }}"
                class="ag-mobile-topbar__search-input"
                placeholder="{{ __('ui.header.buscador_placeholder') }}"
                aria-label="{{ __('ui.header.buscador_aria') }}"
                autocomplete="off"
            >
            <label for="{{ $buscadorId }}" class="ag-mobile-topbar__search-close" aria-label="{{ __('ui.drawer.cerrar') }}">
                <x-atoms.icon name="close" size="sm" />
            </label>
        </form>
    @endif
</div>
