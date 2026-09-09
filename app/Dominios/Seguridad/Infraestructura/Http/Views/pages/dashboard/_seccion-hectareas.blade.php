{{--
    Parcial: área de hectáreas validadas por día.

    Solo cuentan las sesiones VALIDADAS: una sesión cerrada todavía puede
    rechazarse, y esta curva tiene que coincidir con lo que después se
    factura (invariante 6 — la hectárea es dinero).

    Las etiquetas del eje X se acortan a "DD/MM" acá: es formato de eje, no
    un dato distinto (el servidor manda la fecha ISO completa).

    Espera: $serie = {fechas: list<string>, valores: list<string>}.
--}}
<div class="ag-card ag-card--padded">
    <x-molecules.section-head :title="__('seguridad.dashboard.seccion_hectareas_periodo')" />
    <x-molecules.apex-chart
        type="area"
        :series="[['name' => __('seguridad.dashboard.seccion_hectareas_periodo'), 'data' => array_map('floatval', $serie['valores'])]]"
        :labels="array_map(fn ($fecha) => \Illuminate\Support\Carbon::parse($fecha)->format('d/m'), $serie['fechas'])"
        :color-tokens="['--ag-color-primary']"
    />
</div>
