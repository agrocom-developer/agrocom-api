{{--
    Page: ordenes/index (GET /panel/ordenes-mantenimiento, panel.ordenes-mantenimiento.index)
    Listado de órdenes de mantenimiento (HU-37, tarea 53): arquetipo Listado,
    §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera → KPI → toolbar →
    tabla → paginación. Homogeneizado en la tarea 116 con el patrón de
    Contratos (acciones de estado) y Vehículos (tabla y toolbar): antes tenía
    filtros viejos, tabla propia y paginación a mano.

    La orden es un objeto CON máquina de estados
    (`TransicionesOrdenMantenimiento`), así que el badge, la acción de estado y
    su modal comparten el tono de `PasosDeOrdenMantenimiento::TONO_POR_ESTADO`
    — definido una sola vez, nunca reelegido acá.

    **La acción «Cerrar» del listado avisa, no cierra** (`molecules/info-modal`,
    no `confirm-modal`): cerrar una orden exige la descripción final y los
    repuestos consumidos, que son campos de la ficha
    (`CerrarOrdenMantenimientoRequest`). Un botón que posteara desde acá
    rebotaría siempre contra su propia validación, así que el modal muestra la
    transición a la que se va, explica qué hace falta y lleva a la ficha. Es el
    caso que `info-modal` documenta: una acción que todavía no se puede hacer,
    sin nada que «aceptar» que el servidor vaya a rechazar.

    Datos esperados (ver OrdenesMantenimientoController::index()): la
    cáscara de CascaraPanel, más:
    - $ordenes (LengthAwarePaginator<OrdenMantenimiento>): fecha de apertura
      descendente.
    - $etiquetasEquipo (array{dron: array<int,string>, vehiculo: array<int,string>}):
      identificador por id, ya resuelto por el controlador (`DB::table(...)`
      — OrdenMantenimiento no tiene relación Eloquent hacia Dron/Vehiculo,
      son de módulos distintos vía `equipo_tipo`+`equipo_id` sin FK real). Un
      id sin etiqueta (equipo borrado) cae al `#id` crudo.
    - $resumen (array{abiertas, cerradas, correctivas, demora_maxima}): las
      cifras de la franja de KPI, con el MISMO filtro que la tabla.
    - $filtros (array{estado: ?string, equipo_tipo: ?string, q: string}):
      filtros aplicados, para dejar los campos con su valor tras el submit.
    - $puedeCrear / $puedeCerrar (bool): gatean el alta y la acción de estado
      (presentación, no autorización — el servidor revalida en el controlador).

    Gateada por `mantenimiento.orden.ver`, verificado server-side en el
    controlador. Sin acción de eliminar: una orden de mantenimiento no se
    borra (invariante 8), solo se abre y se cierra.

    Estilos en resources/css/pages/ordenes-mantenimiento.css — cero color
    hardcodeado (CLAUDE.md invariante 11).
--}}
@php
    $tonoPorEstado = \App\Dominios\Mantenimiento\Infraestructura\Http\PasosDeOrdenMantenimiento::TONO_POR_ESTADO;
    $estadoCerrada = \App\Dominios\Mantenimiento\Dominio\EstadoOrdenMantenimiento::Cerrada;
