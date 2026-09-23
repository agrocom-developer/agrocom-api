{{--
    Page: stock/movimientos/index (GET /panel/stock/movimientos,
    panel.stock.movimientos.index)
    Listado de solo lectura de los asientos de `inv_movimientos` (tarea 133):
    arquetipo Listado, §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera
    → KPI → filtros → tabla → paginación. Es la mitad de LECTURA que le
    faltaba a Stock: el saldo de `stock/index.blade.php` es un agregado
    derivado, este listado es el detalle de cada asiento que lo explica.

    **Sin columna de acciones**: un movimiento es un asiento contable — ni
    se edita ni se borra (ver docblock de `MovimientoStock` y de
    `stock/index.blade.php`). Una tabla sin ninguna acción no lleva la
    columna vacía, ni el organism de acciones por fila del catálogo.

    Datos esperados (ver StockController::movimientos()): la cáscara de
    CascaraPanel, más:
    - $movimientos (LengthAwarePaginator<MovimientoStock>): del más reciente
      al más viejo, con `repuesto` precargado (sin N+1). Cada fila trae
      `instante` (Carbon, NO persistido): `created_at` ya convertido a la
      zona horaria de quien mira — la vista no calcula zonas.
    - $etiquetasBase (array<int, string>): nombre de base por id (origen y
      destino), ya resuelto por el controlador vía el contrato de Personal
      (MovimientoStock no tiene relación Eloquent hacia PerBase, son de
      módulos distintos). Un id sin etiqueta (base borrada) cae al `#id`
      crudo.
    - $basesDisponibles (array<int, string>): opciones del filtro de base.
    - $repuestosDisponibles (Collection<int, string>): opciones del filtro
      de repuesto (mismo formato que StockController::repuestosDisponibles()).
    - $tonoPorTipo (array<string, string>): tono del badge de cada tipo, de
      StockController::TONO_POR_TIPO.
    - $filtros (array{repuesto_id: ?int, base_id: ?int}): filtros aplicados,
      para dejar los selects con su valor tras el submit. Sin filtro de
      texto libre: `inv_movimientos` no tiene una columna de texto natural
      para buscar (`motivo` es libre y a menudo vacío).

    Cantidades y costos son DECIMAL: la vista solo los formatea
    (`FormatoCantidad`, sin `float`), nunca calcula con ellos (invariante 6).

    Gateada por `inventario.movimiento.ver` (el mismo permiso que el saldo),
    verificado server-side en el controlador.

    Estilos en resources/css/pages/stock.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@use('App\Dominios\Inventario\Infraestructura\Http\FormatoCantidad')
<x-templates.panel-shell :title="__('inventario.movimientos.titulo')" :tema="$tema">
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
        :vista-actual="__('inventario.movimientos.titulo')"
    >
        <div class="ag-stock-movimientos">
            <x-organisms.page-header
                :title="__('inventario.movimientos.titulo')"
                :subtitle="__('inventario.movimientos.subtitulo')"
            >
                <x-slot:actions>
                    <x-molecules.boton-volver :href="route('panel.stock.index')" :label="__('inventario.movimientos.volver')" />
                </x-slot:actions>
            </x-organisms.page-header>

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
                $filtrosPanelActivos = collect($filtros)->filter(fn ($valor) => $valor !== null && $valor !== '')->count();
            @endphp

            @if ($movimientos->isNotEmpty() || $hayFiltrosActivos)
                <div class="ag-stock-movimientos__kpis">
                    <x-molecules.stat-card
                        :label="__('inventario.movimientos.kpi_movimientos')"
                        icon="swap_horiz"
                        :value="$movimientos->total()"
                        :foot="__('inventario.movimientos.kpi_movimientos_pie')"
                    />
                </div>
            @endif

            @if ($hayFiltrosActivos || $movimientos->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.stock.movimientos.index')"
                        :active-count="$filtrosPanelActivos"
                    >
                        <x-atoms.select
                            name="repuesto_id"
                            id="filtro-repuesto"
                            :label="__('inventario.movimientos.filtro_repuesto')"
                            :options="$repuestosDisponibles"
                            :value="$filtros['repuesto_id']"
                            :placeholder="__('inventario.movimientos.filtro_repuesto_todos')"
                        />

                        <x-atoms.select
                            name="base_id"
                            id="filtro-base"
                            :label="__('inventario.movimientos.filtro_base')"
                            :options="$basesDisponibles"
                            :value="$filtros['base_id']"
                            :placeholder="__('inventario.movimientos.filtro_base_todas')"
                        />
                    </x-organisms.filter-panel>
                </div>
            @endif

            @if ($movimientos->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('inventario.movimientos.filtro_vacio_titulo')"
                        :detail="__('inventario.movimientos.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="receipt_long"
                        :title="__('inventario.movimientos.vacio_titulo')"
                        :detail="__('inventario.movimientos.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 1.1fr) minmax(0, 0.9fr) minmax(0, 1.6fr) minmax(0, 1fr) minmax(0, 1fr) minmax(0, 0.9fr) minmax(0, 1fr) minmax(0, 1.4fr)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('inventario.movimientos.col_fecha') }}</span>
                        <span role="columnheader">{{ __('inventario.movimientos.col_tipo') }}</span>
                        <span role="columnheader">{{ __('inventario.movimientos.col_repuesto') }}</span>
                        <span role="columnheader">{{ __('inventario.movimientos.col_base') }}</span>
                        <span role="columnheader">{{ __('inventario.movimientos.col_base_destino') }}</span>
                        <span role="columnheader" class="ag-index-table__cifra-head">{{ __('inventario.movimientos.col_cantidad') }}</span>
                        <span role="columnheader" class="ag-index-table__cifra-head">{{ __('inventario.movimientos.col_costo_unitario') }}</span>
                        <span role="columnheader">{{ __('inventario.movimientos.col_motivo') }}</span>
                    </x-slot:head>

                    @foreach ($movimientos as $movimiento)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($movimientos->currentPage() - 1) * $movimientos->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-index-table__mono">{{ $movimiento->instante->format('d/m/Y H:i') }}</span>
                            <span role="cell">
                                <x-atoms.badge :variant="$tonoPorTipo[$movimiento->tipo] ?? 'neutral'">
                                    {{ __('inventario.aside.tipo_corto.'.$movimiento->tipo) }}
                                </x-atoms.badge>
                            </span>
                            <span role="cell" class="ag-stock-movimientos__repuesto">
                                <span class="ag-stock-movimientos__codigo">{{ $movimiento->repuesto->codigo }}</span>
                                <span class="ag-stock-movimientos__descripcion">{{ $movimiento->repuesto->descripcion }}</span>
                            </span>
                            <span role="cell">{{ $etiquetasBase[$movimiento->base_id] ?? "#{$movimiento->base_id}" }}</span>
                            <span role="cell">
                                @if ($movimiento->base_destino_id !== null)
                                    {{ $etiquetasBase[$movimiento->base_destino_id] ?? "#{$movimiento->base_destino_id}" }}
                                @else
                                    {{ __('inventario.movimientos.sin_dato') }}
                                @endif
                            </span>
                            <span role="cell" class="ag-index-table__cifra">
                                {{ FormatoCantidad::decimal($movimiento->cantidad) }}
                                <span class="ag-stock-movimientos__unidad">{{ $movimiento->repuesto->unidad }}</span>
                            </span>
                            <span role="cell" class="ag-index-table__cifra">
                                @if ($movimiento->costo_unitario !== null)
                                    {{ __('inventario.movimientos.costo_valor', ['monto' => FormatoCantidad::decimal($movimiento->costo_unitario)]) }}
                                @else
                                    {{ __('inventario.movimientos.sin_dato') }}
                                @endif
                            </span>
                            <span role="cell">{{ $movimiento->motivo ?? __('inventario.movimientos.sin_dato') }}</span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$movimientos" :aria-label="__('inventario.movimientos.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
