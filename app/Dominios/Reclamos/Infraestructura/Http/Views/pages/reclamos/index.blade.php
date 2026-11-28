{{--
    Page: reclamos/index (GET /panel/reclamos, panel.reclamos.index)
    Cola de reclamos (ADR 0033 y 0039): arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → toolbar → tabla → paginación.
    Las abiertas, las que esperan a soporte, van primero.

    Datos esperados (ver ReclamosController::index()): la cáscara de CascaraPanel,
    más $reclamos (LengthAwarePaginator<Reclamo>), $nombres (array<int, string>)
    nombre del proveedor por id de reclamo, $filtros (array{estado: ?string}) y
    $estadosFiltro (list<EstadoReclamo>).

    Gateada por `reclamos.reclamo.ver`, verificado server-side en el controlador.
--}}
<x-templates.panel-shell :title="__('reclamos.panel.titulo')" :tema="$tema">
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
        :vista-actual="__('reclamos.panel.titulo')"
    >
        <div class="ag-solicitudes">
            <x-organisms.page-header
                :title="__('reclamos.panel.titulo')"
                :subtitle="__('reclamos.panel.subtitulo')"
            />

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
                $filtrosPanelActivos = ($filtros['estado'] ?? null) !== null && $filtros['estado'] !== '' ? 1 : 0;
                $opcionesEstado = collect($estadosFiltro)->mapWithKeys(fn ($opcion) => [$opcion->value => __('reclamos.estado.'.$opcion->value)])->all();
            @endphp

            @if ($hayFiltrosActivos || $reclamos->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.reclamos.index')"
                        :active-count="$filtrosPanelActivos"
                    >
                        <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                        <x-atoms.select
                            name="estado"
                            id="filtro-estado"
                            :label="__('reclamos.panel.filtro_estado')"
                            :options="$opcionesEstado"
                            :value="$filtros['estado']"
                            :placeholder="__('reclamos.panel.filtro_todos')"
                        />
                    </x-organisms.filter-panel>

                    <x-molecules.table-search
                        :action="route('panel.reclamos.index')"
                        :value="$filtros['q']"
                        :placeholder="__('reclamos.panel.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($reclamos->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('reclamos.panel.filtro_vacio_titulo')"
                        :detail="__('reclamos.panel.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="report"
                        :title="__('reclamos.panel.vacio_titulo')"
                        :detail="__('reclamos.panel.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 1.1fr) minmax(0, 1.2fr) minmax(0, 1.2fr) minmax(0, 0.9fr) minmax(0, 0.9fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('reclamos.panel.col_reclamo') }}</span>
                        <span role="columnheader">{{ __('reclamos.panel.col_motivo') }}</span>
                        <span role="columnheader">{{ __('reclamos.panel.col_proveedor') }}</span>
                        <span role="columnheader">{{ __('reclamos.panel.col_monto') }}</span>
                        <span role="columnheader">{{ __('reclamos.panel.col_estado') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($reclamos as $reclamo)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($reclamos->currentPage() - 1) * $reclamos->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell">
                                <span class="ag-solicitudes__nombre">#{{ $reclamo->id }} · {{ __('reclamos.panel.de_servicio', ['solicitud' => $reclamo->solicitud_id]) }}</span>
                                <span class="ag-solicitudes__sub">{{ __('reclamos.panel.abierta_por_'.$reclamo->abierta_por_rol->value) }} · {{ $reclamo->created_at->format('d/m/Y') }}</span>
                            </span>
                            <span role="cell">{{ __('reclamos.motivo.'.$reclamo->motivo->value) }}</span>
                            <span role="cell">{{ $nombres[$reclamo->id] }}</span>
                            <span role="cell" class="ag-solicitudes__cifra">{{ number_format((float) $reclamo->monto_en_reclamo, 2, ',', '.') }}</span>
                            <span role="cell">
                                <x-atoms.badge :variant="$reclamo->estado->tono()">{{ __('reclamos.estado.'.$reclamo->estado->value) }}</x-atoms.badge>
                                @if ($reclamo->ronda === 2)
                                    <span class="ag-solicitudes__sub">{{ __('reclamos.panel.apelada') }}</span>
                                @endif
                            </span>
                            <span role="cell" class="ag-index-table__acciones">
                                <x-organisms.row-actions>
                                    <x-slot:ver>
                                        <x-molecules.row-action kind="ver" :href="route('panel.reclamos.show', $reclamo->id)" icon="visibility" :label="__('reclamos.panel.ver')" />
                                    </x-slot:ver>
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$reclamos" :aria-label="__('reclamos.panel.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
