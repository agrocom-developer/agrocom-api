{{--
    Page: disputas/index (GET /panel/disputas, panel.disputas.index)
    Cola de disputas (ADR 0033 y 0039): arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → toolbar → tabla → paginación.
    Las abiertas, las que esperan a soporte, van primero.

    Datos esperados (ver DisputasController::index()): la cáscara de CascaraPanel,
    más $disputas (LengthAwarePaginator<Disputa>), $nombres (array<int, string>)
    nombre del proveedor por id de disputa, $filtros (array{estado: ?string}) y
    $estadosFiltro (list<EstadoDisputa>).

    Gateada por `disputas.disputa.ver`, verificado server-side en el controlador.
--}}
<x-templates.panel-shell :title="__('disputas.panel.titulo')" :tema="$tema">
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
        :vista-actual="__('disputas.panel.titulo')"
    >
        <div class="ag-solicitudes">
            <x-organisms.page-header
                :title="__('disputas.panel.titulo')"
                :subtitle="__('disputas.panel.subtitulo')"
            />

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
                $filtrosPanelActivos = ($filtros['estado'] ?? null) !== null && $filtros['estado'] !== '' ? 1 : 0;
                $opcionesEstado = collect($estadosFiltro)->mapWithKeys(fn ($opcion) => [$opcion->value => __('disputas.estado.'.$opcion->value)])->all();
            @endphp

            @if ($hayFiltrosActivos || $disputas->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.disputas.index')"
                        :active-count="$filtrosPanelActivos"
                    >
                        <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                        <x-atoms.select
                            name="estado"
                            id="filtro-estado"
                            :label="__('disputas.panel.filtro_estado')"
                            :options="$opcionesEstado"
                            :value="$filtros['estado']"
                            :placeholder="__('disputas.panel.filtro_todos')"
                        />
                    </x-organisms.filter-panel>

                    <x-molecules.table-search
                        :action="route('panel.disputas.index')"
                        :value="$filtros['q']"
                        :placeholder="__('disputas.panel.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($disputas->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('disputas.panel.filtro_vacio_titulo')"
                        :detail="__('disputas.panel.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="report"
                        :title="__('disputas.panel.vacio_titulo')"
                        :detail="__('disputas.panel.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 1.1fr) minmax(0, 1.2fr) minmax(0, 1.2fr) minmax(0, 0.9fr) minmax(0, 0.9fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('disputas.panel.col_disputa') }}</span>
                        <span role="columnheader">{{ __('disputas.panel.col_motivo') }}</span>
                        <span role="columnheader">{{ __('disputas.panel.col_proveedor') }}</span>
                        <span role="columnheader">{{ __('disputas.panel.col_monto') }}</span>
                        <span role="columnheader">{{ __('disputas.panel.col_estado') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($disputas as $disputa)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($disputas->currentPage() - 1) * $disputas->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell">
                                <span class="ag-solicitudes__nombre">#{{ $disputa->id }} · {{ __('disputas.panel.de_servicio', ['solicitud' => $disputa->solicitud_id]) }}</span>
                                <span class="ag-solicitudes__sub">{{ __('disputas.panel.abierta_por_'.$disputa->abierta_por_rol->value) }} · {{ $disputa->created_at->format('d/m/Y') }}</span>
                            </span>
                            <span role="cell">{{ __('disputas.motivo.'.$disputa->motivo->value) }}</span>
                            <span role="cell">{{ $nombres[$disputa->id] }}</span>
                            <span role="cell" class="ag-solicitudes__cifra">{{ number_format((float) $disputa->monto_en_disputa, 2, ',', '.') }}</span>
                            <span role="cell">
                                <x-atoms.badge :variant="$disputa->estado->tono()">{{ __('disputas.estado.'.$disputa->estado->value) }}</x-atoms.badge>
                                @if ($disputa->ronda === 2)
                                    <span class="ag-solicitudes__sub">{{ __('disputas.panel.apelada') }}</span>
                                @endif
                            </span>
                            <span role="cell" class="ag-index-table__acciones">
                                <x-organisms.row-actions>
                                    <x-slot:ver>
                                        <x-molecules.row-action kind="ver" :href="route('panel.disputas.show', $disputa->id)" icon="visibility" :label="__('disputas.panel.ver')" />
                                    </x-slot:ver>
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$disputas" :aria-label="__('disputas.panel.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
