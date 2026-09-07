{{--
    Parcial: avance por contrato, del más atrasado al más adelantado — lo que
    le importa al dueño es qué contrato no va a llegar.

    Es el MISMO número que el reporte comercial (HU-32): el adaptador delega
    en `ObtenerAvanceComercial` en vez de recalcularlo, así que dashboard y
    reporte no pueden discrepar.

    Espera: $avances (list<AvanceClientePanel>).
--}}
<section>
    <x-molecules.section-head :title="__('seguridad.dashboard.seccion_clientes')" />
    <div class="ag-card">
        <div class="ag-table ag-table--clientes" role="table">
            <div class="ag-table__head" role="row">
                <span role="columnheader">{{ __('seguridad.dashboard.clientes_col_cliente') }}</span>
                <span role="columnheader">{{ __('seguridad.dashboard.clientes_col_ejecucion') }}</span>
                <span role="columnheader" class="ag-table__ha">{{ __('seguridad.dashboard.clientes_col_aplicadas') }}</span>
                <span role="columnheader" class="ag-table__ha">{{ __('seguridad.dashboard.clientes_col_contratadas') }}</span>
            </div>
            @foreach ($avances as $avance)
                <div class="ag-table__row" role="row">
                    <span class="ag-table__strong" role="cell">{{ $avance->clienteNombre }}</span>
                    <span role="cell" class="ag-dash__avance">
                        <span class="ag-bars__track">
                            <span class="ag-bars__fill ag-bars__fill--primary" style="width: {{ $avance->porcentaje }}%"></span>
                        </span>
                        <span class="ag-dash__mono-note">{{ $avance->porcentaje }}%</span>
                    </span>
                    <span role="cell" class="ag-table__ha">{{ number_format((float) $avance->hectareasAplicadas, 2, ',', '.') }}</span>
                    <span role="cell" class="ag-table__ha">{{ number_format((float) $avance->hectareasContratadas, 2, ',', '.') }}</span>
                </div>
            @endforeach
        </div>
    </div>
</section>
