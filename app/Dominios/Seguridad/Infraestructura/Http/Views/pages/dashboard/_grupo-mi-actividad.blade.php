{{--
    Parcial: grupo "Mi actividad" — las secciones acotadas a la persona que
    está mirando (sus sesiones, sus equipos).

    Existe como grupo con encabezado propio porque estas cifras conviven, en
    el tablero de un dueño o un encargado, con las de la operación entera. Sin
    un rótulo que las separe, un "0 sesiones del mes" de quien no vuela se lee
    como si la operación estuviera parada.

    Espera: $secciones (array<string, mixed>) — se leen solo las claves `mis_*`
    y cada una puede faltar.
--}}
<section class="ag-dash__mi-actividad">
    <x-molecules.section-head :title="__('seguridad.dashboard.mi_actividad')" />

    <div class="ag-dash__stack">
        @isset($secciones['mis_sesiones'])
            @include('seguridad::pages.dashboard._seccion-mis-totales', ['totales' => $secciones['mis_sesiones']['totales']])
            @include('seguridad::pages.dashboard._seccion-mis-sesiones', ['sesiones' => $secciones['mis_sesiones']['sesiones']])
        @endisset

        @isset($secciones['mis_equipos'])
            @include('seguridad::pages.dashboard._seccion-mis-equipos', ['equipos' => $secciones['mis_equipos']])
        @endisset
    </div>
</section>
