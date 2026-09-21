{{--
    Page: alertas/index (GET /panel/alertas, panel.alertas.index)
    Bandeja de alertas por excepción (HU-19, tarea 26): el encargado de
    operaciones ve solo lo anómalo (batería caliente, dron sospechoso,
    condiciones forzadas, suma excedida) con filtros por estado/tipo y acción
    de atender. Homogeneizada con el patrón de Estadías (tarea 113): franja
    de KPI + `filter-panel` + `index-table` + `row-actions` + `confirm-modal`.

    La alerta tiene máquina de estados (`MaquinaEstadosAlerta`), pero de un
    solo sentido: pendiente → atendida. Por eso el listado lleva badge y una
    única acción de fila con el tono del estado de llegada; no hay ficha de
    edición, así que tampoco pasos ni resumen relacionado.

    Datos esperados (ver AlertasController::index()): la cáscara de
    CascaraPanel, más:
    - $alertas (LengthAwarePaginator<Alerta>): más reciente primero.
    - $resumen (array{pendientes, atendidas}): cifras de la franja de KPI,
      dentro del mismo filtro que la tabla.
    - $filtros (array{estado: ?string, tipo: ?string}): valores actualmente
      aplicados, para dejar el panel de filtros con la selección hecha.
    - $estadosFiltro, $tiposFiltro (enum cases): opciones de los filtros.
    - $tonoPorEstado (array<string,string>): tono de cada estado, definido una
      sola vez en el controlador.
    - $puedeAtender (bool): si el rol activo tiene `operaciones.alerta.atender`
      — sin él, la fila muestra el estado sin la acción (el servidor revalida
      igual en AlertasController::atender()).

    Gateada por el permiso `operaciones.alerta.ver`, verificado server-side
    en el controlador.

    Estilos en resources/css/pages/alertas.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('operaciones.alertas.titulo')" :tema="$tema">
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
        :vista-actual="__('operaciones.alertas.titulo')"
    >
        <div class="ag-alertas">
            <x-organisms.page-header
                :title="__('operaciones.alertas.titulo')"
                :subtitle="__('operaciones.alertas.subtitulo')"
            />

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
                $filtrosActivosCount = collect($filtros)->filter(fn ($valor) => $valor !== null && $valor !== '')->count();
            @endphp

            {{-- KPI del listado: franja fija bajo la cabecera, mismo patrón que
                 Estadías. Responden al filtro aplicado (una cifra en cero va sin
                 color) y nunca van entre los filtros y la tabla. --}}
            @if ($hayFiltrosActivos || $alertas->isNotEmpty())
                <div class="ag-alertas__kpis">
                    <x-molecules.stat-card
                        :label="__('operaciones.alertas.kpi_pendientes')"
                        icon="notifications_active"
                        :value="$resumen['pendientes']"
                        :state="$resumen['pendientes'] > 0 ? $tonoPorEstado['pendiente'] : null"
                    />
                    <x-molecules.stat-card
                        :label="__('operaciones.alertas.kpi_atendidas')"
                        icon="task_alt"
                        :value="$resumen['atendidas']"
                        :state="$resumen['atendidas'] > 0 ? $tonoPorEstado['atendida'] : null"
                    />
                </div>

                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.alertas.index')"
                        :active-count="$filtrosActivosCount"
                    >
                        <x-atoms.select
                            name="estado"
                            id="filtro-estado"
                            :label="__('operaciones.alertas.filtro_estado')"
                            :options="collect($estadosFiltro)->mapWithKeys(fn ($e) => [$e->value => __('operaciones.alertas.estado.'.$e->value)])->all()"
                            :value="$filtros['estado']"
                            :placeholder="__('operaciones.alertas.filtro_todos')"
                        />

                        <x-atoms.select
                            name="tipo"
                            id="filtro-tipo"
                            :label="__('operaciones.alertas.filtro_tipo')"
                            :options="collect($tiposFiltro)->mapWithKeys(fn ($t) => [$t->value => __('operaciones.alertas.tipo.'.$t->value)])->all()"
                            :value="$filtros['tipo']"
                            :placeholder="__('operaciones.alertas.filtro_todos')"
                        />
                    </x-organisms.filter-panel>
                </div>
            @endif

            @if ($alertas->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('operaciones.alertas.filtro_vacio_titulo')"
                        :detail="__('operaciones.alertas.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="notifications_active"
                        :title="__('operaciones.alertas.vacio_titulo')"
                        :detail="__('operaciones.alertas.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(10rem, 1fr) minmax(0, 2.4fr) minmax(5rem, 0.5fr) minmax(0, 1.2fr) minmax(0, 0.9fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('operaciones.alertas.col_tipo') }}</span>
                        <span role="columnheader">{{ __('operaciones.alertas.col_mensaje') }}</span>
                        <span role="columnheader">{{ __('operaciones.alertas.col_trabajo') }}</span>
                        <span role="columnheader">{{ __('operaciones.alertas.col_estado') }}</span>
                        <span role="columnheader">{{ __('operaciones.alertas.col_fecha') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($alertas as $alerta)
                        @php
                            $estadoFila = $alerta->estado->value;
                            $pendiente = $estadoFila === 'pendiente';
                        @endphp
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($alertas->currentPage() - 1) * $alertas->perPage() + $loop->iteration }}
                            </span>

                            <span role="cell">
                                <x-atoms.badge variant="warning">
                                    {{ __("operaciones.alertas.tipo.{$alerta->tipo->value}") }}
                                </x-atoms.badge>
                            </span>

                            <span role="cell" class="ag-alertas__mensaje">{{ $alerta->mensaje }}</span>

                            <span role="cell">
                                {{ $alerta->trabajo_id !== null ? "#{$alerta->trabajo_id}" : __('operaciones.alertas.sin_trabajo') }}
                            </span>

                            <span role="cell">
                                <x-atoms.badge :variant="$tonoPorEstado[$estadoFila] ?? 'neutral'">
                                    {{ __("operaciones.alertas.estado.{$estadoFila}") }}
                                </x-atoms.badge>

                                @if (! $pendiente)
                                    <span class="ag-alertas__atendida-por">
                                        {{ __('operaciones.alertas.atendida_por', ['id' => $alerta->atendida_por, 'fecha' => $alerta->atendida_en?->format('d/m/Y H:i')]) }}
                                    </span>
                                @endif
                            </span>

                            <span role="cell" class="ag-alertas__mono">{{ $alerta->created_at?->format('d/m/Y H:i') }}</span>

                            <span role="cell" class="ag-index-table__acciones">
                                {{-- Sin acción, sin row-actions: el organism siempre pinta su «⋮»,
                                     y una fila ya atendida quedaría con un menú vacío. --}}
                                @if ($puedeAtender && $pendiente)
                                    @php
                                        $formIdAtender = "alerta-atender-{$alerta->id}";
                                        $modalIdAtender = "alerta-atender-modal-{$alerta->id}";
                                    @endphp

                                    {{-- Form y modal FUERA de row-actions a propósito: ese organism
                                         repite su slot dos veces (visible/menú, ver su docblock), así
                                         que un <form> o un modal con id ahí adentro se duplicaría — y
                                         el que cae dentro del menú ⋮ queda oculto con él y nunca abre.
                                         El disparador vive adentro; el modal y el form, una sola vez,
                                         acá. Mismo criterio que campanias/index y bases/index. --}}
                                    <form id="{{ $formIdAtender }}" method="POST" action="{{ route('panel.alertas.atender', $alerta) }}" hidden>
                                        @csrf
                                    </form>

                                    <x-molecules.confirm-modal
                                        :id="$modalIdAtender"
                                        :form-id="$formIdAtender"
                                        :title="__('operaciones.alertas.confirmar_atender_titulo')"
                                        :message="__('operaciones.alertas.confirmar_atender')"
                                        :confirm-label="__('operaciones.alertas.atender')"
                                        :tone="$tonoPorEstado['atendida']"
                                    >
                                        <x-molecules.state-transition
                                            :from-label="__('operaciones.alertas.estado.pendiente')"
                                            :from-tone="$tonoPorEstado['pendiente']"
                                            :to-label="__('operaciones.alertas.estado.atendida')"
                                            :to-tone="$tonoPorEstado['atendida']"
                                            :label="__('operaciones.alertas.estado_cambio_de_a', [
                                                'desde' => __('operaciones.alertas.estado.pendiente'),
                                                'hacia' => __('operaciones.alertas.estado.atendida'),
                                            ])"
                                        />
                                    </x-molecules.confirm-modal>

                                    <x-organisms.row-actions>
                                        <x-atoms.button
                                            type="button"
                                            data-bs-toggle="modal"
                                            data-bs-target="#{{ $modalIdAtender }}"
                                            :variant="$tonoPorEstado['atendida'].'-outline'"
                                            size="sm"
                                            icon="task_alt"
                                        >
                                            {{ __('operaciones.alertas.atender') }}
                                        </x-atoms.button>
                                    </x-organisms.row-actions>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$alertas" :aria-label="__('operaciones.alertas.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
