{{--
    Page: stock/index (GET /panel/stock, panel.stock.index)
    Listado del stock agregado por (repuesto, base), con alerta de mínimo
    (HU-36, tarea 52): arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → KPI → toolbar → tabla →
    paginación. Homogeneizado en la tarea 117 con el patrón de Órdenes de
    mantenimiento (franja de KPI, `filter-panel`, `index-table`): antes tenía
    el `<form class="ag-filtros">` anterior y la tabla escrita a mano.

    **Sin columna de acciones ni `row-actions`**: `inv_stock` es un agregado
    derivado y de solo lectura desde acá; toda mutación pasa por el alta de un
    movimiento (`RegistrarMovimientoStock`), que es un asiento —ni se edita ni
    se borra—. Una tabla sin ninguna acción no lleva la columna vacía.

    El stock NO tiene máquina de estados: el nivel de cada fila («sin
    existencias» / «por debajo del mínimo») es un dato calculado, no un
    estado, así que no lleva pasos ni tono de `TONO_POR_ESTADO`. Tampoco
    muestra activo/inactivo (guía §6.2).

    Datos esperados (ver StockController::index()): la cáscara de
    CascaraPanel, más:
    - $stock (LengthAwarePaginator<Stock>): base ascendente, con `repuesto`
      precargado (sin N+1). Cada fila ya trae `alerta` y `sin_existencias`
      (bool) calculadas por ListarStock — la vista no evalúa ningún umbral.
    - $resumen (array{repuestos, bases, bajoMinimo, sinExistencias, movimientos,
      periodoDias}): las cifras de la franja de KPI, con el MISMO filtro que la
      tabla (ListarStock::resumen).
    - $etiquetasBase (array<int, string>): nombre de base por id, ya
      resuelto por el controlador vía el contrato de Personal (Stock no tiene
      relación Eloquent hacia PerBase, son de módulos distintos). Un id sin
      etiqueta (base borrada) cae al `#id` crudo.
    - $basesDisponibles (array<int, string>): opciones del filtro de base.
    - $filtros (array{q: string, base_id: ?int}): filtros aplicados, para
      dejar los campos con su valor tras el submit.

    Cantidades y mínimos son DECIMAL: la vista solo los formatea
    (`FormatoCantidad`, sin `float`), nunca calcula con ellos (invariante 6).

    Gateada por `inventario.movimiento.ver`, verificado server-side en el
    controlador. El botón "Registrar movimiento" se oculta con `@puede`
    (presentación, no autorización — el servidor revalida en
    StockController). El botón "Ver movimientos" (tarea 133) no lleva
    `@puede` propio: exige el mismo permiso `.ver` que ya gatea la página
    entera. El link "Ver movimientos" por fila (junto al código del
    repuesto) navega al mismo listado ya filtrado a ese repuesto y esa base
    — mismo patrón que `ag-ordenes__crear-trabajo` de Órdenes, pero de solo
    navegación.

    Estilos en resources/css/pages/stock.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@use('App\Dominios\Inventario\Infraestructura\Http\FormatoCantidad')
<x-templates.panel-shell :title="__('inventario.stock.titulo')" :tema="$tema">
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
        :vista-actual="__('inventario.stock.titulo')"
    >
        <div class="ag-stock">
            <x-organisms.page-header
                :title="__('inventario.stock.titulo')"
                :subtitle="__('inventario.stock.subtitulo')"
            >
                <x-slot:actions>
                    <x-atoms.button :href="route('panel.stock.movimientos.index')" variant="outline" icon="receipt_long">
                        {{ __('inventario.stock.ver_movimientos') }}
                    </x-atoms.button>
                    @puede('inventario.movimiento.crear')
                        <x-atoms.button :href="route('panel.stock.movimientos.create')" variant="primary" icon="add">
                            {{ __('inventario.stock.nuevo_movimiento') }}
                        </x-atoms.button>
                    @endpuede
                </x-slot:actions>
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
                $filtrosPanelActivos = $filtros['base_id'] !== null ? 1 : 0;
            @endphp

            @if ($stock->isNotEmpty())
                {{-- Franja de KPI con el mismo filtro que la tabla (plan §3.6): cuánto
                     hay, qué pide reposición, qué se agotó y cuánto se mueve. Una
                     cifra en cero va sin color. --}}
                <div class="ag-stock__kpis">
                    <x-molecules.stat-card
                        :label="__('inventario.stock.kpi_repuestos')"
                        icon="inventory_2"
                        :value="$resumen['repuestos']"
                        :foot="trans_choice('inventario.stock.kpi_repuestos_pie', $resumen['bases'], ['cantidad' => $resumen['bases']])"
                    />
                    <x-molecules.stat-card
                        :label="__('inventario.stock.kpi_bajo_minimo')"
                        icon="trending_down"
                        :value="$resumen['bajoMinimo']"
                        :foot="__('inventario.stock.kpi_bajo_minimo_pie')"
                        :state="$resumen['bajoMinimo'] > 0 ? 'warning' : null"
                    />
                    <x-molecules.stat-card
                        :label="__('inventario.stock.kpi_sin_existencias')"
                        icon="production_quantity_limits"
                        :value="$resumen['sinExistencias']"
                        :foot="__('inventario.stock.kpi_sin_existencias_pie')"
                        :state="$resumen['sinExistencias'] > 0 ? 'danger' : null"
                    />
                    <x-molecules.stat-card
                        :label="__('inventario.stock.kpi_movimientos')"
                        icon="swap_horiz"
                        :value="$resumen['movimientos']"
                        :foot="__('inventario.stock.kpi_movimientos_pie', ['dias' => $resumen['periodoDias']])"
                        :state="$resumen['movimientos'] > 0 ? 'info' : null"
                    />
                </div>
            @endif

            @if ($hayFiltrosActivos || $stock->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.stock.index')"
                        :active-count="$filtrosPanelActivos"
                    >
                        <input type="hidden" name="q" value="{{ $filtros['q'] }}">

                        <x-atoms.select
                            name="base_id"
                            id="filtro-base"
                            :label="__('inventario.stock.filtro_base')"
                            :options="$basesDisponibles"
                            :value="$filtros['base_id']"
                            :placeholder="__('inventario.stock.filtro_todos')"
                        />
                    </x-organisms.filter-panel>

                    <x-molecules.table-search
                        :action="route('panel.stock.index')"
                        :value="$filtros['q']"
                        :placeholder="__('inventario.stock.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($stock->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('inventario.stock.filtro_vacio_titulo')"
                        :detail="__('inventario.stock.filtro_vacio_detalle')"
                    />
                @else
                    {{-- Sin botón adentro: el vacío de un listado solo explica. El
                         alta ya está en la cabecera, y es el único botón sólido. --}}
                    <x-molecules.empty-state
                        icon="warehouse"
                        :title="__('inventario.stock.vacio_titulo')"
                        :detail="__('inventario.stock.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 2.2fr) minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1.4fr)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('inventario.stock.col_codigo') }}</span>
                        <span role="columnheader">{{ __('inventario.stock.col_base') }}</span>
                        <span role="columnheader">{{ __('inventario.stock.col_cantidad') }}</span>
                        <span role="columnheader">{{ __('inventario.stock.col_minimo') }}</span>
                        <span role="columnheader">{{ __('inventario.stock.col_alerta') }}</span>
                    </x-slot:head>

                    @foreach ($stock as $fila)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($stock->currentPage() - 1) * $stock->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-stock__repuesto">
                                <span class="ag-stock__codigo">{{ $fila->repuesto->codigo }}</span>
                                <span class="ag-stock__descripcion">{{ $fila->repuesto->descripcion }}</span>
                                {{-- Mismo patrón que `ag-ordenes__crear-trabajo`: link de solo
                                     navegación, ya filtrado a este repuesto y esta base. --}}
                                <a
                                    href="{{ route('panel.stock.movimientos.index', ['repuesto_id' => $fila->repuesto_id, 'base_id' => $fila->base_id]) }}"
                                    class="ag-stock__ver-movimientos"
                                >
                                    {{ __('inventario.stock.ver_movimientos') }}
                                </a>
                            </span>
                            <span role="cell">{{ $etiquetasBase[$fila->base_id] ?? "#{$fila->base_id}" }}</span>
                            <span role="cell" class="ag-stock__cantidad">
                                {{ FormatoCantidad::decimal($fila->cantidad) }}
                                <span class="ag-stock__unidad">{{ $fila->repuesto->unidad }}</span>
                            </span>
                            <span role="cell" class="ag-stock__cantidad">
                                {{ FormatoCantidad::decimal($fila->stock_minimo) }}
                                <span class="ag-stock__unidad">{{ $fila->repuesto->unidad }}</span>
                            </span>
                            <span role="cell">
                                @if ($fila->sin_existencias)
                                    <x-atoms.badge variant="danger" icon="error">
                                        {{ __('inventario.stock.alerta_sin_existencias') }}
                                    </x-atoms.badge>
                                @elseif ($fila->alerta)
                                    <x-atoms.badge variant="warning" icon="warning">
                                        {{ __('inventario.stock.alerta_activa') }}
                                    </x-atoms.badge>
                                @else
                                    {{ __('inventario.stock.sin_alerta') }}
                                @endif
                            </span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$stock" :aria-label="__('inventario.stock.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
