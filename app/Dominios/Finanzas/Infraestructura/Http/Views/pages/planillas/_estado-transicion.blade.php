{{--
    Partial: «estado actual → estado destino» de un cambio de estado de
    planilla, con los mismos badges (y colores) del listado. Va dentro del
    modal de confirmación para que se vea, antes de confirmar, a cuál estado
    se pasa. Mismo criterio que `comercial::pages.contratos._estado-transicion`.

    Espera:
    - $desde, $hacia (string): valores de `EstadoPlanilla` (`borrador`,
      `aprobada`).
    - $tonoPorEstado (array<string, string>, opcional): estado → tono; sin él,
      `PlanillasController::TONO_POR_ESTADO`, que es de donde sale el del
      listado.
--}}
@php
    $tonoPorEstado ??= \App\Dominios\Finanzas\Infraestructura\Http\Controllers\Web\PlanillasController::TONO_POR_ESTADO;
@endphp

<x-molecules.state-transition
    :from-label="__('finanzas.planillas.estado.'.$desde)"
    :from-tone="$tonoPorEstado[$desde]"
    :to-label="__('finanzas.planillas.estado.'.$hacia)"
    :to-tone="$tonoPorEstado[$hacia]"
    :label="__('finanzas.planillas.estado_cambio_de_a', [
        'desde' => __('finanzas.planillas.estado.'.$desde),
        'hacia' => __('finanzas.planillas.estado.'.$hacia),
    ])"
/>
