{{--
    Page: auxiliares/index (GET /panel/auxiliares, panel.auxiliares.index)
    Cola de auxiliares de todos los proveedores (ADR 0032, punto 2): arquetipo
    Listado, §6.2 de docs/diseno/guia_pantalla_panel.md. Los pendientes primero.

    Datos esperados (ver AuxiliaresController::index()): la cáscara de
    CascaraPanel, más $auxiliares (LengthAwarePaginator<Auxiliar>), $nombres
    (array<int, DatosCuenta>), $proveedores (Collection<int, string>),
    $usuarioDePiloto (Collection<int, int>: cuenta de cada piloto que registró),
    $filtros (array{q: string, estado: ?string}) y $estadosFiltro.

    Gateada por `proveedores.auxiliar.ver`, verificado server-side en el
    controlador. Estilos en resources/css/pages/proveedores.css — cero color
    hardcodeado (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('proveedores.auxiliares.titulo')" :tema="$tema">
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
        :vista-actual="__('proveedores.auxiliares.titulo')"
    >
        <div class="ag-proveedores">
            <x-organisms.page-header
                :title="__('proveedores.auxiliares.titulo')"
                :subtitle="__('proveedores.auxiliares.subtitulo')"
            />

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
                $filtrosPanelActivos = collect(['estado'])
                    ->filter(fn ($campo) => $filtros[$campo] !== null && $filtros[$campo] !== '')
                    ->count();
                $opcionesEstado = collect($estadosFiltro)->mapWithKeys(fn ($opcion) => [$opcion->value => __('aprobacion.estado.'.$opcion->value)])->all();
            @endphp

            @if ($hayFiltrosActivos || $auxiliares->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.auxiliares.index')"
                        :active-count="$filtrosPanelActivos"
                    >
                        <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                        <x-atoms.select
                            name="estado"
                            id="filtro-estado"
                            :label="__('proveedores.filtro_estado')"
                            :options="$opcionesEstado"
                            :value="$filtros['estado']"
                            :placeholder="__('proveedores.filtro_todos')"
                        />
                    </x-organisms.filter-panel>

                    <x-molecules.table-search
                        :action="route('panel.auxiliares.index')"
                        :value="$filtros['q']"
                        :placeholder="__('proveedores.auxiliares.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($auxiliares->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('proveedores.auxiliares.filtro_vacio_titulo')"
                        :detail="__('proveedores.auxiliares.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="support_agent"
                        :title="__('proveedores.auxiliares.vacio_titulo')"
                        :detail="__('proveedores.auxiliares.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 1.4fr) minmax(0, 1.2fr) minmax(0, 1.2fr) minmax(0, 0.8fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('proveedores.auxiliares.col_auxiliar') }}</span>
                        <span role="columnheader">{{ __('proveedores.auxiliares.col_proveedor') }}</span>
                        <span role="columnheader">{{ __('proveedores.auxiliares.col_registrado_por') }}</span>
                        <span role="columnheader">{{ __('proveedores.auxiliares.col_estado') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($auxiliares as $fila)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($auxiliares->currentPage() - 1) * $auxiliares->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell">
                                <span class="ag-proveedores__nombre">{{ $nombres[$fila->usuario_id]->nombre ?? "#{$fila->usuario_id}" }}</span>
                            </span>
                            <span role="cell">{{ $proveedores[$fila->proveedor_id] ?? "#{$fila->proveedor_id}" }}</span>
                            <span role="cell">
                                @if ($fila->registrado_por_piloto_id !== null)
                                    {{ $nombres[$usuarioDePiloto[$fila->registrado_por_piloto_id] ?? 0]->nombre ?? "#{$fila->registrado_por_piloto_id}" }}
                                @else
                                    {{ __('proveedores.auxiliares.registrado_por_proveedor') }}
                                @endif
                            </span>
                            <span role="cell">
                                <x-atoms.badge :variant="$fila->estado->tono()">{{ __('aprobacion.estado.'.$fila->estado->value) }}</x-atoms.badge>
                            </span>
                            <span role="cell" class="ag-index-table__acciones">
                                <x-organisms.row-actions>
                                    <x-atoms.button :href="route('panel.auxiliares.show', $fila)" :variant="$fila->estado->value === 'pendiente' ? 'distintivo-1-outline' : 'info-outline'" size="sm" :icon="$fila->estado->value === 'pendiente' ? 'fact_check' : 'visibility'">
                                        {{ $fila->estado->value === 'pendiente' ? __('proveedores.revisar') : __('proveedores.ver') }}
                                    </x-atoms.button>
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$auxiliares" :aria-label="__('proveedores.auxiliares.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
