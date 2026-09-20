{{--
    Page: baterias/index (GET /panel/baterias, panel.baterias.index)
    Listado del catálogo de baterías (HU-39, tarea 51): arquetipo Listado,
    §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera → toolbar → tabla →
    paginación. Homogeneizado con el patrón de Propiedades y Drones (tarea
    115): base y estado en `organisms/filter-panel` junto al buscador, tabla en
    `molecules/index-table`, acciones en `organisms/row-actions` y la baja con
    `molecules/confirm-modal` (antes filtros viejos, tabla propia y confirmación
    nativa del navegador).

    El estado es un dato descriptivo (activa / retirada / en mantenimiento), no
    una máquina de estados: badge con tono fijo y columna, sin pasos ni
    acciones de estado. Sin franja de KPI: la alerta de retiro depende de una
    lectura por fila hacia Operaciones (temperatura de la última recarga), y
    contarla para todo el filtro sería una consulta por batería.

    Los ciclos son un contador que sube solo con cada recarga registrada: van
    en mono y de solo lectura en el listado.

    Datos esperados (ver BateriasController::index()): la cáscara de
    CascaraPanel, más:
    - $baterias (LengthAwarePaginator<Bateria>): identificador ascendente.
      Cada fila ya trae `alerta` (bool) y `alerta_motivo` (string|null)
      calculados por ListarBaterias — la vista no evalúa ningún umbral.
    - $etiquetasBase (array<int, string>): nombre de base por id, ya resuelto
      por el controlador (Bateria no tiene relación Eloquent hacia PerBase,
      son de módulos distintos). Un id sin etiqueta (base borrada) cae al
      `#id` crudo.
    - $basesDisponibles (Collection<int, string>): opciones del filtro de base.
    - $tonoPorEstado (array<string, string>): tono del badge de cada estado,
      de `BateriasController::TONO_POR_ESTADO`.
    - $estadosFiltro (list<EstadoBateria>): opciones del filtro de estado.
    - $filtros (array{q: string, base_id: ?int, estado: ?string}): filtros
      aplicados, para dejar los campos con su valor tras el submit.

    Gateada por `mantenimiento.bateria.ver`, verificado server-side en el
    controlador. Los botones "Nueva batería"/"Editar"/"Eliminar" se ocultan
    con `@puede` (presentación, no autorización — el servidor revalida en
    BateriasController).

    Estilos en resources/css/pages/baterias.css — cero color hardcodeado
    (CLAUDE.md invariante 11). El badge de alerta usa "warning" (ámbar),
    reservado a esa columna: el estado nunca lo usa (sistema_diseno_panel.md
    §8).
--}}
<x-templates.panel-shell :title="__('mantenimiento.baterias.titulo')" :tema="$tema">
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
        :vista-actual="__('mantenimiento.baterias.titulo')"
    >
        <div class="ag-baterias">
            <x-organisms.page-header
                :title="__('mantenimiento.baterias.titulo')"
                :subtitle="__('mantenimiento.baterias.subtitulo')"
            >
                @puede('mantenimiento.bateria.crear')
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.baterias.create')" variant="primary" icon="add">
                            {{ __('mantenimiento.baterias.nuevo') }}
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

            @if ($hayFiltrosActivos || $baterias->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.baterias.index')"
                        :active-count="$filtrosPanelActivos"
                    >
                        <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                        <x-atoms.select
                            name="base_id"
                            id="filtro-base"
                            :label="__('mantenimiento.baterias.filtro_base')"
                            :options="$basesDisponibles"
                            :value="$filtros['base_id']"
                            :placeholder="__('mantenimiento.baterias.filtro_todos')"
                        />

                        @php
                            $opcionesEstado = collect($estadosFiltro)->mapWithKeys(fn ($opcion) => [
                                $opcion->value => __('mantenimiento.estado_bateria.'.$opcion->value),
                            ])->all();
                        @endphp
                        <x-atoms.select
                            name="estado"
                            id="filtro-estado"
                            :label="__('mantenimiento.baterias.filtro_estado')"
                            :options="$opcionesEstado"
                            :value="$filtros['estado']"
                            :placeholder="__('mantenimiento.baterias.filtro_todos')"
                        />
                    </x-organisms.filter-panel>

                    <x-molecules.table-search
                        :action="route('panel.baterias.index')"
                        :value="$filtros['q']"
                        :placeholder="__('mantenimiento.baterias.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($baterias->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('mantenimiento.baterias.filtro_vacio_titulo')"
                        :detail="__('mantenimiento.baterias.filtro_vacio_detalle')"
                    />
                @else
                    {{-- Sin botón adentro: el vacío de un listado solo explica. El
                         alta ya está en la cabecera, y es el único botón sólido. --}}
                    <x-molecules.empty-state
                        icon="battery_charging_full"
                        :title="__('mantenimiento.baterias.vacio_titulo')"
                        :detail="__('mantenimiento.baterias.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 1.2fr) minmax(0, 1fr) minmax(0, 0.7fr) minmax(0, 0.9fr) minmax(0, 0.9fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.baterias.col_identificador') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.baterias.col_base') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.baterias.col_ciclos') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.baterias.col_estado') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.baterias.col_alerta') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($baterias as $bateria)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($baterias->currentPage() - 1) * $baterias->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-baterias__identificador">{{ $bateria->identificador }}</span>
                            <span role="cell">
                                {{ $bateria->base_id !== null ? ($etiquetasBase[$bateria->base_id] ?? "#{$bateria->base_id}") : __('mantenimiento.baterias.sin_base') }}
                            </span>
                            <span role="cell" class="ag-baterias__ciclos">{{ $bateria->ciclos_acumulados }}</span>
                            <span role="cell">
                                <x-atoms.badge :variant="$tonoPorEstado[$bateria->estado] ?? 'neutral'">
                                    {{ __('mantenimiento.estado_bateria.'.$bateria->estado) }}
                                </x-atoms.badge>
                            </span>
                            <span role="cell">
                                @if ($bateria->alerta)
                                    <x-atoms.badge variant="warning" icon="battery_alert" :title="__('mantenimiento.baterias.alerta_motivo.'.$bateria->alerta_motivo)">
                                        {{ __('mantenimiento.baterias.alerta_activa') }}
                                    </x-atoms.badge>
                                @else
                                    {{ __('mantenimiento.baterias.sin_alerta') }}
                                @endif
                            </span>

                            <span role="cell" class="ag-index-table__acciones">
                                @php
                                    $formIdEliminar = "bateria-eliminar-{$bateria->id}";
                                    $modalIdEliminar = "bateria-eliminar-modal-{$bateria->id}";
                                @endphp

                                {{-- Form y modal FUERA de row-actions a propósito: ese organism
                                     repite su slot dos veces (visible/menú, ver su docblock), así que
                                     un <form> o un modal con id ahí adentro se duplicaría — y el que
                                     cae dentro del menú ⋮ queda oculto con él y nunca abre. El
                                     disparador vive adentro (es un botón sin id propio, se duplica sin
                                     problema); el modal y el form, una sola vez, acá. Mismo criterio
                                     que campanias/index, bases/index y drones/index. --}}
                                @puede('mantenimiento.bateria.eliminar')
                                    <form id="{{ $formIdEliminar }}" method="POST" action="{{ route('panel.baterias.destroy', $bateria) }}">
                                        @csrf
                                        @method('DELETE')
                                    </form>

                                    <x-molecules.confirm-modal
                                        :id="$modalIdEliminar"
                                        :form-id="$formIdEliminar"
                                        :title="__('mantenimiento.baterias.confirmar_eliminar_titulo')"
                                        :message="__('mantenimiento.baterias.confirmar_baja')"
                                        :confirm-label="__('mantenimiento.baterias.eliminar_accion')"
                                    />
                                @endpuede

                                <x-organisms.row-actions>
                                    @puede('mantenimiento.bateria.editar')
                                        <x-atoms.button :href="route('panel.baterias.edit', $bateria)" variant="warning-outline" size="sm" icon="edit">
                                            {{ __('mantenimiento.baterias.editar') }}
                                        </x-atoms.button>
                                    @endpuede

                                    @puede('mantenimiento.bateria.eliminar')
                                        <x-atoms.button
                                            type="button"
                                            data-bs-toggle="modal"
                                            data-bs-target="#{{ $modalIdEliminar }}"
                                            variant="danger-outline"
                                            size="sm"
                                            icon="delete"
                                        >
                                            {{ __('mantenimiento.baterias.eliminar_accion') }}
                                        </x-atoms.button>
                                    @endpuede
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$baterias" :aria-label="__('mantenimiento.baterias.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
