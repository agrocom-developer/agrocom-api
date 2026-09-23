{{--
    Parcial: pestaña "Mis trabajos" del piloto y del ayudante (tarea 137) —
    lo suyo del mes (sesiones, validadas, hectáreas), sus últimas sesiones
    con el estado de cada una y los drones que operó. Son las mismas
    secciones que "Mi actividad" en "Resumen"; acá van sin el encabezado de
    grupo porque en este tablero no hay cifras de la empresa con las que
    confundirlas.

    Cada `@isset` no es un permiso disfrazado — `ArmarDashboard` ya decidió
    qué claves existen; acá solo se pinta lo que llegó.

    Espera: $secciones (array<string, mixed>).
--}}
<div class="ag-dash__stack">
    @isset($secciones['mis_sesiones'])
        @include('seguridad::pages.dashboard._seccion-mis-totales', ['totales' => $secciones['mis_sesiones']['totales']])
        @include('seguridad::pages.dashboard._seccion-mis-sesiones', ['sesiones' => $secciones['mis_sesiones']['sesiones']])
    @endisset

    @isset($secciones['mis_equipos'])
        @include('seguridad::pages.dashboard._seccion-mis-equipos', ['equipos' => $secciones['mis_equipos']])
    @endisset
</div>
