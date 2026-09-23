{{--
    Parcial: pestaña "Estado de cuentas" del dueño (tarea 135) — por contrato,
    lo contratado, lo ya facturado, el adelanto pedido y lo que falta
    facturar.

    `montoFacturado` es el MISMO número que la tab "Avance por contrato" de
    cualquier otro rol (ambas delegan en `ObtenerAvanceComercial`, HU-32):
    esta tarjeta agrega la mitad en plata que esa no muestra.

    Espera: $secciones['estado_cuentas'] (list<EstadoCuentaContratoPanel>).
--}}
<div class="ag-dash__stack">
    <section>
        <x-molecules.section-head :title="__('seguridad.dashboard.seccion_estado_cuentas')" />
        <div class="ag-card">
            <div class="ag-table ag-table--cuentas" role="table">
                <div class="ag-table__head" role="row">
                    <span role="columnheader">{{ __('seguridad.dashboard.cuentas_col_cliente') }}</span>
                    <span role="columnheader" class="ag-table__ha">{{ __('seguridad.dashboard.cuentas_col_contratado') }}</span>
                    <span role="columnheader" class="ag-table__ha">{{ __('seguridad.dashboard.cuentas_col_facturado') }}</span>
                    <span role="columnheader" class="ag-table__ha">{{ __('seguridad.dashboard.cuentas_col_adelanto') }}</span>
                    <span role="columnheader" class="ag-table__ha">{{ __('seguridad.dashboard.cuentas_col_saldo') }}</span>
                </div>
                @foreach ($secciones['estado_cuentas'] as $estado)
                    <div class="ag-table__row" role="row">
                        <span class="ag-table__strong" role="cell">{{ $estado->clienteNombre }}</span>
                        <span role="cell" class="ag-table__ha">{{ number_format((float) $estado->montoContratado, 2, ',', '.') }}</span>
                        <span role="cell" class="ag-table__ha">{{ number_format((float) $estado->montoFacturado, 2, ',', '.') }}</span>
                        <span role="cell" class="ag-table__ha">{{ number_format((float) $estado->adelantoMonto, 2, ',', '.') }}</span>
                        <span role="cell" class="ag-table__ha ag-table__strong">{{ number_format((float) $estado->saldoPendiente, 2, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
</div>
