{{--
    Parcial: encabezado del tablero de piloto/auxiliar — lo suyo del mes.

    Es la única sección que no habla de la empresa sino de la persona: son
    las cifras que compara con su recibo, así que van arriba de todo.

    Espera: $totales = {sesiones: int, sesionesValidadas: int, hectareas: string}.
--}}
<section class="ag-dash__cards-grid">
    <x-molecules.stat-card
        :label="__('seguridad.dashboard.mis_sesiones_mes')"
        icon="flight_takeoff"
        :value="$totales['sesiones']"
    />
    <x-molecules.stat-card
        :label="__('seguridad.dashboard.mis_sesiones_validadas')"
        icon="task_alt"
        :value="$totales['sesionesValidadas']"
    />
    <x-molecules.stat-card
        :label="__('seguridad.dashboard.mis_hectareas_mes')"
        icon="landscape"
        :value="number_format((float) $totales['hectareas'], 2, ',', '.')"
        value-suffix="ha"
    />
</section>
