{{--
    Molecule: lote-resumen-card (Fase 7 — tab "Resumen por lote"). Cuadro
    informativo por lote: cabecera (lote + cliente + badge de estado),
    barra de avance, y grilla de métricas (hectáreas completadas/
    pendientes/totales, litros de pesticida, L/ha, tiempo de vuelo).

    Sin lógica de negocio: `estadoLabel` ya viene traducido y `tono` ya
    resuelto por el llamador — mismo criterio que stat-card/apex-chart.

    Props:
    - lote, cliente (requeridos): identidad del lote, ya formateados.
    - hectareasTotales, hectareasCompletadas, hectareasPendientes (float).
    - litrosPesticida, litrosPorHectarea (float).
    - tiempoVuelo (string, ya formateado "h:mm").
    - pctCompletado (float, 0-100).
    - tono (success|warning|info|neutral): color del badge y de la barra.
    - estadoLabel (string, ya traducido): texto del badge.
--}}
@props([
    'lote',
    'cliente',
    'hectareasTotales',
    'hectareasCompletadas',
    'hectareasPendientes',
    'litrosPesticida',
    'litrosPorHectarea',
    'tiempoVuelo',
    'pctCompletado',
    'tono' => 'neutral',
    'estadoLabel' => null,
])

<div {{ $attributes->class(['ag-lote-card']) }}>
    <div class="ag-lote-card__head">
        <div class="ag-lote-card__id">
            <h3 class="ag-lote-card__nombre">{{ $lote }}</h3>
            <p class="ag-lote-card__cliente">{{ $cliente }}</p>
        </div>
        @if ($estadoLabel)
            <x-atoms.badge :variant="$tono">{{ $estadoLabel }}</x-atoms.badge>
        @endif
    </div>

    <div class="ag-lote-card__progreso">
        <span class="ag-bars__track">
            <span class="ag-bars__fill ag-bars__fill--primary" style="width: {{ min(100, $pctCompletado) }}%"></span>
        </span>
        <span class="ag-dash__mono-note">{{ number_format($pctCompletado, 1, ',', '.') }}%</span>
    </div>

    <dl class="ag-lote-card__grid">
        <div class="ag-lote-card__metric">
            <dt>{{ __('seguridad.dashboard.lote_col_completadas') }}</dt>
            <dd>{{ number_format($hectareasCompletadas, 1, ',', '.') }} ha</dd>
        </div>
        <div class="ag-lote-card__metric">
            <dt>{{ __('seguridad.dashboard.lote_col_pendientes') }}</dt>
            <dd>{{ number_format($hectareasPendientes, 1, ',', '.') }} ha</dd>
        </div>
        <div class="ag-lote-card__metric">
            <dt>{{ __('seguridad.dashboard.lote_col_total') }}</dt>
            <dd>{{ number_format($hectareasTotales, 1, ',', '.') }} ha</dd>
        </div>
        <div class="ag-lote-card__metric">
            <dt>{{ __('seguridad.dashboard.lote_col_litros') }}</dt>
            <dd>{{ number_format($litrosPesticida, 0, ',', '.') }} L</dd>
        </div>
        <div class="ag-lote-card__metric">
            <dt>{{ __('seguridad.dashboard.lote_col_litros_ha') }}</dt>
            <dd>{{ number_format($litrosPorHectarea, 2, ',', '.') }} L/ha</dd>
        </div>
        <div class="ag-lote-card__metric">
            <dt>{{ __('seguridad.dashboard.lote_col_tiempo') }}</dt>
            <dd>{{ $tiempoVuelo }}</dd>
        </div>
    </dl>
</div>
