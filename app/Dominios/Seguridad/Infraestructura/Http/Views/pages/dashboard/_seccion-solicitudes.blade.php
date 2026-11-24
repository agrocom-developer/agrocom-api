{{--
    Parcial: solicitudes por estado (ADR 0027) — cuántas hay en cada paso del
    ciclo, para ver dónde se acumula el trabajo. Cada fila lleva al listado
    del panel filtrado por ese estado.

    Espera:
    - $solicitudes (list<array{estado: string, cantidad: int}>), ver
      ArmarDashboard::solicitudesPorEstado(): en el orden del ciclo.
--}}
<section class="ag-dash__card">
    <header class="ag-dash__card-head">
        <h2 class="ag-dash__card-title">{{ __('seguridad.dashboard.solicitudes_titulo') }}</h2>
    </header>

    <div class="ag-table" role="table">
        <div class="ag-table__head" role="row">
            <span role="columnheader">{{ __('seguridad.dashboard.col_estado') }}</span>
            <span role="columnheader" class="ag-table__ha">{{ __('seguridad.dashboard.col_cantidad') }}</span>
        </div>
        @foreach ($solicitudes as $fila)
            <div class="ag-table__row" role="row">
                <span class="ag-table__strong" role="cell">
                    <a class="ag-dash__link" href="{{ route('panel.solicitudes.index', ['estado' => $fila['estado']]) }}">{{ __('solicitudes.estado.'.$fila['estado']) }}</a>
                </span>
                <span role="cell" class="ag-table__ha ag-table__mono">{{ $fila['cantidad'] }}</span>
            </div>
        @endforeach
    </div>
</section>
