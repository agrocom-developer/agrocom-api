{{--
    Parcial: cuadros informativos por lote. Reutiliza el mismo vocabulario
    success/warning/info/neutral que la leyenda del tab Mapa — son los mismos
    estados operativos, acá como badge de tarjeta y allá como color de
    polígono.

    Espera: $lotes (list): filas de ArmarDashboard::filaLote().
--}}
@php
    $labelPorTono = [
        'success' => __('seguridad.dashboard.mapa_leyenda_completado'),
        'warning' => __('seguridad.dashboard.mapa_leyenda_atencion'),
        'info' => __('seguridad.dashboard.mapa_leyenda_en_vuelo'),
        'neutral' => __('seguridad.dashboard.mapa_leyenda_programado'),
    ];

    /** Minutos a "h:mm" — formato de presentación, no un dato distinto. */
    $comoDuracion = fn (int $minutos) => sprintf('%d:%02d', intdiv($minutos, 60), $minutos % 60);
@endphp

<div class="ag-dash__lotes-grid">
    @foreach ($lotes as $lote)
        <x-molecules.lote-resumen-card
            :lote="$lote['codigo']"
            :cliente="$lote['cliente'] ?? $lote['propiedad'] ?? ''"
            :hectareas-totales="(float) ($lote['hectareasLote'] ?? 0)"
            :hectareas-completadas="(float) $lote['hectareasAplicadas']"
            :hectareas-pendientes="(float) $lote['hectareasPendientes']"
            :litros-pesticida="(float) $lote['litros']"
            :litros-por-hectarea="(float) $lote['hectareasAplicadas'] > 0 ? round((float) $lote['litros'] / (float) $lote['hectareasAplicadas'], 2) : 0"
            :tiempo-vuelo="$comoDuracion($lote['minutosVuelo'])"
            :pct-completado="$lote['pctCompletado']"
            :tono="$lote['tono']"
            :estado-label="$labelPorTono[$lote['tono']] ?? null"
        />
    @endforeach
</div>
