{{--
    Atom: date
    Selector de fecha propio que reemplaza a `type="date"` nativo crudo (11
    apariciones, tarea 76 / HU-53): cada navegador/SO lo dibuja distinto, no
    respeta los tokens del panel, e ignora `es` en varios de ellos.

    Contrato de progressive enhancement, mismo criterio que `atoms/select`:
    el `<input type="date">` nativo es el control REAL — tiene `name`, recibe
    `required`/`disabled`/`min`/`max`, se envía con el formulario y es lo
    único que existe si `resources/js/atoms/date.js` no cargó (el navegador
    dibuja su propio selector nativo como red de seguridad, no como
    experiencia principal). Si JS carga, arma al lado un botón-disparador +
    diálogo con calendario propio y oculta el nativo con
    `.ag-date__native--enhanced` (opacity 0, sin `display:none`, mismo truco
    que `select` para no romper foco programático ni envío) — pero el nativo
    sigue en el DOM y JS le escribe `.value` (formato ISO `YYYY-MM-DD`, el
    mismo que ya usa `type="date"` hoy) + dispara `change`, así que cualquier
    `wire:model`/listener atado al `name` original sigue funcionando igual
    que antes de esta migración.

    Por qué un diálogo con grid de foco itinerante ("roving tabindex") y no
    el patrón combobox+`aria-activedescendant` de `atoms/select`: acá no hay
    una lista filtrable de opciones, hay una grilla de 2 dimensiones (semana
    × día) — el patrón WAI-ARIA APG correspondiente es "Date Picker Dialog"
    con `role="dialog"` + `role="grid"`, foco real (no simulado) sobre la
    celda activa, y un único elemento con `tabindex="0"` por vez dentro de la
    grilla. Es más simple de razonar con un widget bidimensional y evita
    duplicar el mecanismo de `select` donde no encaja.

    Almacenamiento vs. visualización: el valor SIEMPRE viaja en ISO
    (`YYYY-MM-DD`, lo que espera `date`/`date_format` de Laravel y lo que ya
    manda el `type="date"` nativo hoy — cero cambio de contrato con el
    backend). El formato de visualización del panel (`d/m/Y`) es solo lo que
    se pinta en el botón-disparador y dentro del diálogo; lo arma
    `resources/js/atoms/date.js` a partir del valor ISO.

    Props:
    - name (requerido), id (default = name).
    - value (nullable): fecha ISO `YYYY-MM-DD` preseleccionada.
    - min, max (nullable): fechas ISO, mismo atributo que ya soporta
      `type="date"` nativo — acá además deshabilitan celdas del calendario.
    - label, placeholder, help, error: strings ya traducidos por el llamador
      (ADR 0013). El placeholder es lo que se ve en el disparador sin
      selección — los nombres de mes/día y las etiquetas de navegación SÍ son
      chrome del átomo (mismo criterio que "Buscar…" en `atoms/select`) y
      viven en `lang/es/ui.php` → `ui.date.*`.
    - icon: ícono Material Symbols de prefijo, igual que `atoms/input`
      (opcional — el ícono de calendario del disparador ya es fijo, no hace
      falta pasarlo).
    - required, disabled (bool, default false).

    Semana empezando el lunes: `resources/js/atoms/date.js` calcula el índice
    de columna con `(getDay() + 6) % 7` (JS trae domingo=0) — nunca reordena
    un arreglo de textos, así que el cálculo de fecha real y el de qué
    columna dibujar nunca se desincronizan.

    LSP (`$attributes`): mismo criterio partido que `atoms/select` — la raíz
    (`<div class="ag-date">`) solo fusiona `class` (`->only('class')`), el
    `<input>` nativo recibe el resto (`->except('class')`).

    El botón de limpiar es HERMANO del disparador, no hijo: un `<button>`
    dentro de otro `<button>` es HTML inválido (el parser lo saca del árbol).
    Por eso acá, a diferencia de `atoms/select` (que puede anidar su botón de
    limpiar porque el trigger ahí es un `<div role="combobox">`, no un
    `<button>`), el disparador SÍ es un `<button>` real —da gratis
    Enter/Espacio para abrir— y el botón de limpiar es un elemento flex
    hermano dentro de `.ag-date__control`.
--}}
@props([
    'name',
    'id' => null,
    'label' => null,
    'value' => null,
    'placeholder' => null,
    'error' => null,
    'icon' => null,
    'help' => null,
    'required' => false,
    'disabled' => false,
    'min' => null,
    'max' => null,
])

