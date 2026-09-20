{{--
    Parcial: pestaña "Resumen". Compone las secciones que el rol activo puede
    ver, en orden de criticidad: primero lo que exige una acción (alertas,
    cola de validación), después lo que informa.

    Cada `@isset` no es un permiso disfrazado — `ArmarDashboard` ya decidió
    qué claves existen; acá solo se pinta lo que llegó.

    Espera: $secciones (array<string, mixed>).
--}}
<div class="ag-dash__stack">
    @isset($secciones['alertas'])
        @include('seguridad::pages.dashboard._seccion-alertas', ['alertas' => $secciones['alertas']])
    @endisset

    @isset($secciones['cola_validacion'])
        @include('seguridad::pages.dashboard._seccion-cola-validacion', ['sesiones' => $secciones['cola_validacion']])
    @endisset

    @if (isset($secciones['distribucion_sesiones']) || isset($secciones['hectareas_por_dia']))
        <section class="ag-dash__cards-grid">
            @isset($secciones['distribucion_sesiones'])
                @include('seguridad::pages.dashboard._seccion-distribucion', ['distribucion' => $secciones['distribucion_sesiones']])
            @endisset

            @isset($secciones['hectareas_por_dia'])
                @include('seguridad::pages.dashboard._seccion-hectareas', ['serie' => $secciones['hectareas_por_dia']])
            @endisset
        </section>
    @endif

    @isset($secciones['avance_clientes'])
        @include('seguridad::pages.dashboard._seccion-avance-clientes', ['avances' => $secciones['avance_clientes']])
    @endisset

    @if (isset($secciones['pausas']) || isset($secciones['stock']))
        <div class="ag-dash__grid ag-dash__grid--par">
            @isset($secciones['pausas'])
                @include('seguridad::pages.dashboard._seccion-pausas', ['pausas' => $secciones['pausas']])
            @endisset

            @isset($secciones['stock'])
                @include('seguridad::pages.dashboard._seccion-stock', ['stock' => $secciones['stock']])
            @endisset
        </div>
    @endif

    @isset($secciones['dias_en_hacienda'])
        @include('seguridad::pages.dashboard._seccion-dias-hacienda', ['diasHacienda' => $secciones['dias_en_hacienda']])
    @endisset

    {{-- Bloque personal, SIEMPRE al final y bajo su propio encabezado.
         Suelto arriba, un "0 sesiones del mes" del dueño —que no vuela— se
         leía como si la operación entera estuviera parada. Para un piloto,
         que no tiene ninguna sección global, este bloque queda arriba solo. --}}
    @if (isset($secciones['mis_sesiones']) || isset($secciones['mis_equipos']) || isset($secciones['mi_liquidacion']))
        @include('seguridad::pages.dashboard._grupo-mi-actividad', ['secciones' => $secciones])
    @endif
</div>
