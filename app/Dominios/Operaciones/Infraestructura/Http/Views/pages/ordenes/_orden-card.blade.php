{{--
    Partial: tarjeta de una orden — vista en grilla del listado
    (`?vista=grilla`, homogeneización 17/9/2026). Mismos datos que la fila de
    la tabla, reordenados en tarjeta; mismo bloque de acciones que la fila
    (`_orden-acciones.blade.php`), sin duplicar ese markup.

    Simplificado respecto al mockup de referencia (pedido explícito del
    usuario, que advirtió sobrecarga): sin la fila de chips de
    Aplic./Equipos/Tipo de aplicación ni los chips de límites/parámetros de
    vuelo — esa es información de la ficha de detalle (`show.blade.php`,
    a un click de "Ver"), no del resumen en grilla.

    Espera: $orden (con `categoriaInsumo` cargada, ver
    `ListarOrdenesAplicacion::ejecutar()`), $etiquetasContrato,
    $previstasPorContrato, $resumen, $variantePorEstado — mismas variables que
    ya arma `index.blade.php` para la fila de tabla. La banda del medio
    muestra cuántos lotes tiene la orden y cuántas órdenes de trabajo se
    hicieron; con incidencias o pausas en el campo, el badge "Con
    inconvenientes" (informativo, ADR 0022).
--}}
@php
    $estadoValor = $orden->estado->value;
    $datosOrden = $resumen[$orden->id] ?? ['lotes' => 0, 'ordenes_trabajo' => 0, 'incidencias' => 0, 'pausas' => 0];
    $previstas = $previstasPorContrato[$orden->contrato_id] ?? null;
    $tieneInconvenientes = ($datosOrden['incidencias'] + $datosOrden['pausas']) > 0;
    $dosisTexto = $orden->kilos_por_vuelo !== null
        ? __('operaciones.ordenes.dosis_kilos_por_vuelo', ['cantidad' => number_format((float) $orden->kilos_por_vuelo, 2, ',', '.')])
        : ($orden->litros_ha !== null
            ? __('operaciones.ordenes.dosis_litros_ha', ['cantidad' => number_format((float) $orden->litros_ha, 2, ',', '.')])
            : '—');
@endphp
<article class="ag-ordenes-card" data-estado="{{ $variantePorEstado[$estadoValor] }}">
    <div class="ag-ordenes-card__head">
        <div class="ag-ordenes-card__identidad">
            <p class="ag-ordenes-card__codigo">
                {{ $previstas !== null ? __('operaciones.ordenes.nro_aplicacion_display', ['nro' => $orden->nro_aplicacion, 'total' => $previstas]) : __('operaciones.ordenes.col_aplicacion').' #'.$orden->nro_aplicacion }}
            </p>
            <p class="ag-ordenes-card__cliente">{{ $etiquetasContrato[$orden->contrato_id] ?? "#{$orden->contrato_id}" }}</p>
        </div>
        <div class="ag-ordenes__estados">
            <x-atoms.badge :variant="$variantePorEstado[$estadoValor]">
                {{ __('operaciones.estado.'.$estadoValor) }}
            </x-atoms.badge>
            @if ($tieneInconvenientes)
                <x-atoms.badge variant="warning" icon="warning">
                    {{ __('operaciones.ordenes.badge_inconvenientes') }}
                </x-atoms.badge>
            @endif
        </div>
    </div>

    <div class="ag-ordenes-card__lote">
        <span class="ag-ordenes-card__lote-punto ag-ordenes-card__lote-punto--{{ $variantePorEstado[$estadoValor] }}" aria-hidden="true"></span>
        <span>{{ trans_choice('operaciones.ordenes.lotes_cantidad', $datosOrden['lotes'], ['cantidad' => $datosOrden['lotes']]) }}</span>
        <span class="ag-ordenes-card__lote-hectareas">{{ trans_choice('operaciones.ordenes.ordenes_trabajo_cantidad', $datosOrden['ordenes_trabajo'], ['cantidad' => $datosOrden['ordenes_trabajo']]) }}</span>
    </div>

    <div class="ag-ordenes-card__datos">
        <div class="ag-ordenes-card__dato">
            <p class="ag-ordenes-card__dato-label">{{ __('operaciones.ordenes.col_tipo_aplicacion') }}</p>
            <p class="ag-ordenes-card__dato-valor">{{ __('operaciones.tipo_aplicacion.'.$orden->tipo_aplicacion->value) }}</p>
        </div>
        <div class="ag-ordenes-card__dato">
            <p class="ag-ordenes-card__dato-label">{{ __('operaciones.ordenes.campo_categoria_insumo') }}</p>
            <p class="ag-ordenes-card__dato-valor">{{ $orden->categoriaInsumo?->nombre ?? '—' }}</p>
        </div>
        <div class="ag-ordenes-card__dato">
            <p class="ag-ordenes-card__dato-label">{{ __('operaciones.ordenes.col_dosis') }}</p>
            <p class="ag-ordenes-card__dato-valor ag-ordenes__mono">{{ $dosisTexto }}</p>
        </div>
    </div>

    <div class="ag-ordenes-card__pie">
        <div class="ag-ordenes-card__pie-fecha">
            <span class="ag-ordenes-card__pie-fecha-valor">{{ $orden->fecha_emision->format('d/m/Y') }}</span>
        </div>

        @include('operaciones::pages.ordenes._orden-acciones', ['orden' => $orden, 'contexto' => 'grilla-'])
    </div>
</article>
