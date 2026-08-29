{{--
    Parcial: cuadros informativos por lote (Fase 7). Reutiliza el mismo
    vocabulario success/warning/info/neutral que la leyenda del tab Mapa
    (mapa_leyenda_*) — son los mismos 4 estados operativos, solo que acá se
    ven como badge de tarjeta en vez de punto de mapa.

    Espera:
    - $lotes (list): filas de DatosDemoMapaOperativo::resumenPorLote().
--}}
@php
    $labelPorTono = [
        'success' => __('seguridad.dashboard.mapa_leyenda_completado'),
        'warning' => __('seguridad.dashboard.mapa_leyenda_atencion'),
        'info' => __('seguridad.dashboard.mapa_leyenda_en_vuelo'),
        'neutral' => __('seguridad.dashboard.mapa_leyenda_programado'),
    ];
@endphp

<div class="ag-dash__lotes-grid">
    @foreach ($lotes as $lote)
        <x-molecules.lote-resumen-card
            :lote="$lote['lote']"
            :cliente="$lote['cliente']"
            :hectareas-totales="$lote['hectareasTotales']"
            :hectareas-completadas="$lote['hectareasCompletadas']"
            :hectareas-pendientes="$lote['hectareasPendientes']"
            :litros-pesticida="$lote['litrosPesticida']"
            :litros-por-hectarea="$lote['litrosPorHectarea']"
            :tiempo-vuelo="$lote['tiempoVuelo']"
            :pct-completado="$lote['pctCompletado']"
            :tono="$lote['tono']"
            :estado-label="$labelPorTono[$lote['tono']] ?? null"
        />
    @endforeach
</div>
