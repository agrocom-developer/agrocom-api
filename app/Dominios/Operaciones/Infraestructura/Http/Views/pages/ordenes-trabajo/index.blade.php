{{--
    Page: ordenes-trabajo/index (GET /panel/trabajos, panel.trabajos.index)
    Listado maestro de tandas de trabajo (reforma 18/9/2026): una tanda agrupa
    los equipos que trabajan juntos un período, con parámetros compartidos
    (clima/vuelo, Ph si la orden es de insumo líquido, calda). Arquetipo
    Listado, §6.2 de docs/diseno/guia_pantalla_panel.md.

    Datos esperados (ver OrdenesTrabajoController::index()): la cáscara de
    CascaraPanel, más:
    - $tandas (LengthAwarePaginator<OrdenTrabajo>): con `trabajos` precargada,
      ordenada descendente por id.
    - $filtros (array{orden_id: ?int, nro_aplicacion: ?int}): filtros aplicados,
      para dejar los campos con el valor tras el submit.
    - $etiquetasEquipo (array<int, string>): código de equipo por id, para
      mostrar qué equipos participan de cada tanda vía
      `$tanda->trabajos->pluck('equipo_trabajo_id')`.
    - $puedeCrear (bool): si el rol activo tiene `operaciones.trabajo.crear`
      — sin él, no se ofrece el botón de nueva tanda.

    Gateada por `operaciones.trabajo.ver`. "Ver detalle" a
    `panel.trabajos.show` va siempre. Columna de estado: muestra el estado
    del tablero (`estadoTablero()`) de cada trabajo de la tanda (no un estado
    agregado propio de la tanda, porque `OrdenTrabajo` no tiene máquina de
    estados real). Sin la opción de eliminar tandas — se crean, punto.

    Estilos en resources/css/pages/ordenes-trabajo.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@php
    $variantePorEstadoTablero = [
        'abierto' => 'neutral',
        'cerrado' => 'info',
        'validado' => 'success',
    ];
@endphp
<x-templates.panel-shell :title="__('operaciones.ordenes_trabajo.titulo')" :tema="$tema">
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
        :vista-actual="__('operaciones.ordenes_trabajo.titulo')"
    >
        <div class="ag-ordenes-trabajo">
            <x-organisms.page-header
                :title="__('operaciones.ordenes_trabajo.titulo')"
                :subtitle="__('operaciones.ordenes_trabajo.subtitulo')"
            >
                @puede('operaciones.trabajo.crear')
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.trabajos.create')" variant="primary" icon="add">
                            {{ __('operaciones.ordenes_trabajo.nueva_accion') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-ordenes-trabajo__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
            @endphp

            @if ($hayFiltrosActivos || $tandas->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.trabajos.index')"
                        :active-count="collect(['orden_id', 'nro_aplicacion'])
                            ->filter(fn ($campo) => $filtros[$campo] !== null && $filtros[$campo] !== '')
                            ->count()"
                    >
                        <x-atoms.input
                            type="number"
                            name="orden_id"
                            id="filtro-orden-id"
                            :label="__('operaciones.ordenes_trabajo.filtro_orden')"
                            :value="$filtros['orden_id']"
                            :placeholder="__('ui.tabla.filtro_placeholder')"
                        />

                        <x-atoms.input
                            type="number"
                            name="nro_aplicacion"
                            id="filtro-nro-aplicacion"
                            :label="__('operaciones.ordenes_trabajo.filtro_orden')"
                            :value="$filtros['nro_aplicacion']"
                            :placeholder="__('ui.tabla.filtro_placeholder')"
                        />
                    </x-organisms.filter-panel>
                </div>
            @endif

            @if ($tandas->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('operaciones.ordenes_trabajo.filtro_vacio_titulo')"
                        :detail="__('operaciones.ordenes_trabajo.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="assignment"
                        :title="__('operaciones.ordenes_trabajo.vacio_titulo')"
                        :detail="__('operaciones.ordenes_trabajo.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem 1fr 1fr 1fr 1fr var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('operaciones.ordenes_trabajo.col_tanda') }}</span>
                        <span role="columnheader">{{ __('operaciones.ordenes_trabajo.col_orden') }}</span>
                        <span role="columnheader">{{ __('operaciones.ordenes_trabajo.col_equipos') }}</span>
                        <span role="columnheader">{{ __('operaciones.ordenes_trabajo.col_hectareas') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($tandas as $tanda)
                        @php
                            $equipoIds = $tanda->trabajos->pluck('equipo_trabajo_id')->filter()->unique();
                            $equiposTexto = $equipoIds->map(fn ($id) => $etiquetasEquipo[$id] ?? "#{$id}")->implode(', ');
                            $hectareas = $tanda->trabajos->reduce(
                                fn ($acum, $trabajo) => $acum + (float) $trabajo->hectareas_declaradas,
                                0
                            );
                        @endphp
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($tandas->currentPage() - 1) * $tandas->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-ordenes-trabajo__mono">#{{ $tanda->id }}</span>
                            <span role="cell">Orden #{{ $tanda->orden_id }} (aplicación {{ $tanda->orden->nro_aplicacion }})</span>
                            <span role="cell">{{ $equiposTexto }}</span>
                            <span role="cell" class="ag-ordenes-trabajo__mono">
                                {{ number_format($hectareas, 2, ',', '.') }} ha
                            </span>

                            <span role="cell" class="ag-index-table__acciones">
                                <x-organisms.row-actions>
                                    <x-atoms.button
                                        :href="route('panel.trabajos.show', $tanda)"
                                        variant="info-outline"
                                        size="sm"
                                        icon="visibility"
                                    >
                                        {{ __('operaciones.ordenes_trabajo.ver_accion') }}
                                    </x-atoms.button>
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$tandas" :aria-label="__('operaciones.ordenes_trabajo.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
