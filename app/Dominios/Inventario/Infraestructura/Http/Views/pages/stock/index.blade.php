{{--
    Page: stock/index (GET /panel/stock, panel.stock.index)
    Listado del stock agregado por (repuesto, base), con alerta de mínimo
    (HU-36, tarea 52): arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → filtros → tabla →
    paginación. Mismo molde que baterias/index.blade.php (tarea 51), con la
    alerta de reposición por fila en vez de la de retiro.

    Datos esperados (ver StockController::index()): la cáscara de
    CascaraPanel, más:
    - $stock (LengthAwarePaginator<Stock>): base ascendente, con `repuesto`
      precargado (`with('repuesto')` en ListarStock — sin N+1). Cada fila ya
      trae `alerta` (bool) calculada por ListarStock — la vista no evalúa
      ningún umbral.
    - $etiquetasBase (array<int, string>): nombre de base por id, ya
      resuelto por el controlador (Stock no tiene relación Eloquent hacia
      PerBase, son de módulos distintos). Un id sin etiqueta (base borrada)
      cae al `#id` crudo.
    - $basesDisponibles (Collection<int, string>): opciones del filtro de
      base.
    - $filtros (array{q: string, base_id: ?int}): filtros aplicados, para
      dejar los campos con su valor tras el submit.

    Gateada por `inventario.movimiento.ver`, verificado server-side en el
    controlador. El botón "Registrar movimiento" se oculta con `@puede`
    (presentación, no autorización — el servidor revalida en
    StockController). Sin acciones de editar/eliminar por fila: `inv_stock`
    es un agregado derivado, de solo lectura desde esta pantalla.

    Estilos en resources/css/pages/stock.css — cero color hardcodeado
    (CLAUDE.md invariante 11). El badge de alerta usa "warning" (ámbar),
    mismo criterio que la columna de alerta de baterias/index.
--}}
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
                @puede('inventario.movimiento.crear')
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.stock.movimientos.create')" variant="primary" icon="add">
                            {{ __('inventario.stock.nuevo_movimiento') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-stock__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
            @endphp

            @if ($hayFiltrosActivos || $stock->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-molecules.table-search
                        :action="route('panel.stock.index')"
                        :value="$filtros['q']"
                        :placeholder="__('inventario.stock.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>

                <form method="GET" action="{{ route('panel.stock.index') }}" class="ag-filtros ag-stock__filtros">
                    <input type="hidden" name="q" value="{{ $filtros['q'] }}">

                    <x-atoms.select
                        name="base_id"
                        id="filtro-base"
                        :label="__('inventario.stock.filtro_base')"
                        :options="$basesDisponibles"
                        :value="$filtros['base_id']"
                        :placeholder="__('inventario.stock.filtro_todos')"
                    />

                    <div class="ag-filtros__acciones ag-stock__filtros-acciones">
                        <x-atoms.button type="submit" variant="primary" size="md" icon="search">
                            {{ __('inventario.stock.filtrar') }}
                        </x-atoms.button>

                        @if ($filtros['base_id'] !== null)
                            <x-atoms.button :href="route('panel.stock.index')" variant="text" size="md">
                                {{ __('inventario.stock.limpiar_filtro') }}
                            </x-atoms.button>
                        @endif
                    </div>
                </form>
            @endif

            @if ($stock->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.alert-strip variant="info" icon="warehouse" class="ag-stock__aviso">
                        {{ __('inventario.stock.filtro_vacio') }}
                    </x-molecules.alert-strip>
                @else
                    <x-molecules.empty-state
                        icon="warehouse"
                        :title="__('inventario.stock.vacio_titulo')"
                        :detail="__('inventario.stock.vacio_detalle')"
                    />
                @endif
            @else
                <div class="ag-stock__tabla" role="table">
                    <div class="ag-stock__head" role="row">
                        <span role="columnheader" class="ag-stock__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('inventario.stock.col_codigo') }}</span>
                        <span role="columnheader">{{ __('inventario.stock.col_base') }}</span>
                        <span role="columnheader">{{ __('inventario.stock.col_cantidad') }}</span>
                        <span role="columnheader">{{ __('inventario.stock.col_minimo') }}</span>
                        <span role="columnheader">{{ __('inventario.stock.col_alerta') }}</span>
                    </div>

                    @foreach ($stock as $fila)
                        <div class="ag-stock__fila" role="row">
                            <span role="cell" class="ag-stock__indice">
                                {{ ($stock->currentPage() - 1) * $stock->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-stock__codigo">{{ $fila->repuesto->codigo }} — {{ $fila->repuesto->descripcion }}</span>
                            <span role="cell">{{ $etiquetasBase[$fila->base_id] ?? "#{$fila->base_id}" }}</span>
                            <span role="cell">{{ $fila->cantidad }}</span>
                            <span role="cell">{{ $fila->stock_minimo }}</span>
                            <span role="cell">
                                @if ($fila->alerta)
                                    <x-atoms.badge variant="warning" icon="warning">
                                        {{ __('inventario.stock.alerta_activa') }}
                                    </x-atoms.badge>
                                @else
                                    {{ __('inventario.stock.sin_alerta') }}
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>

                <x-molecules.pagination :paginator="$stock" :aria-label="__('inventario.stock.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
