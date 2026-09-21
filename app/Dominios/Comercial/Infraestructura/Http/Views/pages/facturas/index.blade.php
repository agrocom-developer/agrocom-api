{{--
    Page: facturas/index (GET /panel/facturas, panel.facturas.index)
    Listado de facturas (HU-31, tarea 45; homogeneizado en la tarea 120):
    arquetipo Listado, §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera →
    KPI → toolbar (filter-panel + table-search) → tabla → paginación. Sin
    acciones por fila ni columna de estado: una factura emitida es un snapshot
    inmutable (ni edición, ni baja, ni máquina de estados).

    Datos esperados (ver FacturasController::index()): la cáscara de
    CascaraPanel, más:
    - $facturas (LengthAwarePaginator<Factura>, con `contrato.cliente`
      precargada): fecha de emisión descendente.
    - $resumen (array{cantidad: int, monto: string, hectareas: string}): las
      tres cifras de la franja, resueltas con el MISMO filtro que la tabla
      (ListarFacturas::resumen()); la vista solo las formatea.
    - $filtros (array{q: string, cliente_id: int|null, contrato_id: int|null,
      desde: string|null, hasta: string|null}): valores aplicados, para dejar
      los campos con su valor tras el submit.
    - $clientesDisponibles (array<int, string>): id => razón social, solo los
      clientes con alguna factura.
    - $contratosDisponibles (array<int, string>): id => «Contrato #N — cliente»,
      solo los contratos con alguna factura.

    Gateada por `comercial.factura.ver`, verificado server-side en el
    controlador. El botón "Emitir factura" se oculta con `@puede`
    (presentación, no autorización — el servidor revalida en
    FacturasController).

    Estilos en resources/css/pages/facturas.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@use('App\Dominios\Comercial\Infraestructura\Http\FormatoMonto')

<x-templates.panel-shell :title="__('comercial.facturas.titulo')" :tema="$tema">
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
        :vista-actual="__('comercial.facturas.titulo')"
    >
        <div class="ag-facturas">
            <x-organisms.page-header
                :title="__('comercial.facturas.titulo')"
                :subtitle="__('comercial.facturas.subtitulo')"
            >
                @puede('comercial.factura.crear')
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.facturas.create')" variant="primary" icon="add">
                            {{ __('comercial.facturas.nueva') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-facturas__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
                $filtrosPanelActivos = collect(['cliente_id', 'contrato_id', 'desde', 'hasta'])
                    ->filter(fn ($campo) => $filtros[$campo] !== null && $filtros[$campo] !== '')
                    ->count();
            @endphp

            @if ($hayFiltrosActivos || $facturas->isNotEmpty())
                {{-- Franja de KPI (guía §6.2): bajo la cabecera, ANTES de la toolbar, y con el
                     mismo filtro que la tabla — el monto de arriba es la suma exacta de la
                     columna de abajo. Una cifra en cero va sin color. --}}
                <div class="ag-facturas__kpis">
                    <x-molecules.stat-card
                        :label="__('comercial.facturas.kpi_facturado')"
                        icon="payments"
                        :value="FormatoMonto::decimal($resumen['monto'])"
                        :value-suffix="__('comercial.facturas.unidad_moneda')"
                        :state="$resumen['monto'] !== '0.00' ? 'info' : null"
                    />
                    <x-molecules.stat-card
                        :label="__('comercial.facturas.kpi_cantidad')"
                        icon="receipt_long"
                        :value="$resumen['cantidad']"
                        :state="$resumen['cantidad'] > 0 ? 'distintivo-1' : null"
                    />
                    <x-molecules.stat-card
                        :label="__('comercial.facturas.kpi_hectareas')"
                        icon="landscape"
                        :value="FormatoMonto::decimal($resumen['hectareas'])"
                        :value-suffix="__('comercial.facturas.unidad_hectareas')"
                        :state="$resumen['hectareas'] !== '0.00' ? 'distintivo-2' : null"
                    />
                </div>

                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.facturas.index')"
                        :active-count="$filtrosPanelActivos"
                    >
                        <input type="hidden" name="q" value="{{ $filtros['q'] }}">

                        <x-atoms.select
                            name="cliente_id"
                            id="filtro-cliente"
                            :label="__('comercial.facturas.filtro_cliente')"
                            :options="$clientesDisponibles"
                            :value="$filtros['cliente_id']"
                            :placeholder="__('comercial.facturas.filtro_cliente_placeholder')"
                        />

                        <x-atoms.select
                            name="contrato_id"
                            id="filtro-contrato"
                            :label="__('comercial.facturas.filtro_contrato')"
                            :options="$contratosDisponibles"
                            :value="$filtros['contrato_id']"
                            :placeholder="__('comercial.facturas.filtro_contrato_placeholder')"
                        />

                        <x-atoms.date
                            name="desde"
                            id="filtro-desde"
                            :label="__('comercial.facturas.filtro_desde')"
                            :value="$filtros['desde']"
                            :placeholder="__('comercial.facturas.filtro_placeholder_desde')"
                        />

                        <x-atoms.date
                            name="hasta"
                            id="filtro-hasta"
                            :label="__('comercial.facturas.filtro_hasta')"
                            :value="$filtros['hasta']"
                            :placeholder="__('comercial.facturas.filtro_placeholder_hasta')"
                        />
                    </x-organisms.filter-panel>

                    <x-molecules.table-search
                        :action="route('panel.facturas.index')"
                        :value="$filtros['q']"
                        :placeholder="__('comercial.facturas.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($facturas->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('comercial.facturas.filtro_vacio_titulo')"
                        :detail="__('comercial.facturas.filtro_vacio_detalle')"
                    />
                @else
                    <x-molecules.empty-state
                        icon="receipt_long"
                        :title="__('comercial.facturas.vacio_titulo')"
                        :detail="__('comercial.facturas.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem 1.5fr 0.9fr 0.8fr 1fr 1fr 1.1fr 1fr">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('comercial.facturas.col_cliente') }}</span>
                        <span role="columnheader">{{ __('comercial.facturas.col_contrato') }}</span>
                        <span role="columnheader">{{ __('comercial.facturas.col_acta') }}</span>
                        <span role="columnheader" class="ag-index-table__cifra-head">{{ __('comercial.facturas.col_hectareas') }}</span>
                        <span role="columnheader" class="ag-index-table__cifra-head">{{ __('comercial.facturas.col_precio_ha') }}</span>
                        <span role="columnheader" class="ag-index-table__cifra-head">{{ __('comercial.facturas.col_monto') }}</span>
                        <span role="columnheader">{{ __('comercial.facturas.col_fecha_emision') }}</span>
                    </x-slot:head>

                    @foreach ($facturas as $factura)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($facturas->currentPage() - 1) * $facturas->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-facturas__cliente">{{ $factura->contrato->cliente->razon_social }}</span>
                            <span role="cell">{{ __('comercial.facturas.contrato_valor', ['id' => $factura->contrato_id]) }}</span>
                            <span role="cell">{{ __('comercial.facturas.acta_valor', ['id' => $factura->acta_id]) }}</span>
                            <span role="cell" class="ag-index-table__cifra">{{ FormatoMonto::decimal($factura->hectareas_facturadas) }}</span>
                            <span role="cell" class="ag-index-table__cifra">{{ __('comercial.facturas.precio_ha_valor', ['monto' => FormatoMonto::decimal($factura->precio_ha)]) }}</span>
                            <span role="cell" class="ag-index-table__cifra">{{ __('comercial.facturas.monto_valor', ['monto' => FormatoMonto::decimal($factura->monto)]) }}</span>
                            <span role="cell" class="ag-index-table__mono">{{ $factura->fecha_emision->format('d/m/Y') }}</span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$facturas" :aria-label="__('comercial.facturas.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
