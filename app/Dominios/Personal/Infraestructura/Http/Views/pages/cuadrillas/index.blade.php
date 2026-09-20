{{--
    Page: cuadrillas/index (GET /panel/cuadrillas, panel.cuadrillas.index)
    Listado de cuadrillas (tarea "cuadrillas-estadias", 19/9/2026): arquetipo
    Listado, §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera → filtros →
    tabla → paginación.

    Datos esperados (ver CuadrillasController::index()): la cáscara de
    CascaraPanel, más:
    - $equipos (LengthAwarePaginator<EquipoTrabajo>, con `base` precargada):
      sin orden específica (la BD los devuelve por id).
    - $etiquetasBase (array<int, string>): base_id => nombre, para las filas
      de la tabla (sin N+1, resolto en el controlador).
    - $basesDisponibles (Collection<int, string>): id => nombre, para el
      select del filtro por base.
    - $filtros (array{q: string, base_id: int|null, estado: string|null}):
      filtros aplicados.
    - $estadosFiltro (list<EstadoEquipoTrabajo>): opciones del select de
      estado (enum cases).
    - $tonoPorEstado (array<string, string>): estado → tono del badge
      (mismo mapa que los pasos de la ficha de edición, invariante de
      diseño).
    - $integrantesPorEquipo (array<int, array{piloto: string|null, ayudantes:
      list<string>}>): equipo_id → resumen de integrantes vigentes HOY
      (resuelto sin N+1 en el controlador).
    - $dronPorEquipo (array<int, string>): equipo_id → etiqueta del dron
      vigente HOY, ya resuelto.
    - $puedeCrear/puedeEditar/puedeEliminar (bool).

    Gateada por `personal.equipo_trabajo.ver`, verificado server-side en el
    controlador. El botón "Nueva cuadrilla" y las acciones de fila se ocultan
    con `@puede` (presentación, no autorización — el servidor revalida).

    Estilos en resources/css/pages/cuadrillas.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('personal.equipos_trabajo.titulo')" :tema="$tema">
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
        :vista-actual="__('personal.equipos_trabajo.titulo')"
    >
        <div class="ag-cuadrillas">
            <x-organisms.page-header
                :title="__('personal.equipos_trabajo.titulo')"
                :subtitle="__('personal.equipos_trabajo.subtitulo')"
            >
                @puede('personal.equipo_trabajo.crear')
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.cuadrillas.create')" variant="primary" icon="add">
                            {{ __('personal.equipos_trabajo.nuevo') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-cuadrillas__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($errors->has('equipo_trabajo'))
                <x-molecules.alert-strip variant="danger" icon="error" class="ag-cuadrillas__aviso">
                    {{ $errors->first('equipo_trabajo') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
                $filtrosPanelActivos = collect(['base_id', 'estado'])
                    ->filter(fn ($campo) => $filtros[$campo] !== null && $filtros[$campo] !== '')
                    ->count();
            @endphp

            @if ($hayFiltrosActivos || $equipos->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.cuadrillas.index')"
                        :active-count="$filtrosPanelActivos"
                    >
                        <input type="hidden" name="q" value="{{ $filtros['q'] }}">

                        <x-atoms.select
                            name="base_id"
                            id="filtro-base"
                            :label="__('personal.equipos_trabajo.filtro_base')"
                            :options="$basesDisponibles"
                            :value="$filtros['base_id']"
                            :placeholder="__('personal.equipos_trabajo.filtro_todos')"
                        />

                        <x-atoms.select
                            name="estado"
                            id="filtro-estado"
                            :label="__('personal.equipos_trabajo.filtro_estado')"
                            :options="collect($estadosFiltro)->mapWithKeys(fn ($estado) => [$estado->value => __('personal.equipos_trabajo.estado.'.$estado->value)])"
                            :value="$filtros['estado']"
                            :placeholder="__('personal.equipos_trabajo.filtro_todos')"
                        />
                    </x-organisms.filter-panel>

                    <x-molecules.table-search
                        :action="route('panel.cuadrillas.index')"
                        :value="$filtros['q']"
                        :placeholder="__('personal.equipos_trabajo.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($equipos->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('personal.equipos_trabajo.filtro_vacio_titulo')"
                        :detail="__('personal.equipos_trabajo.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="groups"
                        :title="__('personal.equipos_trabajo.vacio_titulo')"
                        :detail="__('personal.equipos_trabajo.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 0.8fr) minmax(0, 1.4fr) minmax(0, 2fr) minmax(0, 1.2fr) minmax(0, 1.2fr) minmax(0, 1.4fr) minmax(0, 0.9fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('personal.equipos_trabajo.col_codigo') }}</span>
                        <span role="columnheader">{{ __('personal.equipos_trabajo.col_nombre') }}</span>
                        <span role="columnheader">{{ __('personal.equipos_trabajo.col_integrantes') }}</span>
                        <span role="columnheader">{{ __('personal.equipos_trabajo.col_dron') }}</span>
                        <span role="columnheader">{{ __('personal.equipos_trabajo.col_base') }}</span>
                        <span role="columnheader">{{ __('personal.equipos_trabajo.col_vigencia') }}</span>
                        <span role="columnheader">{{ __('personal.equipos_trabajo.col_estado') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($equipos as $equipo)
                        @php
                            $integrantesFila = $integrantesPorEquipo[$equipo->id] ?? ['piloto' => null, 'ayudantes' => []];
                            $dronFila = $dronPorEquipo[$equipo->id] ?? null;
                        @endphp
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($equipos->currentPage() - 1) * $equipos->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-cuadrillas__mono">{{ $equipo->codigo }}</span>
                            <span role="cell">{{ $equipo->nombre ?? __('personal.equipos_trabajo.sin_nombre') }}</span>
                            <span role="cell" class="ag-cuadrillas__integrantes">
                                @if ($integrantesFila['piloto'] === null && $integrantesFila['ayudantes'] === [])
                                    <span class="ag-cuadrillas__atenuado">{{ __('personal.equipos_trabajo.sin_integrantes') }}</span>
                                @else
                                    <span>{{ $integrantesFila['piloto'] ?? __('personal.equipos_trabajo.sin_piloto') }}</span>
                                    @if ($integrantesFila['ayudantes'] !== [])
                                        <span class="ag-cuadrillas__atenuado">{{ implode(' · ', $integrantesFila['ayudantes']) }}</span>
                                    @endif
                                @endif
                            </span>
                            <span role="cell" @class(['ag-cuadrillas__mono', 'ag-cuadrillas__atenuado' => $dronFila === null])>
                                {{ $dronFila ?? __('personal.equipos_trabajo.sin_dron') }}
                            </span>
                            <span role="cell">{{ $etiquetasBase[$equipo->base_id] ?? '—' }}</span>
                            <span role="cell" class="ag-cuadrillas__mono">
                                {{ $equipo->desde->format('d/m/Y') }} – {{ $equipo->hasta?->format('d/m/Y') ?? __('personal.equipos_trabajo.vigente') }}
                            </span>
                            <span role="cell">
                                <x-atoms.badge :variant="$tonoPorEstado[$equipo->estado->value] ?? 'neutral'">
                                    {{ __('personal.equipos_trabajo.estado.'.$equipo->estado->value) }}
                                </x-atoms.badge>
                            </span>

                            <span role="cell" class="ag-index-table__acciones">
                                {{-- Form FUERA de row-actions a propósito: ese organism repite su
                                     slot dos veces (visible/menú) — un <form> con id ahí adentro se
                                     duplicaría. El botón de confirm-button lo envía por su `form`. --}}
                                @if ($puedeEliminar)
                                    <form id="cuadrilla-eliminar-{{ $equipo->id }}" method="POST" action="{{ route('panel.cuadrillas.destroy', $equipo) }}">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                @endif

                                <x-organisms.row-actions>
                                    <x-atoms.button :href="route('panel.cuadrillas.show', $equipo)" variant="outline" size="sm" icon="visibility">
                                        {{ __('personal.equipos_trabajo.ver') }}
                                    </x-atoms.button>

                                    @if ($puedeEditar)
                                        <x-atoms.button :href="route('panel.cuadrillas.edit', $equipo)" variant="warning-outline" size="sm" icon="edit">
                                            {{ __('personal.equipos_trabajo.editar') }}
                                        </x-atoms.button>
                                    @endif

                                    @if ($puedeEliminar)
                                        <span class="ag-row-actions__item">
                                            <x-molecules.confirm-button
                                                :form-id="'cuadrilla-eliminar-'.$equipo->id"
                                                :title="__('personal.equipos_trabajo.confirmar_eliminar_titulo')"
                                                :message="__('personal.equipos_trabajo.confirmar_baja')"
                                                :confirm-label="__('personal.equipos_trabajo.eliminar_accion')"
                                                variant="danger-outline"
                                                size="sm"
                                                icon="delete"
                                            >
                                                {{ __('personal.equipos_trabajo.eliminar_accion') }}
                                            </x-molecules.confirm-button>
                                        </span>
                                    @endif
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$equipos" :aria-label="__('personal.equipos_trabajo.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
