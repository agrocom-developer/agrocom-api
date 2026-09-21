{{--
    Partial: «estado actual → estado destino» de un cambio de estado de
    rendición, con los mismos badges (y colores) del listado. Va dentro del
    modal de confirmación para que se vea, antes de confirmar, a cuál estado
    se pasa. Mismo criterio que `comercial::pages.contratos._estado-transicion`.

    Espera:
    - $desde, $hacia (string): valores de `EstadoRendicion` (`abierta`,
      `presentada`, `aprobada`).
    - $tonoPorEstado (array<string, string>, opcional): estado → tono; sin él,
      `RendicionesController::TONO_POR_ESTADO`, que es de donde sale el del
      listado.
--}}
@php
    $tonoPorEstado ??= \App\Dominios\Finanzas\Infraestructura\Http\Controllers\Web\RendicionesController::TONO_POR_ESTADO;
@endphp

<x-molecules.state-transition
    :from-label="__('finanzas.rendiciones.estado.'.$desde)"
    :from-tone="$tonoPorEstado[$desde]"
    :to-label="__('finanzas.rendiciones.estado.'.$hacia)"
    :to-tone="$tonoPorEstado[$hacia]"
    :label="__('finanzas.rendiciones.estado_cambio_de_a', [
        'desde' => __('finanzas.rendiciones.estado.'.$desde),
        'hacia' => __('finanzas.rendiciones.estado.'.$hacia),
    ])"
/>
