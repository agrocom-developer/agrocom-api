{{--
    Page: trabajos/index (GET /panel/trabajos, panel.trabajos.index)
    Listado de los trabajos que los equipos abrieron sobre solicitudes de
    servicio (ADR 0037): arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → toolbar → tabla →
    paginación. Del más reciente al más viejo; el detalle (sesiones, acta,
    reporte, evidencias) vive en la ficha del trabajo.

    Datos esperados (ver TrabajosController::index()): la cáscara de
    CascaraPanel, más:
    - $trabajos (LengthAwarePaginator<Trabajo>) con `sesiones` cargadas.
    - $contexto (array<int, DatosContextoSolicitud>): solicitud y cliente por
      `solicitud_id`.
    - $filtros (array{q: string, estado: ?string}).
    - $estadosFiltro (list<EstadoTableroTrabajo>).

    Gateada por `operaciones.trabajo.ver`, verificado server-side en el
    controlador. Estilos en resources/css/pages/solicitudes.css — cero color
    hardcodeado (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('operaciones.trabajos.titulo')" :tema="$tema">
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
        :vista-actual="__('operaciones.trabajos.titulo')"
    >
        <div class="ag-solicitudes">
            <x-organisms.page-header
                :title="__('operaciones.trabajos.titulo')"
                :subtitle="__('operaciones.trabajos.subtitulo')"
            />

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($errors->any())
                <x-molecules.alert-strip variant="danger" icon="error">
                    {{ $errors->first() }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
                $filtrosPanelActivos = $filtros['estado'] !== null && $filtros['estado'] !== '' ? 1 : 0;
                $opcionesEstado = collect($estadosFiltro)->mapWithKeys(fn ($opcion) => [$opcion->value => __('operaciones.trabajos.estado.'.$opcion->value)])->all();
                $tonoEstado = ['abierto' => 'neutral', 'cerrado' => 'info', 'validado' => 'success'];
            @endphp

            @if ($hayFiltrosActivos || $trabajos->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.trabajos.index')"
                        :active-count="$filtrosPanelActivos"
                    >
                        <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                        <x-atoms.select
                            name="estado"
                            id="filtro-estado"
                            :label="__('operaciones.trabajos.filtro_estado')"
                            :options="$opcionesEstado"
                            :value="$filtros['estado']"
                            :placeholder="__('operaciones.trabajos.filtro_todos')"
                        />
                    </x-organisms.filter-panel>

                    <x-molecules.table-search
                        :action="route('panel.trabajos.index')"
                        :value="$filtros['q']"
                        :placeholder="__('operaciones.trabajos.buscar_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($trabajos->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('operaciones.trabajos.filtro_vacio')"
                        :detail="__('operaciones.trabajos.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="work_history"
                        :title="__('operaciones.trabajos.vacio_titulo')"
                        :detail="__('operaciones.trabajos.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 1.6fr) minmax(0, 1fr) minmax(0, 0.8fr) minmax(0, 0.8fr) minmax(0, 0.9fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('operaciones.trabajos.col_solicitud') }}</span>
                        <span role="columnheader">{{ __('operaciones.trabajos.col_estado') }}</span>
                        <span role="columnheader">{{ __('operaciones.trabajos.col_hectareas') }}</span>
                        <span role="columnheader">{{ __('operaciones.trabajos.col_sesiones') }}</span>
                        <span role="columnheader">{{ __('operaciones.trabajos.col_inicio') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($trabajos as $trabajo)
                        @php
                            $solicitud = $contexto[(int) $trabajo->solicitud_id] ?? null;
                            $estadoTablero = $trabajo->estadoTablero();
                        @endphp
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($trabajos->currentPage() - 1) * $trabajos->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell">
                                <span class="ag-solicitudes__nombre">{{ $solicitud?->etiqueta() ?? '—' }}</span>
                                <span class="ag-solicitudes__sub">{{ $solicitud?->clienteNombre ?? '—' }}</span>
                            </span>
                            <span role="cell">
                                <x-atoms.badge :variant="$tonoEstado[$estadoTablero->value] ?? 'neutral'">
                                    {{ __('operaciones.trabajos.estado.'.$estadoTablero->value) }}
                                </x-atoms.badge>
                            </span>
                            <span role="cell" class="ag-solicitudes__cifra">{{ number_format((float) $trabajo->hectareas_declaradas, 2, ',', '.') }} ha</span>
                            <span role="cell" class="ag-solicitudes__cifra">{{ $trabajo->sesiones->whereNull('anulada_en')->count() }}</span>
                            <span role="cell">{{ $trabajo->inicio->format('d/m/Y') }}</span>
                            <span role="cell" class="ag-index-table__acciones">
                                <x-organisms.row-actions>
                                    <x-atoms.button :href="route('panel.trabajos.detalle', $trabajo)" variant="info-outline" size="sm" icon="visibility">
                                        {{ __('operaciones.trabajos.ver_detalle') }}
                                    </x-atoms.button>
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$trabajos" :aria-label="__('operaciones.trabajos.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
