{{--
    Parcial: pestaña "Estados de las aplicaciones" del encargado de
    operaciones (tarea 136) — cuántas órdenes de aplicación hay en cada
    estado de su ciclo de vida.

    "Aplicaciones" son las ÓRDENES (`ope_ordenes_aplicacion`, ADR 0022), no
    las sesiones de vuelo: esas viven en `_seccion-distribucion`.

    Espera: $secciones['estado_ordenes_aplicacion'], ya resuelto por
    ArmarDashboard::estadoOrdenesAplicacion().
--}}
<div class="ag-dash__stack">
    @include('seguridad::pages.dashboard._seccion-estado-ordenes', ['ordenes' => $secciones['estado_ordenes_aplicacion']])
</div>
