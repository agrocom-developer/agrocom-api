{{--
    Page: reportes-tecnicos/index (GET /panel/reportes/tecnicos, panel.reportes.tecnicos.index)
    Listado de reportes técnicos (HU-43, tarea 57): arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → filtros → tabla. Solo
    lectura, sin paginación: agrega datos ya persistidos, un volumen acotado
    por trabajos realmente cerrados y conformados (mismo criterio que
    reportes-comerciales/index.blade.php).

    Datos esperados (ver ReportesTecnicosController::index()): la cáscara de
    CascaraPanel, más:
    - $reportes (list<array{reporteId, trabajoId, loteCodigo, nroAplicacion,
      clienteId, clienteNombre, generadoEn}>): ya agregado por
      ListarReportesTecnicos, la vista no calcula nada.
    - $filtros (array{cliente_id: ?int, desde: ?string, hasta: ?string}):
      valores actualmente aplicados, para dejar el formulario con la
      selección hecha tras el submit.
    - $clientesDisponibles (Collection<int, string> id => nombre): opciones
      del select de cliente — sale de una llamada sin filtro al mismo caso de
      uso (Operaciones no puede leer Cliente::query() directo, ADR 0003).

    Gateada por `operaciones.reporte.ver` (mismo permiso que la descarga
    individual), verificado server-side en el controlador. El link de
    descarga de cada fila reusa panel.trabajos.reporte-pdf — esta pantalla no
    sirve el PDF.

    Estilos en resources/css/pages/reportes-tecnicos.css — cero color
    hardcodeado (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('operaciones.reportes_tecnicos.titulo')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :campana="$campana"
        :periodo="$periodo"
        :version="$version"
        :vista-actual="__('operaciones.reportes_tecnicos.titulo')"
    >
        <div class="ag-reportes-tecnicos">
            <x-organisms.page-header
                :title="__('operaciones.reportes_tecnicos.titulo')"
                :subtitle="__('operaciones.reportes_tecnicos.subtitulo')"
            />

            @php $hayFiltrosActivos = $filtros['cliente_id'] !== null || $filtros['desde'] !== null || $filtros['hasta'] !== null; @endphp

            <form method="GET" action="{{ route('panel.reportes.tecnicos.index') }}" class="ag-filtros ag-reportes-tecnicos__filtros">
                <div class="ag-input">
                    <label for="filtro-cliente" class="ag-input__label">{{ __('operaciones.reportes_tecnicos.filtro_cliente') }}</label>
                    <div class="ag-input__control">
                        <select name="cliente_id" id="filtro-cliente" class="ag-input__field">
                            <option value="">{{ __('operaciones.reportes_tecnicos.filtro_cliente_placeholder') }}</option>
                            @foreach ($clientesDisponibles as $clienteId => $clienteNombre)
                                <option value="{{ $clienteId }}" @selected((string) $filtros['cliente_id'] === (string) $clienteId)>
                                    {{ $clienteNombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="ag-input">
                    <label for="filtro-desde" class="ag-input__label">{{ __('operaciones.reportes_tecnicos.filtro_desde') }}</label>
                    <div class="ag-input__control">
                        <input type="date" name="desde" id="filtro-desde" class="ag-input__field" value="{{ $filtros['desde'] }}">
                    </div>
                </div>

                <div class="ag-input">
                    <label for="filtro-hasta" class="ag-input__label">{{ __('operaciones.reportes_tecnicos.filtro_hasta') }}</label>
                    <div class="ag-input__control">
                        <input type="date" name="hasta" id="filtro-hasta" class="ag-input__field" value="{{ $filtros['hasta'] }}">
                    </div>
                </div>

                <div class="ag-filtros__acciones ag-reportes-tecnicos__filtros-acciones">
                    <x-atoms.button type="submit" variant="primary" size="md" icon="filter_alt">
                        {{ __('operaciones.reportes_tecnicos.filtrar') }}
                    </x-atoms.button>

                    @if ($hayFiltrosActivos)
                        <x-atoms.button href="{{ route('panel.reportes.tecnicos.index') }}" variant="text" size="md">
                            {{ __('operaciones.reportes_tecnicos.limpiar_filtro') }}
                        </x-atoms.button>
                    @endif
                </div>
            </form>

            @if (empty($reportes))
                <x-molecules.alert-strip variant="info" icon="summarize" class="ag-reportes-tecnicos__aviso">
                    {{ __($hayFiltrosActivos ? 'operaciones.reportes_tecnicos.filtro_vacio' : 'operaciones.reportes_tecnicos.vacio') }}
                </x-molecules.alert-strip>
            @else
                <div class="ag-reportes-tecnicos__tabla" role="table">
                    <div class="ag-reportes-tecnicos__head" role="row">
                        <span role="columnheader">{{ __('operaciones.reportes_tecnicos.col_trabajo') }}</span>
                        <span role="columnheader">{{ __('operaciones.reportes_tecnicos.col_cliente') }}</span>
                        <span role="columnheader">{{ __('operaciones.reportes_tecnicos.col_generado') }}</span>
                        <span role="columnheader">{{ __('operaciones.reportes_tecnicos.col_descarga') }}</span>
                    </div>

                    @foreach ($reportes as $fila)
                        <div class="ag-reportes-tecnicos__fila" role="row">
                            <span role="cell" class="ag-reportes-tecnicos__trabajo">
                                {{ __('operaciones.reportes_tecnicos.trabajo_lote', ['lote' => $fila['loteCodigo'], 'aplicacion' => $fila['nroAplicacion']]) }}
                            </span>
                            <span role="cell">{{ $fila['clienteNombre'] }}</span>
                            <span role="cell">{{ $fila['generadoEn']->format('d/m/Y H:i') }}</span>
                            <span role="cell">
                                <x-atoms.button href="{{ route('panel.trabajos.reporte-pdf', $fila['trabajoId']) }}" variant="outline" size="sm" icon="picture_as_pdf">
                                    {{ __('operaciones.reportes_tecnicos.descargar_pdf') }}
                                </x-atoms.button>
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
