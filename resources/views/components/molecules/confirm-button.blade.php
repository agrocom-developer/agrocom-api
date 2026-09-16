{{--
    Molecule: confirm-button (15/9/2026)
    Botón que confirma una acción con un modal propio del panel en vez del
    `confirm()` nativo del navegador (pedido directo: "Localhost dice: ..."
    no tiene tokens, no respeta el tema, y no se puede traducir su chrome).
    Reemplaza el patrón `<form onsubmit="return confirm('...')">` que
    todavía usan varias pantallas — migran cuando les toque su pasada, no
    todas de una.

    Anatomía (pedido directo, referencia de mockup: ícono en círculo +
    título + mensaje en un solo bloque, sin la barra de header con "×" de un
    modal genérico — eso es chrome de más para una simple confirmación):
    ícono en círculo tonal arriba, título en negrita, mensaje debajo,
    acciones al pie a la derecha (Cancelar outline + Confirmar sólido). Sigue
    cerrando con click afuera/Escape (comportamiento nativo del modal de
    Bootstrap), aunque no haya un botón "×" visible.

    Sin JS propio: mismo criterio que `atoms/image-modal` — el modal es
    Bootstrap nativo (`data-bs-toggle="modal"`, ya cargado globalmente en
    `resources/js/app.js`) y el botón "Confirmar" es un `<button
    type="submit" form="...">` que apunta al FORM real por su `id` (atributo
    HTML `form`, soportado nativo — el botón vive fuera del `<form>`, en el
    modal, pero lo envía igual sin una línea de JS). El disparador visible
    es `type="button"`: abre el modal, no envía nada por sí solo.

    Props:
    - formId (requerido): `id` del `<form>` que hay que enviar al confirmar
      — el llamador se lo pone al `<form>` de la fila/pantalla.
    - title (requerido): título del modal, ya traducido.
    - message (requerido): pregunta de confirmación, ya traducida.
    - confirmLabel (requerido): texto del botón que sí envía el form.
    - cancelLabel (nullable): texto del botón que cierra sin hacer nada —
      sin pasarlo, cae en `ui.action.cancel` (mismo chrome genérico en toda
      pantalla).
    - tone: danger|success|warning|info|neutral (default "danger" — la
      mayoría de las confirmaciones son sobre algo que no se puede deshacer).
      Colorea el círculo del ícono Y el botón "Confirmar" (danger → botón
      `danger` sólido; cualquier otro tono → botón `primary`, nunca un
      color de estado en un botón de acción — mismo criterio de "el color de
      estado no compite con el CTA" que ya documenta `atoms/button`).
    - modalIcon (nullable): ícono Material Symbols del círculo — sin
      pasarlo, `warning` en tono danger, `check_circle` en cualquier otro.
    - variant/size/icon: del botón DISPARADOR visible (el que ve el usuario
      en la fila/pantalla) — mismas props que `atoms/button`, sin relación
      con `tone`/`modalIcon` (el disparador puede ser un `warning-outline`
      "Editar" mientras el modal es de tono `danger`).

    LSP (`$attributes`): se fusiona en el botón DISPARADOR, nunca en el
    modal ni en el botón de confirmar adentro — mismo criterio que
    `atoms/image-modal`.
--}}
@props([
    'formId',
    'title',
    'message',
    'confirmLabel',
    'cancelLabel' => null,
    'tone' => 'danger',
    'modalIcon' => null,
    'variant' => 'outline',
    'size' => 'sm',
    'icon' => null,
])

@php
    $modalId = 'ag-confirm-modal-'.\Illuminate\Support\Str::random(8);
    $tituloId = "{$modalId}-titulo";
    $modalIcon ??= $tone === 'danger' ? 'warning' : 'check_circle';
    $varianteBotonConfirmar = $tone === 'danger' ? 'danger' : 'primary';
@endphp

<x-atoms.button
    type="button"
    data-bs-toggle="modal"
    data-bs-target="#{{ $modalId }}"
    :variant="$variant"
    :size="$size"
    :icon="$icon"
    {{ $attributes }}
>
    {{ $slot }}
</x-atoms.button>

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true" aria-labelledby="{{ $tituloId }}">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content ag-confirm-modal__content">
            <div class="ag-confirm-modal__body">
                <span class="ag-confirm-modal__icon ag-confirm-modal__icon--{{ $tone }}">
                    <x-atoms.icon :name="$modalIcon" size="md" />
                </span>
                <div class="ag-confirm-modal__text">
                    <h2 class="ag-confirm-modal__title" id="{{ $tituloId }}">{{ $title }}</h2>
                    <p class="ag-confirm-modal__message">{{ $message }}</p>
                </div>
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
