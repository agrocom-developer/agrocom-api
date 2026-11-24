{{--
    Page: drones/index (GET /panel/drones-flota, panel.drones-flota.index)
    Los drones del marketplace de todos los proveedores (ADR 0029): arquetipo
    Listado, §6.2 de docs/diseno/guia_pantalla_panel.md. Los pendientes de
    revisión primero. Se llama `drones-flota` para no chocar con el catálogo
    operativo `panel.drones.*` (`ope_drones`), que usa la ejecución en el lote.

    Datos esperados (ver DronesController::index()): la cáscara de CascaraPanel,
    más $drones (LengthAwarePaginator<Dron>), $duenos (array<int, string>:
    nombre del proveedor dueño), $filtros (array{q: string, estado: ?string,
    origen: ?string}), $estadosFiltro y $origenesFiltro.

    Gateada por `flota.dron.ver`, verificado server-side en el controlador.
    Estilos en resources/css/pages/proveedores.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('flota.drones.titulo')" :tema="$tema">
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
        :vista-actual="__('flota.drones.titulo')"
    >
        <div class="ag-proveedores">
            <x-organisms.page-header
                :title="__('flota.drones.titulo')"
                :subtitle="__('flota.drones.subtitulo')"
            />

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
                $filtrosPanelActivos = collect(['estado', 'origen'])
                    ->filter(fn ($campo) => $filtros[$campo] !== null && $filtros[$campo] !== '')
                    ->count();
                $opcionesEstado = collect($estadosFiltro)->mapWithKeys(fn ($opcion) => [$opcion->value => __('aprobacion.estado.'.$opcion->value)])->all();
                $opcionesOrigen = collect($origenesFiltro)->mapWithKeys(fn ($opcion) => [$opcion->value => __('flota.origen.'.$opcion->value)])->all();
            @endphp

            @if ($hayFiltrosActivos || $drones->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.drones-flota.index')"
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
                        <x-atoms.select
                            name="origen"
                            id="filtro-origen"
                            :label="__('flota.drones.filtro_origen')"
                            :options="$opcionesOrigen"
                            :value="$filtros['origen']"
                            :placeholder="__('proveedores.filtro_todos')"
                        />
                    </x-organisms.filter-panel>

                    <x-molecules.table-search
                        :action="route('panel.drones-flota.index')"
                        :value="$filtros['q']"
                        :placeholder="__('flota.drones.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($drones->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('flota.drones.filtro_vacio_titulo')"
                        :detail="__('flota.drones.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="airplanemode_active"
                        :title="__('flota.drones.vacio_titulo')"
                        :detail="__('flota.drones.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 1.4fr) minmax(0, 1.2fr) minmax(0, 0.7fr) minmax(0, 0.6fr) minmax(0, 0.8fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('flota.drones.col_dron') }}</span>
                        <span role="columnheader">{{ __('flota.drones.col_dueno') }}</span>
                        <span role="columnheader">{{ __('flota.drones.col_capacidad') }}</span>
                        <span role="columnheader">{{ __('flota.drones.col_ciclos') }}</span>
                        <span role="columnheader">{{ __('flota.drones.col_estado') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($drones as $dron)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($drones->currentPage() - 1) * $drones->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell">
                                <span class="ag-proveedores__nombre">{{ $dron->marca }} {{ $dron->modelo }}</span>
                                <span class="ag-proveedores__sub">{{ $dron->numero_serie }}</span>
                            </span>
                            <span role="cell">
                                {{ $duenos[$dron->dueno_proveedor_id] ?? "#{$dron->dueno_proveedor_id}" }}
                                <span class="ag-proveedores__sub">{{ __('flota.origen.'.$dron->origen->value) }}</span>
                            </span>
                            <span role="cell" class="ag-proveedores__cifra">{{ __('flota.drones.litros', ['litros' => $dron->capacidad_tanque_l]) }}</span>
                            <span role="cell" class="ag-proveedores__cifra">{{ $dron->ciclos_bateria }}</span>
                            <span role="cell">
                                <x-atoms.badge :variant="$dron->estado->tono()">{{ __('aprobacion.estado.'.$dron->estado->value) }}</x-atoms.badge>
                            </span>
                            <span role="cell" class="ag-index-table__acciones">
                                <x-organisms.row-actions>
                                    <x-atoms.button :href="route('panel.drones-flota.show', $dron)" variant="info-outline" size="sm" icon="visibility">
                                        {{ $dron->estado->value === 'pendiente' ? __('proveedores.revisar') : __('proveedores.ver') }}
                                    </x-atoms.button>
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$drones" :aria-label="__('flota.drones.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
