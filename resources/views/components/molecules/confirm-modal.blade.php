{{--
    Molecule: confirm-modal (extracted from confirm-button, 17/9/2026)
    Modal de confirmación standalone — recibe su `id` como prop OBLIGATORIA,
    permitiendo que múltiples invocaciones (p.ej. trigger visible + trigger en
    menú "⋮") compartan el mismo modal en el DOM sin duplicación.

    Esta molécula se usa cuando el trigger disparador y el modal necesitan vivir
    en lugares diferentes del documento (p.ej. dentro vs. fuera de
    `organisms/row-actions`). Si disparador y modal pueden ser hermanos, usa
    `molecules/confirm-button` que combina ambos.

    Props:
    - id (requerido): identificador único del modal — el llamador es responsable
      de elegir un id determinístico que no colisione con otros en la página
      (p.ej. "campania-cerrar-modal-{{ $campania->id }}").
    - formId (requerido): `id` del `<form>` que hay que enviar al confirmar
      — el llamador se lo pone al `<form>` de la fila/pantalla.
    - title (requerido): título del modal, ya traducido.
    - message (requerido): pregunta de confirmación, ya traducida.
    - confirmLabel (requerido): texto del botón que sí envía el form.
    - cancelLabel (nullable): texto del botón que cierra sin hacer nada —
      sin pasarlo, cae en `ui.action.cancel` (mismo chrome genérico en toda
      pantalla).
    - tone: danger|success|warning|info|neutral|alert|distintivo-1|2|3|
      primary-2 (default "danger" — la mayoría de las confirmaciones son sobre
      algo que no se puede deshacer). Son los tonos de `atoms/badge`: un modal
      que confirma un cambio de ESTADO pasa el tono de ese estado, así se ve
      antes de confirmar a cuál se pasa (19/9/2026). Colorea el círculo del
      ícono Y el botón "Confirmar" (danger → botón `danger` sólido; cualquier
      otro tono → botón `primary`, nunca un color de estado en un botón de
      acción — mismo criterio de "el color de estado no compite con el CTA"
      que ya documenta `atoms/button`).
    - modalIcon (nullable): ícono Material Symbols del círculo — sin
      pasarlo, `warning` en tono danger, `check_circle` en cualquier otro.

    Slot por defecto (opcional, 19/9/2026): CAMPOS que viajan con la
    confirmación — p. ej. el motivo de pausar o cancelar una orden. Se
    dibujan entre el mensaje y los botones. Los controles NO están dentro del
    `<form>` (ese vive fuera, ver `formId`): cada uno se asocia a él con el
    atributo HTML `form="{{ $formId }}"` (los átomos `textarea`/`select`/
    `input` lo dejan pasar a su control), y el navegador los valida y los
    envía con el botón "Confirmar". Sin slot, el modal se ve exactamente como
    siempre.
--}}
@props([
    'id',
    'formId',
    'title',
    'message',
    'confirmLabel',
    'cancelLabel' => null,
    'tone' => 'danger',
    'modalIcon' => null,
])

@php
    $tituloId = "{$id}-titulo";
    $modalIcon ??= $tone === 'danger' ? 'warning' : 'check_circle';
    $varianteBotonConfirmar = $tone === 'danger' ? 'danger' : 'primary';
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
                <x-atoms.button type="button" variant="outline" data-bs-dismiss="modal">
                    {{ $cancelLabel ?? __('ui.action.cancel') }}
                </x-atoms.button>
                <x-atoms.button type="submit" form="{{ $formId }}" :variant="$varianteBotonConfirmar">
                    {{ $confirmLabel }}
                </x-atoms.button>
            </div>
        </div>
    </div>
</div>
