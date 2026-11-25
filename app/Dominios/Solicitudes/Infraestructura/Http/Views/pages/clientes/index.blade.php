{{--
    Page: clientes/index (GET /panel/clientes, panel.clientes.index)
    Los clientes del marketplace: arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → toolbar → tabla → paginación.
    Los suspendidos van primero. El cliente se registra solo desde la app o lo carga
    la oficina («Registrar cliente», con permiso); acá se consulta y, con permiso, se
    suspende o reactiva desde su ficha.

    Datos esperados (ver ClientesController::index()): la cáscara de CascaraPanel,
    más $puedeCrear (bool), $clientes (LengthAwarePaginator<Cliente>), $filtros (array{q: string,
    estado: ?string}) y $estadosFiltro (list<EstadoCliente>).

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
            >
                @if ($puedeCrear)
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.clientes.create')" variant="primary" icon="person_add">{{ __('solicitudes.clientes.registrar') }}</x-atoms.button>
                    </x-slot:actions>
                @endif
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
                $opcionesEstado = collect($estadosFiltro)->mapWithKeys(fn ($opcion) => [$opcion->value => __('solicitudes.clientes.estado.'.$opcion->value)])->all();
            @endphp

            @if ($hayFiltrosActivos || $clientes->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.clientes.index')"
                        :active-count="$filtros['estado'] ? 1 : 0"
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
                <x-molecules.index-table columns="3rem minmax(0, 1.8fr) minmax(0, 0.8fr) minmax(0, 0.8fr) minmax(0, 0.8fr) minmax(0, 0.8fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('solicitudes.clientes.col_cliente') }}</span>
                        <span role="columnheader">{{ __('solicitudes.clientes.col_tipo') }}</span>
                        <span role="columnheader">{{ __('solicitudes.clientes.col_estado') }}</span>
                        <span role="columnheader">{{ __('solicitudes.clientes.col_calificacion') }}</span>
                        <span role="columnheader">{{ __('solicitudes.clientes.col_alta') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($clientes as $cliente)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($clientes->currentPage() - 1) * $clientes->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell">
                                <span class="ag-solicitudes__nombre">{{ $cliente->razon_social }}</span>
                                <span class="ag-solicitudes__sub">{{ implode(' · ', array_filter([$cliente->documento_fiscal, $cliente->telefono])) ?: __('solicitudes.clientes.sin_contacto') }}</span>
                            </span>
                            <span role="cell">{{ __('solicitudes.clientes.tipo.'.$cliente->tipo->value) }}</span>
                            <span role="cell">
                                <x-atoms.badge :variant="$cliente->estado->tono()">{{ __('solicitudes.clientes.estado.'.$cliente->estado->value) }}</x-atoms.badge>
                            </span>
                            <span role="cell" class="ag-solicitudes__cifra">
                                @if ($cliente->calificaciones_conteo > 0)
                                    {{ $cliente->calificacion_promedio }} ({{ $cliente->calificaciones_conteo }})
                                @else
                                    {{ __('solicitudes.clientes.sin_calificaciones') }}
                                @endif
                            </span>
                            <span role="cell">{{ $cliente->created_at->format('d/m/Y') }}</span>
                            <span role="cell" class="ag-index-table__acciones">
                                <x-organisms.row-actions>
                                    <x-atoms.button :href="route('panel.clientes.show', $cliente)" variant="info-outline" size="sm" icon="visibility">
                                        {{ __('solicitudes.clientes.ver') }}
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
