{{--
    Page: facturas/index (GET /panel/facturas, panel.facturas.index)
    Listado de facturas (HU-31, tarea 45): arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → tabla → paginación. Sin
    filtros propios todavía (ver ListarFacturas) y sin acciones por fila: una
    factura emitida es un snapshot inmutable, mismo criterio que
    `anticipos/index.blade.php` pero sin siquiera baja.

    Datos esperados (ver FacturasController::index()): la cáscara de
    CascaraPanel, más:
    - $facturas (LengthAwarePaginator<Factura>, con `contrato.cliente`
      precargada): fecha de emisión descendente.

    Gateada por `comercial.factura.ver`, verificado server-side en el
    controlador. El botón "Emitir factura" se oculta con `@puede`
    (presentación, no autorización — el servidor revalida en
    FacturasController).

    Estilos en resources/css/pages/facturas.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
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
                        <x-atoms.button href="{{ route('panel.facturas.create') }}" variant="primary" icon="add">
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

            @if ($facturas->isEmpty())
                <x-molecules.empty-state
                    icon="receipt_long"
                    :title="__('comercial.facturas.vacio_titulo')"
                    :detail="__('comercial.facturas.vacio_detalle')"
                />
            @else
                <div class="ag-facturas__tabla" role="table">
                    <div class="ag-facturas__head" role="row">
                        <span role="columnheader">{{ __('comercial.facturas.col_cliente') }}</span>
                        <span role="columnheader">{{ __('comercial.facturas.col_acta') }}</span>
                        <span role="columnheader">{{ __('comercial.facturas.col_hectareas') }}</span>
                        <span role="columnheader">{{ __('comercial.facturas.col_precio_ha') }}</span>
                        <span role="columnheader">{{ __('comercial.facturas.col_monto') }}</span>
                        <span role="columnheader">{{ __('comercial.facturas.col_fecha_emision') }}</span>
                    </div>

                    @foreach ($facturas as $factura)
                        <div class="ag-facturas__fila" role="row">
                            <span role="cell" class="ag-facturas__cliente">{{ $factura->contrato->cliente->razon_social }}</span>
                            <span role="cell">{{ __('comercial.facturas.acta_valor', ['id' => $factura->acta_id]) }}</span>
                            <span role="cell" class="ag-facturas__cifra">{{ number_format((float) $factura->hectareas_facturadas, 2, ',', '.') }}</span>
                            <span role="cell" class="ag-facturas__cifra">{{ __('comercial.facturas.precio_ha_valor', ['monto' => number_format((float) $factura->precio_ha, 2, ',', '.')]) }}</span>
                            <span role="cell" class="ag-facturas__cifra">{{ __('comercial.facturas.monto_valor', ['monto' => number_format((float) $factura->monto, 2, ',', '.')]) }}</span>
                            <span role="cell" class="ag-facturas__cifra">{{ $factura->fecha_emision->format('d/m/Y') }}</span>
                        </div>
                    @endforeach
                </div>

                @if ($facturas->hasPages())
                    <nav class="ag-facturas__paginacion" aria-label="{{ __('comercial.facturas.paginacion_aria') }}">
                        @if (! $facturas->onFirstPage())
                            <x-atoms.button href="{{ $facturas->previousPageUrl() }}" variant="outline" size="sm" icon="chevron_left">
                                {{ __('comercial.facturas.paginacion_anterior') }}
                            </x-atoms.button>
                        @endif

                        <span class="ag-facturas__paginacion-info">
                            {{ __('comercial.facturas.paginacion_info', ['actual' => $facturas->currentPage(), 'total' => $facturas->lastPage()]) }}
                        </span>

                        @if ($facturas->hasMorePages())
                            <x-atoms.button href="{{ $facturas->nextPageUrl() }}" variant="outline" size="sm" icon="chevron_right" iconPosition="end">
                                {{ __('comercial.facturas.paginacion_siguiente') }}
                            </x-atoms.button>
                        @endif
                    </nav>
                @endif
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
