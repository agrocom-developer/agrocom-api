{{--
    Page: drones/index (GET /panel/drones, panel.drones.index)
    Listado de la flota de drones (HU-27, tarea 36): arquetipo Listado, §6.2
    de docs/diseno/guia_pantalla_panel.md — cabecera → toolbar → tabla →
    paginación. Homogeneizado con el patrón de Clientes y Bases (tarea 113):
    tabla en `molecules/index-table`, acciones en `organisms/row-actions` y la
    baja con `molecules/confirm-modal`. Un dron es catálogo simple
    (identificador, modelo, capacidad): sin filtros más que el buscador, así
    que no lleva `filter-panel` (mismo criterio que Campañas y Bases), y sin
    columna de estado ni KPI — el caso de uso del listado no calcula
    ninguna cifra.

    Columna "Capacidad de carga" (HU-81, tarea 96): un dron puede tener
    litros, kilos, ambos o ninguno — se muestran las dos cifras que existan
    separadas por "·", sin agregar columna nueva.

    Datos esperados (ver DronesController::index()): la cáscara de
    CascaraPanel, más:
    - $drones (LengthAwarePaginator<Dron>): identificador ascendente.
    - $filtros (array{q: string}): búsqueda aplicada, para dejar el campo
      con el valor tras el submit.

    Gateada por `operaciones.dron.ver`, verificado server-side en el
    controlador. Los botones "Nuevo dron"/"Editar"/"Eliminar" se ocultan con
    `@puede` (presentación, no autorización — el servidor revalida en
    DronesController).

    Estilos en resources/css/pages/drones.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('operaciones.drones.titulo')" :tema="$tema">
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
        :vista-actual="__('operaciones.drones.titulo')"
    >
        <div class="ag-drones">
            <x-organisms.page-header
                :title="__('operaciones.drones.titulo')"
                :subtitle="__('operaciones.drones.subtitulo')"
            >
                @puede('operaciones.dron.crear')
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.drones.create')" variant="primary" icon="add">
                            {{ __('operaciones.drones.nuevo') }}
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
            @endphp

            @if ($hayFiltrosActivos || $drones->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-molecules.table-search
                        :action="route('panel.drones.index')"
                        :value="$filtros['q']"
                        :placeholder="__('operaciones.drones.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($drones->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('operaciones.drones.filtro_vacio_titulo')"
                        :detail="__('operaciones.drones.filtro_vacio_detalle')"
                    />
                @else
                    {{-- Sin botón adentro: el vacío de un listado solo explica. El
                         alta ya está en la cabecera, y es el único botón sólido. --}}
                    <x-molecules.empty-state
                        icon="airplanemode_active"
                        :title="__('operaciones.drones.vacio_titulo')"
                        :detail="__('operaciones.drones.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 1.5fr) minmax(0, 1.5fr) minmax(0, 1fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('operaciones.drones.col_identificador') }}</span>
                        <span role="columnheader">{{ __('operaciones.drones.col_modelo') }}</span>
                        <span role="columnheader">{{ __('operaciones.drones.col_capacidad') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($drones as $dron)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($drones->currentPage() - 1) * $drones->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-drones__identificador">{{ $dron->identificador }}</span>
                            <span role="cell">{{ $dron->modelo ?? __('operaciones.drones.sin_modelo') }}</span>
                            <span role="cell" class="ag-drones__capacidad">
                                @php
                                    $capacidades = array_filter([
                                        $dron->capacidad_l !== null ? __('operaciones.drones.capacidad_valor', ['cantidad' => (int) $dron->capacidad_l]) : null,
                                        $dron->capacidad_kg !== null ? __('operaciones.drones.capacidad_kg_valor', ['cantidad' => number_format((float) $dron->capacidad_kg, 2, ',', '.')]) : null,
                                    ]);
                                @endphp
                                {{ $capacidades !== [] ? implode(' · ', $capacidades) : __('operaciones.drones.sin_capacidad') }}
                            </span>

                            <span role="cell" class="ag-index-table__acciones">
                                @php
                                    $formIdEliminar = "dron-eliminar-{$dron->id}";
                                    $modalIdEliminar = "dron-eliminar-modal-{$dron->id}";
                                @endphp

                                {{-- Form y modal FUERA de row-actions a propósito: ese organism
                                     repite su slot dos veces (visible/menú, ver su docblock), así que
                                     un <form> o un modal con id ahí adentro se duplicaría — y el que
                                     cae dentro del menú ⋮ queda oculto con él y nunca abre. El
                                     disparador vive adentro (es un botón sin id propio, se duplica sin
                                     problema); el modal y el form, una sola vez, acá. Mismo criterio
                                     que campanias/index y bases/index. --}}
                                @puede('operaciones.dron.eliminar')
                                    <form id="{{ $formIdEliminar }}" method="POST" action="{{ route('panel.drones.destroy', $dron) }}">
                                        @csrf
                                        @method('DELETE')
                                    </form>

                                    <x-molecules.confirm-modal
                                        :id="$modalIdEliminar"
                                        :form-id="$formIdEliminar"
                                        :title="__('operaciones.drones.confirmar_eliminar_titulo')"
                                        :message="__('operaciones.drones.confirmar_baja')"
                                        :confirm-label="__('operaciones.drones.eliminar_accion')"
                                    />
                                @endpuede

                                <x-organisms.row-actions>
                                    @puede('operaciones.dron.editar')
                                        <x-atoms.button :href="route('panel.drones.edit', $dron)" variant="warning-outline" size="sm" icon="edit">
                                            {{ __('operaciones.drones.editar') }}
                                        </x-atoms.button>
                                    @endpuede

                                    @puede('operaciones.dron.eliminar')
                                        <x-atoms.button
                                            type="button"
                                            data-bs-toggle="modal"
                                            data-bs-target="#{{ $modalIdEliminar }}"
                                            variant="danger-outline"
                                            size="sm"
                                            icon="delete"
                                        >
                                            {{ __('operaciones.drones.eliminar_accion') }}
                                        </x-atoms.button>
                                    @endpuede
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$drones" :aria-label="__('operaciones.drones.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
