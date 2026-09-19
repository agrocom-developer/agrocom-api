{{--
    Partial: formularios + modales de las acciones de UNA orden de aplicación
    (Activar, Pausar, Reanudar, Cerrar, Cancelar, Eliminar; ADR 0022). Lo usan
    la fila y la tarjeta del listado (vía `_orden-acciones.blade.php`) y el
    detalle (`show.blade.php`): los botones que abren cada modal viven en
    otro lado, este partial solo deja listo lo que tienen que abrir.

    Forms + `molecules/confirm-modal` FUERA de `row-actions` y de cualquier
    otro `<form>` a propósito (mismo motivo ya documentado en
    `_orden-acciones.blade.php`): un form con id dentro de un slot que se
    repite quedaría duplicado. Pausar y Cancelar piden datos (motivo, causa):
    van como campos del slot por defecto de `confirm-modal`, asociados al
    `<form>` de afuera con el atributo HTML `form` — sin JS.

    Los `<form>` van con `hidden`: no dibujan nada y, en el detalle, no suman un
    hueco (`gap`) por cada uno dentro del contenedor flex. Siguen enviándose
    con el botón "Confirmar" del modal, que los referencia por el atributo
    `form`.

    Espera: $orden (OrdenAplicacion).
    - $contexto (string, default ''): prefijo para los ids (`lista-`,
      `grilla-`, `detalle-`) — la misma orden puede aparecer más de una vez en
      el documento (fila + tarjeta) y un id repetido dejaría al botón
      "Confirmar" enviando el form equivocado.

    Cada acción se muestra solo si el estado la admite Y el rol activo tiene
    el permiso (`@puede`). Presentación, no autorización: el servidor
    revalida (OrdenesController) y la máquina de estados decide la
    transición. Pausar, cerrar y cancelar solo existen en el panel — la app
    de campo nunca las dispara.
--}}
@php
    $contexto ??= '';
    $estadoValor = $orden->estado->value;
    $sufijo = "{$contexto}{$orden->id}";
    $opcionesCausa = [
        'cliente' => __('operaciones.ordenes.causa_cliente'),
        'fuerza_mayor' => __('operaciones.ordenes.causa_fuerza_mayor'),
    ];
@endphp

@puede('operaciones.orden.activar')
    @if ($estadoValor === 'emitida')
        <form id="orden-activar-{{ $sufijo }}" method="POST" action="{{ route('panel.ordenes.activar', $orden) }}" hidden>
            @csrf
        </form>

        <x-molecules.confirm-modal
            :id="'orden-activar-modal-'.$sufijo"
            :form-id="'orden-activar-'.$sufijo"
            :title="__('operaciones.ordenes.confirmar_activar_titulo')"
            :message="__('operaciones.ordenes.confirmar_activar')"
            :confirm-label="__('operaciones.ordenes.activar_accion')"
            tone="success"
        />
    @endif
@endpuede

@puede('operaciones.orden.pausar')
    @if ($estadoValor === 'vigente')
        <form id="orden-pausar-{{ $sufijo }}" method="POST" action="{{ route('panel.ordenes.pausar', $orden) }}" hidden>
            @csrf
        </form>

        <x-molecules.confirm-modal
            :id="'orden-pausar-modal-'.$sufijo"
            :form-id="'orden-pausar-'.$sufijo"
            :title="__('operaciones.ordenes.confirmar_pausar_titulo')"
            :message="__('operaciones.ordenes.confirmar_pausar')"
            :confirm-label="__('operaciones.ordenes.pausar_accion')"
            :cancel-label="__('ui.action.close')"
            tone="warning"
            modal-icon="pause_circle"
        >
            <x-atoms.textarea
                name="motivo_pausa"
                :id="'motivo-pausa-'.$sufijo"
                :form="'orden-pausar-'.$sufijo"
                :label="__('operaciones.ordenes.motivo_pausa')"
                :placeholder="__('operaciones.ordenes.motivo_pausa_placeholder')"
                :rows="3"
                required
            />
        </x-molecules.confirm-modal>
    @endif

    @if ($estadoValor === 'pausada')
        <form id="orden-reanudar-{{ $sufijo }}" method="POST" action="{{ route('panel.ordenes.reanudar', $orden) }}" hidden>
            @csrf
        </form>

        <x-molecules.confirm-modal
            :id="'orden-reanudar-modal-'.$sufijo"
            :form-id="'orden-reanudar-'.$sufijo"
            :title="__('operaciones.ordenes.confirmar_reanudar_titulo')"
            :message="__('operaciones.ordenes.confirmar_reanudar')"
            :confirm-label="__('operaciones.ordenes.reanudar_accion')"
            tone="success"
            modal-icon="play_circle"
        />
    @endif
