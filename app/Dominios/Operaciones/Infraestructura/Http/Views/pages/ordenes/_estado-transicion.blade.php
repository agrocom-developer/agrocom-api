{{--
    Partial: «estado actual → estado destino» de un cambio de estado de una
    orden de aplicación, con los mismos badges (y colores) del listado, el
    detalle y los pasos (`PasosDeOrden::TONO_POR_ESTADO`). Va dentro del modal
    de confirmación para que se vea, antes de confirmar, a cuál estado se
    pasa — la misma simbología en todo objeto con máquina de estados
    (21/9/2026, pedido directo; la referencia es el de contratos). Lo usa
    `_orden-modales.blade.php`, que comparten el listado, el detalle y la
    ficha de edición.

    Espera:
    - $desde, $hacia (string): valores de `EstadoOrdenAplicacion`.
--}}
@php
    $tonoPorEstado = \App\Dominios\Operaciones\Infraestructura\Http\PasosDeOrden::TONO_POR_ESTADO;
@endphp

<x-molecules.state-transition
    :from-label="__('operaciones.estado.'.$desde)"
    :from-tone="$tonoPorEstado[$desde]"
    :to-label="__('operaciones.estado.'.$hacia)"
    :to-tone="$tonoPorEstado[$hacia]"
    :label="__('operaciones.ordenes.estado_cambio_de_a', [
        'desde' => __('operaciones.estado.'.$desde),
        'hacia' => __('operaciones.estado.'.$hacia),
    ])"
/>
