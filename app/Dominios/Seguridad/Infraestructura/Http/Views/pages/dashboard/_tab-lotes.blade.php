{{--
    Parcial: pestaña "Resumen por lote" — una tarjeta por lote con avance,
    hectáreas, litros y tiempo de vuelo acumulados.

    Espera: $secciones['resumen_por_lote'] (list).
--}}
<div class="ag-dash__stack">
    <section>
        <x-molecules.section-head :title="__('seguridad.dashboard.seccion_resumen_lote')" />
        @include('seguridad::pages.dashboard._resumen-por-lote', ['lotes' => $secciones['resumen_por_lote']])
    </section>
</div>
