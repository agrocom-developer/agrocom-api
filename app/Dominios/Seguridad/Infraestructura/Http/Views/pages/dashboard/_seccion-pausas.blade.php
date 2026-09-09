{{--
    Parcial: pausas por causa del mes (HU-44). El ancho de cada barra es el
    porcentaje sobre el total de minutos, calculado acá porque es geometría
    de la barra, no una regla de negocio: el agregado ya vino sumado.

    Espera: $pausas = {total_minutos: int, por_causa: array<string, int>}.
--}}
@php
    $totalMinutos = max(1, $pausas['total_minutos']);
    $causas = collect($pausas['por_causa'])
        ->filter(fn (int $minutos) => $minutos > 0)
        ->sortDesc()
        ->map(fn (int $minutos, string $causa) => [
            'causa' => __('operaciones.pausas.causa.'.$causa),
            'horas' => __('seguridad.dashboard.pausas_minutos', ['minutos' => $minutos]),
            'pct' => round($minutos / $totalMinutos * 100, 1),
            'tono' => 'warning',
        ])
        ->values();
@endphp

<div class="ag-card ag-card--padded">
    <div class="ag-card__head ag-card__head--flush">
        <h2 class="ag-card__title">{{ __('seguridad.dashboard.pausas_titulo') }}</h2>
        <span class="ag-dash__mono-note">{{ __('seguridad.dashboard.pausas_minutos', ['minutos' => $pausas['total_minutos']]) }}</span>
    </div>
    @include('seguridad::pages.dashboard._barras-pausas', ['causas' => $causas])
</div>
