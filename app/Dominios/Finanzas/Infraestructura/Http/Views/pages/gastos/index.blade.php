{{--
    Page: gastos/index (GET /panel/gastos, panel.gastos.index)
    Listado de gastos (HU-33, tarea 47): arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → KPI → toolbar → tabla →
    paginación. Homogeneizado en la tarea 118 con el patrón de Estadías:
    franja de KPI, `filter-panel`, `index-table`, `row-actions` y
    `confirm-modal` para la baja (antes era la confirmación nativa del
    navegador y el `<form class="ag-filtros">` anterior).

    Sin acción de editar (invariante de esta tarea: un gasto es inmutable
    salvo baja, ver Aplicacion/CrearGasto) y sin buscador: los cinco filtros
    (rubro, cuadrilla, base, campaña y período) son todos del panel.

    Un gasto no tiene máquina de estados ni activo/inactivo: no lleva pasos,
    badge de estado ni columna de activo (guía §6.2).

    Datos esperados (ver GastosController::index()): la cáscara de
    CascaraPanel, más:
    - $gastos (LengthAwarePaginator<Gasto>): fecha descendente.
    - $etiquetasRubro / $etiquetasBase / $etiquetasEquipo (array<int, string>):
      id => nombre.
    - $etiquetasTrabajo (array<int, string>): trabajo_id => etiqueta legible,
      solo de los trabajos presentes en la página actual (evita un JOIN en
      el listado — mismo criterio que AnticiposController::etiquetasPersona()).
    - $rubrosDisponibles / $basesDisponibles / $equiposDisponibles /
      $campaniasDisponibles (Collection<int, string>): para los <select> de
      filtro. `$campaniasDisponibles` NO se filtra por estado (a diferencia
      de la del formulario de alta): una campaña `cerrada` sigue teniendo
      historial de gastos que filtrar.
    - $filtros (array{rubro_id, base_id, trabajo_id, equipo_trabajo_id,
      campania_id, periodo}): valores aplicados, para dejar los campos con
      el valor tras el submit.
    - $resumen (array{total, cantidad, sinComprobante, internos}): las cifras
      de la franja de KPI, con el MISMO filtro que la tabla
      (ListarGastos::resumen). Los montos son DECIMAL sumados con BigDecimal:
      la vista solo los formatea (`FormatoMonto`, sin `float`), nunca calcula
      con ellos (invariante 6).
    - $puedeEliminar (bool): gatea el botón "Eliminar" por fila.

    Gateada por `finanzas.gasto.ver`, verificado server-side en el
    controlador. El botón "Nuevo gasto" y "Eliminar" se ocultan con `@puede`
    o `$puedeEliminar` (presentación, no autorización — el servidor
    revalida en GastosController).

    Estilos en resources/css/pages/gastos.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@use('App\Dominios\Finanzas\Infraestructura\Http\FormatoMonto')
<x-templates.panel-shell :title="__('finanzas.gastos.titulo')" :tema="$tema">
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
        :vista-actual="__('finanzas.gastos.titulo')"
    >
        <div class="ag-gastos">
            <x-organisms.page-header
                :title="__('finanzas.gastos.titulo')"
                :subtitle="__('finanzas.gastos.subtitulo')"
            >
                @puede('finanzas.gasto.crear')
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.gastos.create')" variant="primary" icon="add">
                            {{ __('finanzas.gastos.nuevo') }}
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
            @if ($hayFiltrosActivos || $gastos->isNotEmpty())
                <div class="ag-gastos__kpis">
                    <x-molecules.stat-card
                        :label="__('finanzas.gastos.kpi_total')"
                        icon="payments"
                        :value="FormatoMonto::decimal($resumen['total'])"
                        :value-suffix="__('finanzas.gastos.unidad_moneda')"
                        :state="$resumen['cantidad'] > 0 ? 'info' : null"
                    />
                    <x-molecules.stat-card
                        :label="__('finanzas.gastos.kpi_cantidad')"
                        icon="receipt_long"
                        :value="$resumen['cantidad']"
                        :state="$resumen['cantidad'] > 0 ? 'distintivo-1' : null"
                    />
                    <x-molecules.stat-card
                        :label="__('finanzas.gastos.kpi_sin_comprobante')"
                        icon="attach_file"
                        :value="$resumen['sinComprobante']"
                        :foot="__('finanzas.gastos.kpi_sin_comprobante_pie')"
                        :state="$resumen['sinComprobante'] > 0 ? 'warning' : null"
                    />
                    <x-molecules.stat-card
                        :label="__('finanzas.gastos.kpi_internos')"
                        icon="domain"
                        :value="FormatoMonto::decimal($resumen['internos'])"
                        :value-suffix="__('finanzas.gastos.unidad_moneda')"
                        :foot="__('finanzas.gastos.kpi_internos_pie')"
                        :state="$resumen['internos'] !== '0.00' ? 'distintivo-2' : null"
                    />
                </div>
            @endif

            @if ($hayFiltrosActivos || $gastos->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.gastos.index')"
                        :active-count="$filtrosActivosCount"
                    >
                        <x-atoms.select
                            name="rubro_id"
                            id="filtro-rubro"
                            :label="__('finanzas.gastos.filtro_rubro')"
                            :options="$rubrosDisponibles"
                            :value="(string) $filtros['rubro_id']"
                            :placeholder="__('finanzas.gastos.filtro_rubro_placeholder')"
                        />

                        <x-atoms.select
                            name="equipo_trabajo_id"
                            id="filtro-equipo"
                            :label="__('finanzas.gastos.filtro_equipo')"
                            :options="$equiposDisponibles"
                            :value="(string) $filtros['equipo_trabajo_id']"
                            :placeholder="__('finanzas.gastos.filtro_equipo_placeholder')"
                        />

                        <x-atoms.select
                            name="base_id"
                            id="filtro-base"
                            :label="__('finanzas.gastos.filtro_base')"
                            :options="$basesDisponibles"
                            :value="(string) $filtros['base_id']"
                            :placeholder="__('finanzas.gastos.filtro_base_placeholder')"
                        />

                        <x-atoms.select
                            name="campania_id"
                            id="filtro-campania"
                            :label="__('finanzas.gastos.filtro_campania')"
                            :options="$campaniasDisponibles"
                            :value="(string) $filtros['campania_id']"
                            :placeholder="__('finanzas.gastos.filtro_campania_placeholder')"
                        />

                        <x-atoms.input
                            type="month"
                            name="periodo"
                            id="filtro-periodo"
                            :label="__('finanzas.gastos.filtro_periodo')"
                            :value="$filtros['periodo']"
                        />
                    </x-organisms.filter-panel>
                </div>
            @endif

            @if ($gastos->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('finanzas.gastos.filtro_vacio_titulo')"
                        :detail="__('finanzas.gastos.filtro_vacio_detalle')"
                    />
                @else
                    {{-- Sin botón adentro: el vacío de un listado solo explica. El
                         alta ya está en la cabecera, y es el único botón sólido. --}}
                    <x-molecules.empty-state
                        icon="receipt_long"
                        :title="__('finanzas.gastos.vacio_titulo')"
                        :detail="__('finanzas.gastos.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 0.9fr) minmax(0, 1.1fr) minmax(0, 1.6fr) minmax(0, 1fr) minmax(0, 1fr) var(--ag-row-actions-width)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('finanzas.gastos.col_fecha') }}</span>
                        <span role="columnheader">{{ __('finanzas.gastos.col_rubro') }}</span>
                        <span role="columnheader">{{ __('finanzas.gastos.col_imputacion') }}</span>
                        <span role="columnheader" class="ag-index-table__cifra-head">{{ __('finanzas.gastos.col_monto') }}</span>
                        <span role="columnheader">{{ __('finanzas.gastos.col_comprobante') }}</span>
                        <span role="columnheader" class="ag-index-table__acciones-head">{{ __('ui.tabla.col_acciones') }}</span>
                    </x-slot:head>

                    @foreach ($gastos as $gasto)
                        @php
                            $formIdEliminar = "gasto-eliminar-{$gasto->id}";
                            $modalIdEliminar = "gasto-eliminar-modal-{$gasto->id}";
                        @endphp

                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($gastos->currentPage() - 1) * $gastos->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-index-table__mono">{{ $gasto->fecha->format('d/m/Y') }}</span>
                            <span role="cell">{{ $etiquetasRubro[$gasto->rubro_id] ?? "#{$gasto->rubro_id}" }}</span>
                            <span role="cell">
                                @if ($gasto->equipo_trabajo_id !== null)
                                    {{ __('finanzas.gastos.imputacion_equipo', ['equipo' => $etiquetasEquipo[$gasto->equipo_trabajo_id] ?? "#{$gasto->equipo_trabajo_id}"]) }}
                                @elseif ($gasto->trabajo_id !== null)
                                    {{ $etiquetasTrabajo[$gasto->trabajo_id] ?? __('finanzas.gastos.imputacion_trabajo', ['id' => $gasto->trabajo_id]) }}
                                @elseif ($gasto->base_id !== null)
                                    {{ __('finanzas.gastos.imputacion_base', ['base' => $etiquetasBase[$gasto->base_id] ?? "#{$gasto->base_id}"]) }}
                                @else
                                    {{ __('finanzas.gastos.imputacion_general') }}
                                @endif
                            </span>
                            <span role="cell" class="ag-index-table__cifra">{{ __('finanzas.gastos.monto_valor', ['monto' => FormatoMonto::decimal($gasto->monto)]) }}</span>
                            <span role="cell">
                                @if ($gasto->comprobante_url !== null)
                                    <x-atoms.badge variant="neutral" icon="attach_file">
                                        {{ __('finanzas.gastos.comprobante_adjunto') }}
                                    </x-atoms.badge>
                                @else
                                    <span class="ag-gastos__atenuado">{{ __('finanzas.gastos.comprobante_sin') }}</span>
                                @endif
                            </span>

                            <span role="cell" class="ag-index-table__acciones">
                                {{-- Form y modal FUERA de row-actions a propósito: ese organism
                                     repite su slot dos veces (visible/menú, ver su docblock),
                                     así que un <form> o un modal con id ahí adentro se
                                     duplicaría — y el que cae dentro del menú ⋮ queda oculto
                                     con él y nunca abre. El disparador sí va adentro (es un
                                     botón sin id propio). Mismo criterio que repuestos/index. --}}
                                @if ($puedeEliminar)
                                    <form id="{{ $formIdEliminar }}" method="POST" action="{{ route('panel.gastos.destroy', $gasto) }}">
                                        @csrf
                                        @method('DELETE')
                                    </form>

                                    <x-molecules.confirm-modal
                                        :id="$modalIdEliminar"
                                        :form-id="$formIdEliminar"
                                        :title="__('finanzas.gastos.confirmar_baja_titulo')"
                                        :message="__('finanzas.gastos.confirmar_baja')"
                                        :confirm-label="__('finanzas.gastos.eliminar_accion')"
                                    />
                                @endif

                                @if ($gasto->comprobante_url !== null || $puedeEliminar)
                                    <x-organisms.row-actions>
                                        @if ($gasto->comprobante_url !== null)
                                            <x-atoms.button
                                                :href="route('panel.gastos.comprobante', $gasto)"
                                                target="_blank"
                                                rel="noopener"
                                                variant="info-outline"
                                                size="sm"
                                                icon="visibility"
                                            >
                                                {{ __('finanzas.gastos.comprobante_ver') }}
                                            </x-atoms.button>
                                        @endif

                                        @if ($puedeEliminar)
                                            <x-atoms.button
                                                type="button"
                                                data-bs-toggle="modal"
                                                :data-bs-target="'#'.$modalIdEliminar"
                                                variant="danger-outline"
                                                size="sm"
                                                icon="delete"
                                            >
                                                {{ __('finanzas.gastos.eliminar_accion') }}
                                            </x-atoms.button>
                                        @endif
                                    </x-organisms.row-actions>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$gastos" :aria-label="__('finanzas.gastos.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
