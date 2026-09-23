{{--
    Parcial: pestaña "Proceso de los trabajos" del encargado de operaciones
    (tarea 136) — lo que frena o consume el avance de los trabajos: las
    sesiones que esperan validación y los minutos de pausa del mes por causa.
    Ambas secciones ya existían en "Resumen" para otros roles; acá solo se
    agrupan, no hay contenido nuevo.

    Cada `@isset` no es un permiso disfrazado — `ArmarDashboard` ya decidió
    qué claves existen; acá solo se pinta lo que llegó.

    Espera: $secciones (array<string, mixed>).
--}}
<div class="ag-dash__stack">
    @isset($secciones['cola_validacion'])
        @include('seguridad::pages.dashboard._seccion-cola-validacion', ['sesiones' => $secciones['cola_validacion']])
    @endisset

    @isset($secciones['pausas'])
        @include('seguridad::pages.dashboard._seccion-pausas', ['pausas' => $secciones['pausas']])
    @endisset
</div>
