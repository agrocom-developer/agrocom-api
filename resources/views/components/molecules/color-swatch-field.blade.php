{{--
    Molecule: color-swatch-field (16/9/2026, corregido el mismo día)
    Control compacto de UNA fila para un campo de "paleta curada" (hoy solo
    `Propiedad.color`, Comercial): swatch chico + HEX en texto + botón
    "Cambiar" que despliega un POPUP anclado (dropdown de Bootstrap, mismo
    lenguaje que `organisms/filter-panel`) con la paleta completa —
    corrección directa del dueño sobre la primera versión: "no es un modal
    dialog, tiene que ser un popup de los colores". Reemplaza a los 16
    círculos en fila que antes pintaba directo `atoms/color-swatch-picker`
    dentro del formulario.

    Por qué es UN solo componente y no dos (a diferencia del primer intento,
    `color-swatch-field` + `color-palette-modal` separados): un dropdown de
    Bootstrap necesita que el trigger y el `.dropdown-menu` sean HERMANOS
    directos dentro del mismo `.dropdown` — a diferencia de un modal, que se
    puede targetear por id desde cualquier parte del documento. Separarlos
    en dos piezas de catálogo independientes ya no es posible con este
    patrón de interacción.

    Por qué molecule y no atom: compone `atoms/button` y `atoms/color-swatch-picker`
    — un atom no puede componer otro átomo (`guia_pantalla_panel.md` §2).

    Selección inmediata, sin paso de "confirmar": clickear un swatch
    dispara `change` en su radio (`atoms/color-swatch-picker`) y ESE MISMO
    click, por comportamiento default de Bootstrap (`data-bs-auto-close`
    sin especificar = `true`), cierra el popup — no hace falta ningún botón
    "Aplicar", ni JS que lo intercepte.

    Accesibilidad: el HEX siempre viaja como TEXTO visible junto al swatch
    (nunca solo color); el nombre accesible del color actual (`colorName`,
    derivado acá mismo de `options[value]`) viaja en un `<span>`
    visualmente oculto. La selección real adentro del popup hereda toda la
    accesibilidad ya resuelta por `atoms/color-swatch-picker` (radios
    nativos, nombre accesible por opción, insignia de check que no depende
    del color).

    Sincronizar el swatch+hex+nombre de la fila cuando se elige un color
    adentro del popup es JS que arma `frontend`
    (`resources/js/molecules/color-swatch-field.js`) — hooks:
    - Raíz: `data-ag-color-swatch-field`.
    - Swatch: `data-ag-color-swatch-field-swatch`.
    - Texto HEX: `data-ag-color-swatch-field-hex`.
    - Nombre accesible: `data-ag-color-swatch-field-name`.
    - Cada radio de adentro: `data-ag-color-swatch-field-input` (mismo
      mecanismo que antes, `atoms/color-swatch-picker` reenvía
      `$attributes->except('class')` a cada radio, no al fieldset).

    Props:
    - id (requerido): DOM id de la raíz.
    - name (requerido): nombre del campo real — se reenvía a
      `atoms/color-swatch-picker`, es lo que viaja en el POST.
    - options (array, requerido): `hex => etiqueta`, ya traducida — mismo
      contrato que `atoms/color-swatch-picker`. El nombre accesible del
      color actual (`colorName`) se deriva de acá (`options[value]`), el
      llamador no lo calcula aparte.
    - label (nullable): rótulo visual arriba del control, un `<p>` (mismo
      criterio que `molecules/file-field`).
    - value (nullable): HEX actualmente elegido.
    - changeLabel (requerido): copy ya traducido del botón que abre el popup.
    - placeholderLabel (nullable): copy para cuando `value` está vacío.
    - help, error (nullable).
    - required, disabled (bool, default false): se reenvían al picker de
      adentro; `disabled` además deshabilita el botón "Cambiar".

    LSP (`$attributes`): fusiona `class` en la raíz.
--}}
@props([
    'id',
    'name',
    'options' => [],
    'label' => null,
    'value' => null,
    'changeLabel',
    'placeholderLabel' => null,
    'help' => null,
    'error' => null,
    'required' => false,
    'disabled' => false,
])

@php
    $helpId = $help ? "{$id}-help" : null;
    $errorId = $error ? "{$id}-error" : null;
    $describedBy = trim(($helpId ?? '').' '.($errorId ?? ''));
    $tieneColor = filled($value);
    $colorName = $tieneColor ? ($options[$value] ?? null) : null;
@endphp

<div
    id="{{ $id }}"
    {{ $attributes->class(['ag-color-swatch-field']) }}
    data-ag-color-swatch-field
>
    @if ($label)
        <p class="ag-color-swatch-field__label">{{ $label }}</p>
    @endif

    <div
        class="ag-color-swatch-field__control{{ $error ? ' ag-color-swatch-field__control--error' : '' }}"
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
    >
        <span
            class="ag-color-swatch-field__swatch{{ $tieneColor ? '' : ' ag-color-swatch-field__swatch--empty' }}"
            @if ($tieneColor) style="--ag-color-swatch-fill: {{ $value }};" @endif
            data-ag-color-swatch-field-swatch
            aria-hidden="true"
        ></span>

        @if ($tieneColor)
            <span class="ag-color-swatch-field__hex" data-ag-color-swatch-field-hex>{{ strtoupper($value) }}</span>
            @if ($colorName)
                <span class="ag-color-swatch-field__sr-name" data-ag-color-swatch-field-name>{{ $colorName }}</span>
            @endif
        @elseif ($placeholderLabel)
            <span class="ag-color-swatch-field__placeholder" data-ag-color-swatch-field-hex>{{ $placeholderLabel }}</span>
        @endif

        <div class="dropdown ag-color-swatch-field__dropdown">
            <x-atoms.button
                type="button"
                variant="outline"
                size="sm"
                class="ag-color-swatch-field__trigger"
                data-bs-toggle="dropdown"
                aria-expanded="false"
                :disabled="$disabled"
            >
                {{ $changeLabel }}
            </x-atoms.button>

            <div class="dropdown-menu dropdown-menu-end ag-color-swatch-field__menu">
                <x-atoms.color-swatch-picker
                    :name="$name"
                    :options="$options"
                    :value="$value"
                    :required="$required"
                    :disabled="$disabled"
                    data-ag-color-swatch-field-input
                />
            </div>
        </div>
    </div>

    @if ($help)
        <p id="{{ $helpId }}" class="ag-color-swatch-field__help">{{ $help }}</p>
    @endif

    @if ($error)
        <p id="{{ $errorId }}" class="ag-color-swatch-field__error" role="alert">{{ $error }}</p>
    @endif
</div>