@php
    $dateId = $id ?? $name;
    $helpId = $help ? "{$dateId}-help" : null;
    $errorId = $error ? "{$dateId}-error" : null;
    $describedBy = trim(($helpId ?? '').' '.($errorId ?? ''));
    $dialogId = "{$dateId}-dialog";

    // Espejo del formato ISO->d/m/Y que arma date.js, para que el disparador
    // muestre algo correcto server-side incluso antes de que JS corra (igual
    // que el $etiquetaActual de atoms/select). Manipulación de string, no
    // Carbon/DateTime: $value ya es 'YYYY-MM-DD', partirlo evita cualquier
    // interpretación de huso horario para un dato que no lleva hora.
    $valorMostrado = null;
    if ($value) {
        [$anioValor, $mesValor, $diaValor] = explode('-', $value);
        $valorMostrado = "{$diaValor}/{$mesValor}/{$anioValor}";
    }
@endphp

<div
    {{ $attributes->class(['ag-date'])->only('class') }}
    data-ag-date
    data-label-meses="{{ implode(',', __('ui.date.meses')) }}"
    data-label-dias="{{ implode(',', __('ui.date.dias_cortos')) }}"
    data-label-dias-completos="{{ implode(',', __('ui.date.dias_completos')) }}"
    data-label-placeholder="{{ $placeholder }}"
>
    @if ($label)
        <label for="{{ $dateId }}" class="ag-date__label" id="{{ $dateId }}-label">
            {{ $label }}
            @if ($required)
                <span class="ag-date__required" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <div class="ag-date__control {{ $error ? 'ag-date__control--error' : '' }}">
        @if ($icon)
            <x-atoms.icon :name="$icon" size="sm" class="ag-date__icon" />
        @endif

        <input
            type="date"
            name="{{ $name }}"
            id="{{ $dateId }}"
            value="{{ $value }}"
            @if ($min) min="{{ $min }}" @endif
            @if ($max) max="{{ $max }}" @endif
            @if ($required) required @endif
            @if ($disabled) disabled @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            class="ag-date__native"
            {{ $attributes->except('class') }}
        >

        {{-- Disparador armado por resources/js/atoms/date.js. Oculto por
             defecto (`hidden`): sin JS, el <input type="date"> nativo de
             arriba es el único control visible y funcional. Botón real (no
             div con role sintético): Enter/Espacio para abrir ya los da
             gratis la semántica nativa de <button>. --}}
        <button
            type="button"
            class="ag-date__trigger"
            data-ag-date-trigger
            aria-haspopup="dialog"
            aria-expanded="false"
            aria-controls="{{ $dialogId }}"
            @if ($label)
                aria-labelledby="{{ $dateId }}-label"
            @elseif ($placeholder)
                aria-label="{{ $placeholder }}"
            @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            @if ($disabled) disabled @endif
            hidden
        >
            <span class="ag-date__value" data-ag-date-value>{{ $valorMostrado ?? $placeholder }}</span>
            <x-atoms.icon name="calendar_month" size="sm" class="ag-date__icon-trigger" />
        </button>

        <button
            type="button"
            class="ag-date__clear"
            data-ag-date-clear
            tabindex="-1"
            aria-hidden="true"
            aria-label="{{ __('ui.date.limpiar') }}"
            hidden
        >
            <x-atoms.icon name="close" size="sm" />
        </button>
    </div>

    {{-- Diálogo modal (WAI-ARIA APG "Date Picker Dialog"): armado y pintado
         enteramente por date.js (no hay forma de saber en Blade qué mes hay
         que mostrar sin duplicar la lógica de "mes inicial" en dos lados). --}}
    <div
        class="ag-date__dialog"
        id="{{ $dialogId }}"
        role="dialog"
        aria-modal="true"
        aria-label="{{ $label ?? __('ui.date.elegir_fecha') }}"
        data-ag-date-dialog
        hidden
    >
        <div class="ag-date__dialog-header">
            <button type="button" class="ag-date__nav" data-ag-date-prev-month aria-label="{{ __('ui.date.mes_anterior') }}">
                <x-atoms.icon name="chevron_left" size="sm" />
            </button>
            <p class="ag-date__heading" data-ag-date-heading aria-live="polite"></p>
            <button type="button" class="ag-date__nav" data-ag-date-next-month aria-label="{{ __('ui.date.mes_siguiente') }}">
                <x-atoms.icon name="chevron_right" size="sm" />
            </button>
        </div>

        <table class="ag-date__grid" role="grid" data-ag-date-grid></table>

        <div class="ag-date__dialog-footer">
            <button type="button" class="ag-date__today" data-ag-date-today>{{ __('ui.date.hoy') }}</button>
        </div>
    </div>

    @if ($help)
        <p id="{{ $helpId }}" class="ag-date__help">{{ $help }}</p>
    @endif

    @if ($error)
        <p id="{{ $errorId }}" class="ag-date__error" role="alert">{{ $error }}</p>
    @endif
</div>
