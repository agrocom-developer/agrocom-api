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
    - label (nullable): texto ya traducido, a la derecha del control. Sin
      label, quien lo use debe pasar `aria-label` vía atributos adicionales
      (`$attributes` se reenvía al `<input>`).
    - checked (bool, default false): estado inicial.
    - disabled (bool, default false).
    - help (nullable): texto de ayuda debajo, mismo patrón que `atoms/input`.
--}}
@props([
    'name',
    'id' => null,
    'label' => null,
    'checked' => false,
    'disabled' => false,
    'help' => null,
])

@php
    $inputId = $id ?? $name;
    $helpId = $help ? "{$inputId}-help" : null;
@endphp

<div class="ag-switch">
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
