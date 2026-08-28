{{--
    Parcial: tabla de eventos individuales de pausa (Fase 6 — drill-down del
    tab Pausas, bajo el agregado por causa de _barras-pausas). Reutiliza el
    skin `.ag-table` (mismo criterio visual que la tabla de sesiones), con
    su propia grilla de 4 columnas vía `.ag-table--eventos`.

    Espera:
    - $eventos (list): DatosDemoPanel::pausasEventos().
--}}
<div class="ag-table ag-table--eventos" role="table">
    <div class="ag-table__head" role="row">
        <span role="columnheader">{{ __('seguridad.dashboard.pausas_col_hora') }}</span>
        <span role="columnheader">{{ __('seguridad.dashboard.pausas_col_lote') }}</span>
        <span role="columnheader">{{ __('seguridad.dashboard.pausas_col_causa') }}</span>
        <span role="columnheader">{{ __('seguridad.dashboard.pausas_col_duracion') }}</span>
    </div>
    @foreach ($eventos as $evento)
        <div class="ag-table__row" role="row">
            <span class="ag-table__mono" role="cell">{{ $evento['hora'] }}</span>
            <span class="ag-table__strong" role="cell">{{ $evento['lote'] }}</span>
            <span role="cell">
                <span class="ag-table__dot ag-table__dot--{{ $evento['tono'] }}" aria-hidden="true"></span>
                {{ $evento['causa'] }}
            </span>
            <span class="ag-table__mono" role="cell">{{ $evento['duracion'] }}</span>
        </div>
    @endforeach
</div>
