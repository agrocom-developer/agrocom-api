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
    - helpTone (nullable): "accent" | "alert" — colorea el texto de ayuda con
      el acento de marca o el de alerta (`ag-input__help--accent`/`--alert`),
      para una ayuda que es una SUGERENCIA o un aviso y no una aclaración
      neutra. Sin pasarlo, el gris de siempre.
    - icon: nombre de ícono Material Symbols para el prefijo del campo.
    - suffix (nullable, 20/9/2026): unidad del valor ("km", "h", "kg",
      "ciclos"), ya traducida por el llamador. Se pinta al final del control,
      DENTRO del mismo borde, en mono y muted (`ag-input__suffix`): así la
      unidad es parte del campo y no un rótulo suelto ni un paréntesis en el
      label. Es texto de lectura, no un control: no recibe foco ni viaja en
      el POST. Su id entra en `aria-describedby` para que el lector de
      pantalla diga "1200, km".
    - required (bool, default false).
    - variant: "boxed" (default, Material outlined — caja con borde/fondo
      propios, la de siempre) | "line" (línea editorial: sin caja, solo
      `border-bottom`, label uppercase — rediseño de login, HU-02 tercera
      vuelta). Mismo marcado/props/accesibilidad en ambas variantes (label,
      icon, error, help, toggle de password, `aria-describedby`): "line" es
      un modificador de clase (`ag-input--line`) resuelto en CSS
      (`resources/css/components/input.css`), no un componente aparte —
      así el resto del panel sigue usando "boxed" sin que este átomo se
      bifurque en dos archivos.

    LSP (`$attributes`, ver docs/diseno/guia_pantalla_panel.md §3): este átomo
    tiene raíz envolvente (`<div class="ag-input">`) + control real
    (`<input>`), no un único elemento raíz. Se resolvió partiendo el bag en
    dos: el `<div>` raíz solo fusiona la `class` de layout (`->only('class')`)
    — así una utilidad como `ag-form-section__field--full` llega al hijo del
    grid que la necesita. El `<input>` recibe el resto del bag salvo `class`
    (`->except('class')`) — así `disabled`, `data-*`, `aria-*`, `wire:model`,
    etc. le siguen llegando al control real como antes, y la clase fija
    `ag-input__field` no se duplica ni se ensucia con la clase de layout.
--}}
@props([
    'type' => 'text',
    // `name` puede ir en null para un campo de LECTURA (`readonly`), que
    // muestra un dato pero no lo manda en el POST — el nombre en
    // `/panel/perfil` es el primer caso. Sin `name` hay que pasar `id`:
    // el `for` del label sale de ahí, y un label sin destino no es un label.
    'name' => null,
    'id' => null,
    'label' => null,
    'value' => null,
    'placeholder' => null,
    'error' => null,
    'icon' => null,
    'suffix' => null,
    'help' => null,
    'helpTone' => null,
    'required' => false,
    'variant' => 'boxed',
])

@php
    $inputId = $id ?? $name;
    $isPassword = $type === 'password';
    $suffixId = $suffix ? "{$inputId}-suffix" : null;
    $helpId = $help ? "{$inputId}-help" : null;
    $errorId = $error ? "{$inputId}-error" : null;
    $describedBy = trim(($suffixId ?? '').' '.($helpId ?? '').' '.($errorId ?? ''));
@endphp

<div {{ $attributes->class(['ag-input', 'ag-input--line' => $variant === 'line'])->only('class') }}>
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
            @if ($name !== null) name="{{ $name }}" @endif
            id="{{ $inputId }}"
            value="{{ $value }}"
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($required) required @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            class="ag-input__field"
            {{ $attributes->except('class') }}
        >

        @if ($suffix)
            <span id="{{ $suffixId }}" class="ag-input__suffix">{{ $suffix }}</span>
        @endif

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
        <p id="{{ $helpId }}" @class(['ag-input__help', "ag-input__help--{$helpTone}" => $helpTone])>{{ $help }}</p>
    @endif

    @if ($error)
        <p id="{{ $errorId }}" class="ag-input__error" role="alert">{{ $error }}</p>
    @endif
</div>
