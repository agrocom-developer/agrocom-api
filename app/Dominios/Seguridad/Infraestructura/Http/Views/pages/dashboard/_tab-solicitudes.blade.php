{{--
    Parcial: pestaña «Solicitudes» del dashboard (ADR 0027) — el panorama de
    la unidad de negocio: cuántas solicitudes hay en cada estado.

    Espera `$secciones['solicitudes_por_estado']`, ver
    ArmarDashboard::solicitudesPorEstado().
--}}
<div class="ag-dash__stack">
    @isset($secciones['solicitudes_por_estado'])
        @include('seguridad::pages.dashboard._seccion-solicitudes', ['solicitudes' => $secciones['solicitudes_por_estado']])
    @endisset
</div>
