{{--
    Partial: «estado actual → estado destino» de un cambio de estado de
    campaña, con los mismos badges (y colores) del listado. Va dentro del
    modal de confirmación para que se vea, antes de confirmar, a cuál estado
    se pasa — la misma simbología en todo objeto con máquina de estados
    (21/9/2026, pedido directo; la referencia es el de contratos). Lo usan los
    pasos de la ficha de edición (`_cambio-estado.blade.php`) y las acciones
    del listado (`index.blade.php`).

    Espera:
    - $desde, $hacia (string): valores de `EstadoCampania` (`abierta`, …).
--}}
@php
    $tonoPorEstado = \App\Dominios\Campania\Infraestructura\Http\Controllers\Web\CampaniasController::TONO_POR_ESTADO;
@endphp

<x-molecules.state-transition
    :from-label="__('campania.campania.estado.'.$desde)"
    :from-tone="$tonoPorEstado[$desde]"
    :to-label="__('campania.campania.estado.'.$hacia)"
    :to-tone="$tonoPorEstado[$hacia]"
    :label="__('campania.campanias.estado_cambio_de_a', [
        'desde' => __('campania.campania.estado.'.$desde),
        'hacia' => __('campania.campania.estado.'.$hacia),
    ])"
/>
