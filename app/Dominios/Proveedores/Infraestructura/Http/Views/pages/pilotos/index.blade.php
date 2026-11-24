{{--
    Page: pilotos/index (GET /panel/pilotos, panel.pilotos.index)
    Cola de pilotos de todos los proveedores (ADR 0032): arquetipo Listado, §6.2
    de docs/diseno/guia_pantalla_panel.md. Los pendientes primero. La aprobación
    vive en la ficha (`show`): decidir exige ver la licencia y los documentos.

    Datos esperados (ver PilotosController::index()): la cáscara de CascaraPanel,
    más $pilotos (LengthAwarePaginator<Piloto>), $nombres (array<int, DatosCuenta>),
    $proveedores (Collection<int, string>), $filtros (array{q: string, estado: ?string})
    y $estadosFiltro (list<EstadoAprobacion>).

    Gateada por `proveedores.piloto.ver`, verificado server-side en el controlador.
    Estilos en resources/css/pages/proveedores.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('proveedores.pilotos.titulo')" :tema="$tema">
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
        :vista-actual="__('proveedores.pilotos.titulo')"
    >
        <div class="ag-proveedores">
            <x-organisms.page-header
                :title="__('proveedores.pilotos.titulo')"
                :subtitle="__('proveedores.pilotos.subtitulo')"
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

            @if ($hayFiltrosActivos || $pilotos->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.pilotos.index')"
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
                        :action="route('panel.pilotos.index')"
                        :value="$filtros['q']"
                        :placeholder="__('proveedores.pilotos.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($pilotos->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('proveedores.pilotos.filtro_vacio_titulo')"
                        :detail="__('proveedores.pilotos.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="person"
                        :title="__('proveedores.pilotos.vacio_titulo')"
                        :detail="__('proveedores.pilotos.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 1.4fr) minmax(0, 1.2fr) minmax(0, 1fr) minmax(0, 0.8fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('proveedores.pilotos.col_piloto') }}</span>
                        <span role="columnheader">{{ __('proveedores.pilotos.col_proveedor') }}</span>
                        <span role="columnheader">{{ __('proveedores.pilotos.col_licencia') }}</span>
                        <span role="columnheader">{{ __('proveedores.pilotos.col_estado') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($pilotos as $fila)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($pilotos->currentPage() - 1) * $pilotos->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell">
                                <span class="ag-proveedores__nombre">{{ $nombres[$fila->usuario_id]->nombre ?? "#{$fila->usuario_id}" }}</span>
                                <span class="ag-proveedores__sub">{{ $fila->categoria_habilitacion }}</span>
                            </span>
                            <span role="cell">{{ $proveedores[$fila->proveedor_id] ?? "#{$fila->proveedor_id}" }}</span>
                            <span role="cell">
                                {{ $fila->licencia_numero }}
                                <span class="ag-proveedores__sub">{{ __('proveedores.pilotos.licencia_vence', ['fecha' => $fila->licencia_vence->format('d/m/Y')]) }}</span>
                            </span>
                            <span role="cell">
                                <x-atoms.badge :variant="$fila->estado->tono()">{{ __('aprobacion.estado.'.$fila->estado->value) }}</x-atoms.badge>
                            </span>
                            <span role="cell" class="ag-index-table__acciones">
                                <x-organisms.row-actions>
                                    <x-atoms.button :href="route('panel.pilotos.show', $fila)" variant="info-outline" size="sm" icon="visibility">
                                        {{ $fila->estado->value === 'pendiente' ? __('proveedores.revisar') : __('proveedores.ver') }}
                                    </x-atoms.button>
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$pilotos" :aria-label="__('proveedores.pilotos.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
