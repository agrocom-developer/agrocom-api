{{--
    Page: solicitudes/index (GET /panel/solicitudes, panel.solicitudes.index)
    Cola y seguimiento de las solicitudes de servicio (ADR 0027): arquetipo
    Listado, §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera → toolbar →
    tabla → paginación. Las que esperan proveedor (sin proveedor, en asignación,
    publicadas) van primero. La ficha trae el historial y la cancelación: decidir
    sobre una solicitud exige ver su recorrido.

    Datos esperados (ver SolicitudesController::index()): la cáscara de
    CascaraPanel, más:
    - $solicitudes (LengthAwarePaginator<SolicitudServicio>) con `terreno` y `cliente`.
    - $ofertasVigentes (array<int, int>): solicitud → cantidad de ofertas vigentes.
    - $filtros (array{q: string, estado: ?string, region: ?string, zona: ?int}).
    - $estadosFiltro (list<EstadoSolicitud>), $regionesFiltro (list<Macrorregion>) y
      $zonasFiltro (array<int, string>).

    Gateada por `solicitudes.solicitud.ver`, verificado server-side en el
    controlador. Estilos en resources/css/pages/solicitudes.css — cero color
    hardcodeado (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('solicitudes.panel.titulo')" :tema="$tema">
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
        :vista-actual="__('solicitudes.panel.titulo')"
    >
        <div class="ag-solicitudes">
            <x-organisms.page-header
                :title="__('solicitudes.panel.titulo')"
                :subtitle="__('solicitudes.panel.subtitulo')"
            />

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
                $filtrosPanelActivos = collect([$filtros['estado'], $filtros['region'], $filtros['zona']])->filter(fn ($valor) => $valor !== null && $valor !== '')->count();
                $opcionesEstado = collect($estadosFiltro)->mapWithKeys(fn ($opcion) => [$opcion->value => __('solicitudes.estado.'.$opcion->value)])->all();
                $opcionesRegion = collect($regionesFiltro)->mapWithKeys(fn ($opcion) => [$opcion->value => __('solicitudes.macrorregion.'.$opcion->value)])->all();
            @endphp

            @if ($hayFiltrosActivos || $solicitudes->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.solicitudes.index')"
                        :active-count="$filtrosPanelActivos"
                    >
                        <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                        <x-atoms.select
                            name="estado"
                            id="filtro-estado"
                            :label="__('solicitudes.panel.filtro_estado')"
                            :options="$opcionesEstado"
                            :value="$filtros['estado']"
                            :placeholder="__('solicitudes.panel.filtro_todos')"
                        />
                        <x-atoms.select
                            name="region"
                            id="filtro-region"
                            :label="__('solicitudes.panel.filtro_region')"
                            :options="$opcionesRegion"
                            :value="$filtros['region']"
                            :placeholder="__('solicitudes.panel.filtro_todas')"
                        />
                        <x-atoms.select
                            name="zona"
                            id="filtro-zona"
                            :label="__('solicitudes.panel.filtro_zona')"
                            :options="$zonasFiltro"
                            :value="$filtros['zona']"
                            :placeholder="__('solicitudes.panel.filtro_todas')"
                        />
                    </x-organisms.filter-panel>

                    <x-molecules.table-search
                        :action="route('panel.solicitudes.index')"
                        :value="$filtros['q']"
                        :placeholder="__('solicitudes.panel.buscar_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($solicitudes->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('solicitudes.panel.filtro_vacio_titulo')"
                        :detail="__('solicitudes.panel.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="request_quote"
                        :title="__('solicitudes.panel.vacio_titulo')"
                        :detail="__('solicitudes.panel.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 1.6fr) minmax(0, 1fr) minmax(0, 1fr) minmax(0, 0.8fr) minmax(0, 0.9fr) minmax(0, 0.9fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('solicitudes.panel.col_solicitud') }}</span>
                        <span role="columnheader">{{ __('solicitudes.panel.col_zona') }}</span>
                        <span role="columnheader">{{ __('solicitudes.panel.col_estado') }}</span>
                        <span role="columnheader">{{ __('solicitudes.panel.col_hectareas') }}</span>
                        <span role="columnheader">{{ __('solicitudes.panel.col_precio') }}</span>
                        <span role="columnheader">{{ __('solicitudes.panel.col_ventana') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($solicitudes as $solicitud)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($solicitudes->currentPage() - 1) * $solicitudes->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell">
                                <span class="ag-solicitudes__nombre">#{{ $solicitud->id }} · {{ $solicitud->terreno->nombre }}</span>
                                <span class="ag-solicitudes__sub">{{ $solicitud->cliente->razon_social }}</span>
                            </span>
                            <span role="cell">
                                <span class="ag-solicitudes__nombre">{{ $solicitud->terreno->zona->nombre ?? __('solicitudes.panel.sin_zona') }}</span>
                                <span class="ag-solicitudes__sub">
                                    {{ $solicitud->terreno->zona?->macrorregion ? __('solicitudes.macrorregion.'.$solicitud->terreno->zona->macrorregion) : '' }}
                                    · {{ __('solicitudes.tipo_aplicacion.'.$solicitud->tipo_aplicacion->value) }}
                                </span>
                            </span>
                            <span role="cell">
                                <x-atoms.badge :variant="$solicitud->estado->tono()">{{ __('solicitudes.estado.'.$solicitud->estado->value) }}</x-atoms.badge>
                                @if ($solicitud->urgente)
                                    <x-atoms.badge variant="alert">{{ __('solicitudes.panel.urgente') }}</x-atoms.badge>
                                @endif
                                @if (($ofertasVigentes[$solicitud->id] ?? 0) > 0)
                                    <span class="ag-solicitudes__sub">{{ trans_choice('solicitudes.panel.ofertas_vigentes', $ofertasVigentes[$solicitud->id], ['cantidad' => $ofertasVigentes[$solicitud->id]]) }}</span>
                                @endif
                            </span>
                            <span role="cell" class="ag-solicitudes__cifra">{{ number_format((float) $solicitud->hectareas_solicitadas, 2, ',', '.') }} ha</span>
                            <span role="cell" class="ag-solicitudes__cifra">
                                @if ($solicitud->precio_ha_acordado !== null)
                                    {{ number_format((float) $solicitud->precio_ha_acordado, 2, ',', '.') }}
                                    <span class="ag-solicitudes__sub">{{ __('solicitudes.panel.precio_acordado') }}</span>
                                @elseif ($solicitud->precio_ha_propuesto !== null)
                                    {{ number_format((float) $solicitud->precio_ha_propuesto, 2, ',', '.') }}
                                    <span class="ag-solicitudes__sub">{{ __('solicitudes.panel.precio_propuesto') }}</span>
                                @else
                                    —
                                @endif
                            </span>
                            <span role="cell">{{ $solicitud->ventana_desde->format('d/m') }} – {{ $solicitud->ventana_hasta->format('d/m/Y') }}</span>
                            <span role="cell" class="ag-index-table__acciones">
                                <x-organisms.row-actions>
                                    <x-atoms.button :href="route('panel.solicitudes.show', $solicitud)" variant="info-outline" size="sm" icon="visibility">
                                        {{ __('solicitudes.panel.ver') }}
                                    </x-atoms.button>
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$solicitudes" :aria-label="__('solicitudes.panel.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
