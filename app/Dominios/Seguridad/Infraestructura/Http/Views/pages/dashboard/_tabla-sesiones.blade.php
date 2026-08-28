{{--
    Parcial: tabla de sesiones (quinta vuelta — maquetas 4a/5a).
    Dos representaciones del MISMO dato, conmutadas por breakpoint en
    pages/dashboard.css:
    - `.ag-table`: la grilla de escritorio (≥1200) con columnas
      HORA/LOTE/PILOTO/DRON/HA/ESTADO.
    - `.ag-session-list`: la lista de dos líneas de tablet (<1200) — hora +
      lote arriba, piloto · dron · ha abajo, chip de estado a la derecha.

    Espera:
    - $sesiones (list): filas de DatosDemoPanel::sesiones().
    - $limiteLista (int|null, opcional): tope de filas SOLO para la lista de
      tablet (la maqueta 5a muestra 4).
--}}
@php($limiteLista = $limiteLista ?? null)

<div class="ag-table" role="table">
    <div class="ag-table__head" role="row">
        <span role="columnheader">{{ __('seguridad.dashboard.col_hora') }}</span>
        <span role="columnheader">{{ __('seguridad.dashboard.col_lote') }}</span>
        <span role="columnheader">{{ __('seguridad.dashboard.col_piloto') }}</span>
        <span role="columnheader">{{ __('seguridad.dashboard.col_dron') }}</span>
        <span role="columnheader">{{ __('seguridad.dashboard.col_ha') }}</span>
        <span role="columnheader">{{ __('seguridad.dashboard.col_estado') }}</span>
    </div>
    @foreach ($sesiones as $sesion)
        <div class="ag-table__row" role="row">
            <span class="ag-table__mono" role="cell">{{ $sesion['hora'] }}</span>
            <span class="ag-table__strong" role="cell">{{ $sesion['lote'] }}</span>
            <span role="cell">{{ $sesion['piloto'] }}</span>
            <span class="ag-table__mono" role="cell">{{ $sesion['dron'] }}</span>
            <span role="cell">{{ $sesion['ha'] }}</span>
            <span role="cell">
                <x-atoms.badge :variant="$sesion['variante']">{{ __('operaciones.sesion.estado.'.$sesion['estado']) }}</x-atoms.badge>
            </span>
        </div>
    @endforeach
</div>

<div class="ag-session-list">
    @foreach (($limiteLista !== null ? array_slice($sesiones, 0, $limiteLista) : $sesiones) as $sesion)
        <div class="ag-session-list__row">
            <span class="ag-session-list__hora">{{ $sesion['hora'] }}</span>
            <span class="ag-session-list__body">
                <span class="ag-session-list__lote">{{ $sesion['lote'] }}</span>
                <span class="ag-session-list__meta">{{ $sesion['piloto'] }} · {{ $sesion['dron'] }} · {{ $sesion['ha'] }}</span>
            </span>
            <x-atoms.badge :variant="$sesion['variante']">{{ __('operaciones.sesion.estado.'.$sesion['estado']) }}</x-atoms.badge>
        </div>
    @endforeach
</div>
