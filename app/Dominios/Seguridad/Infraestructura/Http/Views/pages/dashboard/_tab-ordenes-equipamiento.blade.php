{{--
    Parcial: pestaña "Órdenes y equipamiento" del jefe de campo (tarea 138) — el
    estado de las órdenes de aplicación con las haciendas que cubren y el
    equipamiento de las cuadrillas que las trabajan. Cruza `Operaciones` (estado
    y cuadrillas de la orden), `Comercial` (haciendas, por los lotes) y
    `Mantenimiento` (estado de dron, vehículo, generador y baterías).

    Solo las órdenes abiertas llevan equipamiento: el estado de una batería HOY
    no dice nada de una orden ya consumida o cancelada. De cada cuadrilla se
    nombran solo las piezas que NO están operativas; el detalle completo vive en
    la pestaña «Recursos».

    Bajo 768 px la tabla no scrollea: cada fila se apila y `data-label` rotula
    cada dato (ver dashboard.css).

    Solo lectura: el número de cada orden y «Ver órdenes» llevan a su pantalla,
    cuyo permiso (`operaciones.orden.ver`) es el mismo que gatea esta sección.

    Espera: $secciones['ordenes_con_equipamiento'] (list<{id, nroAplicacion,
    estado, tono, abierta, fechaEmision, clientes: list<string>, haciendas:
    list<string>, hectareas, equiposNecesarios, cuadrillasAsignadas, cuadrillas:
    list<{equipo, recursos, fuera: list<{tipoEtiqueta, identificador,
    etiquetaEstado, tono}>}>}>), ya resuelto por
    ArmarDashboard::ordenesConEquipamiento().
--}}
<div class="ag-dash__stack">
    <section>
        <x-molecules.section-head :title="__('seguridad.dashboard.seccion_ordenes_equipamiento')">
            <x-slot:actions>
                <a class="ag-dash__link" href="{{ route('panel.ordenes.index') }}">{{ __('seguridad.dashboard.ordenes_equipamiento_ver') }}</a>
            </x-slot:actions>
        </x-molecules.section-head>
        <div class="ag-card">
            <div class="ag-table-scroll">
                <div class="ag-table ag-table--ordenes-equipo" role="table">
                    <div class="ag-table__head" role="row">
                        <span role="columnheader">{{ __('seguridad.dashboard.ordenes_equipamiento_col_orden') }}</span>
                        <span role="columnheader">{{ __('seguridad.dashboard.ordenes_equipamiento_col_estado') }}</span>
                        <span role="columnheader">{{ __('seguridad.dashboard.ordenes_equipamiento_col_haciendas') }}</span>
                        <span role="columnheader">{{ __('seguridad.dashboard.ordenes_equipamiento_col_cuadrillas') }}</span>
                        <span role="columnheader">{{ __('seguridad.dashboard.ordenes_equipamiento_col_equipamiento') }}</span>
                    </div>

                    @foreach ($secciones['ordenes_con_equipamiento'] as $orden)
                        <div class="ag-table__row" role="row">
                            <span class="ag-table__strong" role="cell">
                                <a class="ag-dash__link" href="{{ route('panel.ordenes.show', $orden['id']) }}">{{ __('seguridad.dashboard.ordenes_equipamiento_numero', ['id' => $orden['id'], 'nro' => $orden['nroAplicacion']]) }}</a>
                                <span class="ag-table__sub">{{ implode(', ', $orden['clientes']) }}</span>
                            </span>

                            <span role="cell" class="ag-table__estado" data-label="{{ __('seguridad.dashboard.ordenes_equipamiento_col_estado') }}">
                                <span class="ag-table__dot ag-table__dot--{{ $orden['tono'] }}" aria-hidden="true"></span>{{ __('operaciones.estado.'.$orden['estado']) }}
                            </span>

                            <span role="cell" data-label="{{ __('seguridad.dashboard.ordenes_equipamiento_col_haciendas') }}">
                                {{ implode(', ', $orden['haciendas']) }}
                                <span class="ag-table__sub">{{ __('seguridad.dashboard.ordenes_equipamiento_hectareas', ['hectareas' => number_format((float) $orden['hectareas'], 2, ',', '.')]) }}</span>
                            </span>

                            <span role="cell" data-label="{{ __('seguridad.dashboard.ordenes_equipamiento_col_cuadrillas') }}">
                                @if ($orden['abierta'])
                                    {{ __('seguridad.dashboard.ordenes_equipamiento_cuadrillas', ['asignadas' => $orden['cuadrillasAsignadas'], 'necesarias' => $orden['equiposNecesarios']]) }}
                                @else
                                    —
                                @endif
                            </span>

                            <span role="cell" class="ag-dash__equipamiento-orden" data-label="{{ __('seguridad.dashboard.ordenes_equipamiento_col_equipamiento') }}">
                                @if (! $orden['abierta'])
                                    <span class="ag-dash__mono-note">—</span>
                                @elseif ($orden['cuadrillas'] === [])
                                    <span class="ag-dash__mono-note">{{ __('seguridad.dashboard.ordenes_equipamiento_sin_cuadrillas') }}</span>
                                @else
                                    @foreach ($orden['cuadrillas'] as $cuadrilla)
                                        <span class="ag-dash__equipamiento-cuadrilla">
                                            <span class="ag-table__strong">{{ $cuadrilla['equipo'] }}</span>
                                            @if ($cuadrilla['fuera'] === [])
                                                <span class="ag-table__estado">
                                                    <span class="ag-table__dot ag-table__dot--success" aria-hidden="true"></span>{{ trans_choice('seguridad.dashboard.ordenes_equipamiento_todo_operativo', $cuadrilla['recursos'], ['cantidad' => $cuadrilla['recursos']]) }}
                                                </span>
                                            @else
                                                <span class="ag-table__estado">
                                                    <span class="ag-table__dot ag-table__dot--neutral" aria-hidden="true"></span>{{ __('seguridad.dashboard.ordenes_equipamiento_fuera', ['cantidad' => count($cuadrilla['fuera']), 'total' => $cuadrilla['recursos']]) }}
                                                </span>
                                                @foreach ($cuadrilla['fuera'] as $pieza)
                                                    <span class="ag-table__sub">{{ $pieza['tipoEtiqueta'] }} {{ $pieza['identificador'] }} · {{ $pieza['etiquetaEstado'] }}</span>
                                                @endforeach
                                            @endif
                                        </span>
                                    @endforeach
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
</div>
