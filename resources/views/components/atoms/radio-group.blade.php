{{--
    Atom: radio-group
    Selección única entre pocas opciones fijas, con `<input type="radio">`
    reales agrupados en un `<fieldset>`/`<legend>` — es la semántica nativa
    correcta para esto, así que no hay JS propio: el navegador ya mueve el
    foco con las flechas entre radios del mismo `name` y Espacio/click marca.
    Sin lógica de negocio: no valida, no resuelve el catálogo de opciones —
    el llamador ya le pasa `options` como `id => etiqueta`, traducida
    (ADR 0013).

    Props:
    - name (requerido), id (default = name).
    - options (array, default []): `valor => etiqueta`, ya traducida.
    - value (nullable): valor actualmente seleccionado (debe existir como
      clave de `options`).
    - label: texto ya traducido, usado como `<legend>`. Sin `label` no hay
      forma de darle nombre accesible al grupo con este átomo (no hay un
      único control al que reenviarle un `aria-label` del llamador, como sí
      pasa en `atoms/select`/`atoms/date` con su `<select>`/`<input>` nativo)
      — pasar siempre `label` es la única forma soportada de accesibilidad
      acá.
    - help, error: strings ya traducidos por el llamador.
    - required, disabled (bool, default false): se aplican a todos los
      radios del grupo (mismo criterio que Livewire: cada radio lleva el
      `wire:model`/atributo repetido, el navegador solo exige que al menos
      uno del grupo compartido por `name` termine marcado).

    LSP (`$attributes`, ver docs/diseno/guia_pantalla_panel.md §3): mismo
    criterio partido que `atoms/select`/`atoms/date` — la raíz
    (`<fieldset class="ag-radio-group">`) solo fusiona `class`
    (`->only('class')`); el resto del bag (`->except('class')`, p. ej.
    `wire:model`) se reenvía a CADA `<input type="radio">` del grupo, que es
    lo que necesita un binding de Livewire atado al mismo modelo.
--}}
@props([
    'name',
    'id' => null,
    'label' => null,
    'options' => [],
    'value' => null,
    'error' => null,
    'help' => null,
    'required' => false,
    'disabled' => false,
])

@php
    $groupId = $id ?? $name;
    $helpId = $help ? "{$groupId}-help" : null;
    $errorId = $error ? "{$groupId}-error" : null;
    $describedBy = trim(($helpId ?? '').' '.($errorId ?? ''));
@endphp

<fieldset
    {{ $attributes->class(['ag-radio-group'])->only('class') }}
    @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
>
    @if ($label)
        <legend class="ag-radio-group__legend">
            {{ $label }}
            @if ($required)
                <span class="ag-radio-group__required" aria-hidden="true">*</span>
            @endif
        </legend>
    @endif

    <div class="ag-radio-group__options">
        @foreach ($options as $optValue => $optLabel)
            @php $optionId = "{$groupId}-opt-{$loop->index}"; @endphp
            <label for="{{ $optionId }}" class="ag-radio-group__option">
                <input
                    type="radio"
                    name="{{ $name }}"
                    id="{{ $optionId }}"
                    value="{{ $optValue }}"
                    @checked((string) $value === (string) $optValue)
                    @if ($required) required @endif
                    @if ($disabled) disabled @endif
                    class="ag-radio-group__input"
                    {{ $attributes->except('class') }}
                >

                <span class="ag-radio-group__dot" aria-hidden="true"></span>
                <span class="ag-radio-group__option-label">{{ $optLabel }}</span>
            </label>
        @endforeach
    </div>

    @if ($help)
        <p id="{{ $helpId }}" class="ag-radio-group__help">{{ $help }}</p>
    @endif

    @if ($error)
        <p id="{{ $errorId }}" class="ag-radio-group__error" role="alert">{{ $error }}</p>
    @endif
</fieldset>
