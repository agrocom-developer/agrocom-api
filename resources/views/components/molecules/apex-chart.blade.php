{{--
    Molecule: apex-chart. Contenedor para un gráfico ApexCharts
    (resources/js/organisms/dashboard-charts.js lo instancia vía import()
    dinámico — ver resources/js/app.js). Sin lógica de presentación acá: el
    llamador ya trae `series`/`labels` con la forma que cada tipo de gráfico
    de ApexCharts espera; este componente solo serializa esos datos a
    `data-*` para que el JS los lea.

    Props:
    - type (requerido): pie|donut|area|radialBar.
    - series (requerido): shape según `type` —
        pie/donut: list<number>; area: list<array{name: string, data: list<number>}>;
        radialBar: list<number> (un solo valor 0-100).
    - labels (opcional): pie/donut → nombre de cada porción (ya traducido);
        area → categorías del eje X; radialBar → nombre bajo el valor central.
    - colorTokens (opcional): list<string>, nombres de token `--ag-color-*`
        (NUNCA hex — CLAUDE.md invariante 11), resueltos en JS vía
        shared/color-tokens.js.
    - height (opcional, default 260): alto en px que se pasa a `chart.height`
        de ApexCharts (el propio contenedor solo lleva ese valor como
        `min-height` mientras el chunk diferido carga — ApexCharts fija el
        alto final real, que en pie/donut suele ser mayor por la leyenda).
--}}
@props([
    'type',
    'series',
    'labels' => [],
    'colorTokens' => [],
    'height' => 260,
])

<div
    {{ $attributes->class(['ag-apex-chart']) }}
    data-ag-chart="{{ $type }}"
    data-ag-chart-series="{{ json_encode($series) }}"
    data-ag-chart-labels="{{ json_encode($labels) }}"
    data-ag-chart-color-tokens="{{ json_encode($colorTokens) }}"
    data-ag-chart-height="{{ $height }}"
    style="min-height: {{ $height }}px"
></div>
