{{--
    Page: planes/index (GET /panel/planes-mantenimiento, panel.planes-mantenimiento.index)
    Listado de planes de mantenimiento preventivo (HU-38, tarea 54):
    arquetipo Listado, §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera
    → toolbar → tabla → paginación. Homogeneizado en la tarea 116 con el
    patrón de Vehículos: `index-table`, `row-actions`, `confirm-modal` para la
    baja (antes era el `confirm()` del navegador) y `pagination`.

    Solo buscador, sin `filter-panel`: las dos dimensiones del plan son texto
    (modelo y tarea) y la tercera —la alerta— no es una columna de la base
    sino un cálculo por fila contra las horas de vuelo, así que no se puede
    filtrar por ella en la consulta. Un catálogo tampoco muestra
    activo/inactivo (guía §6.2).

    El plan NO tiene máquina de estados: la alerta es un dato calculado, no un
    estado, así que no lleva pasos ni acciones de transición.

    Datos esperados (ver PlanesMantenimientoController::index()): la cáscara
    de CascaraPanel, más:
    - $planes (LengthAwarePaginator<PlanMantenimiento>): modelo ascendente,
      luego umbral ascendente. Cada fila ya trae `alerta` (bool) calculada
      por ListarPlanesMantenimiento — la vista no evalúa ningún umbral ni
      consulta horas de vuelo.
    - $filtros (array{q: string}): búsqueda aplicada, para dejar el campo con
      su valor tras el submit.

    Gateada por `mantenimiento.plan.ver`, verificado server-side en el
    controlador. Los botones "Nuevo plan"/"Editar"/"Eliminar" se ocultan con
    `@puede` (presentación, no autorización — el servidor revalida en
    PlanesMantenimientoController).

    Estilos en resources/css/pages/planes.css — cero color hardcodeado
    (CLAUDE.md invariante 11). El badge de alerta usa "warning" (ámbar),
    mismo criterio que la columna de alerta de baterias/index.blade.php.
--}}
<x-templates.panel-shell :title="__('mantenimiento.planes.titulo')" :tema="$tema">
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
        :vista-actual="__('mantenimiento.planes.titulo')"
    >
        <div class="ag-planes">
            <x-organisms.page-header
                :title="__('mantenimiento.planes.titulo')"
                :subtitle="__('mantenimiento.planes.subtitulo')"
            >
                @puede('mantenimiento.plan.crear')
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.planes-mantenimiento.create')" variant="primary" icon="add">
                            {{ __('mantenimiento.planes.nuevo') }}
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
                $hayBusqueda = $filtros['q'] !== '';
            @endphp

            @if ($hayBusqueda || $planes->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-molecules.table-search
                        :action="route('panel.planes-mantenimiento.index')"
                        :value="$filtros['q']"
                        :placeholder="__('mantenimiento.planes.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($planes->isEmpty())
                @if ($hayBusqueda)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('mantenimiento.planes.filtro_vacio_titulo')"
                        :detail="__('mantenimiento.planes.filtro_vacio_detalle')"
                    />
                @else
                    {{-- Sin botón adentro: el vacío de un listado solo explica. El
                         alta ya está en la cabecera, y es el único botón sólido. --}}
                    <x-molecules.empty-state
                        icon="checklist"
                        :title="__('mantenimiento.planes.vacio_titulo')"
                        :detail="__('mantenimiento.planes.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 1.2fr) minmax(0, 1.8fr) minmax(0, 1fr) minmax(0, 1fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.planes.col_modelo') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.planes.col_tarea') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.planes.col_horas_umbral') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.planes.col_alerta') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($planes as $plan)
                        @php
                            $formIdEliminar = "plan-eliminar-{$plan->id}";
                            $modalIdEliminar = "plan-eliminar-modal-{$plan->id}";
                        @endphp

                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($planes->currentPage() - 1) * $planes->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-planes__modelo">{{ $plan->modelo }}</span>
                            <span role="cell">{{ $plan->tarea }}</span>
                            <span role="cell" class="ag-planes__umbral">{{ $plan->horas_umbral }}</span>
                            <span role="cell">
                                @if ($plan->alerta)
                                    <x-atoms.badge variant="warning" icon="warning" :title="__('mantenimiento.planes.alerta_titulo')">
                                        {{ __('mantenimiento.planes.alerta_activa') }}
                                    </x-atoms.badge>
                                @else
                                    {{ __('mantenimiento.planes.sin_alerta') }}
                                @endif
                            </span>

                            <span role="cell" class="ag-index-table__acciones">
                                {{-- Form y modal FUERA de row-actions a propósito: ese organism
                                     repite su slot dos veces (visible/menú, ver su docblock),
                                     así que un <form> o un modal con id ahí adentro se
                                     duplicaría — y el que cae dentro del menú ⋮ queda oculto
                                     con él y nunca abre. El disparador sí va adentro (es un
                                     botón sin id propio). Mismo criterio que vehiculos/index. --}}
                                @puede('mantenimiento.plan.eliminar')
                                    <form id="{{ $formIdEliminar }}" method="POST" action="{{ route('panel.planes-mantenimiento.destroy', $plan) }}">
                                        @csrf
                                        @method('DELETE')
                                    </form>

                                    <x-molecules.confirm-modal
                                        :id="$modalIdEliminar"
                                        :form-id="$formIdEliminar"
                                        :title="__('mantenimiento.planes.confirmar_baja_titulo')"
                                        :message="__('mantenimiento.planes.confirmar_baja')"
                                        :confirm-label="__('mantenimiento.planes.eliminar_accion')"
                                    />
                                @endpuede

                                <x-organisms.row-actions>
                                    @puede('mantenimiento.plan.editar')
                                        <x-atoms.button :href="route('panel.planes-mantenimiento.edit', $plan)" variant="warning-outline" size="sm" icon="edit">
                                            {{ __('mantenimiento.planes.editar') }}
                                        </x-atoms.button>
                                    @endpuede

                                    @puede('mantenimiento.plan.eliminar')
                                        <x-atoms.button
                                            type="button"
                                            data-bs-toggle="modal"
                                            :data-bs-target="'#'.$modalIdEliminar"
                                            variant="danger-outline"
                                            size="sm"
                                            icon="delete"
                                        >
                                            {{ __('mantenimiento.planes.eliminar_accion') }}
                                        </x-atoms.button>
                                    @endpuede
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$planes" :aria-label="__('mantenimiento.planes.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
