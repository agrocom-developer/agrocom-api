{{--
    Partial: «estado actual → estado destino» del acceso de una cuenta, con los
    mismos badges (y colores) del listado. Va dentro del modal de confirmación
    de bloquear/desbloquear para que se vea, antes de confirmar, a cuál estado
    se pasa. Mismo criterio que `finanzas::pages.rendiciones._estado-transicion`.

    Espera:
    - $desde, $hacia (string): `activo` o `bloqueado`.
    - $tonoPorEstado (array<string, string>, opcional): estado → tono; sin él,
      `UsuariosController::TONO_POR_ESTADO`, que es de donde sale el del
      listado.
--}}
@php
    $tonoPorEstado ??= \App\Dominios\Seguridad\Infraestructura\Http\Controllers\Web\UsuariosController::TONO_POR_ESTADO;
@endphp

<x-molecules.state-transition
    :from-label="__('seguridad.usuarios.estado_'.$desde)"
    :from-tone="$tonoPorEstado[$desde]"
    :to-label="__('seguridad.usuarios.estado_'.$hacia)"
    :to-tone="$tonoPorEstado[$hacia]"
    :label="__('seguridad.usuarios.estado_cambio_de_a', [
        'desde' => __('seguridad.usuarios.estado_'.$desde),
        'hacia' => __('seguridad.usuarios.estado_'.$hacia),
    ])"
/>
