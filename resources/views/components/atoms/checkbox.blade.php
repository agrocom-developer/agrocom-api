{{--
    Atom: checkbox
    Casilla booleana suelta — reemplaza los 7 `type="checkbox"` crudos del
    panel (tarea 76 / HU-53). Sin lógica de negocio: no valida, no decide qué
    pasa al marcarla — eso es de quien la consume.

    Distinto de `atoms/switch`: mismo control real (`<input type="checkbox">`)
    y misma técnica visual (input oculto con "visually-hidden", nunca
    `display:none`, + una caja hermana pintada por CSS a partir de
    `:checked`), pero acá la caja es cuadrada con un check de Material
    Symbols (no una pastilla con thumb) — es la semántica de "marcar un
    ítem", no la de "prender/apagar una opción" que ya cubre `switch`.

    Props:
    - name (requerido), id (default = name), value (default "1", el valor
      enviado cuando está marcada — convención de Laravel).
    - label, help, error: strings ya traducidos por el llamador (ADR 0013).
    - checked (bool, default false), required (bool, default false),
      disabled (bool, default false).

    LSP (`$attributes`, ver docs/diseno/guia_pantalla_panel.md §3): mismo
    criterio partido que `atoms/select`/`atoms/date` — la raíz
    (`<div class="ag-checkbox">`) solo fusiona `class` (`->only('class')`),
    el `<input>` real recibe el resto (`->except('class')`) para que
    `data-*`, `wire:model`, etc. le sigan llegando al control real.
--}}
@props([
    'name',
    'id' => null,
    'label' => null,
    'value' => '1',
    'checked' => false,
    'required' => false,
    'disabled' => false,
    'help' => null,
    'error' => null,
])

@php
    $inputId = $id ?? $name;
    $helpId = $help ? "{$inputId}-help" : null;
    $errorId = $error ? "{$inputId}-error" : null;
    $describedBy = trim(($helpId ?? '').' '.($errorId ?? ''));
@endphp

<div {{ $attributes->class(['ag-checkbox'])->only('class') }}>
    <label for="{{ $inputId }}" class="ag-checkbox__control">
        <input
            type="checkbox"
            name="{{ $name }}"
            id="{{ $inputId }}"
            value="{{ $value }}"
            @checked($checked)
            @if ($required) required @endif
            @if ($disabled) disabled @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            class="ag-checkbox__input"
            {{ $attributes->except('class') }}
        >

        <span class="ag-checkbox__box" aria-hidden="true">
            <x-atoms.icon name="check" size="sm" class="ag-checkbox__check" />
        </span>

        @if ($label)
            <span class="ag-checkbox__label">
                {{ $label }}
                @if ($required)
                    <span class="ag-checkbox__required" aria-hidden="true">*</span>
                @endif
            </span>
        @endif
    </label>

    @if ($help)
        <p id="{{ $helpId }}" class="ag-checkbox__help">{{ $help }}</p>
    @endif

    @if ($error)
        <p id="{{ $errorId }}" class="ag-checkbox__error" role="alert">{{ $error }}</p>
    @endif
</div>
