{{--
    Atom: input
    Campo de texto/password/email genérico. Sin lógica de negocio: no valida,
    no conoce el `$errors` bag global de Laravel — el llamador (molecule/organism/
    componente Livewire) le pasa el mensaje de error ya resuelto.

    Props:
    - type (default "text"): "password" agrega automáticamente el botón de
      mostrar/ocultar (ver resources/js/atoms/input.js).
    - name (requerido), id (default = name).
    - label, placeholder, help, error: strings ya traducidos por el llamador
      (este átomo no decide textos, ADR 0013).
    - icon: nombre de ícono Material Symbols para el prefijo del campo.
    - required (bool, default false).
--}}
@props([
    'type' => 'text',
    'name',
    'id' => null,
    'label' => null,
    'value' => null,
    'placeholder' => null,
    'error' => null,
    'icon' => null,
    'help' => null,
    'required' => false,
])

@php
    $inputId = $id ?? $name;
    $isPassword = $type === 'password';
    $helpId = $help ? "{$inputId}-help" : null;
    $errorId = $error ? "{$inputId}-error" : null;
    $describedBy = trim(($helpId ?? '').' '.($errorId ?? ''));
@endphp

<div class="ag-input">
    @if ($label)
        <label for="{{ $inputId }}" class="ag-input__label">
            {{ $label }}
            @if ($required)
                <span class="ag-input__required" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <div class="ag-input__control {{ $error ? 'ag-input__control--error' : '' }}">
        @if ($icon)
            <x-atoms.icon :name="$icon" size="sm" class="ag-input__icon" />
        @endif

        <input
            type="{{ $type }}"
            name="{{ $name }}"
            id="{{ $inputId }}"
            value="{{ $value }}"
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($required) required @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->class(['ag-input__field']) }}
        >

        @if ($isPassword)
            <button
                type="button"
                class="ag-input__toggle"
                data-ag-toggle-password
                data-label-show="{{ __('ui.input.show_password') }}"
                data-label-hide="{{ __('ui.input.hide_password') }}"
                aria-label="{{ __('ui.input.show_password') }}"
                aria-pressed="false"
            >
                <x-atoms.icon name="visibility" size="sm" />
            </button>
        @endif
    </div>

    @if ($help)
        <p id="{{ $helpId }}" class="ag-input__help">{{ $help }}</p>
    @endif

    @if ($error)
        <p id="{{ $errorId }}" class="ag-input__error" role="alert">{{ $error }}</p>
    @endif
</div>
