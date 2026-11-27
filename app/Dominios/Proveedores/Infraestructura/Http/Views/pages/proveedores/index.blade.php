{{--
    Page: proveedores/index (GET /panel/proveedores, panel.proveedores.index)
    Cola de proveedores del marketplace (ADR 0028 y 0032): arquetipo Listado,
    §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera → toolbar → tabla →
    paginación. Los pendientes de revisión van primero, del más antiguo al más
    nuevo. Las acciones de fila son Ver y las
    transiciones de la máquina de aprobación que el estado y el permiso admiten
    (aprobar, rechazar, suspender, rehabilitar, eliminar), en el orden fijo de
    `organisms/row-actions`; la ficha (`show`) sigue mostrando documentos y gente.

    Datos esperados (ver ProveedoresController::index()): la cáscara de
    CascaraPanel, más:
    - $proveedores (LengthAwarePaginator<Proveedor>).
    - $titulares (array<int, DatosCuenta>): cuenta titular por id.
    - $filtros (array{q: string, estado: ?string, tipo: ?string}).
    - $estadosFiltro (list<EstadoAprobacion>), $tiposFiltro (list<TipoProveedor>).
    - $accionesPorEstado (array<string, list<AccionAprobacion>>): acciones de fila por estado.

    Gateada por `proveedores.proveedor.ver`, verificado server-side en el
    controlador. Estilos en resources/css/pages/proveedores.css — cero color
    hardcodeado (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('proveedores.proveedores.titulo')" :tema="$tema">
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
        :vista-actual="__('proveedores.proveedores.titulo')"
    >
        <div class="ag-proveedores">
            <x-organisms.page-header
                :title="__('proveedores.proveedores.titulo')"
                :subtitle="__('proveedores.proveedores.subtitulo')"
            >
                @if ($puedeCrear)
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.proveedores.create')" variant="primary" icon="add">{{ __('proveedores.alta_manual.registrar_proveedor') }}</x-atoms.button>
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
                $filtrosPanelActivos = collect(['estado', 'tipo'])
                    ->filter(fn ($campo) => $filtros[$campo] !== null && $filtros[$campo] !== '')
                    ->count();
                $opcionesEstado = collect($estadosFiltro)->mapWithKeys(fn ($opcion) => [$opcion->value => __('aprobacion.estado.'.$opcion->value)])->all();
                $opcionesTipo = collect($tiposFiltro)->mapWithKeys(fn ($opcion) => [$opcion->value => __('proveedores.tipo.'.$opcion->value)])->all();
            @endphp

            @if ($hayFiltrosActivos || $proveedores->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.proveedores.index')"
                        :active-count="$filtrosPanelActivos"
                    >
                        <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                        <x-atoms.select
                            name="estado"
                            id="filtro-estado"
                            :label="__('proveedores.proveedores.filtro_estado')"
                            :options="$opcionesEstado"
                            :value="$filtros['estado']"
                            :placeholder="__('proveedores.filtro_todos')"
                        />
                        <x-atoms.select
                            name="tipo"
                            id="filtro-tipo"
                            :label="__('proveedores.proveedores.filtro_tipo')"
                            :options="$opcionesTipo"
                            :value="$filtros['tipo']"
                            :placeholder="__('proveedores.filtro_todos')"
                        />
                    </x-organisms.filter-panel>

                    <x-molecules.table-search
                        :action="route('panel.proveedores.index')"
                        :value="$filtros['q']"
                        :placeholder="__('proveedores.proveedores.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($proveedores->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('proveedores.proveedores.filtro_vacio_titulo')"
                        :detail="__('proveedores.proveedores.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="local_shipping"
                        :title="__('proveedores.proveedores.vacio_titulo')"
                        :detail="__('proveedores.proveedores.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 1.6fr) minmax(0, 0.9fr) minmax(0, 0.9fr) minmax(0, 0.8fr) minmax(0, 0.8fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('proveedores.proveedores.col_proveedor') }}</span>
                        <span role="columnheader">{{ __('proveedores.proveedores.col_tipo') }}</span>
                        <span role="columnheader">{{ __('proveedores.proveedores.col_estado') }}</span>
                        <span role="columnheader">{{ __('proveedores.proveedores.col_calificacion') }}</span>
                        <span role="columnheader">{{ __('proveedores.proveedores.col_alta') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($proveedores as $proveedor)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($proveedores->currentPage() - 1) * $proveedores->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell">
                                <span class="ag-proveedores__nombre">{{ $proveedor->razon_social }}</span>
                                <span class="ag-proveedores__sub">
                                    {{ $titulares[$proveedor->usuario_titular_id]->nombre ?? __('proveedores.proveedores.sin_titular') }}
                                    @if ($proveedor->documento_fiscal)
                                        · {{ $proveedor->documento_fiscal }}
                                    @endif
                                </span>
                            </span>
                            <span role="cell">
                                <x-atoms.badge :variant="$proveedor->tipo->value === 'propio' ? 'primary-2' : 'neutral'">
                                    {{ __('proveedores.tipo.'.$proveedor->tipo->value) }}
                                </x-atoms.badge>
                            </span>
                            <span role="cell">
                                <x-atoms.badge :variant="$proveedor->estado->tono()">{{ __('aprobacion.estado.'.$proveedor->estado->value) }}</x-atoms.badge>
                            </span>
                            <span role="cell" class="ag-proveedores__cifra">
                                @if ($proveedor->calificaciones_conteo > 0)
                                    {{ $proveedor->calificacion_promedio }} ({{ $proveedor->calificaciones_conteo }})
                                @else
                                    {{ __('proveedores.proveedores.sin_calificaciones') }}
                                @endif
                            </span>
                            <span role="cell">{{ $proveedor->created_at->format('d/m/Y') }}</span>
                            <span role="cell" class="ag-index-table__acciones">
                                <x-organisms.row-actions-aprobacion
                                    :acciones="$accionesPorEstado[$proveedor->estado->value] ?? []"
                                    :estado="$proveedor->estado"
                                    :nombre="$proveedor->razon_social"
                                    :url-resolver="route('panel.proveedores.resolver', $proveedor->id)"
                                    :modal-base="'proveedor-'.$proveedor->id"
                                    :href-ver="route('panel.proveedores.show', $proveedor)"
                                    :label-ver="__('proveedores.ver')"
                                />
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$proveedores" :aria-label="__('proveedores.proveedores.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
