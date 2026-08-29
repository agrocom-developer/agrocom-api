{{--
    Parcial: detalle de clientes (Fase 5) — actividad reciente + estado de
    contrato combinados en una sola tabla, dentro del tab Resumen.

    Espera:
    - $clientes (list): filas de DatosDemoPanel::detalleClientes().

    Sin variante de tablet/móvil propia (a diferencia de _tabla-sesiones):
    en vez de una lista de dos líneas o fichas, esta tabla se mantiene
    entera con scroll horizontal (.ag-table-scroll) bajo 1200px — son 6
    columnas con datos que no se resumen bien en una ficha compacta.
--}}
@php
    $tonoPorEstadoContrato = [
        'vigente' => 'success',
        'borrador' => 'neutral',
        'finalizado' => 'neutral',
        'cancelado' => 'danger',
    ];
@endphp

<div class="ag-table-scroll">
    <div class="ag-table ag-table--clientes" role="table">
        <div class="ag-table__head" role="row">
            <span role="columnheader">{{ __('seguridad.dashboard.clientes_col_cliente') }}</span>
            <span role="columnheader">{{ __('seguridad.dashboard.clientes_col_actividad') }}</span>
            <span role="columnheader">{{ __('seguridad.dashboard.clientes_col_ultima_sesion') }}</span>
            <span role="columnheader">{{ __('seguridad.dashboard.clientes_col_contrato') }}</span>
            <span role="columnheader">{{ __('seguridad.dashboard.clientes_col_ejecucion') }}</span>
            <span role="columnheader">{{ __('seguridad.dashboard.clientes_col_vence') }}</span>
        </div>
        @foreach ($clientes as $cliente)
            @php($tono = $tonoPorEstadoContrato[$cliente['contrato']['estado']] ?? 'neutral')
            <div class="ag-table__row" role="row">
                <span class="ag-table__strong" role="cell">{{ $cliente['cliente'] }}</span>
                <span role="cell">{{ __('seguridad.dashboard.clientes_actividad', ['sesiones' => $cliente['actividad']['sesionesPeriodo'], 'ha' => $cliente['actividad']['hectareasPeriodo']]) }}</span>
                <span class="ag-table__mono" role="cell">{{ $cliente['actividad']['ultimaSesion'] }}</span>
                <span role="cell">
                    <x-atoms.badge :variant="$tono">{{ __('comercial.contrato.estado.'.$cliente['contrato']['estado']) }}</x-atoms.badge>
                </span>
                <span role="cell" class="ag-dash__clientes-ejecucion">
                    <span class="ag-bars__track ag-dash__clientes-track">
                        <span class="ag-bars__fill ag-bars__fill--primary" style="width: {{ min(100, $cliente['contrato']['pctEjecutado']) }}%"></span>
                    </span>
                    <span class="ag-dash__mono-note">{{ $cliente['contrato']['hectareasEjecutadas'] }} / {{ $cliente['contrato']['hectareasContratadas'] }}</span>
                </span>
                <span
                    role="cell"
                    class="ag-table__mono {{ $cliente['contrato']['diasParaVencer'] < 0 ? 'ag-dash__clientes-vencido' : ($cliente['contrato']['diasParaVencer'] <= 20 ? 'ag-dash__clientes-por-vencer' : '') }}"
                >
                    @if ($cliente['contrato']['diasParaVencer'] < 0)
                        {{ __('seguridad.dashboard.clientes_vencido') }}
                    @else
                        {{ __('seguridad.dashboard.clientes_vence_en', ['dias' => $cliente['contrato']['diasParaVencer']]) }}
                    @endif
                </span>
            </div>
        @endforeach
    </div>
</div>
