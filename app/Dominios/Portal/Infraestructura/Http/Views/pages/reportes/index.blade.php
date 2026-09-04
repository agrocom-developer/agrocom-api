{{--
    Page: portal/reportes/index (GET /portal/reportes, portal.reportes.index)
    HU-41 (tarea 55): reportes técnicos por lote del contrato del cliente
    autenticado, con descarga de PDF (portal.reportes.pdf).

    Datos esperados (ver ReportesPortalController::index()): $userName, $tema
    (cáscara mínima), más:
    - $reportes (list<DatosReporteTecnico>): ya acotados al contrato propio
      por LecturaReporteTecnico::listarPorContrato() — esta vista no filtra
      nada, solo presenta.
--}}
<x-templates.panel-shell :title="__('portal.reportes.titulo')" :tema="$tema" :tema-url="route('portal.preferencias.tema')">
    <x-templates.portal-layout :user-name="$userName">
        <div class="ag-portal-reportes">
            <x-organisms.page-header
                :title="__('portal.reportes.titulo')"
                :subtitle="__('portal.reportes.subtitulo')"
            />

            @if (empty($reportes))
                <x-molecules.alert-strip variant="info" icon="summarize">
                    {{ __('portal.reportes.vacio') }}
                </x-molecules.alert-strip>
            @else
                <div class="ag-portal-lista" role="table">
                    <div class="ag-portal-lista__head" role="row">
                        <span role="columnheader">{{ __('portal.reportes.col_lote') }}</span>
                        <span role="columnheader">{{ __('portal.reportes.col_generado') }}</span>
                        <span role="columnheader"></span>
                        <span role="columnheader">{{ __('portal.reportes.col_descarga') }}</span>
                    </div>

                    @foreach ($reportes as $reporte)
                        <div class="ag-portal-lista__fila" role="row">
                            <span role="cell">{{ __('portal.reportes.lote_valor', ['id' => $reporte->loteId]) }}</span>
                            <span role="cell" class="ag-portal-lista__cifra">{{ $reporte->generadoEn->format('d/m/Y H:i') }}</span>
                            <span role="cell"></span>
                            <span role="cell">
                                @if ($reporte->pdfPath)
                                    <x-atoms.button :href="route('portal.reportes.pdf', $reporte->reporteId)" variant="outline" size="sm" icon="download">
                                        {{ __('portal.reportes.descargar') }}
                                    </x-atoms.button>
                                @else
                                    <span class="ag-portal-lista__cifra">{{ __('portal.reportes.sin_pdf') }}</span>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-templates.portal-layout>
</x-templates.panel-shell>
