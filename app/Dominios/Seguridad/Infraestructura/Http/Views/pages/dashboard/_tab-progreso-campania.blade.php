{{--
    Parcial: pestaña "Progreso de campaña" del dueño (tarea 135) — el MISMO
    avance por lote de "Avance por lote" (otros roles), totalizado en una
    franja de KPI en vez de fila por fila: al dueño le interesa el número de
    toda la operación, no lote por lote.

    Espera: $secciones['progreso_campania'] (array{lotes: int,
    hectareasTotales: string, hectareasAplicadas: string,
    hectareasPendientes: string, pctCompletado: float, sesiones: int,
    sesionesValidadas: int}), ya resuelto por ArmarDashboard::progresoCampania().
--}}
@php($progreso = $secciones['progreso_campania'])

<div class="ag-dash__stack">
    <section>
        <x-molecules.section-head :title="__('seguridad.dashboard.seccion_progreso_campania')" />
        <div class="ag-dash__cards-grid">
            <x-molecules.stat-card
                :label="__('seguridad.dashboard.progreso_campania_kpi_totales')"
                icon="landscape"
                :value="number_format((float) $progreso['hectareasTotales'], 2, ',', '.')"
                :value-suffix="__('seguridad.dashboard.progreso_campania_unidad_hectareas')"
                :foot="trans_choice('seguridad.dashboard.progreso_campania_lotes', $progreso['lotes'], ['cantidad' => $progreso['lotes']])"
                state="distintivo-1"
            />
            <x-molecules.stat-card
                :label="__('seguridad.dashboard.progreso_campania_kpi_aplicadas')"
                icon="task_alt"
                :value="number_format((float) $progreso['hectareasAplicadas'], 2, ',', '.')"
                :value-suffix="__('seguridad.dashboard.progreso_campania_unidad_hectareas')"
                state="success"
            />
            <x-molecules.stat-card
                :label="__('seguridad.dashboard.progreso_campania_kpi_pendientes')"
                icon="hourglass_top"
                :value="number_format((float) $progreso['hectareasPendientes'], 2, ',', '.')"
                :value-suffix="__('seguridad.dashboard.progreso_campania_unidad_hectareas')"
                :state="(float) $progreso['hectareasPendientes'] > 0 ? 'warning' : null"
            />
            <x-molecules.stat-card
                :label="__('seguridad.dashboard.progreso_campania_kpi_pct')"
                icon="donut_large"
                :value="number_format($progreso['pctCompletado'], 1, ',', '.')"
                value-suffix="%"
                state="info"
            />
        </div>
    </section>
</div>
