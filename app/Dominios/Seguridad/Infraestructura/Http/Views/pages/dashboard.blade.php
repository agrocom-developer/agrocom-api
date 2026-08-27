{{--
    Page: dashboard (GET /panel/dashboard, panel.dashboard)
    Estructura: layout HTML + panel-layout sin Livewire wrapper. El cambio de
    rol activo se maneja mediante un componente Livewire pequeño dentro del
    popover (sidebar-nav/topbar), no una envoltura de página completa.

    Datos esperados (ver DashboardController::index()):
    - menu (list<ItemMenu>): árbol ya filtrado para el rol activo.
    - roles (Collection<SecRole>): roles vivos del usuario, para el selector
      de cambio de rol de sidebar-nav/topbar.
    - rolActivoId (int).
    - activeRoleLabel (string|null): sec_role.name del rol activo.
    - userName (string): sec_user.name.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Agrocom') }} — Panel</title>

    @vite('resources/css/app.css')
    @livewireStyles
</head>
<body>
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
    >
        <h1>{{ __('seguridad.dashboard.titulo') }}</h1>
        <p style="color: var(--ag-color-text-muted); margin-top: var(--ag-space-2); max-width: 40rem">
            {{ __('seguridad.dashboard.bienvenida', ['nombre' => $userName, 'rol' => $activeRoleLabel]) }}
        </p>
    </x-templates.panel-layout>

    @vite('resources/js/app.js')
    @livewireScripts
</body>
</html>

