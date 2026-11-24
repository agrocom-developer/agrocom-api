{{--
    Page: asignaciones/index (GET /panel/asignaciones, panel.asignaciones.index)
    Cola de asignación (ADR 0030): arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → toolbar → tabla. Las
    solicitudes que esperan proveedor (sin proveedor, en asignación, publicadas)
    van primero; después las ya asignadas que todavía no salieron al lote. La
    ficha muestra las rondas y las ofertas, y permite asignar a mano.

    Datos esperados (ver AsignacionesController::index()): la cáscara de
    CascaraPanel, más:
    - $cola (list<DatosSolicitudParaAsignar>).
    - $ofertasVivas (array<int, int>): ofertas vivas por solicitud.
    - $ciclos (array<int, int>): el ciclo de asignación más reciente por solicitud.
    - $filtros (array{estado: ?string}) y $estadosFiltro (list<string>).

    Gateada por `asignacion.asignacion.ver`, verificado server-side en el
    controlador. Estilos de resources/css/pages/solicitudes.css — cero color
    hardcodeado (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('asignacion.panel.titulo')" :tema="$tema">
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
        :vista-actual="__('asignacion.panel.titulo')"
    >
        <div class="ag-solicitudes">
            <x-organisms.page-header
                :title="__('asignacion.panel.titulo')"
                :subtitle="__('asignacion.panel.subtitulo')"
            />

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = ($filtros['estado'] ?? null) !== null && $filtros['estado'] !== '';
                $opcionesEstado = collect($estadosFiltro)->mapWithKeys(fn ($valor) => [$valor => __('solicitudes.estado.'.$valor)])->all();
                $tonoPorEstado = ['publicada' => 'info', 'en_asignacion' => 'info', 'sin_proveedor' => 'warning', 'asignada' => 'primary-2', 'en_camino' => 'primary-2'];
            @endphp

            @if ($hayFiltrosActivos || count($cola) > 0)
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.asignaciones.index')"
                        :active-count="$hayFiltrosActivos ? 1 : 0"
                    >
                        <x-atoms.select
                            name="estado"
                            id="filtro-estado"
                            :label="__('asignacion.panel.filtro_estado')"
                            :options="$opcionesEstado"
                            :value="$filtros['estado']"
                            :placeholder="__('asignacion.panel.filtro_todos')"
                        />
                    </x-organisms.filter-panel>
                </div>
            @endif

            @if (count($cola) === 0)
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('asignacion.panel.filtro_vacio_titulo')"
                        :detail="__('asignacion.panel.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="alt_route"
                        :title="__('asignacion.panel.vacio_titulo')"
                        :detail="__('asignacion.panel.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 1.6fr) minmax(0, 0.9fr) minmax(0, 0.8fr) minmax(0, 0.8fr) minmax(0, 0.9fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('asignacion.panel.col_solicitud') }}</span>
                        <span role="columnheader">{{ __('asignacion.panel.col_estado') }}</span>
                        <span role="columnheader">{{ __('asignacion.panel.col_ofertas') }}</span>
                        <span role="columnheader">{{ __('asignacion.panel.col_hectareas') }}</span>
                        <span role="columnheader">{{ __('asignacion.panel.col_ventana') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($cola as $fila)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">{{ $loop->iteration }}</span>
                            <span role="cell">
                                <span class="ag-solicitudes__nombre">#{{ $fila->id }} · {{ $fila->zonaNombre ?? $fila->terrenoNombre }}</span>
                                <span class="ag-solicitudes__sub">{{ __('solicitudes.tipo_aplicacion.'.$fila->tipoAplicacion) }}</span>
                            </span>
                            <span role="cell">
                                <x-atoms.badge :variant="$tonoPorEstado[$fila->estado] ?? 'neutral'">{{ __('solicitudes.estado.'.$fila->estado) }}</x-atoms.badge>
                                @if ($fila->urgente)
                                    <x-atoms.badge variant="alert">{{ __('asignacion.panel.urgente') }}</x-atoms.badge>
                                @endif
                            </span>
                            <span role="cell" class="ag-solicitudes__cifra">
                                {{ __('asignacion.panel.ofertas_vivas', ['cantidad' => $ofertasVivas[$fila->id] ?? 0]) }}
                                @if (isset($ciclos[$fila->id]) && $ciclos[$fila->id] > 1)
                                    <span class="ag-solicitudes__sub">{{ __('asignacion.panel.ciclo', ['ciclo' => $ciclos[$fila->id]]) }}</span>
                                @endif
                            </span>
                            <span role="cell" class="ag-solicitudes__cifra">{{ number_format((float) $fila->hectareas, 2, ',', '.') }} ha</span>
                            <span role="cell">{{ \Illuminate\Support\Carbon::parse($fila->ventanaDesde)->format('d/m') }} – {{ \Illuminate\Support\Carbon::parse($fila->ventanaHasta)->format('d/m/Y') }}</span>
                            <span role="cell" class="ag-index-table__acciones">
                                <x-organisms.row-actions>
                                    <x-atoms.button :href="route('panel.asignaciones.show', $fila->id)" variant="info-outline" size="sm" icon="visibility">
                                        {{ __('asignacion.panel.ver') }}
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
