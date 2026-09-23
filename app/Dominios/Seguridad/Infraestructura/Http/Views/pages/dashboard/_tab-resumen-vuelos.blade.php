{{--
    Parcial: pestaña "Resumen de vuelos" del encargado de operaciones (tarea
    136) — hectáreas validadas por día, por semana o por mes, con un selector
    de granularidad que NO recarga la página.

    Las tres series llegan ya calculadas desde `ArmarDashboard`, cada una en
    su propio panel; el selector es `molecules/tabs` en su variante
    `segmented` y el mecanismo es el `tab` nativo de Bootstrap (el mismo de
    las pestañas de la página): mostrar otra granularidad es mostrar otro
    panel, sin pedirle nada al servidor. Solo cuentan las sesiones
    VALIDADAS — la misma regla que "Hectáreas aplicadas por día" (invariante
    6: la curva tiene que coincidir con lo que después se factura).

    Las etiquetas del eje X se acortan acá (DD/MM el día y el lunes de la
    semana, MM/AAAA el mes): es formato de eje, el servidor manda la fecha
    ISO completa.

    Espera: $secciones['resumen_vuelos'] = array<'dia'|'semana'|'mes',
    {fechas: list<string>, valores: list<string>, total: string}>.
--}}
@php
    $vuelos = $secciones['resumen_vuelos'];

    $granularidades = [
        'dia' => ['formato' => 'd/m'],
        'semana' => ['formato' => 'd/m'],
        'mes' => ['formato' => 'm/Y'],
    ];

    $selector = collect($granularidades)
        ->map(fn (array $g, string $clave) => [
            'id' => 'ag-vuelos-'.$clave,
            'label' => __('seguridad.dashboard.vuelos_'.$clave),
            'active' => $clave === array_key_first($granularidades),
        ])
        ->values()
        ->all();
@endphp

<div class="ag-dash__stack">
    <section>
        <div class="ag-card ag-card--padded">
            <div class="ag-card__head ag-card__head--flush ag-dash__vuelos-cabecera">
                <h2 class="ag-card__title">{{ __('seguridad.dashboard.seccion_resumen_vuelos') }}</h2>
                <x-molecules.tabs
                    variant="segmented"
                    :items="$selector"
                    :aria-label="__('seguridad.dashboard.vuelos_granularidad_aria')"
                />
            </div>

            <div class="tab-content">
                @foreach ($granularidades as $clave => $granularidad)
                    @php($serie = $vuelos[$clave])

                    <div
                        class="tab-pane fade {{ $loop->first ? 'show active' : '' }}"
                        id="ag-vuelos-{{ $clave }}"
                        role="tabpanel"
                        tabindex="0"
                    >
                        <p class="ag-dash__vuelos-total">
                            <span class="ag-dash__vuelos-cifra">
                                {{ number_format((float) $serie['total'], 2, ',', '.') }}
                                <span class="ag-dash__vuelos-unidad">{{ __('seguridad.dashboard.progreso_campania_unidad_hectareas') }}</span>
                            </span>
                            <span class="ag-dash__mono-note">{{ __('seguridad.dashboard.vuelos_periodo_'.$clave, ['cantidad' => count($serie['fechas'])]) }}</span>
                        </p>

                        <x-molecules.apex-chart
                            type="area"
                            :series="[['name' => __('seguridad.dashboard.vuelos_serie'), 'data' => array_map('floatval', $serie['valores'])]]"
                            :labels="array_map(fn ($fecha) => \Illuminate\Support\Carbon::parse($fecha)->format($granularidad['formato']), $serie['fechas'])"
                            :color-tokens="['--ag-color-primary']"
                        />

                        @if ($clave === 'semana')
                            <p class="ag-dash__vuelos-nota">{{ __('seguridad.dashboard.vuelos_nota_semana') }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>
</div>