@endphp
<x-templates.panel-shell :title="__('mantenimiento.ordenes.titulo')" :tema="$tema">
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
        :vista-actual="__('mantenimiento.ordenes.titulo')"
    >
        <div class="ag-ordenes-mantenimiento">
            <x-organisms.page-header
                :title="__('mantenimiento.ordenes.titulo')"
                :subtitle="__('mantenimiento.ordenes.subtitulo')"
            >
                @if ($puedeCrear)
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.ordenes-mantenimiento.create')" variant="primary" icon="add">
                            {{ __('mantenimiento.ordenes.nuevo') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endif
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
                $filtrosPanelActivos = collect(['estado', 'equipo_tipo'])
                    ->filter(fn ($campo) => $filtros[$campo] !== null && $filtros[$campo] !== '')
                    ->count();
            @endphp

            @if ($ordenes->isNotEmpty())
                {{-- Franja de KPI con el mismo filtro que la tabla (plan §3.6):
                     cuánto trabajo queda abierto, cuánto se terminó, cuánto fue
                     reacción y cuánto lleva esperando la orden más vieja. --}}
                <div class="ag-ordenes-mantenimiento__kpis">
                    <x-molecules.stat-card
                        :label="__('mantenimiento.ordenes.kpi_abiertas')"
                        icon="build_circle"
                        :value="$resumen['abiertas']"
                        :state="$resumen['abiertas'] > 0 ? 'warning' : null"
                    />
                    <x-molecules.stat-card
                        :label="__('mantenimiento.ordenes.kpi_cerradas')"
                        icon="task_alt"
                        :value="$resumen['cerradas']"
                        :state="$resumen['cerradas'] > 0 ? 'success' : null"
                    />
                    <x-molecules.stat-card
                        :label="__('mantenimiento.ordenes.kpi_correctivas')"
                        icon="report"
                        :value="$resumen['correctivas']"
                        :foot="__('mantenimiento.ordenes.kpi_correctivas_pie')"
                        :state="$resumen['correctivas'] > 0 ? 'distintivo-1' : null"
                    />
                    <x-molecules.stat-card
                        :label="__('mantenimiento.ordenes.kpi_demora')"
                        icon="hourglass_top"
                        :value="$resumen['demora_maxima']"
                        :value-suffix="__('mantenimiento.ordenes.kpi_demora_sufijo')"
                        :foot="__('mantenimiento.ordenes.kpi_demora_pie')"
                        :state="$resumen['demora_maxima'] > 0 ? 'info' : null"
                    />
                </div>
            @endif

            @if ($hayFiltrosActivos || $ordenes->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.ordenes-mantenimiento.index')"
                        :active-count="$filtrosPanelActivos"
                    >
                        <input type="hidden" name="q" value="{{ $filtros['q'] }}">

                        @php
                            $opcionesEstado = collect($tonoPorEstado)->mapWithKeys(fn ($_, $valor) => [
                                $valor => __('mantenimiento.orden.estado.'.$valor),
                            ])->all();
                            $opcionesEquipoTipo = [
                                'dron' => __('mantenimiento.equipo_tipo.dron'),
                                'vehiculo' => __('mantenimiento.equipo_tipo.vehiculo'),
                            ];
                        @endphp

                        <x-atoms.select
                            name="estado"
                            id="filtro-estado"
                            :label="__('mantenimiento.ordenes.filtro_estado')"
                            :options="$opcionesEstado"
                            :value="$filtros['estado']"
                            :placeholder="__('mantenimiento.ordenes.filtro_todos')"
                        />

                        <x-atoms.select
                            name="equipo_tipo"
                            id="filtro-equipo-tipo"
                            :label="__('mantenimiento.ordenes.filtro_equipo_tipo')"
                            :options="$opcionesEquipoTipo"
                            :value="$filtros['equipo_tipo']"
                            :placeholder="__('mantenimiento.ordenes.filtro_todos')"
                        />
                    </x-organisms.filter-panel>

                    <x-molecules.table-search
                        :action="route('panel.ordenes-mantenimiento.index')"
                        :value="$filtros['q']"
                        :placeholder="__('mantenimiento.ordenes.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($ordenes->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('mantenimiento.ordenes.filtro_vacio_titulo')"
                        :detail="__('mantenimiento.ordenes.filtro_vacio_detalle')"
                    />
                @else
                    {{-- Sin botón adentro: el vacío de un listado solo explica. El
                         alta ya está en la cabecera, y es el único botón sólido. --}}
                    <x-molecules.empty-state
                        icon="build"
                        :title="__('mantenimiento.ordenes.vacio_titulo')"
                        :detail="__('mantenimiento.ordenes.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 1.6fr) minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.ordenes.col_equipo') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.ordenes.col_tipo') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.ordenes.col_estado') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.ordenes.col_fecha_apertura') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.ordenes.col_fecha_cierre') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($ordenes as $orden)
                        @php
                            $estadoValor = $orden->estado->value;
                            $modalIdCerrar = "orden-mantenimiento-cerrar-modal-{$orden->id}";
                            $puedePedirCierre = $puedeCerrar
                                && \App\Dominios\Mantenimiento\Dominio\MaquinaEstados\TransicionesOrdenMantenimiento::permitida($orden->estado, $estadoCerrada);
                        @endphp

                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($ordenes->currentPage() - 1) * $ordenes->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-ordenes-mantenimiento__equipo">
                                {{ __('mantenimiento.equipo_tipo.'.$orden->equipo_tipo) }} · {{ $etiquetasEquipo[$orden->equipo_tipo][$orden->equipo_id] ?? "#{$orden->equipo_id}" }}
                            </span>
                            <span role="cell">{{ __('mantenimiento.tipo_orden.'.$orden->tipo) }}</span>
                            <span role="cell">
                                <x-atoms.badge :variant="$tonoPorEstado[$estadoValor]">
                                    {{ __('mantenimiento.orden.estado.'.$estadoValor) }}
                                </x-atoms.badge>
                            </span>
                            <span role="cell">{{ $orden->fecha_apertura->format('d/m/Y') }}</span>
                            <span role="cell">{{ $orden->fecha_cierre?->format('d/m/Y') ?? __('mantenimiento.ordenes.sin_fecha_cierre') }}</span>

                            <span role="cell" class="ag-index-table__acciones">
                                {{-- Modal FUERA de row-actions a propósito: ese organism
                                     repite su slot dos veces (visible/menú, ver su docblock),
                                     así que un elemento con id ahí adentro se duplicaría — y
                                     el que cae dentro del menú ⋮ queda oculto con él y nunca
                                     abre. El disparador sí va adentro (es un botón sin id
                                     propio). Mismo criterio que contratos/index. --}}
                                @if ($puedePedirCierre)
                                    <x-molecules.info-modal
                                        :id="$modalIdCerrar"
                                        :title="__('mantenimiento.ordenes.aviso_cierre_titulo')"
                                        :message="__('mantenimiento.ordenes.aviso_cierre_mensaje')"
                                        :close-label="__('ui.action.close')"
                                        :tone="$tonoPorEstado[$estadoCerrada->value]"
                                        modal-icon="build"
                                    >
                                        @include('mantenimiento::pages.ordenes._estado-transicion', [
                                            'desde' => $estadoValor,
                                            'hacia' => $estadoCerrada->value,
                                        ])

                                        <x-slot:actions>
                                            <x-atoms.button :href="route('panel.ordenes-mantenimiento.edit', $orden)" variant="outline" icon="open_in_new">
                                                {{ __('mantenimiento.ordenes.aviso_cierre_accion') }}
                                            </x-atoms.button>
                                        </x-slot:actions>
                                    </x-molecules.info-modal>
                                @endif

                                <x-organisms.row-actions>
                                    <x-atoms.button :href="route('panel.ordenes-mantenimiento.edit', $orden)" variant="info-outline" size="sm" icon="visibility">
                                        {{ __('mantenimiento.ordenes.ver_accion') }}
                                    </x-atoms.button>

                                    @if ($puedePedirCierre)
                                        {{-- Tono del estado al que lleva (plan §3.1): el mismo
                                             `success` del badge «Cerrada» y del modal. --}}
                                        <x-atoms.button
                                            type="button"
                                            data-bs-toggle="modal"
                                            :data-bs-target="'#'.$modalIdCerrar"
                                            :variant="$tonoPorEstado[$estadoCerrada->value].'-outline'"
                                            size="sm"
                                            icon="build"
                                        >
                                            {{ __('mantenimiento.ordenes.boton_cerrar') }}
                                        </x-atoms.button>
                                    @endif
                                </x-organisms.row-actions>
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$ordenes" :aria-label="__('mantenimiento.ordenes.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
