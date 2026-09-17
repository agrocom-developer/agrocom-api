{{--
    Atom: switch
    Toggle on/off GENÉRICO DE FORMULARIO — distinto de `molecules/theme-toggle`
    (ese es presentación pura atada al tema, sin `name`/`checked`/`value`, y
    alterna `data-bs-theme` vía JS). Este átomo es un `<input type="checkbox">`
    real: se envía con el formulario que lo contenga, funciona con teclado y
    lectores de pantalla sin ningún JS propio (el estado visual se resuelve
    100% en CSS con el pseudo-selector `:checked`, mismo criterio que
    `molecules/plan-card`).

    Sin lógica de negocio: no decide qué pasa al togglear — eso es de quien
    lo consume (formulario Livewire/Blade).

    Props:
    - name (requerido).
    - id (nullable, default = name).
    - label (nullable): texto ya traducido, a la derecha del control —
      pensado para una fila suelta tipo "Activo", o para una respuesta que
      cambia con el estado ("Sí"/"No"), no para el nombre del campo cuando
      el switch comparte fila de grid con un `atoms/input`/`atoms/select`
      (ver `fieldLabel`). Sin ningún label, quien lo use debe pasar
      `aria-label` vía atributos adicionales (`$attributes` se reenvía al
      `<input>`).
    - fieldLabel (nullable, 17/9/2026): rótulo del campo, en la misma
      posición que `atoms/input__label` (arriba del control, no al lado) —
      así el switch queda a la altura real del control de sus vecinos de
      fila, no de su label. Antes de este prop cada pantalla lo resolvía
      envolviendo el átomo en un `<div class="ag-input"><span
      class="ag-input__label">` a mano (ver `lotes/_lote-terreno.blade.php`,
      commit antes de este) — reaparecía la clase de un átomo ajeno por
      fuera de su componente. `fieldLabel` y `label` no son excluyentes: se
      puede tener el rótulo del campo arriba y una respuesta Sí/No al lado
      del track.
    - checked (bool, default false): estado inicial.
    - disabled (bool, default false).
    - help (nullable): texto de ayuda debajo, mismo patrón que `atoms/input`.
--}}
@props([
    'name',
    'id' => null,
    'label' => null,
    'fieldLabel' => null,
    'checked' => false,
    'disabled' => false,
    'help' => null,
])

@php
    $inputId = $id ?? $name;
    $helpId = $help ? "{$inputId}-help" : null;
@endphp

<div class="ag-switch">
    @if ($fieldLabel)
        <label for="{{ $inputId }}" class="ag-switch__field-label">{{ $fieldLabel }}</label>
    @endif

    <label for="{{ $inputId }}" class="ag-switch__control">
        <input
            type="checkbox"
            name="{{ $name }}"
            id="{{ $inputId }}"
            @checked($checked)
            @disabled($disabled)
            @if ($helpId) aria-describedby="{{ $helpId }}" @endif
            {{ $attributes->class(['ag-switch__input']) }}
        >

        <span class="ag-switch__track" aria-hidden="true">
            <span class="ag-switch__thumb"></span>
        </span>

        @if ($label)
            <span class="ag-switch__label">{{ $label }}</span>
        @endif
    </label>

    @if ($help)
        <p id="{{ $helpId }}" class="ag-switch__help">{{ $help }}</p>
    @endif
</div>
