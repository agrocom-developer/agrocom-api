{{--
    Molecule: distribution-bar (auditoría visual externa, obs. #5 y #6 —
    reemplaza a donut-chart, retirado). Mismo prop shape que donut-chart:
    100% cambio de presentación, DatosDemoPanel::distribucionSesiones() no
    cambia. KPI grande (el total) a la izquierda + barra horizontal 100%
    apilada a la derecha, leyenda en grilla de 4 columnas debajo — sin el
    hueco de ancho que dejaba el donut con leyenda al costado, y sin el
    número "48" huérfano del `count` de section-head (ahora vive acá, como
    KPI de la propia tarjeta).

    Props:
    - segments (requerido): list<{estado, valor, pct, tono}>. `estado` es
      la clave de `operaciones.sesion.estado.*` (se resuelve acá, nunca
      antes — ADR 0013); `tono` uno de success|warning|info|neutral.
    - total (nullable string|int): cifra grande junto a la barra.
    - centerLabel (nullable string): rótulo chico bajo el total.
--}}
@props([
    'segments' => [],
    'total' => null,
    'centerLabel' => null,
])

@php
    $colorPorTono = [
        'success' => 'var(--ag-color-success)',
        'warning' => 'var(--ag-color-warning)',
        'info' => 'var(--ag-color-info)',
        'neutral' => 'var(--ag-color-text-faint)',
    ];
@endphp

<div {{ $attributes->class(['ag-distribution-bar']) }}>
    <div class="ag-distribution-bar__kpi">
        @if ($total !== null)
            <span class="ag-distribution-bar__total">{{ $total }}</span>
        @endif
        @if ($centerLabel)
            <span class="ag-distribution-bar__label">{{ $centerLabel }}</span>
        @endif
    </div>

    <div class="ag-distribution-bar__main">
        <div class="ag-distribution-bar__track" role="img" aria-label="{{ $centerLabel }}">
            @foreach ($segments as $segmento)
                <span
                    class="ag-distribution-bar__segment"
                    style="width: {{ $segmento['pct'] }}%; background: {{ $colorPorTono[$segmento['tono']] ?? $colorPorTono['neutral'] }};"
                ></span>
            @endforeach
        </div>

        <ul class="ag-distribution-bar__legend">
            @foreach ($segments as $segmento)
                <li class="ag-distribution-bar__legend-item">
                    <span class="ag-distribution-bar__dot ag-distribution-bar__dot--{{ $segmento['tono'] }}" aria-hidden="true"></span>
                    <span class="ag-distribution-bar__legend-label">{{ __('operaciones.sesion.estado.'.$segmento['estado']) }}</span>
                    <span class="ag-distribution-bar__legend-value">{{ $segmento['valor'] }}</span>
                    <span class="ag-distribution-bar__legend-pct">{{ $segmento['pct'] }}%</span>
                </li>
            @endforeach
        </ul>
    </div>
</div>
