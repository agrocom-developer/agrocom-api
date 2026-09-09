{{--
    Page: estadias/index (GET /panel/estadias, panel.estadias.index)
    Consulta de estadías del equipo en cada hacienda (HU-51, tarea 74): entrada
    y salida del equipo cargadas desde la app de campo, con filtro por rango de
    fechas, equipo de trabajo y campo. Totales de días efectivos por equipo y
    por campo. Solo lectura — la estadía nace en `/api/sync`, nunca se
    crea/edita desde el panel.

    Datos esperados (ver EstadiasHaciendaController::index()): la cáscara de
    CascaraPanel, más:
    - $estadias (LengthAwarePaginator<EstadiaHacienda>, más reciente primero).
    - $filtros (array{desde: ?string, hasta: ?string, equipo_trabajo_id: ?int, campo_id: ?int}):
      valores actualmente aplicados.
    - $etiquetasEquipo (array<int,string>: id => "CÓDIGO — Nombre" o solo "CÓDIGO").
    - $etiquetasCampo (array<int,string>: id => nombre del campo).
    - $etiquetasVehiculo (array<int,string>: id => identificador del vehículo).
    - $equiposDisponibles (Collection<int>, para el <select> de filtro).
    - $camposDisponibles (Collection<int>, para el <select> de filtro).
    - $diasPorEquipo (array<int,float>: id de equipo => total de días efectivos).
    - $diasPorCampo (array<int,float>: id de campo => total de días efectivos).

    Gateada por el permiso `operaciones.estadia.ver`, verificado server-side en
    el controlador (no hay acción mutable acá que ocultar con @puede).

    Estilos en resources/css/pages/estadias.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('operaciones.estadias.titulo')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :campaniaActiva="$campaniaActiva"
        :periodo="$periodo"
        :version="$version"
    >
        <x-organisms.page-header
            :title="__('operaciones.estadias.titulo')"
            :subtitle="__('operaciones.estadias.subtitulo')"
        />

        @php $hayFiltrosActivos = $filtros['desde'] !== null || $filtros['hasta'] !== null || $filtros['equipo_trabajo_id'] !== null || $filtros['campo_id'] !== null; @endphp

        <form method="GET" action="{{ route('panel.estadias.index') }}" class="ag-filtros ag-estadias__filtros">
            @php
                $opcionesEquipo = collect($equiposDisponibles)->mapWithKeys(fn ($etiqueta, $id) => [
                    $id => $etiqueta
                ])->all();
            @endphp

            <x-atoms.date
                name="desde"
                id="filtro-desde"
                label="{{ __('operaciones.estadias.filtro_desde') }}"
                :value="$filtros['desde']"
                :placeholder="__('operaciones.estadias.filtro_placeholder_desde')"
            />

            <x-atoms.date
                name="hasta"
                id="filtro-hasta"
                label="{{ __('operaciones.estadias.filtro_hasta') }}"
                :value="$filtros['hasta']"
                :placeholder="__('operaciones.estadias.filtro_placeholder_hasta')"
            />

            <x-atoms.select
                name="equipo_trabajo_id"
                id="filtro-equipo"
                label="{{ __('operaciones.estadias.filtro_equipo') }}"
                :options="$opcionesEquipo"
                :value="$filtros['equipo_trabajo_id']"
                :placeholder="__('operaciones.estadias.filtro_todos')"
            />

            @php
                $opcionesCampo = collect($camposDisponibles)->mapWithKeys(fn ($etiqueta, $id) => [
                    $id => $etiqueta
                ])->all();
            @endphp

            <x-atoms.select
                name="campo_id"
                id="filtro-campo"
                label="{{ __('operaciones.estadias.filtro_campo') }}"
                :options="$opcionesCampo"
                :value="$filtros['campo_id']"
                :placeholder="__('operaciones.estadias.filtro_todos')"
            />

            <div class="ag-filtros__acciones ag-estadias__filtros-acciones">
                <x-atoms.button type="submit" variant="primary" size="md" icon="filter_alt">
                    {{ __('operaciones.estadias.filtrar') }}
                </x-atoms.button>

                @if ($hayFiltrosActivos)
                    <x-atoms.button href="{{ route('panel.estadias.index') }}" variant="text" size="md">
                        {{ __('operaciones.estadias.limpiar_filtros') }}
                    </x-atoms.button>
                @endif
            </div>
        </form>

        @if ($estadias->isEmpty())
            <x-molecules.alert-strip variant="info" icon="fact_check" class="ag-estadias__aviso">
                {{ __($hayFiltrosActivos ? 'operaciones.estadias.filtro_vacio' : 'operaciones.estadias.vacio') }}
            </x-molecules.alert-strip>
        @else
            @if (! empty($diasPorEquipo) || ! empty($diasPorCampo))
                <div class="ag-estadias__totales">
                    @if (! empty($diasPorEquipo))
                        <div class="ag-estadias__totales-seccion">
                            <h3 class="ag-estadias__totales-titulo">{{ __('operaciones.estadias.totales_equipo') }}</h3>
                            <div class="ag-estadias__totales-grid">
                                @foreach ($diasPorEquipo as $equipoId => $dias)
                                    <div class="ag-estadias__total-item">
                                        <span class="ag-estadias__total-etiqueta">
                                            {{ $etiquetasEquipo[$equipoId] ?? '—' }}
                                        </span>
                                        <span class="ag-estadias__total-valor">
                                            {{ number_format($dias, 1) }}
                                        </span>
                                        <span class="ag-estadias__total-unidad">
                                            {{ __('operaciones.estadias.dias') }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if (! empty($diasPorCampo))
                        <div class="ag-estadias__totales-seccion">
                            <h3 class="ag-estadias__totales-titulo">{{ __('operaciones.estadias.totales_campo') }}</h3>
                            <div class="ag-estadias__totales-grid">
                                @foreach ($diasPorCampo as $campoId => $dias)
                                    <div class="ag-estadias__total-item">
                                        <span class="ag-estadias__total-etiqueta">
                                            {{ $etiquetasCampo[$campoId] ?? '—' }}
                                        </span>
                                        <span class="ag-estadias__total-valor">
                                            {{ number_format($dias, 1) }}
                                        </span>
                                        <span class="ag-estadias__total-unidad">
                                            {{ __('operaciones.estadias.dias') }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            <div class="ag-estadias__tabla" role="table">
                <div class="ag-estadias__head" role="row">
                    <span role="columnheader">{{ __('operaciones.estadias.col_equipo') }}</span>
                    <span role="columnheader">{{ __('operaciones.estadias.col_campo') }}</span>
                    <span role="columnheader">{{ __('operaciones.estadias.col_vehiculo') }}</span>
                    <span role="columnheader">{{ __('operaciones.estadias.col_entrada') }}</span>
                    <span role="columnheader">{{ __('operaciones.estadias.col_salida') }}</span>
                </div>

                @foreach ($estadias as $estadia)
                    <div class="ag-estadias__fila" role="row">
                        <span role="cell" class="ag-estadias__equipo">
                            {{ $etiquetasEquipo[$estadia->equipo_trabajo_id] ?? '—' }}
                        </span>

                        <span role="cell">
                            {{ $etiquetasCampo[$estadia->campo_id] ?? '—' }}
                        </span>

                        <span role="cell">
                            {{ $etiquetasVehiculo[$estadia->vehiculo_id] ?? __('operaciones.estadias.sin_vehiculo') }}
                        </span>

                        <span role="cell">
                            {{ $estadia->entrada->format('d/m/Y H:i') }}
                        </span>

                        <span role="cell">
                            @if ($estadia->salida !== null)
                                {{ $estadia->salida->format('d/m/Y H:i') }}
                            @else
                                <x-atoms.badge variant="warning">
                                    {{ __('operaciones.estadias.en_curso') }}
                                </x-atoms.badge>
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>

            @if ($estadias->hasPages())
                <nav class="ag-estadias__paginacion" aria-label="{{ __('operaciones.estadias.paginacion_aria') }}">
                    @if (! $estadias->onFirstPage())
                        <x-atoms.button href="{{ $estadias->previousPageUrl() }}" variant="outline" size="sm" icon="chevron_left">
                            {{ __('operaciones.estadias.paginacion_anterior') }}
                        </x-atoms.button>
                    @endif

                    <span class="ag-estadias__paginacion-info">
                        {{ __('operaciones.estadias.paginacion_info', ['actual' => $estadias->currentPage(), 'total' => $estadias->lastPage()]) }}
                    </span>

                    @if ($estadias->hasMorePages())
                        <x-atoms.button href="{{ $estadias->nextPageUrl() }}" variant="outline" size="sm" icon="chevron_right" iconPosition="end">
                            {{ __('operaciones.estadias.paginacion_siguiente') }}
                        </x-atoms.button>
                    @endif
                </nav>
            @endif
        @endif
    </x-templates.panel-layout>
</x-templates.panel-shell>
