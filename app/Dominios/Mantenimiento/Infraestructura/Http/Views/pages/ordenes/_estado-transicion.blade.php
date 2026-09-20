{{--
    Partial: «estado actual → estado destino» de un cambio de estado de una
    orden de mantenimiento, con los mismos badges (y colores) del listado. Va
    dentro del modal —de confirmación o de aviso— para que se vea, antes de
    seguir, a cuál estado se pasa. Lo usan los pasos de la ficha
    (`_cambio-estado.blade.php`) y las acciones del listado
    (`index.blade.php`).

    Mismo papel que `comercial::pages.contratos._estado-transicion`, y por el
    mismo motivo vive en el módulo y no en el catálogo: lo genérico ya es
    `molecules/state-transition`; lo de acá es el par de claves de idioma y el
    mapa de tonos de ESTE objeto.

    Espera:
    - $desde, $hacia (string): valores de `EstadoOrdenMantenimiento`
      (`abierta`, `cerrada`).
--}}
@php
    $tonoPorEstado = \App\Dominios\Mantenimiento\Infraestructura\Http\PasosDeOrdenMantenimiento::TONO_POR_ESTADO;
@endphp

<x-molecules.state-transition
    :from-label="__('mantenimiento.orden.estado.'.$desde)"
    :from-tone="$tonoPorEstado[$desde]"
    :to-label="__('mantenimiento.orden.estado.'.$hacia)"
    :to-tone="$tonoPorEstado[$hacia]"
    :label="__('mantenimiento.ordenes.estado_cambio_de_a', [
        'desde' => __('mantenimiento.orden.estado.'.$desde),
        'hacia' => __('mantenimiento.orden.estado.'.$hacia),
    ])"
/>
