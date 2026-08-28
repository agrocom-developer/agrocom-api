{{--
    Molecule: donut-chart (Fase 4 del rediseño de dashboard — gráfica mock,
    docs/ganadosoft-dashboard.html §2.3: anillo hueco con conic-gradient +
    leyenda, CERO librería nueva. La paleta original se descarta — usa los
    tonos categóricos ya existentes del catálogo (success/warning/info/
    neutral), mismo vocabulario que `variante` en las filas de sesiones.

    Sin lógica de negocio: el llamador ya trae `pct` calculado (mismo
    criterio que `_barras-pausas` con `pausas.causas[].pct`) — este
    componente solo acumula los cortes del conic-gradient.

    Props:
    - segments (requerido): list<{estado, valor, pct, tono}>. `estado` es
      la clave de `operaciones.sesion.estado.*` (se resuelve acá, nunca
      antes — ADR 0013); `tono` uno de success|warning|info|neutral.
    - total (nullable string|int): cifra grande en el centro del anillo.
    - centerLabel (nullable string): rótulo chico bajo el total, ya
      traducido.
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

    $acumulado = 0;
    $cortes = collect($segments)->map(function ($segmento) use (&$acumulado, $colorPorTono) {
        $inicio = $acumulado;
        $acumulado += (float) $segmento['pct'];
        $color = $colorPorTono[$segmento['tono']] ?? $colorPorTono['neutral'];

        return "{$color} {$inicio}% {$acumulado}%";
    })->implode(', ');
@endphp

<div {{ $attributes->class(['ag-donut-chart']) }}>
    <div class="ag-donut-chart__ring" style="background: conic-gradient({{ $cortes }});">
        <div class="ag-donut-chart__hole">
            @if ($total !== null)
                <span class="ag-donut-chart__total">{{ $total }}</span>
            @endif
            @if ($centerLabel)
                <span class="ag-donut-chart__center-label">{{ $centerLabel }}</span>
            @endif
        </div>
    </div>

    <ul class="ag-donut-chart__legend">
        @foreach ($segments as $segmento)
            <li class="ag-donut-chart__legend-item">
                <span class="ag-donut-chart__dot ag-donut-chart__dot--{{ $segmento['tono'] }}" aria-hidden="true"></span>
                <span class="ag-donut-chart__legend-label">{{ __('operaciones.sesion.estado.'.$segmento['estado']) }}</span>
                <span class="ag-donut-chart__legend-value">{{ $segmento['valor'] }}</span>
                <span class="ag-donut-chart__legend-pct">{{ $segmento['pct'] }}%</span>
            </li>
        @endforeach
    </ul>
</div>
