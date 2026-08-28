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
    - statCards (array): MOCK, ver DashboardController::generarStatCardsPorRol().
    - notificaciones (array): MOCK, ver DashboardController::generarNotificacionesMock().
    - ordenesPorEstado (array): conteo MOCK sobre los estados REALES de
      Operaciones::EstadoOrdenAplicacion, ver
      DashboardController::generarOrdenesPorEstadoMock().
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
        :notifications="$notificaciones"
    >
        <h1>{{ __('seguridad.dashboard.titulo') }}</h1>
        <p class="ag-dashboard__intro">
            {{ __('seguridad.dashboard.bienvenida', ['nombre' => $userName, 'rol' => $activeRoleLabel]) }}
        </p>

        {{-- Grid de stat-cards (MOCK) --}}
        @if (count($statCards) > 0)
            <div class="ag-dashboard__stat-grid">
                @foreach ($statCards as $card)
                    <x-molecules.stat-card
                        :icon="$card['icon']"
                        :value="$card['value']"
                        :label="$card['label']"
                        :variant="$card['variant'] ?? 'neutral'"
                        :trend="$card['trend'] ?? null"
                        :trend-direction="$card['trendDirection'] ?? 'neutral'"
                    />
                @endforeach
            </div>

            {{-- Sección "Estados de órdenes" (solo para roles con vista completa) — vocabulario
                 REAL de dominio (App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion, ver
                 DashboardController::generarOrdenesPorEstadoMock()), solo el conteo es MOCK. --}}
            @if (count($ordenesPorEstado) > 0)
                <div class="ag-dashboard__orders-section">
                    <h2 class="ag-dashboard__orders-title">
                        {{ __('seguridad.dashboard.mock.ordenes_por_estado') }}
                    </h2>
                    <div class="ag-dashboard__orders-badges">
                        @foreach ($ordenesPorEstado as $orden)
                            <x-atoms.badge :variant="$orden['variant']">
                                <strong>{{ $orden['count'] }}</strong> {{ $orden['label'] }}
                            </x-atoms.badge>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif
    </x-templates.panel-layout>

    @vite('resources/js/app.js')
    @livewireScripts
</body>
</html>

