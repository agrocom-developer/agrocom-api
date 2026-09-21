{{--
    Partial: formularios + modales del cambio de estado de UNA campaña, para
    los pasos de `molecules/step-arrow` de la ficha de edición
    (`edit.blade.php`). El paso solo abre el modal; el modal envía el
    `<form>` de acá a `panel.campanias.cambiar-estado`, que pasa por
    `CambiarEstadoCampania` → la máquina de estados (invariante 7).

    Va FUERA del `<form>` de `_formulario.blade.php` a propósito — un `<form>`
    no puede anidarse en otro — y los `<form>` van con `hidden` para no
    sumar un hueco en el contenedor. Mismo motivo y mismo patrón que
    `operaciones::pages.ordenes._orden-modales`.

    Espera:
    - $campania (Campania).
    - $pasosEstado (list<array{...}>): los de `PasosDeEstado::armar()`. Solo
      los pasos `next` (a los que el usuario puede pasar) traen su
      formulario y su modal; el `id` del modal es el `modal` del paso.

    Textos y tono de cada confirmación: los mismos que el listado
    (`index.blade.php`) — abrir en `success`, cerrar en `danger`.
--}}
@foreach ($pasosEstado as $paso)
    @continue($paso['status'] !== 'next')

    @php
        $formIdEstado = "campania-estado-form-{$paso['key']}";
    @endphp

    <form id="{{ $formIdEstado }}" method="POST" action="{{ route('panel.campanias.cambiar-estado', $campania) }}" hidden>
        @csrf
        <input type="hidden" name="estado" value="{{ $paso['key'] }}">
    </form>

    @if ($paso['key'] === 'abierta')
        <x-molecules.confirm-modal
            :id="$paso['modal']"
            :form-id="$formIdEstado"
            :title="__('campania.campanias.confirmar_abrir_titulo')"
            :message="__('campania.campanias.confirmar_abrir')"
            :confirm-label="__('campania.campanias.accion_abrir')"
            tone="success"
        >
            @include('campania::pages.campanias._estado-transicion', ['desde' => $campania->estado->value, 'hacia' => 'abierta'])
        </x-molecules.confirm-modal>
    @elseif ($paso['key'] === 'cerrada')
        <x-molecules.confirm-modal
            :id="$paso['modal']"
            :form-id="$formIdEstado"
            :title="__('campania.campanias.confirmar_cerrar_titulo')"
            :message="__('campania.campanias.confirmar_cerrar')"
            :confirm-label="__('campania.campanias.accion_cerrar')"
            tone="alert"
        >
            @include('campania::pages.campanias._estado-transicion', ['desde' => $campania->estado->value, 'hacia' => 'cerrada'])
        </x-molecules.confirm-modal>
    @endif
@endforeach
