{{--
    Partial: formulario + modal del cambio de estado de UNA estadía (En curso →
    Finalizada), para los pasos de `molecules/step-arrow` de la ficha de
    edición. El paso solo abre el modal; el modal envía el `<form>` de acá a
    `panel.estadias.finalizar`, que pasa por `FinalizarEstadiaHacienda` → la
    tabla de transiciones (invariante 7).

    Va FUERA del `<form>` de `_formulario.blade.php` a propósito — un `<form>`
    no puede anidarse en otro — y el `<form>` va con `hidden` para no sumar un
    hueco. Mismo patrón que `campania::pages.campanias._cambio-estado`.

    Finalizar pide un dato: cuándo se fue la cuadrilla. Va en el slot del
    `confirm-modal`, asociado al formulario con el atributo `form` (mismo
    patrón que el motivo de pausa de `operaciones::pages.ordenes._orden-modales`).

    Espera:
    - $estadia (EstadiaHacienda).
    - $pasosEstado (list<array{...}>): los de `PasosDeEstado::armar()`. Solo un
      paso `next` trae su formulario y su modal; el `id` del modal es el
      `modal` del paso.
    - $tonoPorEstado (array<string, string>): el mismo del listado.
--}}
@foreach ($pasosEstado as $paso)
    @continue($paso['status'] !== 'next')

    @php
        $formIdEstado = "estadia-estado-form-{$paso['key']}";
        $estadoActual = $estadia->estado()->value;
    @endphp

    <form id="{{ $formIdEstado }}" method="POST" action="{{ route('panel.estadias.finalizar', $estadia) }}" hidden>
        @csrf
    </form>

    <x-molecules.confirm-modal
        :id="$paso['modal']"
        :form-id="$formIdEstado"
        :title="__('operaciones.estadias.confirmar_finalizar_titulo')"
        :message="__('operaciones.estadias.confirmar_finalizar')"
        :confirm-label="__('operaciones.estadias.finalizar_accion')"
        :tone="$tonoPorEstado[$paso['key']] ?? 'success'"
        modal-icon="logout"
    >
        <x-molecules.state-transition
            :from-label="__('operaciones.estadias.estado.'.$estadoActual)"
            :from-tone="$tonoPorEstado[$estadoActual] ?? 'neutral'"
            :to-label="__('operaciones.estadias.estado.'.$paso['key'])"
            :to-tone="$tonoPorEstado[$paso['key']] ?? 'neutral'"
            :label="__('operaciones.estadias.estado_cambio_de_a', [
                'desde' => __('operaciones.estadias.estado.'.$estadoActual),
                'hacia' => __('operaciones.estadias.estado.'.$paso['key']),
            ])"
        />

        <x-atoms.datetime
            name="salida"
            id="estadia-salida-finalizar"
            :form="$formIdEstado"
            :label="__('operaciones.estadias.campo_salida_finalizar')"
            :value="old('salida', now()->format('Y-m-d\TH:i'))"
            :help="__('operaciones.estadias.campo_salida_finalizar_ayuda')"
            :error="$errors->first('salida')"
            required
        />
    </x-molecules.confirm-modal>
@endforeach
