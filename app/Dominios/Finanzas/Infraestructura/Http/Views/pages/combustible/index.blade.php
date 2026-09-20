{{--
    Page: combustible/index (GET /panel/combustible, panel.combustible.index)
    Listado de cargas de combustible (HU-35, tarea 49): arquetipo Listado,
    §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera → KPI → toolbar →
    tabla → paginación. Homogeneizado en la tarea 118 con el patrón de
    Estadías: franja de KPI, `filter-panel`, `index-table`, `row-actions` y
    `confirm-modal` para la baja (antes era el `confirm()` nativo del
    navegador y el `<form class="ag-filtros">` anterior).

    Sin acción de editar (invariante de esta tarea: una carga es inmutable
    salvo baja, ver Aplicacion/CrearCombustible) y sin buscador: los cinco
    filtros (base, cuadrilla, campaña y rango de fechas) son todos del panel.
    La única acción de la fila es «Eliminar», así que sin ese permiso la tabla
    no lleva columna de acciones.

    Una carga no tiene máquina de estados ni activo/inactivo: no lleva pasos,
    badge de estado ni columna de activo (guía §6.2).

    Datos esperados (ver CombustibleController::index()): la cáscara de
    CascaraPanel, más:
    - $combustibles (LengthAwarePaginator<Combustible>): fecha descendente.
    - $etiquetasBase / $etiquetasEquipo (array<int, string>): id => nombre.
    - $etiquetasRecurso (array<string, string>): clave compuesta
      `"{tipo}:{id}"` => etiqueta, acotada a la página actual (tarea 73,
      mismo criterio que `GastosController::etiquetasTrabajo()`).
    - $basesDisponibles / $equiposDisponibles / $campaniasDisponibles
      (Collection<int, string>): para los <select> de filtro.
      `$campaniasDisponibles` NO se filtra por estado (a diferencia de la
      del formulario de alta): una campaña `cerrada` sigue teniendo
      historial de combustible que filtrar.
    - $filtros (array{base_id, desde, hasta, equipo_trabajo_id,
      campania_id}): valores aplicados, para dejar los campos con el valor
      tras el submit.
    - $resumen (array{total, cantidad, litros, equipos}): las cifras de la
      franja de KPI, con el MISMO filtro que la tabla
      (ListarCombustibles::resumen). Montos y litros son DECIMAL sumados con
      BigDecimal: la vista solo los formatea (`FormatoMonto`, sin `float`),
      nunca calcula con ellos (invariante 6).
    - $puedeEliminar (bool): gatea el botón "Eliminar" por fila.

    Gateada por `finanzas.combustible.ver`, verificado server-side en el
    controlador. El botón "Nueva carga" y "Eliminar" se ocultan con `@puede`
    o `$puedeEliminar` (presentación, no autorización — el servidor revalida
    en CombustibleController).

    Estilos en resources/css/pages/combustible.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@use('App\Dominios\Finanzas\Infraestructura\Http\FormatoMonto')
<x-templates.panel-shell :title="__('finanzas.combustible.titulo')" :tema="$tema">
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
        :vista-actual="__('finanzas.combustible.titulo')"
    >
        <div class="ag-combustible">
            <x-organisms.page-header
                :title="__('finanzas.combustible.titulo')"
                :subtitle="__('finanzas.combustible.subtitulo')"
            >
                @puede('finanzas.combustible.crear')
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.combustible.create')" variant="primary" icon="add">
                            {{ __('finanzas.combustible.nuevo') }}
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
                $filtrosActivosCount = collect($filtros)->filter(fn ($valor) => $valor !== null && $valor !== '')->count();
            @endphp

            {{-- KPI del listado: franja fija bajo la cabecera (plan §3.6). Responden
                 al filtro aplicado, y con la cuadrilla elegida el total es el total de
                 esa cuadrilla. Una cifra en cero va sin color. --}}
            @if ($hayFiltrosActivos || $combustibles->isNotEmpty())
                <div class="ag-combustible__kpis">
                    <x-molecules.stat-card
                        :label="__('finanzas.combustible.kpi_total')"
                        icon="payments"
                        :value="FormatoMonto::decimal($resumen['total'])"
                        :value-suffix="__('finanzas.combustible.unidad_moneda')"
                        :state="$resumen['cantidad'] > 0 ? 'info' : null"
                    />
                    <x-molecules.stat-card
                        :label="__('finanzas.combustible.kpi_cantidad')"
                        icon="local_gas_station"
                        :value="$resumen['cantidad']"
                        :state="$resumen['cantidad'] > 0 ? 'distintivo-1' : null"
                    />
                    <x-molecules.stat-card
                        :label="__('finanzas.combustible.kpi_litros')"
                        icon="water_drop"
                        :value="FormatoMonto::decimal($resumen['litros'])"
                        :value-suffix="__('finanzas.combustible.unidad_litros')"
                        :state="$resumen['cantidad'] > 0 ? 'distintivo-2' : null"
                    />
                    <x-molecules.stat-card
                        :label="__('finanzas.combustible.kpi_equipos')"
                        icon="groups"
                        :value="$resumen['equipos']"
                        :foot="__('finanzas.combustible.kpi_equipos_pie')"
                        :state="$resumen['equipos'] > 0 ? 'success' : null"
                    />
                </div>
            @endif

            @if ($hayFiltrosActivos || $combustibles->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.combustible.index')"
                        :active-count="$filtrosActivosCount"
                    >
                        <x-atoms.select
                            name="base_id"
                            id="filtro-base"
                            :label="__('finanzas.combustible.filtro_base')"
                            :options="$basesDisponibles"
                            :value="(string) $filtros['base_id']"
                            :placeholder="__('finanzas.combustible.filtro_base_placeholder')"
                        />

                        <x-atoms.select
                            name="equipo_trabajo_id"
                            id="filtro-equipo"
                            :label="__('finanzas.combustible.filtro_equipo')"
                            :options="$equiposDisponibles"
                            :value="(string) $filtros['equipo_trabajo_id']"
                            :placeholder="__('finanzas.combustible.filtro_equipo_placeholder')"
                        />

                        <x-atoms.select
                            name="campania_id"
                            id="filtro-campania"
                            :label="__('finanzas.combustible.filtro_campania')"
                            :options="$campaniasDisponibles"
                            :value="(string) $filtros['campania_id']"
                            :placeholder="__('finanzas.combustible.filtro_campania_placeholder')"
                        />

                        <x-atoms.date
                            name="desde"
                            id="filtro-desde"
                            :label="__('finanzas.combustible.filtro_desde')"
                            :value="$filtros['desde']"
                        />

                        <x-atoms.date
                            name="hasta"
                            id="filtro-hasta"
                            :label="__('finanzas.combustible.filtro_hasta')"
                            :value="$filtros['hasta']"
                        />
                    </x-organisms.filter-panel>
                </div>
            @endif

            @if ($combustibles->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('finanzas.combustible.filtro_vacio_titulo')"
                        :detail="__('finanzas.combustible.filtro_vacio_detalle')"
                    />
                @else
                    {{-- Sin botón adentro: el vacío de un listado solo explica. El
                         alta ya está en la cabecera, y es el único botón sólido. --}}
                    <x-molecules.empty-state
                        icon="local_gas_station"
                        :title="__('finanzas.combustible.vacio_titulo')"
                        :detail="__('finanzas.combustible.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table :columns="'3rem minmax(0, 0.9fr) minmax(0, 1fr) minmax(0, 1.3fr) minmax(0, 1.3fr) minmax(0, 0.9fr) minmax(0, 1fr)'.($puedeEliminar ? ' var(--ag-row-actions-width)' : '')">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('finanzas.combustible.col_fecha') }}</span>
                        <span role="columnheader">{{ __('finanzas.combustible.col_base') }}</span>
                        <span role="columnheader">{{ __('finanzas.combustible.col_equipo') }}</span>
                        <span role="columnheader">{{ __('finanzas.combustible.col_recurso') }}</span>
                        <span role="columnheader" class="ag-index-table__cifra-head">{{ __('finanzas.combustible.col_litros') }}</span>
                        <span role="columnheader" class="ag-index-table__cifra-head">{{ __('finanzas.combustible.col_monto') }}</span>
                        @if ($puedeEliminar)
                            <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                        @endif
                    </x-slot:head>

                    @foreach ($combustibles as $combustible)
                        @php
                            $formIdEliminar = "combustible-eliminar-{$combustible->id}";
                            $modalIdEliminar = "combustible-eliminar-modal-{$combustible->id}";
                        @endphp

                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($combustibles->currentPage() - 1) * $combustibles->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-index-table__mono">{{ $combustible->fecha->format('d/m/Y') }}</span>
                            <span role="cell">{{ $etiquetasBase[$combustible->base_id] ?? "#{$combustible->base_id}" }}</span>
                            <span role="cell">{{ $etiquetasEquipo[$combustible->equipo_trabajo_id] ?? "#{$combustible->equipo_trabajo_id}" }}</span>
                            <span role="cell">{{ $etiquetasRecurso["{$combustible->recurso_tipo}:{$combustible->recurso_id}"] ?? "{$combustible->recurso_tipo} #{$combustible->recurso_id}" }}</span>
                            <span role="cell" class="ag-index-table__cifra">{{ __('finanzas.combustible.litros_valor', ['litros' => FormatoMonto::decimal($combustible->litros)]) }}</span>
                            <span role="cell" class="ag-index-table__cifra">{{ __('finanzas.combustible.monto_valor', ['monto' => FormatoMonto::decimal($combustible->monto)]) }}</span>

                            @if ($puedeEliminar)
                                <span role="cell" class="ag-index-table__acciones">
                                    {{-- Form y modal FUERA de row-actions a propósito: ese organism
                                         repite su slot dos veces (visible/menú, ver su docblock),
                                         así que un <form> o un modal con id ahí adentro se
                                         duplicaría — y el que cae dentro del menú ⋮ queda oculto
                                         con él y nunca abre. El disparador sí va adentro (es un
                                         botón sin id propio). Mismo criterio que repuestos/index. --}}
                                    <form id="{{ $formIdEliminar }}" method="POST" action="{{ route('panel.combustible.destroy', $combustible) }}">
                                        @csrf
                                        @method('DELETE')
                                    </form>

                                    <x-molecules.confirm-modal
                                        :id="$modalIdEliminar"
                                        :form-id="$formIdEliminar"
                                        :title="__('finanzas.combustible.confirmar_baja_titulo')"
                                        :message="__('finanzas.combustible.confirmar_baja')"
                                        :confirm-label="__('finanzas.combustible.eliminar_accion')"
                                    />

                                    <x-organisms.row-actions>
                                        <x-atoms.button
                                            type="button"
                                            data-bs-toggle="modal"
                                            :data-bs-target="'#'.$modalIdEliminar"
                                            variant="danger-outline"
                                            size="sm"
                                            icon="delete"
                                        >
                                            {{ __('finanzas.combustible.eliminar_accion') }}
                                        </x-atoms.button>
                                    </x-organisms.row-actions>
                                </span>
                            @endif
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$combustibles" :aria-label="__('finanzas.combustible.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
