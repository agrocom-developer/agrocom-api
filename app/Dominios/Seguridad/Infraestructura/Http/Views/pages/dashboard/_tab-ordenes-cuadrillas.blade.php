{{--
    Parcial: pestaña "Órdenes de trabajo" del jefe de campo (tarea 138) — las
    Órdenes de Trabajo (tandas) que siguen en marcha, agrupadas por la cuadrilla
    que las ejecuta. Una misma tanda con dos cuadrillas figura bajo las dos, cada
    vez con lo de esa cuadrilla.

    Solo lectura: «Ver» lleva a la ficha de la tanda; asignar o reasignar
    cuadrillas vive en su pantalla, no acá.

    Espera: $secciones['ordenes_trabajo_por_cuadrilla'] (list<{equipoTrabajoId,
    equipo, tandas: list<{ordenTrabajoId, ordenId, nroAplicacion, estado, tono,
    lotes: list<string>, trabajosAbiertos, trabajosTotal, hectareasDeclaradas}>}>),
    ya resuelto por ArmarDashboard::ordenesTrabajoPorCuadrilla().
--}}
<div class="ag-dash__stack">
    <section>
        <x-molecules.section-head :title="__('seguridad.dashboard.seccion_ordenes_cuadrillas')">
            <x-slot:actions>
                <a class="ag-dash__link" href="{{ route('panel.trabajos.index') }}">{{ __('seguridad.dashboard.ordenes_cuadrillas_ver') }}</a>
            </x-slot:actions>
        </x-molecules.section-head>
        <div class="ag-card">
            <div class="ag-table-scroll">
                <div class="ag-table ag-table--tandas" role="table">
                    <div class="ag-table__head" role="row">
                        <span role="columnheader">{{ __('seguridad.dashboard.ordenes_cuadrillas_col_trabajo') }}</span>
                        <span role="columnheader">{{ __('seguridad.dashboard.ordenes_cuadrillas_col_aplicacion') }}</span>
                        <span role="columnheader">{{ __('seguridad.dashboard.ordenes_cuadrillas_col_lotes') }}</span>
                        <span role="columnheader" class="ag-table__ha">{{ __('seguridad.dashboard.ordenes_cuadrillas_col_trabajos') }}</span>
                        <span role="columnheader" class="ag-table__ha">{{ __('seguridad.dashboard.ordenes_cuadrillas_col_hectareas') }}</span>
                        <span role="columnheader">{{ __('seguridad.dashboard.ordenes_cuadrillas_col_estado') }}</span>
                    </div>

                    @foreach ($secciones['ordenes_trabajo_por_cuadrilla'] as $cuadrilla)
                        <div class="ag-table__grupo" role="row">
                            <span role="cell" class="ag-table__strong">{{ $cuadrilla['equipo'] }}</span>
                            <span role="cell" class="ag-dash__mono-note">
                                {{ trans_choice('seguridad.dashboard.ordenes_cuadrillas_tandas', count($cuadrilla['tandas']), ['cantidad' => count($cuadrilla['tandas'])]) }}
                            </span>
                        </div>

                        @foreach ($cuadrilla['tandas'] as $tanda)
                            <div class="ag-table__row" role="row">
                                <span class="ag-table__strong" role="cell">
                                    <a class="ag-dash__link" href="{{ route('panel.trabajos.show', $tanda['ordenTrabajoId']) }}">#{{ $tanda['ordenTrabajoId'] }}</a>
                                </span>
                                <span role="cell">
                                    {{ __('seguridad.dashboard.ordenes_cuadrillas_aplicacion', ['id' => $tanda['ordenId'], 'nro' => $tanda['nroAplicacion']]) }}
                                </span>
                                <span role="cell" class="ag-table__sub">{{ implode(', ', $tanda['lotes']) }}</span>
                                <span role="cell" class="ag-table__ha">
                                    {{ __('seguridad.dashboard.ordenes_cuadrillas_abiertos', ['abiertos' => $tanda['trabajosAbiertos'], 'total' => $tanda['trabajosTotal']]) }}
                                </span>
                                <span role="cell" class="ag-table__ha">{{ number_format((float) $tanda['hectareasDeclaradas'], 2, ',', '.') }}</span>
                                <span role="cell" class="ag-table__estado">
                                    <span class="ag-table__dot ag-table__dot--{{ $tanda['tono'] }}" aria-hidden="true"></span>{{ __('operaciones.estado.'.$tanda['estado']) }}
                                </span>
                            </div>
                        @endforeach
                    @endforeach
                </div>
            </div>
        </div>
    </section>
</div>
