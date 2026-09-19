{{--
    Molecule: info-modal (19/9/2026)
    Modal informativo: dice algo y se cierra. Es el hermano de `confirm-modal`
    para cuando una acción todavía NO se puede hacer y lo útil es explicar por
    qué y a dónde ir — sin formulario y sin botón de confirmar, así el usuario
    no tiene nada que "aceptar" que el servidor luego rechace. Tiene el mismo
    aspecto que `confirm-modal` (reusa sus clases `ag-confirm-modal__*`), y se
    abre igual: un botón con `data-bs-toggle="modal" data-bs-target="#{id}"`.

    Props:
    - id (requerido): identificador único del modal en la página.
    - title (requerido), message (requerido): ya traducidos.
    - closeLabel (requerido): texto del botón que cierra, ya traducido.
    - tone: los mismos que `confirm-modal` (danger|success|warning|info|neutral|
      alert|distintivo-1|2|3|primary-2), default "warning": es un aviso, no una
      confirmación. Colorea el círculo del ícono; si el aviso es sobre un cambio
      de estado, lleva el tono del estado al que se quería pasar.
    - modalIcon: ícono Material Symbols del círculo (default `info`).

    Slots:
    - default (opcional): contenido bajo el mensaje — un detalle, una lista.
    - actions (opcional): botones o enlaces que van antes del de cerrar (p. ej.
      "Ir a la orden"), ya armados por la pantalla.
--}}
@props([
    'id',
    'title',
    'message',
    'closeLabel',
    'tone' => 'warning',
    'modalIcon' => 'info',
])

@php
    $tituloId = "{$id}-titulo";
@endphp

<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-hidden="true" aria-labelledby="{{ $tituloId }}">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content ag-confirm-modal__content">
            <div class="ag-confirm-modal__body">
                <span class="ag-confirm-modal__icon ag-confirm-modal__icon--{{ $tone }}">
                    <x-atoms.icon :name="$modalIcon" size="md" />
                </span>
                <h2 class="ag-confirm-modal__title" id="{{ $tituloId }}">{{ $title }}</h2>
                <p class="ag-confirm-modal__message">{{ $message }}</p>
                @if (! $slot->isEmpty())
                    <div class="ag-confirm-modal__campos">
                        {{ $slot }}
                    </div>
                @endif
            </div>
            <div class="ag-confirm-modal__footer">
                @isset($actions)
                    {{ $actions }}
                @endisset
                <x-atoms.button type="button" variant="primary" data-bs-dismiss="modal">
                    {{ $closeLabel }}
                </x-atoms.button>
            </div>
        </div>
    </div>
</div>
