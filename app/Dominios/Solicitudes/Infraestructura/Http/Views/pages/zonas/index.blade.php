{{--
    Page: zonas/index (GET /panel/zonas, panel.zonas.index)
    Las zonas de Santa Cruz: arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → toolbar → tabla → paginación.
    Las zonas agrupan los terrenos de los clientes y la base de los proveedores.

    Datos esperados (ver ZonasController::index()): la cáscara de CascaraPanel,
    más $zonas (LengthAwarePaginator<Zona>), $terrenos y $proveedores (mapas
    zona_id => cantidad), $filtros (array{q: string, region: ?string}),
    $regiones (list<string>) y $puedeGestionar (bool).

    Gateada por `solicitudes.zona.ver`; el alta, la edición y la baja exigen
    `solicitudes.zona.gestionar`, verificado server-side.
--}}
<x-templates.panel-shell :title="__('solicitudes.zonas.titulo')" :tema="$tema">
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
        :vista-actual="__('solicitudes.zonas.titulo')"
    >
        <div class="ag-solicitudes">
            <x-organisms.page-header
                :title="__('solicitudes.zonas.titulo')"
                :subtitle="__('solicitudes.zonas.subtitulo')"
            >
                @if ($puedeGestionar)
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.zonas.create')" variant="primary" icon="add">
                            {{ __('solicitudes.zonas.nueva') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endif
            </x-organisms.page-header>

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
                $opcionesRegion = collect($regiones)->mapWithKeys(fn ($region) => [$region => __('solicitudes.region.'.$region)])->all();
            @endphp

            @if ($hayFiltrosActivos || $zonas->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.zonas.index')"
                        :active-count="$filtros['region'] ? 1 : 0"
                    >
                        <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                        <x-atoms.select
                            name="region"
                            id="filtro-region"
                            :label="__('solicitudes.zonas.filtro_region')"
                            :options="$opcionesRegion"
                            :value="$filtros['region']"
                            :placeholder="__('solicitudes.panel.filtro_todos')"
                        />
                    </x-organisms.filter-panel>

                    <x-molecules.table-search
                        :action="route('panel.zonas.index')"
                        :value="$filtros['q']"
                        :placeholder="__('solicitudes.zonas.buscar_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($zonas->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('solicitudes.zonas.filtro_vacio_titulo')"
                        :detail="__('solicitudes.zonas.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="pin_drop"
                        :title="__('solicitudes.zonas.vacio_titulo')"
                        :detail="__('solicitudes.zonas.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 1.6fr) minmax(0, 1fr) minmax(0, 1fr) minmax(0, 0.7fr) minmax(0, 0.9fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('solicitudes.zonas.col_zona') }}</span>
                        <span role="columnheader">{{ __('solicitudes.zonas.col_region') }}</span>
                        <span role="columnheader">{{ __('solicitudes.zonas.col_ubicacion') }}</span>
                        <span role="columnheader">{{ __('solicitudes.zonas.col_terrenos') }}</span>
                        <span role="columnheader">{{ __('solicitudes.zonas.col_proveedores') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($zonas as $zona)
                        @php($usada = ($terrenos[$zona->id] ?? 0) > 0 || ($proveedores[$zona->id] ?? 0) > 0)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($zonas->currentPage() - 1) * $zonas->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell"><span class="ag-solicitudes__nombre">{{ $zona->nombre }}</span></span>
                            <span role="cell">{{ $zona->macrorregion ? __('solicitudes.region.'.$zona->macrorregion) : '—' }}</span>
                            <span role="cell" class="ag-solicitudes__cifra">{{ $zona->centroide_lat }}, {{ $zona->centroide_lng }}</span>
                            <span role="cell" class="ag-solicitudes__cifra">{{ $terrenos[$zona->id] ?? 0 }}</span>
                            <span role="cell" class="ag-solicitudes__cifra">{{ $proveedores[$zona->id] ?? 0 }}</span>
                            <span role="cell" class="ag-index-table__acciones">
                                @if ($puedeGestionar)
                                    <x-organisms.row-actions>
                                        <x-atoms.button :href="route('panel.zonas.edit', $zona)" variant="warning-outline" size="sm" icon="edit">
                                            {{ __('solicitudes.zonas.editar') }}
                                        </x-atoms.button>
                                        @unless ($usada)
                                            <x-atoms.button
                                                type="button"
                                                variant="danger-outline"
                                                size="sm"
                                                icon="delete"
                                                data-bs-toggle="modal"
                                                data-bs-target="#zona-eliminar-modal-{{ $zona->id }}"
                                            >{{ __('solicitudes.zonas.eliminar') }}</x-atoms.button>
                                        @endunless
                                    </x-organisms.row-actions>
                                    @unless ($usada)
                                        <form id="zona-eliminar-form-{{ $zona->id }}" method="POST" action="{{ route('panel.zonas.destroy', $zona) }}" hidden>
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                    @endunless
                                @endif
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                @if ($puedeGestionar)
                    @foreach ($zonas as $zona)
                        @continue(($terrenos[$zona->id] ?? 0) > 0 || ($proveedores[$zona->id] ?? 0) > 0)
                        <x-molecules.confirm-modal
                            :id="'zona-eliminar-modal-'.$zona->id"
                            :form-id="'zona-eliminar-form-'.$zona->id"
                            :title="__('solicitudes.zonas.eliminar_titulo', ['zona' => $zona->nombre])"
                            :message="__('solicitudes.zonas.eliminar_mensaje')"
                            :confirm-label="__('solicitudes.zonas.eliminar_confirmar')"
                            tone="danger"
                        />
                    @endforeach
                @endif

                <x-molecules.pagination :paginator="$zonas" :aria-label="__('solicitudes.zonas.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
