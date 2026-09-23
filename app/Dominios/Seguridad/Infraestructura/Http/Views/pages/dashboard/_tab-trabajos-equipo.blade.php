{{--
    Parcial: pestaña "Trabajos por equipo" del dueño (tarea 135) — lo que
    cada equipo tiene abierto AHORA MISMO, del más cargado al menos.

    A diferencia de "Avance por lote" (que agrupa por lote), esto agrupa por
    `equipo_trabajo_id`: la pregunta que responde es "qué está haciendo cada
    cuadrilla hoy", no "cómo va cada lote".

    Espera: $secciones['trabajos_por_equipo'] (list<array{equipoTrabajoId:
    int, equipo: string, trabajosAbiertos: int, hectareasDeclaradas: string,
    lotes: list<string>, ultimoInicio: ?string}>), ya resuelto por
    ArmarDashboard::trabajosPorEquipo().
--}}
<div class="ag-dash__stack">
    <section>
        <x-molecules.section-head :title="__('seguridad.dashboard.seccion_trabajos_equipo')" />
        <div class="ag-card">
            <div class="ag-table ag-table--trabajos-equipo" role="table">
                <div class="ag-table__head" role="row">
                    <span role="columnheader">{{ __('seguridad.dashboard.trabajos_equipo_col_equipo') }}</span>
                    <span role="columnheader" class="ag-table__ha">{{ __('seguridad.dashboard.trabajos_equipo_col_abiertos') }}</span>
                    <span role="columnheader" class="ag-table__ha">{{ __('seguridad.dashboard.trabajos_equipo_col_hectareas') }}</span>
                    <span role="columnheader">{{ __('seguridad.dashboard.trabajos_equipo_col_lotes') }}</span>
                    <span role="columnheader">{{ __('seguridad.dashboard.trabajos_equipo_col_ultimo') }}</span>
                </div>
                @foreach ($secciones['trabajos_por_equipo'] as $fila)
                    <div class="ag-table__row" role="row">
                        <span class="ag-table__strong" role="cell">{{ $fila['equipo'] }}</span>
                        <span role="cell" class="ag-table__ha">{{ $fila['trabajosAbiertos'] }}</span>
                        <span role="cell" class="ag-table__ha">{{ number_format((float) $fila['hectareasDeclaradas'], 2, ',', '.') }}</span>
                        <span role="cell" class="ag-table__sub">{{ implode(', ', $fila['lotes']) }}</span>
                        <span role="cell" class="ag-table__mono">
                            {{ $fila['ultimoInicio'] ? \Illuminate\Support\Carbon::parse($fila['ultimoInicio'])->format('d/m H:i') : '—' }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
</div>
