{{--
    Parcial: donut de sesiones por estado.

    El mapeo tono → token vive acá y no en el JS porque `apex-chart` recibe
    NOMBRES de token, nunca colores (invariante 11): el chunk de gráficos los
    resuelve contra el tema activo, así que el donut se repinta solo al
    cambiar claro/oscuro.

    Espera: $distribucion (list<{estado, tono, valor}>).
--}}
@php
    $tokenPorTono = [
        'success' => '--ag-color-success',
        'warning' => '--ag-color-warning',
        'info' => '--ag-color-info',
        'neutral' => '--ag-color-text-faint',
    ];
@endphp

<div class="ag-card ag-card--padded">
    <x-molecules.section-head :title="__('seguridad.dashboard.seccion_sesiones_estado')" />
    <x-molecules.apex-chart
        type="donut"
        :series="array_column($distribucion, 'valor')"
        :labels="array_map(fn ($s) => __('operaciones.sesion.estado.'.$s['estado']), $distribucion)"
        :color-tokens="array_map(fn ($s) => $tokenPorTono[$s['tono']] ?? $tokenPorTono['neutral'], $distribucion)"
        :height="320"
    />
</div>
