{{--
    Partial: formularios y modales de confirmación de las acciones de aprobación
    de una ficha (ADR 0032). Cada acción tiene su `<form>` oculto y su
    `confirm-modal` con la ficha «estado actual → estado destino»; rechazar,
    suspender y dar de baja piden el motivo, que viaja con el form por el
    atributo `form` (los controles viven en el modal, el form fuera).

    Espera:
    - $acciones (list<AccionAprobacion>), $estado (EstadoAprobacion), $nombre (string).
    - $urlResolver (string): a dónde se envía la decisión (POST).
    - $modalBase (string): prefijo del id de los modales (el mismo de `_maquina-estado-aprobacion`).
    - $desde (string|null): `listado` cuando las acciones salen de una fila del listado; el servidor
      vuelve entonces al listado y no a la ficha.
--}}
@php
    $tonoPorAccion = [
        'aprobar' => 'success',
        'rehabilitar' => 'success',
        'rechazar' => 'danger',
        'suspender' => 'warning',
        'dar_de_baja' => 'danger',
    ];
@endphp
@foreach ($acciones as $accion)
    @php
        $formId = "{$modalBase}-{$accion->value}-form";
        $destino = $accion->estadoDestino();
    @endphp
    <form id="{{ $formId }}" method="POST" action="{{ $urlResolver }}" hidden>
        @csrf
        <input type="hidden" name="accion" value="{{ $accion->value }}">
        @if (($desde ?? null) !== null)
            <input type="hidden" name="desde" value="{{ $desde }}">
        @endif
    </form>

    <x-molecules.confirm-modal
        :id="$modalBase.'-'.$accion->value.'-modal'"
        :form-id="$formId"
        :title="__('proveedores.aprobacion.modal.'.$accion->value.'.titulo', ['nombre' => $nombre])"
        :message="__('proveedores.aprobacion.modal.'.$accion->value.'.mensaje')"
        :confirm-label="__('aprobacion.accion.'.$accion->value)"
        :tone="$tonoPorAccion[$accion->value] ?? 'danger'"
    >
        <x-molecules.state-transition
            :from-label="__('aprobacion.estado.'.$estado->value)"
            :to-label="__('aprobacion.estado.'.$destino->value)"
            :from-tone="$estado->tono()"
            :to-tone="$destino->tono()"
            :label="__('proveedores.aprobacion.cambio_aria', ['de' => __('aprobacion.estado.'.$estado->value), 'a' => __('aprobacion.estado.'.$destino->value)])"
        />
        @if ($accion->requiereMotivo())
            <x-atoms.textarea
                name="motivo"
                :id="$modalBase.'-'.$accion->value.'-motivo'"
                :form="$formId"
                :label="__('proveedores.aprobacion.motivo_etiqueta')"
                :help="__('proveedores.aprobacion.motivo_ayuda')"
                :rows="3"
                required
            />
        @endif
    </x-molecules.confirm-modal>
@endforeach