@endpuede

@puede('operaciones.orden.cerrar')
    @if ($estadoValor === 'vigente')
        <form id="orden-cerrar-{{ $sufijo }}" method="POST" action="{{ route('panel.ordenes.cerrar', $orden) }}" hidden>
            @csrf
        </form>

        <x-molecules.confirm-modal
            :id="'orden-cerrar-modal-'.$sufijo"
            :form-id="'orden-cerrar-'.$sufijo"
            :title="__('operaciones.ordenes.confirmar_cerrar_titulo')"
            :message="__('operaciones.ordenes.confirmar_cerrar')"
            :confirm-label="__('operaciones.ordenes.cerrar_accion')"
            tone="info"
            modal-icon="done_all"
        />
    @endif
@endpuede

@puede('operaciones.orden.cancelar')
    @if ($estadoValor === 'vigente' || $estadoValor === 'pausada')
        <form id="orden-cancelar-{{ $sufijo }}" method="POST" action="{{ route('panel.ordenes.cancelar', $orden) }}" hidden>
            @csrf
        </form>

        <x-molecules.confirm-modal
            :id="'orden-cancelar-modal-'.$sufijo"
            :form-id="'orden-cancelar-'.$sufijo"
            :title="__('operaciones.ordenes.confirmar_cancelar_titulo')"
            :message="__('operaciones.ordenes.confirmar_cancelar')"
            :confirm-label="__('operaciones.ordenes.cancelar_confirmar')"
            :cancel-label="__('ui.action.close')"
            tone="danger"
        >
            <x-atoms.select
                name="causa_cancelacion"
                :id="'causa-cancelacion-'.$sufijo"
                :form="'orden-cancelar-'.$sufijo"
                :label="__('operaciones.ordenes.campo_causa_cancelacion')"
                :options="$opcionesCausa"
                :placeholder="__('operaciones.ordenes.causa_placeholder')"
                :help="__('operaciones.ordenes.ayuda_causa_fuerza_mayor')"
                required
            />
            <x-atoms.textarea
                name="motivo_cancelacion"
                :id="'motivo-cancelacion-'.$sufijo"
                :form="'orden-cancelar-'.$sufijo"
                :label="__('operaciones.ordenes.motivo_cancelacion')"
                :placeholder="__('operaciones.ordenes.motivo_cancelacion_placeholder')"
                :rows="3"
                required
            />
        </x-molecules.confirm-modal>
    @endif
@endpuede

@puede('operaciones.orden.eliminar')
    @if ($estadoValor === 'emitida')
        <form id="orden-eliminar-{{ $sufijo }}" method="POST" action="{{ route('panel.ordenes.destroy', $orden) }}" hidden>
            @csrf
            @method('DELETE')
        </form>

        <x-molecules.confirm-modal
            :id="'orden-eliminar-modal-'.$sufijo"
            :form-id="'orden-eliminar-'.$sufijo"
            :title="__('operaciones.ordenes.confirmar_eliminar_titulo')"
            :message="__('operaciones.ordenes.confirmar_baja')"
            :confirm-label="__('operaciones.ordenes.eliminar_accion')"
            tone="danger"
        />
    @endif
@endpuede
