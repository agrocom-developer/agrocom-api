{{--
    Atom: textarea
    Campo de texto multilínea genérico. Sin lógica de negocio: no valida, no
    conoce el `$errors` bag global de Laravel — el llamador le pasa el
    mensaje de error ya resuelto. Mismo contrato que `atoms/input`, sin
    `type`/`icon`/toggle de password (no aplican a un control multilínea) y
    con `rows` en su lugar.

    Props:
    - name (requerido), id (default = name).
    - label, placeholder, help, error: strings ya traducidos por el llamador
      (ADR 0013).
    - value (nullable): contenido inicial.
    - rows (default 4).
    - required, disabled (bool, default false).

    LSP (`$attributes`, ver docs/diseno/guia_pantalla_panel.md §3): mismo
    criterio partido que `atoms/input` — la raíz (`<div class="ag-textarea">`)
    solo fusiona `class` (`->only('class')`), el `<textarea>` recibe el resto
    (`->except('class')`) para que `data-*`, `wire:model`, etc. le sigan
    llegando al control real.
--}}
@props([
    'name',
    'id' => null,
    'label' => null,
    'value' => null,
    'placeholder' => null,
    'error' => null,
    'help' => null,
    'rows' => 4,
    'required' => false,
    'disabled' => false,
])

@php
    $textareaId = $id ?? $name;
    $helpId = $help ? "{$textareaId}-help" : null;
    $errorId = $error ? "{$textareaId}-error" : null;
    $describedBy = trim(($helpId ?? '').' '.($errorId ?? ''));
@endphp

<div {{ $attributes->class(['ag-textarea'])->only('class') }}>
    @if ($label)
        <label for="{{ $textareaId }}" class="ag-textarea__label">
            {{ $label }}
            @if ($required)
                <span class="ag-textarea__required" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <div class="ag-textarea__control {{ $error ? 'ag-textarea__control--error' : '' }}">
        <textarea
            name="{{ $name }}"
            id="{{ $textareaId }}"
            rows="{{ $rows }}"
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($required) required @endif
            @if ($disabled) disabled @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            class="ag-textarea__field"
            {{ $attributes->except('class') }}
        >{{ $value }}</textarea>
    </div>

    @if ($help)
        <p id="{{ $helpId }}" class="ag-textarea__help">{{ $help }}</p>
    @endif

    @if ($error)
        <p id="{{ $errorId }}" class="ag-textarea__error" role="alert">{{ $error }}</p>
    @endif
</div>
