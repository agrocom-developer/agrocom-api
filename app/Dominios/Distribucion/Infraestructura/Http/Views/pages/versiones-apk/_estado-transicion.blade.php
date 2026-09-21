{{--
    Partial: «estado actual → estado destino» de una versión del APK, con los
    mismos badges (y colores) del listado. Va dentro del modal de confirmación
    para que se vea, antes de confirmar, a cuál estado se pasa. Mismo criterio
    que `finanzas::pages.rendiciones._estado-transicion`.

    Espera:
    - $desde, $hacia (string): valores de `EstadoVersionApk` (`pendiente`,
      `autorizada`, `rechazada`).
    - $tonoPorEstado (array<string, string>, opcional): estado → tono; sin él,
      `VersionesApkController::TONO_POR_ESTADO`, que es de donde sale el del
      listado.
--}}
@php
    $tonoPorEstado ??= \App\Dominios\Distribucion\Infraestructura\Http\Controllers\Web\VersionesApkController::TONO_POR_ESTADO;
@endphp

<x-molecules.state-transition
    :from-label="__('distribucion.versiones.estado_'.$desde)"
    :from-tone="$tonoPorEstado[$desde]"
    :to-label="__('distribucion.versiones.estado_'.$hacia)"
    :to-tone="$tonoPorEstado[$hacia]"
    :label="__('distribucion.versiones.estado_cambio_de_a', [
        'desde' => __('distribucion.versiones.estado_'.$desde),
        'hacia' => __('distribucion.versiones.estado_'.$hacia),
    ])"
/>
