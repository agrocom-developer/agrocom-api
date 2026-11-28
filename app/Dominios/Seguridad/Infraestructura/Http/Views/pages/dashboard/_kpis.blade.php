{{-- KPIs del tablero: cada tarjeta solo con el permiso de lo que muestra. Variables: $tablero, $pendientes, $puedeVerSolicitudes, $enMercado, $enCurso, $totalPendientes, $hectareas. --}}
<div class="ag-tablero__kpis">
    @if ($puedeVerSolicitudes)
    <x-molecules.stat-card :label="__('seguridad.dashboard.kpi_solicitudes')" icon="request_quote" :value="number_format($tablero['total_solicitudes'], 0, ',', '.')" state="info" />
    <x-molecules.stat-card :label="__('seguridad.dashboard.kpi_en_mercado')" icon="storefront" :value="number_format($enMercado, 0, ',', '.')" state="success" />
    <x-molecules.stat-card :label="__('seguridad.dashboard.kpi_en_curso')" icon="flight" :value="number_format($enCurso, 0, ',', '.')" state="primary-2" />
    <x-molecules.stat-card :label="__('seguridad.dashboard.kpi_hectareas')" icon="landscape" :value="$hectareas($tablero['hectareas_en_mercado'])" value-suffix="ha" state="distintivo-1" />
    @endif
    @if ($pendientes !== [])
    <x-molecules.stat-card :label="__('seguridad.dashboard.kpi_pendientes')" icon="pending_actions" :value="number_format($totalPendientes, 0, ',', '.')" :state="$totalPendientes > 0 ? 'warning' : null" />
    @endif
</div>
