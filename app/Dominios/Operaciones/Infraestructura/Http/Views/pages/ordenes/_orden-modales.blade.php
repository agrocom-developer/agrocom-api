{{--
    Partial: formularios + modales de las acciones de UNA orden de aplicación
    (Activar, Pausar, Reanudar, Cerrar, Cancelar, Eliminar; ADR 0022). Lo usan
    la fila y la tarjeta del listado (vía `_orden-acciones.blade.php`), y los
    pasos de estado (`molecules/step-arrow`, `PasosDeOrden`) de la ficha de
    edición y del detalle: los botones y pasos que abren cada modal viven en
    otro lado, este partial solo deja listo lo que tienen que abrir. Los ids de
    los modales (`orden-{acción}-modal-{sufijo}`) son los que arma
    `PasosDeOrden`; un test los vigila.

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
      `grilla-`, `detalle-`, `edicion-`) — la misma orden puede aparecer más de
      una vez en el documento (fila + tarjeta) y un id repetido dejaría al botón
      "Confirmar" enviando el form equivocado.
    - $resumenOrden (array|null, default null): el resumen de esta orden
      (`Aplicacion/ResumenDeOrdenes`, con `trabajos` y `trabajos_abiertos`). Con
      él, «Cerrar» se ofrece según `PoliticaCierreOrden`: si la orden todavía no
      tiene órdenes de trabajo, le faltan hectáreas por asignar o algún equipo no
      terminó el suyo, en lugar del modal de confirmación va un aviso (`molecules/info-modal`, sin `<form>` ni
      botón de confirmar) que dice qué falta y a dónde ir. La restricción es
      informativa acá y firme en el servidor (`MaquinaEstadosOrden::cerrar()`).
    - $conEliminar (bool, default true): si suma el modal de eliminar. Solo el
      listado lo ofrece; la ficha de edición y el detalle no (dar de baja es una
      acción propia del listado).

    Cada acción se muestra solo si el estado la admite Y el rol activo tiene
    el permiso (`@puede`). Presentación, no autorización: el servidor
    revalida (OrdenesController) y la máquina de estados decide la
    transición. Pausar, cerrar y cancelar solo existen en el panel — la app
    de campo nunca las dispara.
--}}
@php
    $contexto ??= '';
    $conEliminar ??= true;
    $resumenOrden ??= null;
    $estadoValor = $orden->estado->value;
    $sufijo = "{$contexto}{$orden->id}";
    // Quién pidió la cancelación o qué la causó: cliente, dueño o factor externo
    // (`CausaCancelacionOrden`); las dos primeras consumen el número de aplicación.
    $opcionesCausa = collect(\App\Dominios\Operaciones\Dominio\CausaCancelacionOrden::cases())
        ->mapWithKeys(fn ($causa) => [$causa->value => __('operaciones.ordenes.causa_'.$causa->value)])
        ->all();
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
        >
            @include('operaciones::pages.ordenes._estado-transicion', ['desde' => $estadoValor, 'hacia' => 'vigente'])
        </x-molecules.confirm-modal>
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
            @include('operaciones::pages.ordenes._estado-transicion', ['desde' => $estadoValor, 'hacia' => 'pausada'])

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
        >
            @include('operaciones::pages.ordenes._estado-transicion', ['desde' => $estadoValor, 'hacia' => 'vigente'])
        </x-molecules.confirm-modal>
    @endif
@endpuede

@puede('operaciones.orden.cerrar')
    @if ($estadoValor === 'vigente')
        @php
            $impedimentoCierre = null;
            $mensajeCierre = null;

            if ($resumenOrden !== null) {
                $impedimentoCierre = \App\Dominios\Operaciones\Dominio\PoliticaCierreOrden::impedimento(
                    $resumenOrden['trabajos'],
                    $resumenOrden['trabajos_abiertos'],
                    $resumenOrden['hectareas_solicitadas'],
                    $resumenOrden['hectareas_asignadas'],
                );
                $hectareasFaltan = \App\Dominios\Operaciones\Dominio\PoliticaCierreOrden::hectareasSinAsignar($resumenOrden['hectareas_solicitadas'], $resumenOrden['hectareas_asignadas']);
                $mensajeCierre = match ($impedimentoCierre) {
                    \App\Dominios\Operaciones\Dominio\ImpedimentoCierreOrden::SinTrabajos => __('operaciones.ordenes.cierre_sin_trabajos'),
                    \App\Dominios\Operaciones\Dominio\ImpedimentoCierreOrden::HectareasSinAsignar => __('operaciones.ordenes.cierre_hectareas_sin_asignar', ['hectareas' => number_format((float) (string) $hectareasFaltan, 2, ',', '.')]),
                    \App\Dominios\Operaciones\Dominio\ImpedimentoCierreOrden::TrabajosAbiertos => trans_choice('operaciones.ordenes.cierre_trabajos_abiertos', $resumenOrden['trabajos_abiertos'], ['cantidad' => $resumenOrden['trabajos_abiertos']]),
                    default => null,
                };
            }
        @endphp

        @if ($impedimentoCierre !== null)
            {{-- Aviso, no confirmación: sin `<form>` ni botón de confirmar. --}}
            <x-molecules.info-modal
                :id="'orden-cerrar-modal-'.$sufijo"
                :title="__('operaciones.ordenes.cierre_no_disponible_titulo')"
                :message="$mensajeCierre"
                :close-label="__('operaciones.ordenes.cierre_entendido')"
                tone="info"
                modal-icon="block"
            >
                <x-slot:actions>
                    @if ($impedimentoCierre !== \App\Dominios\Operaciones\Dominio\ImpedimentoCierreOrden::TrabajosAbiertos)
                        {{-- Sin trabajos o con hectáreas sin asignar: lo que falta es repartir equipos. --}}
                        @puede('operaciones.orden.asignar_equipos')
                            <x-atoms.button :href="route('panel.reparto-cuadrillas.show', $orden)" variant="outline" icon="groups">
                                {{ __('operaciones.ordenes.vinculo_asignar_equipos') }}
                            </x-atoms.button>
                        @endpuede
                    @else
                        @puede('operaciones.trabajo.ver')
                            <x-atoms.button :href="route('panel.trabajos.index', ['orden_id' => $orden->id])" variant="outline" icon="work_history">
                                {{ __('operaciones.ordenes.vinculo_trabajos') }}
                            </x-atoms.button>
                        @endpuede
                    @endif
                </x-slot:actions>
            </x-molecules.info-modal>
        @else
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
            >
                @include('operaciones::pages.ordenes._estado-transicion', ['desde' => $estadoValor, 'hacia' => 'consumida'])
            </x-molecules.confirm-modal>
        @endif
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
            @include('operaciones::pages.ordenes._estado-transicion', ['desde' => $estadoValor, 'hacia' => 'cancelada'])

            <x-atoms.select
                name="causa_cancelacion"
                :id="'causa-cancelacion-'.$sufijo"
                :form="'orden-cancelar-'.$sufijo"
                :label="__('operaciones.ordenes.campo_causa_cancelacion')"
                :options="$opcionesCausa"
                :placeholder="__('operaciones.ordenes.causa_placeholder')"
                :help="__('operaciones.ordenes.ayuda_causa_cancelacion')"
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
    @if ($conEliminar && $estadoValor === 'emitida')
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
