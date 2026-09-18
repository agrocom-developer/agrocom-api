{{--
    Parcial: tabla de sesiones. Dos representaciones del MISMO dato,
    conmutadas por breakpoint en pages/dashboard.css:
    - `.ag-table`: grilla de escritorio (≥1200) con HORA/LOTE/PILOTO/DRON/HA/ESTADO.
    - `.ag-session-list`: lista de dos líneas de tablet (768–1199).
    - `.ag-dash__hoy` + fichas: móvil (<768), en `_fichas-sesiones`.

    Lo usan la cola de validación y "mis sesiones": el mismo componente con
    otro filtro, no dos tablas que mantener.

    Espera:
    - $sesiones (list): filas ya resueltas por ArmarDashboard (el lote y el
      piloto vienen con nombre, no con id).
    - $limiteLista (int|null, opcional): tope de filas SOLO para la lista de
      tablet.
--}}
@php
    $limiteLista = $limiteLista ?? null;

    /** Minutos a "h:mm" — formato, no dato. `null` en una sesión sin cerrar. */
    $comoDuracion = fn (?int $minutos) => $minutos === null
        ? '—'
        : sprintf('%d:%02d', intdiv($minutos, 60), $minutos % 60);
@endphp

<div class="ag-table" role="table">
    <div class="ag-table__head" role="row">
        <span role="columnheader">{{ __('seguridad.dashboard.col_hora') }}</span>
        <span role="columnheader">{{ __('seguridad.dashboard.col_lote') }}</span>
        <span role="columnheader">{{ __('seguridad.dashboard.col_piloto') }}</span>
        <span role="columnheader">{{ __('seguridad.dashboard.col_dron') }}</span>
        <span role="columnheader" class="ag-table__ha">{{ __('seguridad.dashboard.col_vuelo') }}</span>
        <span role="columnheader" class="ag-table__ha">{{ __('seguridad.dashboard.col_ha') }}</span>
        <span role="columnheader">{{ __('seguridad.dashboard.col_estado') }}</span>
    </div>
    @foreach ($sesiones as $sesion)
        <div class="ag-table__row" role="row">
            <span class="ag-table__mono" role="cell">{{ \Illuminate\Support\Carbon::parse($sesion['inicio'])->format('d/m H:i') }}</span>
            <span class="ag-table__strong" role="cell">
                <a class="ag-dash__link" href="{{ route('panel.trabajos.detalle', $sesion['trabajoId']) }}">{{ $sesion['lote'] }}</a>
                @if ($sesion['propiedad'])
                    <span class="ag-table__sub">{{ $sesion['propiedad'] }}</span>
                @endif
            </span>
            <span role="cell">{{ $sesion['piloto'] ?? '—' }}</span>
            <span class="ag-table__mono" role="cell">{{ $sesion['dron'] ?? '—' }}</span>
            <span role="cell" class="ag-table__ha ag-table__mono">{{ $comoDuracion($sesion['minutosVuelo']) }}</span>
            <span role="cell" class="ag-table__ha">{{ number_format((float) $sesion['hectareas'], 2, ',', '.') }}</span>
            <span role="cell" class="ag-table__estado">
                <span class="ag-table__dot ag-table__dot--{{ $sesion['tono'] }}" aria-hidden="true"></span>
                {{ __('operaciones.sesion.estado.'.$sesion['estado']) }}
            </span>
        </div>
    @endforeach
</div>

<div class="ag-session-list">
    @foreach (($limiteLista !== null ? array_slice($sesiones, 0, $limiteLista) : $sesiones) as $sesion)
        <div class="ag-session-list__row">
            <span class="ag-session-list__hora">{{ \Illuminate\Support\Carbon::parse($sesion['inicio'])->format('H:i') }}</span>
            <span class="ag-session-list__body">
                <span class="ag-session-list__lote">{{ $sesion['lote'] }}</span>
                <span class="ag-session-list__meta">
                    {{ $sesion['piloto'] ?? '—' }} · {{ $sesion['dron'] ?? '—' }} · {{ $comoDuracion($sesion['minutosVuelo']) }} · {{ number_format((float) $sesion['hectareas'], 2, ',', '.') }} ha
                </span>
            </span>
            <x-atoms.badge :variant="$sesion['tono']">{{ __('operaciones.sesion.estado.'.$sesion['estado']) }}</x-atoms.badge>
        </div>
    @endforeach
</div>

<div class="ag-dash__hoy">
    @include('seguridad::pages.dashboard._fichas-sesiones', ['sesiones' => $sesiones])
</div>
