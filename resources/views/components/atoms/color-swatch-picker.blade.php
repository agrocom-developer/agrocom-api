{{--
    Atom: color-swatch-picker
    Selección única entre una PALETA CURADA de colores fijos — variante
    visual de "elegir una opción entre pocas" que usa swatches (círculos de
    color) en vez de texto/puntos. Igual que `atoms/radio-group`, son
    `<input type="radio">` reales agrupados en un `<fieldset>`/`<legend>`:
    sin JS propio, el navegador ya mueve el foco con las flechas entre
    opciones del mismo `name` y Espacio/click marca.

    Qué NO es: no es un selector de color libre (sin input de texto, sin
    `<input type="color">` nativo, sin hex picker) — la paleta es un
    conjunto FIJO que el llamador pasa en `options`, a propósito, para que
    dos registros nunca terminen con tonos casi idénticos (pedido explícito
    del dueño). Tampoco valida ni resuelve el catálogo de colores: el
    llamador ya le pasa `options` como `hex => etiqueta`, traducida
    (ADR 0013) — el color EN SÍ (`#RRGGBB`) es el valor de cada opción, no
    un id que haya que resolver contra otra tabla.

    Por qué NO extiende/envuelve `atoms/radio-group` (independiente, mismo
    nivel): un atom "no compone ningún otro componente del catálogo"
    (docs/diseno/guia_pantalla_panel.md §2) — envolver `<x-atoms.radio-group>`
    lo pasaría de nivel. Reutiliza la MISMA técnica de accesibilidad nativa
    (fieldset + legend + radios reales, input oculto "visually-hidden" +
    control hermano pintado en CSS a partir de `:checked` — igual que
    `atoms/checkbox`/`atoms/switch`), pero con marcado propio porque la
    forma visual (swatch circular, no punto + texto) y la lógica de
    selección (insignia con check, no solo cambio de color) son distintas.
    Es el mismo criterio que ya usa el catálogo con `role-card` (no hay una
    molécula "lista de roles" que envuelva un átomo genérico): una pieza
    nueva para una forma nueva, no un wrapper.

    Invariante 11 de CLAUDE.md (ningún color hardcodeado): el HEX de cada
    swatch es DATO pasado por el llamador (paleta curada de negocio, no un
    token de theming del panel — un color de propiedad no cambia entre
    tema claro/oscuro) y viaja como custom property inline
    (`style="--ag-color-swatch-fill: ..."`), nunca como literal dentro de
    `color-swatch-picker.css`: ese archivo no tiene un solo hex, exactamente
    igual que ningún componente del catálogo referencia un `src` de imagen
    hardcodeado — es la misma categoría que un dato de negocio (compárese
    con cómo `atoms/logo` recibe su `src`). El ANILLO que separa cada
    swatch de su fondo (para que no se funda en ningún tema) sí es un token
    real (`--ag-color-border-strong` en reposo, `--ag-color-primary`
    seleccionado) — ver `color-swatch-picker.css`.

    Props:
    - name (requerido), id (default = name).
    - options (array, default []): `hex => etiqueta`, ya traducida. El HEX
      es tanto la clave (lo que se envía al submit) como el color a pintar.
    - value (nullable): hex actualmente seleccionado (debe existir como
      clave de `options`).
    - label: texto ya traducido, usado como `<legend>` — igual que
      `atoms/radio-group`, es la única forma soportada de darle nombre
      accesible al grupo con este átomo.
    - help, error: strings ya traducidos por el llamador.
    - required, disabled (bool, default false): se aplican a todos los
      radios del grupo (mismo criterio que `atoms/radio-group`).

    Accesibilidad — la selección NUNCA depende solo del color:
    - El estado real lo lleva el `<input type="radio">` nativo
      (`:checked`/`aria-checked` implícito) — un lector de pantalla anuncia
      "seleccionado" sin depender de nada visual.
    - Cada opción lleva un `<span>` con el NOMBRE del color (visualmente
      oculto, técnica "visually-hidden") como contenido del `<label>`, así
      el nombre accesible del radio es "Verde bosque", no "#218349" ni
      "radio 4".
    - La opción seleccionada muestra una insignia con ícono de check que
      APARECE (`scale(0)→scale(1)`, igual que el punto de `radio-group` y
      el check de `checkbox`) sobre un fondo de token fijo
      (`--ag-color-primary` + ícono `--ag-color-primary-contrast`, blanco
      verificado AA en `sistema_diseno_panel.md` §1.3) — nunca el color de
      la opción: así el check se lee igual de bien sobre cualquiera de los
      13 tonos de la paleta, en vez de depender de que cada hex tenga
      contraste suficiente con un ícono blanco/negro fijo.
    - El anillo del swatch también cambia a `--ag-color-primary` cuando está
      marcado (mismo lenguaje que el borde de `atoms/radio-group`), como
      señal redundante — no la única.

    LSP (`$attributes`, ver docs/diseno/guia_pantalla_panel.md §3): mismo
    criterio partido que `atoms/radio-group` — la raíz
    (`<fieldset class="ag-color-swatch-picker">`) solo fusiona `class`
    (`->only('class')`); el resto del bag (`->except('class')`, p. ej.
    `wire:model`) se reenvía a CADA `<input type="radio">` del grupo.
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
    {{ $attributes->class(['ag-color-swatch-picker'])->only('class') }}
    @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
>
    @if ($label)
        <legend class="ag-color-swatch-picker__legend">
            {{ $label }}
            @if ($required)
                <span class="ag-color-swatch-picker__required" aria-hidden="true">*</span>
            @endif
        </legend>
    @endif

    <div class="ag-color-swatch-picker__options">
        @foreach ($options as $optValue => $optLabel)
            @php $optionId = "{$groupId}-opt-{$loop->index}"; @endphp
            <label for="{{ $optionId }}" class="ag-color-swatch-picker__option" title="{{ $optLabel }}">
                <input
                    type="radio"
                    name="{{ $name }}"
                    id="{{ $optionId }}"
                    value="{{ $optValue }}"
                    @checked((string) $value === (string) $optValue)
                    @if ($required) required @endif
                    @if ($disabled) disabled @endif
                    class="ag-color-swatch-picker__input"
                    {{ $attributes->except('class') }}
                >

                <span class="ag-color-swatch-picker__swatch" style="--ag-color-swatch-fill: {{ $optValue }};" aria-hidden="true">
                    <span class="ag-color-swatch-picker__check">
                        <x-atoms.icon name="check" size="sm" />
                    </span>
                </span>

                <span class="ag-color-swatch-picker__sr-label">{{ $optLabel }}</span>
            </label>
        @endforeach
    </div>

    @if ($help)
        <p id="{{ $helpId }}" class="ag-color-swatch-picker__help">{{ $help }}</p>
    @endif

    @if ($error)
        <p id="{{ $errorId }}" class="ag-color-swatch-picker__error" role="alert">{{ $error }}</p>
    @endif
</fieldset>
