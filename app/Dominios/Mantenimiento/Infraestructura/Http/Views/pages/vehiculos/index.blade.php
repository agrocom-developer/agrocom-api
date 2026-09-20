{{--
    Page: vehiculos/index (GET /panel/vehiculos, panel.vehiculos.index)
    Listado de la flota de vehículos (HU-40, tarea 50): arquetipo Listado,
    §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera → toolbar → tabla →
    paginación. Homogeneizado con el patrón de Propiedades y Drones (tarea
    115): base y estado en `organisms/filter-panel` junto al buscador, tabla en
    `molecules/index-table`, acciones en `organisms/row-actions` y la baja con
    `molecules/confirm-modal` (antes filtros viejos, tabla propia y `confirm()`
    nativo).

    El estado (activo / en taller / en pausa / de baja) es información
    operativa pero un dato descriptivo, no una máquina de estados: badge con
    tono fijo y columna, sin pasos ni acciones de estado. Sin franja de KPI: el
    caso de uso del listado no calcula ninguna cifra y, ante la duda, no se
    agrega.

    Datos esperados (ver VehiculosController::index()): la cáscara de
    CascaraPanel, más:
    - $vehiculos (LengthAwarePaginator<Vehiculo>): identificador ascendente.
    - $etiquetasBase (array<int, string>): nombre de base por id, ya resuelto
      por el controlador (Vehiculo no tiene relación Eloquent hacia PerBase,
      son de módulos distintos). Un id sin etiqueta (base borrada) cae al
      `#id` crudo.
    - $basesDisponibles (Collection<int, string>): opciones del filtro de base.
    - $tonoPorEstado (array<string, string>): tono del badge de cada estado,
      de `VehiculosController::TONO_POR_ESTADO`.
    - $estadosFiltro (list<EstadoVehiculo>): opciones del filtro de estado.
    - $filtros (array{q: string, base_id: ?int, estado: ?string}): filtros
      aplicados, para dejar los campos con su valor tras el submit.

    Gateada por `mantenimiento.vehiculo.ver`, verificado server-side en el
    controlador. Los botones "Nuevo vehículo"/"Editar"/"Eliminar" se ocultan
    con `@puede` (presentación, no autorización — el servidor revalida en
    VehiculosController).

    Estilos en resources/css/pages/vehiculos.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('mantenimiento.vehiculos.titulo')" :tema="$tema">
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
        :vista-actual="__('mantenimiento.vehiculos.titulo')"
    >
        <div class="ag-vehiculos">
            <x-organisms.page-header
                :title="__('mantenimiento.vehiculos.titulo')"
                :subtitle="__('mantenimiento.vehiculos.subtitulo')"
            >
                @puede('mantenimiento.vehiculo.crear')
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.vehiculos.create')" variant="primary" icon="add">
                            {{ __('mantenimiento.vehiculos.nuevo') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
                $filtrosPanelActivos = collect(['base_id', 'estado'])
                    ->filter(fn ($campo) => $filtros[$campo] !== null && $filtros[$campo] !== '')
                    ->count();
            @endphp

            @if ($hayFiltrosActivos || $vehiculos->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.vehiculos.index')"
                        :active-count="$filtrosPanelActivos"
                    >
                        <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                        <x-atoms.select
                            name="base_id"
                            id="filtro-base"
                            :label="__('mantenimiento.vehiculos.filtro_base')"
                            :options="$basesDisponibles"
                            :value="$filtros['base_id']"
                            :placeholder="__('mantenimiento.vehiculos.filtro_todos')"
                        />

                        @php
                            $opcionesEstado = collect($estadosFiltro)->mapWithKeys(fn ($opcion) => [
                                $opcion->value => __('mantenimiento.estado.'.$opcion->value),
                            ])->all();
                        @endphp
                        <x-atoms.select
                            name="estado"
                            id="filtro-estado"
                            :label="__('mantenimiento.vehiculos.filtro_estado')"
                            :options="$opcionesEstado"
                            :value="$filtros['estado']"
                            :placeholder="__('mantenimiento.vehiculos.filtro_todos')"
                        />
                    </x-organisms.filter-panel>

                    <x-molecules.table-search
                        :action="route('panel.vehiculos.index')"
                        :value="$filtros['q']"
                        :placeholder="__('mantenimiento.vehiculos.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($vehiculos->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('mantenimiento.vehiculos.filtro_vacio_titulo')"
                        :detail="__('mantenimiento.vehiculos.filtro_vacio_detalle')"
                    />
                @else
                    {{-- Sin botón adentro: el vacío de un listado solo explica. El
                         alta ya está en la cabecera, y es el único botón sólido. --}}
                    <x-molecules.empty-state
                        icon="local_shipping"
                        :title="__('mantenimiento.vehiculos.vacio_titulo')"
                        :detail="__('mantenimiento.vehiculos.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 1.4fr) minmax(0, 1.2fr) minmax(0, 1fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.vehiculos.col_identificador') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.vehiculos.col_base') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.vehiculos.col_estado') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($vehiculos as $vehiculo)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($vehiculos->currentPage() - 1) * $vehiculos->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-vehiculos__identificador">{{ $vehiculo->identificador }}</span>
                            <span role="cell">
                                {{ $vehiculo->base_id !== null ? ($etiquetasBase[$vehiculo->base_id] ?? "#{$vehiculo->base_id}") : __('mantenimiento.vehiculos.sin_base') }}
                            </span>
                            <span role="cell">
                                <x-atoms.badge :variant="$tonoPorEstado[$vehiculo->estado] ?? 'neutral'">
                                    {{ __('mantenimiento.estado.'.$vehiculo->estado) }}
                                </x-atoms.badge>
                            </span>

                            <span role="cell" class="ag-index-table__acciones">
                                @php
                                    $formIdEliminar = "vehiculo-eliminar-{$vehiculo->id}";
                                    $modalIdEliminar = "vehiculo-eliminar-modal-{$vehiculo->id}";
                                @endphp

                                {{-- Form y modal FUERA de row-actions a propósito: ese organism
                                     repite su slot dos veces (visible/menú, ver su docblock), así que
                                     un <form> o un modal con id ahí adentro se duplicaría — y el que
                                     cae dentro del menú ⋮ queda oculto con él y nunca abre. El
                                     disparador vive adentro (es un botón sin id propio, se duplica sin
                                     problema); el modal y el form, una sola vez, acá. Mismo criterio
                                     que campanias/index, bases/index y drones/index. --}}
                                @puede('mantenimiento.vehiculo.eliminar')
                                    <form id="{{ $formIdEliminar }}" method="POST" action="{{ route('panel.vehiculos.destroy', $vehiculo) }}">
                                        @csrf
                                        @method('DELETE')
                                    </form>

                                    <x-molecules.confirm-modal
                                        :id="$modalIdEliminar"
                                        :form-id="$formIdEliminar"
                                        :title="__('mantenimiento.vehiculos.confirmar_eliminar_titulo')"
                                        :message="__('mantenimiento.vehiculos.confirmar_baja')"
                                        :confirm-label="__('mantenimiento.vehiculos.eliminar_accion')"
                                    />
                                @endpuede

                                <x-organisms.row-actions>
                                    @puede('mantenimiento.vehiculo.editar')
                                        <x-atoms.button :href="route('panel.vehiculos.edit', $vehiculo)" variant="warning-outline" size="sm" icon="edit">
                                            {{ __('mantenimiento.vehiculos.editar') }}
                                        </x-atoms.button>
                                    @endpuede

                                    @puede('mantenimiento.vehiculo.eliminar')
                                        <x-atoms.button
                                            type="button"
                                            data-bs-toggle="modal"
                                            data-bs-target="#{{ $modalIdEliminar }}"
                                            variant="danger-outline"
                                            size="sm"
                                            icon="delete"
                                        >
                                            {{ __('mantenimiento.vehiculos.eliminar_accion') }}
                                        </x-atoms.button>
                                    @endpuede
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$vehiculos" :aria-label="__('mantenimiento.vehiculos.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
