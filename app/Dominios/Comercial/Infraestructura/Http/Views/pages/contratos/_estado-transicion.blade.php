{{--
    Partial: «estado actual → estado destino» de un cambio de estado de
    contrato, con los mismos badges (y colores) del listado. Va dentro del
    modal de confirmación —o de aviso— para que se vea, antes de confirmar, a
    cuál estado se pasa. Lo usan los pasos de la ficha de edición
    (`_cambio-estado.blade.php`) y las acciones del listado (`index.blade.php`).

    Espera:
    - $desde, $hacia (string): valores de `EstadoContrato` (`vigente`, …).
    - $tonoPorEstado (array<string, string>, opcional): estado → tono; sin él,
      `PasosDeContrato::TONO_POR_ESTADO`, que es de donde sale el del listado.
--}}
@php
    $tonoPorEstado ??= \App\Dominios\Comercial\Infraestructura\Http\PasosDeContrato::TONO_POR_ESTADO;
@endphp

<div
    class="ag-contratos-estado__transicion"
    role="group"
    aria-label="{{ __('comercial.contratos.estado_cambio_de_a', [
        'desde' => __('comercial.contrato.estado.'.$desde),
        'hacia' => __('comercial.contrato.estado.'.$hacia),
    ]) }}"
>
    <x-atoms.badge :variant="$tonoPorEstado[$desde]">{{ __('comercial.contrato.estado.'.$desde) }}</x-atoms.badge>
    <x-atoms.icon name="arrow_forward" size="sm" class="ag-contratos-estado__flecha" />
    <x-atoms.badge :variant="$tonoPorEstado[$hacia]">{{ __('comercial.contrato.estado.'.$hacia) }}</x-atoms.badge>
</div>
