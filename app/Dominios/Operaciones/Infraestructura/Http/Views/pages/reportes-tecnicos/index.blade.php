{{--
    Page: reportes-tecnicos/index (GET /panel/reportes/tecnicos, panel.reportes.tecnicos.index)
    Listado de reportes técnicos (HU-43, tarea 57; homogeneizado en la tarea
    114): arquetipo Listado, §6.2 de docs/diseno/guia_pantalla_panel.md —
    cabecera → toolbar → tabla. Solo lectura, sin paginación: agrega datos ya
    persistidos, un volumen acotado por trabajos realmente cerrados y
    conformados (mismo criterio que reportes-comerciales/index.blade.php).

    Sin acción de alta en la cabecera: un reporte técnico no se crea a mano —
    se genera solo al firmar el acta del trabajo.

    Datos esperados (ver ReportesTecnicosController::index()): la cáscara de
    CascaraPanel, más:
    - $reportes (list<array{reporteId, trabajoId, loteCodigo, nroAplicacion,
      clienteId, clienteNombre, generadoEn}>): ya agregado por
      ListarReportesTecnicos, la vista no calcula nada.
    - $filtros (array{cliente_id: ?int, desde: ?string, hasta: ?string}):
      valores actualmente aplicados, para dejar el panel de filtros con la
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
        :zona-horaria="$zonaHoraria ?? null"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :version="$version"
        :vista-actual="__('operaciones.reportes_tecnicos.titulo')"
    >
        <div class="ag-reportes-tecnicos">
            <x-organisms.page-header
                :title="__('operaciones.reportes_tecnicos.titulo')"
                :subtitle="__('operaciones.reportes_tecnicos.subtitulo')"
            />

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
                $filtrosActivosCount = collect($filtros)->filter(fn ($valor) => $valor !== null && $valor !== '')->count();
            @endphp

            @if ($hayFiltrosActivos || ! empty($reportes))
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.reportes.tecnicos.index')"
                        :active-count="$filtrosActivosCount"
                    >
                        <x-atoms.select
                            name="cliente_id"
                            id="filtro-cliente"
                            :label="__('operaciones.reportes_tecnicos.filtro_cliente')"
                            :options="$clientesDisponibles"
                            :value="$filtros['cliente_id']"
                            :placeholder="__('operaciones.reportes_tecnicos.filtro_cliente_placeholder')"
                        />

                        <x-atoms.date
                            name="desde"
                            id="filtro-desde"
                            :label="__('operaciones.reportes_tecnicos.filtro_desde')"
                            :value="$filtros['desde']"
                        />

                        <x-atoms.date
                            name="hasta"
                            id="filtro-hasta"
                            :label="__('operaciones.reportes_tecnicos.filtro_hasta')"
                            :value="$filtros['hasta']"
                        />
                    </x-organisms.filter-panel>
                </div>
            @endif

            @if (empty($reportes))
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('operaciones.reportes_tecnicos.filtro_vacio_titulo')"
                        :detail="__('operaciones.reportes_tecnicos.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="summarize"
                        :title="__('operaciones.reportes_tecnicos.vacio_titulo')"
                        :detail="__('operaciones.reportes_tecnicos.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 1.6fr) minmax(0, 1.6fr) minmax(0, 1.1fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('operaciones.reportes_tecnicos.col_trabajo') }}</span>
                        <span role="columnheader">{{ __('operaciones.reportes_tecnicos.col_cliente') }}</span>
                        <span role="columnheader">{{ __('operaciones.reportes_tecnicos.col_generado') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($reportes as $fila)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">{{ $loop->iteration }}</span>
                            <span role="cell" class="ag-reportes-tecnicos__trabajo">
                                {{ __('operaciones.reportes_tecnicos.trabajo_lote', ['lote' => $fila['loteCodigo'], 'aplicacion' => $fila['nroAplicacion']]) }}
                            </span>
                            <span role="cell">{{ $fila['clienteNombre'] }}</span>
                            <span role="cell" class="ag-reportes-tecnicos__mono">{{ $fila['generadoEn']->format('d/m/Y H:i') }}</span>

                            <span role="cell" class="ag-index-table__acciones">
                                <x-organisms.row-actions>
                                    <x-atoms.button
                                        :href="route('panel.trabajos.reporte-pdf', $fila['trabajoId'])"
                                        variant="outline"
                                        size="sm"
                                        icon="picture_as_pdf"
                                    >
                                        {{ __('operaciones.reportes_tecnicos.descargar_pdf') }}
                                    </x-atoms.button>
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
