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

    @isset($secciones['mis_sesiones'])
        @include('seguridad::pages.dashboard._seccion-mis-totales', ['totales' => $secciones['mis_sesiones']['totales']])
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

    @isset($secciones['mis_sesiones'])
        @include('seguridad::pages.dashboard._seccion-mis-sesiones', ['sesiones' => $secciones['mis_sesiones']['sesiones']])
    @endisset

    @isset($secciones['mis_equipos'])
        @include('seguridad::pages.dashboard._seccion-mis-equipos', ['equipos' => $secciones['mis_equipos']])
    @endisset

    @isset($secciones['mi_liquidacion'])
        @include('seguridad::pages.dashboard._seccion-mi-liquidacion', ['liquidacion' => $secciones['mi_liquidacion']])
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
</div>
