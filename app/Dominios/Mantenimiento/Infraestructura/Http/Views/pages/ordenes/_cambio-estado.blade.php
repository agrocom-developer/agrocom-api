{{--
    Partial: los modales del cambio de estado de UNA orden de mantenimiento,
    para los pasos de `molecules/step-arrow` de su ficha
    (`_formulario.blade.php`). Mismo papel que
    `comercial::pages.contratos._cambio-estado`, con una diferencia.

    **Acá no hay un `<form>` propio.** El de contratos arma uno por transición
    porque el cambio de estado viaja solo; el cierre de una orden viaja con la
    descripción final y los repuestos elegidos, que son campos del formulario
    de la ficha. Así que el modal apunta por `formId` a ESE formulario
    (`#orden-mantenimiento-form`, que ya postea a
    `panel.ordenes-mantenimiento.cerrar`) y lo envía al confirmar. Por eso el
    partial va igual FUERA del `<form>`: un `<form>` no puede anidarse en otro,
    y el modal de Bootstrap tiene que poder abrirse desde los dos disparadores
    (el paso de arriba y el botón de la barra de acciones).

    Quien aplica la transición, con sus guardas de stock y de descripción
    final, sigue siendo `MaquinaEstadosOrdenMantenimiento` (invariante 7 de
    CLAUDE.md).

    Espera:
    - $orden (OrdenMantenimiento).
    - $pasosEstado (list<array{...}>): los de
      `PasosDeOrdenMantenimiento::armar()`. Solo los pasos `next` traen modal;
      su `id` es el `modal` del paso.

    El TONO de cada modal es el del estado al que se pasa —el de su badge, ver
    `PasosDeOrdenMantenimiento::TONO_POR_ESTADO`— y dentro va la ficha «estado
    actual → estado destino» (`molecules/state-transition`), para ver antes de
    confirmar a cuál se pasa.
--}}
@php
    $tonoPorEstado = \App\Dominios\Mantenimiento\Infraestructura\Http\PasosDeOrdenMantenimiento::TONO_POR_ESTADO;
@endphp

@foreach ($pasosEstado as $paso)
    @continue($paso['status'] !== 'next')

    <x-molecules.confirm-modal
        :id="$paso['modal']"
        form-id="orden-mantenimiento-form"
        :title="__('mantenimiento.ordenes.confirmar_cierre_titulo')"
        :message="__('mantenimiento.ordenes.confirmar_cierre')"
        :confirm-label="__('mantenimiento.ordenes.boton_cerrar')"
        :tone="$tonoPorEstado[$paso['key']]"
        modal-icon="build"
    >
        <x-molecules.state-transition
            :from-label="__('mantenimiento.orden.estado.'.$orden->estado->value)"
            :from-tone="$tonoPorEstado[$orden->estado->value]"
            :to-label="__('mantenimiento.orden.estado.'.$paso['key'])"
            :to-tone="$tonoPorEstado[$paso['key']]"
            :label="__('mantenimiento.ordenes.estado_cambio_de_a', [
                'desde' => __('mantenimiento.orden.estado.'.$orden->estado->value),
                'hacia' => __('mantenimiento.orden.estado.'.$paso['key']),
            ])"
        />
    </x-molecules.confirm-modal>
@endforeach
