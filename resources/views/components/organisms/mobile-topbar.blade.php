{{--
    Organism: mobile-topbar (quinta vuelta — móvil <768px, maqueta 5b)
    Header oscuro (mismos tokens del riel, constantes entre temas):
    hamburguesa que abre organisms/module-drawer, logo, nombre de la app con
    `ROL · CAMPAÑA` en mono lima, campana con badge y avatar. Debajo, la
    banda de breadcrumb: ícono del módulo activo + "Módulo › Vista" + lupa.
    El contenido scrollea; este header no (flex:0 0 auto en panel-layout).

    Web vista en el móvil, no app: sin barra inferior ni botón flotante.
    Visible solo <768px — ver topbar.css.

    Props:
    - moduloIcono / moduloLabel / vistaActual: módulo activo (ya
      normalizado/traducido por panel-layout).
    - activeRoleLabel (nullable string): nombre legible del rol activo, única
      línea de meta bajo el breadcrumb desde que cayó el chip de campaña
      (9/9/2026): venía en `null` fijo por ADR 0015 punto 1 — la campaña es
      del cliente, no hay una "activa" de sesión que mostrar acá.
    - notifications (list): solo para el contador de la campana.
    - userName (nullable string): para las iniciales del avatar.
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
    $iniciales = collect(preg_split('/\s+/', trim((string) $userName)))
        ->filter()
        ->map(fn ($palabra) => mb_strtoupper(mb_substr($palabra, 0, 1)))
        ->take(2)
        ->implode('');
    $notificacionesSinLeer = collect($notifications)->filter(fn ($n) => (bool) data_get($n, 'unread', false))->count();
    $meta = trim((string) $activeRoleLabel);
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

        <span class="ag-mobile-topbar__bell" aria-label="{{ __('ui.topbar.notifications') }}">
            <x-atoms.icon name="notifications" size="sm" />
            @if ($notificacionesSinLeer > 0)
                <span class="ag-mobile-topbar__bell-count" aria-hidden="true">{{ $notificacionesSinLeer > 9 ? '9+' : $notificacionesSinLeer }}</span>
            @endif
        </span>

        @if ($userName)
            <span class="ag-mobile-topbar__avatar" aria-hidden="true">{{ $iniciales ?: '?' }}</span>
        @endif
    </div>

    @if ($moduloLabel)
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
            <x-atoms.icon name="search" size="sm" class="ag-mobile-topbar__crumb-search" :label="__('ui.header.buscador_aria')" />
        </div>
    @endif
</div>
