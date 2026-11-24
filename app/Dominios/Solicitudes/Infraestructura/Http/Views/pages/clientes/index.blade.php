{{--
    Page: clientes/index (GET /panel/clientes, panel.clientes.index)
    Clientes del marketplace: arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → toolbar → tabla → paginación.
    Los suspendidos van primero. Los clientes se registran solos desde la app;
    el panel los consulta y, con permiso, los suspende desde su ficha.

    Datos esperados (ver ClientesController::index()): la cáscara de CascaraPanel,
    más $clientes (LengthAwarePaginator<Cliente>), $solicitudesPorCliente
    (array<int, int>), $filtros (array{q, estado, tipo}), $estadosFiltro
    (list<EstadoCliente>) y $tiposFiltro (list<TipoCliente>).

    Gateada por `solicitudes.cliente.ver`, verificado server-side.
--}}
<x-templates.panel-shell :title="__('solicitudes.clientes.titulo')" :tema="$tema">
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
        :vista-actual="__('solicitudes.clientes.titulo')"
    >
        <div class="ag-solicitudes">
            <x-organisms.page-header
                :title="__('solicitudes.clientes.titulo')"
                :subtitle="__('solicitudes.clientes.subtitulo')"
            />

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
                $filtrosPanelActivos = collect([$filtros['estado'], $filtros['tipo']])->filter(fn ($valor) => $valor !== null && $valor !== '')->count();
                $opcionesEstado = collect($estadosFiltro)->mapWithKeys(fn ($opcion) => [$opcion->value => __('solicitudes.clientes.estado.'.$opcion->value)])->all();
                $opcionesTipo = collect($tiposFiltro)->mapWithKeys(fn ($opcion) => [$opcion->value => __('solicitudes.clientes.tipo.'.$opcion->value)])->all();
            @endphp

            @if ($hayFiltrosActivos || $clientes->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.clientes.index')"
                        :active-count="$filtrosPanelActivos"
                    >
                        <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                        <x-atoms.select
                            name="estado"
                            id="filtro-estado"
                            :label="__('solicitudes.clientes.filtro_estado')"
                            :options="$opcionesEstado"
                            :value="$filtros['estado']"
                            :placeholder="__('solicitudes.panel.filtro_todos')"
                        />
                        <x-atoms.select
                            name="tipo"
                            id="filtro-tipo"
                            :label="__('solicitudes.clientes.filtro_tipo')"
                            :options="$opcionesTipo"
                            :value="$filtros['tipo']"
                            :placeholder="__('solicitudes.panel.filtro_todos')"
                        />
                    </x-organisms.filter-panel>

                    <x-molecules.table-search
                        :action="route('panel.clientes.index')"
                        :value="$filtros['q']"
                        :placeholder="__('solicitudes.clientes.buscar_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($clientes->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('solicitudes.clientes.filtro_vacio_titulo')"
                        :detail="__('solicitudes.clientes.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="agriculture"
                        :title="__('solicitudes.clientes.vacio_titulo')"
                        :detail="__('solicitudes.clientes.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 1.6fr) minmax(0, 1fr) minmax(0, 0.9fr) minmax(0, 0.8fr) minmax(0, 0.8fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('solicitudes.clientes.col_cliente') }}</span>
                        <span role="columnheader">{{ __('solicitudes.clientes.col_contacto') }}</span>
                        <span role="columnheader">{{ __('solicitudes.clientes.col_estado') }}</span>
                        <span role="columnheader">{{ __('solicitudes.clientes.col_calificacion') }}</span>
                        <span role="columnheader">{{ __('solicitudes.clientes.col_solicitudes') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($clientes as $cliente)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($clientes->currentPage() - 1) * $clientes->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell">
                                <span class="ag-solicitudes__nombre">{{ $cliente->razon_social }}</span>
                                <span class="ag-solicitudes__sub">{{ __('solicitudes.clientes.tipo.'.$cliente->tipo->value) }}</span>
                            </span>
                            <span role="cell">
                                {{ $cliente->telefono ?? '—' }}
                                <span class="ag-solicitudes__sub">{{ $cliente->documento_fiscal ?? '—' }}</span>
                            </span>
                            <span role="cell">
                                <x-atoms.badge :variant="$cliente->estado->value === 'activo' ? 'success' : 'danger'">{{ __('solicitudes.clientes.estado.'.$cliente->estado->value) }}</x-atoms.badge>
                            </span>
                            <span role="cell" class="ag-solicitudes__cifra">
                                @if ($cliente->calificaciones_conteo > 0)
                                    {{ number_format((float) $cliente->calificacion_promedio, 2, ',', '.') }}
                                    <span class="ag-solicitudes__sub">({{ $cliente->calificaciones_conteo }})</span>
                                @else
                                    —
                                @endif
                            </span>
                            <span role="cell" class="ag-solicitudes__cifra">{{ $solicitudesPorCliente[$cliente->id] ?? 0 }}</span>
                            <span role="cell" class="ag-index-table__acciones">
                                <x-organisms.row-actions>
                                    <x-atoms.button :href="route('panel.clientes.show', $cliente)" variant="info-outline" size="sm" icon="visibility">
                                        {{ __('solicitudes.panel.ver') }}
                                    </x-atoms.button>
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$clientes" :aria-label="__('solicitudes.clientes.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
