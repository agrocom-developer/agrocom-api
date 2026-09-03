{{--
    Page: reportes-comerciales/index (GET /panel/reportes/comercial, panel.reportes.comercial.index)
    Reporte comercial de avance (HU-32, tarea 46): arquetipo Listado, §6.2 de
    docs/diseno/guia_pantalla_panel.md — cabecera → filtros → tabla. Solo
    lectura, sin paginación: agrega datos ya persistidos por contrato, un
    volumen acotado por el tamaño real de la cartera de clientes.

    Datos esperados (ver ReportesComercialesController::index()): la cáscara
    de CascaraPanel, más:
    - $avance (list<array{contratoId, clienteNombre, hectareasContratadas,
      hectareasAplicadas, hectareasFacturadas, montoFacturado}>): ya agregado
      por ObtenerAvanceComercial, la vista no calcula nada.
    - $filtros (array{cliente_id: ?int, contrato_id: ?int}): valores
      actualmente aplicados, para dejar el formulario con la selección hecha
      tras el submit.
    - $clientesDisponibles (Collection<Cliente> con solo id/razon_social),
      $contratosDisponibles (Collection<Contrato> con solo id/cliente_id,
      `cliente` precargada): opciones de los selects.

    Gateada por `comercial.reporte.ver` (exclusivo del dueño), verificado
    server-side en el controlador.

    Estilos en resources/css/pages/reportes-comerciales.css — cero color
    hardcodeado (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('comercial.reportes_comerciales.titulo')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :campana="$campana"
        :periodo="$periodo"
        :version="$version"
        :vista-actual="__('comercial.reportes_comerciales.titulo')"
    >
        <div class="ag-reportes-comerciales">
            <x-organisms.page-header
                :title="__('comercial.reportes_comerciales.titulo')"
                :subtitle="__('comercial.reportes_comerciales.subtitulo')"
            >
                <x-slot:actions>
                    <x-atoms.button
                        href="{{ route('panel.reportes.comercial.exportar', $filtros) }}"
                        variant="outline"
                        icon="download"
                    >
                        {{ __('comercial.reportes_comerciales.exportar') }}
                    </x-atoms.button>
                </x-slot:actions>
            </x-organisms.page-header>

            @php $hayFiltrosActivos = $filtros['cliente_id'] !== null || $filtros['contrato_id'] !== null; @endphp

            <form method="GET" action="{{ route('panel.reportes.comercial.index') }}" class="ag-reportes-comerciales__filtros">
                <div class="ag-input">
                    <label for="filtro-cliente" class="ag-input__label">{{ __('comercial.reportes_comerciales.filtro_cliente') }}</label>
                    <div class="ag-input__control">
                        <select name="cliente_id" id="filtro-cliente" class="ag-input__field">
                            <option value="">{{ __('comercial.reportes_comerciales.filtro_todos') }}</option>
                            @foreach ($clientesDisponibles as $cliente)
                                <option value="{{ $cliente->id }}" @selected($filtros['cliente_id'] === $cliente->id)>
                                    {{ $cliente->razon_social }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="ag-input">
                    <label for="filtro-contrato" class="ag-input__label">{{ __('comercial.reportes_comerciales.filtro_contrato') }}</label>
                    <div class="ag-input__control">
                        <select name="contrato_id" id="filtro-contrato" class="ag-input__field">
                            <option value="">{{ __('comercial.reportes_comerciales.filtro_todos') }}</option>
                            @foreach ($contratosDisponibles as $contrato)
                                <option value="{{ $contrato->id }}" @selected($filtros['contrato_id'] === $contrato->id)>
                                    {{ __('comercial.reportes_comerciales.filtro_contrato_opcion', ['id' => $contrato->id, 'cliente' => $contrato->cliente->razon_social]) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="ag-reportes-comerciales__filtros-acciones">
                    <x-atoms.button type="submit" variant="primary" size="md" icon="filter_alt">
                        {{ __('comercial.reportes_comerciales.filtrar') }}
                    </x-atoms.button>

                    @if ($hayFiltrosActivos)
                        <x-atoms.button href="{{ route('panel.reportes.comercial.index') }}" variant="text" size="md">
                            {{ __('comercial.reportes_comerciales.limpiar_filtros') }}
                        </x-atoms.button>
                    @endif
                </div>
            </form>

            @if (empty($avance))
                <x-molecules.alert-strip variant="info" icon="insert_chart" class="ag-reportes-comerciales__aviso">
                    {{ __($hayFiltrosActivos ? 'comercial.reportes_comerciales.filtro_vacio' : 'comercial.reportes_comerciales.vacio') }}
                </x-molecules.alert-strip>
            @else
                <div class="ag-reportes-comerciales__tabla" role="table">
                    <div class="ag-reportes-comerciales__head" role="row">
                        <span role="columnheader">{{ __('comercial.reportes_comerciales.col_cliente') }}</span>
                        <span role="columnheader">{{ __('comercial.reportes_comerciales.col_contrato') }}</span>
                        <span role="columnheader">{{ __('comercial.reportes_comerciales.col_hectareas_contratadas') }}</span>
                        <span role="columnheader">{{ __('comercial.reportes_comerciales.col_hectareas_aplicadas') }}</span>
                        <span role="columnheader">{{ __('comercial.reportes_comerciales.col_hectareas_facturadas') }}</span>
                        <span role="columnheader">{{ __('comercial.reportes_comerciales.col_monto_facturado') }}</span>
                    </div>

                    @foreach ($avance as $fila)
                        <div class="ag-reportes-comerciales__fila" role="row">
                            <span role="cell" class="ag-reportes-comerciales__cliente">{{ $fila['clienteNombre'] }}</span>
                            <span role="cell">{{ __('comercial.reportes_comerciales.contrato_valor', ['id' => $fila['contratoId']]) }}</span>
                            <span role="cell" class="ag-reportes-comerciales__cifra">{{ __('comercial.reportes_comerciales.hectareas_valor', ['cantidad' => number_format((float) $fila['hectareasContratadas'], 2, ',', '.')]) }}</span>
                            <span role="cell" class="ag-reportes-comerciales__cifra">{{ __('comercial.reportes_comerciales.hectareas_valor', ['cantidad' => number_format((float) $fila['hectareasAplicadas'], 2, ',', '.')]) }}</span>
                            <span role="cell" class="ag-reportes-comerciales__cifra">{{ __('comercial.reportes_comerciales.hectareas_valor', ['cantidad' => number_format((float) $fila['hectareasFacturadas'], 2, ',', '.')]) }}</span>
                            <span role="cell" class="ag-reportes-comerciales__cifra">{{ __('comercial.reportes_comerciales.monto_valor', ['monto' => number_format((float) $fila['montoFacturado'], 2, ',', '.')]) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
