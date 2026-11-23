{{--
    Parcial: pestaña "Recursos" del jefe de campo (tarea 138) — los recursos
    que se ocupan: las cuadrillas con trabajo abierto y lo que llevan.

    El bloque llega solo si el rol activo tiene el permiso de su pantalla
    completa (`personal.equipo_trabajo.ver`): `ArmarDashboard` ya decidió qué
    claves existen y acá solo se pinta lo que llegó.

    Espera: $secciones (array<string, mixed>), con `recursos_en_uso` (ver
    ArmarDashboard::recursosEnUso()).
--}}
<div class="ag-dash__stack">
    @isset($secciones['recursos_en_uso'])
        @include('seguridad::pages.dashboard._seccion-recursos-en-uso', ['recursos' => $secciones['recursos_en_uso']])
    @endisset
</div>
