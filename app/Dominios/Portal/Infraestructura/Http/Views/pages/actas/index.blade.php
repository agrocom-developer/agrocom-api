{{--
    Page: portal/actas/index (GET /portal/actas, portal.actas.index)
    HU-41 (tarea 55): actas de conformidad firmadas del contrato del cliente
    autenticado, con descarga de PDF (portal.actas.pdf).

    Datos esperados (ver ActasPortalController::index()): $userName, $tema
    (cáscara mínima), más:
    - $actas (list<DatosActaConformada>): ya acotadas al contrato propio por
      LecturaActaConformada::listarFirmadasPorContrato() — esta vista no
      filtra nada, solo presenta.
--}}
<x-templates.panel-shell :title="__('portal.actas.titulo')" :tema="$tema" :tema-url="route('portal.preferencias.tema')">
    <x-templates.portal-layout :user-name="$userName">
        <div class="ag-portal-actas">
            <x-organisms.page-header
                :title="__('portal.actas.titulo')"
                :subtitle="__('portal.actas.subtitulo')"
            />

            @if (empty($actas))
                <x-molecules.alert-strip variant="info" icon="description">
                    {{ __('portal.actas.vacio') }}
                </x-molecules.alert-strip>
            @else
                <div class="ag-portal-lista" role="table">
                    <div class="ag-portal-lista__head" role="row">
                        <span role="columnheader">{{ __('portal.actas.col_acta') }}</span>
                        <span role="columnheader">{{ __('portal.actas.col_hectareas') }}</span>
                        <span role="columnheader"></span>
                        <span role="columnheader">{{ __('portal.actas.col_descarga') }}</span>
                    </div>

                    @foreach ($actas as $acta)
                        <div class="ag-portal-lista__fila" role="row">
                            <span role="cell">{{ __('portal.actas.acta_valor', ['id' => $acta->actaId]) }}</span>
                            <span role="cell" class="ag-portal-lista__cifra">{{ __('portal.actas.hectareas_valor', ['cantidad' => number_format((float) $acta->hectareasConformadas, 2, ',', '.')]) }}</span>
                            <span role="cell"></span>
                            <span role="cell">
                                @if ($acta->pdfPath)
                                    <x-atoms.button :href="route('portal.actas.pdf', $acta->actaId)" variant="outline" size="sm" icon="download">
                                        {{ __('portal.actas.descargar') }}
                                    </x-atoms.button>
                                @else
                                    <span class="ag-portal-lista__cifra">{{ __('portal.actas.sin_pdf') }}</span>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-templates.portal-layout>
</x-templates.panel-shell>
