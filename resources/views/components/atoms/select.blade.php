{{--
    Atom: select
    Combobox accesible que reemplaza al `<select class="ag-input__field">`
    crudo repetido en 70 pantallas (tarea 76 / HU-53). Sin lógica de negocio:
    no valida, no resuelve el catálogo de opciones — el llamador ya le pasa
    `options` como `id => etiqueta`, traducida (ADR 0013).

    Contrato de progressive enhancement: el `<select>` nativo es el control
    REAL — el que tiene `name`, recibe `required`/`disabled`, se envía con el
    formulario y es lo único que existe si `resources/js/atoms/select.js` no
    cargó. JS (si carga) arma al lado un combobox (`role="combobox"` +
    listbox con búsqueda/teclado) y oculta visualmente el nativo con la clase
    `ag-select__native--enhanced` — pero el nativo sigue en el DOM y JS le
    escribe `.value` + dispara `change` en cada selección, así que cualquier
    `wire:model`/listener atado al `name` original sigue funcionando igual
    que antes de esta migración.

    Props:
    - name (requerido), id (default = name).
    - options (array, default []): `valor => etiqueta`, ya traducida.
    - value (nullable): valor actualmente seleccionado (debe existir como
      clave de `options`).
    - label, placeholder, help, error: strings ya traducidos por el llamador
      (este átomo no decide textos de negocio, ADR 0013 — los textos propios
      del combobox, "Buscar…"/"Sin resultados"/"Limpiar", sí son chrome del
      átomo y viven en `lang/es/ui.php`, mismo criterio que el toggle de
      contraseña de `atoms/input`).
    - icon: ícono Material Symbols de prefijo, igual que `atoms/input`.
    - required, disabled (bool, default false).
    - actionIcon/actionHref/actionLabel (nullable, los tres van juntos):
      sufijo del control — un botón-ícono dentro del mismo borde del select
      (mismo lugar que `ag-select__clear`, no un elemento aparte), pensado
      para "crear nuevo" sin salir del combobox (p. ej. `cliente_id` del
      formulario de contrato). Relleno naranja de marca (`--ag-color-accent`,
      ídem `ag-button--accent`), navega de página completa a `actionHref`
      (nunca abre modal — mismo criterio que el resto del panel) y lleva
      `data-ag-link-accent`, el mismo hook que ya usan los links de alta
      rápida de propiedad/lote para que `contratos-form.js` (si la página lo
      trae) guarde el borrador del formulario antes de navegar. `actionLabel`
      es el tooltip (Bootstrap, `data-bs-toggle="tooltip"`, ya inicializado
      globalmente en `resources/js/app.js`) y siempre el `aria-label`
      (nombre accesible completo, aunque haya `actionText` visible — mismo
      criterio que un botón con ícono+texto corto pero descripción más larga
      para el lector de pantalla). `actionText` (nullable): texto corto
      opcional junto al ícono (p. ej. "Nuevo"); sin él, el botón es solo-
      ícono. Sin `actionIcon`+`actionHref` no se renderiza nada nuevo: los
      ~70 usos existentes del átomo quedan igual. `actionHidden` (bool,
      default false): arranca con `hidden` en el sufijo — para el caso de
      "Propiedad" del formulario de contrato, deshabilitado hasta elegir
      cliente, donde el JS de la página saca el `hidden` a mano (mismo
      criterio que el `disabled` del `<select>`).

    Búsqueda automática: con más de 8 opciones el combobox arma un filtro de
    texto dentro del propio desplegable — por PALABRAS: cada palabra escrita
    debe aparecer en la etiqueta, en cualquier orden ("cotoca santa" encuentra
    "Cotoca - Santa Cruz"), sin distinguir mayúsculas, minúsculas ni tildes —
    un `<input>` REAL que recibe el foco al abrir (19/9/2026;
    antes era una fila decorativa que no tomaba foco: pulsarla no hacía nada, no
    aparecía el teclado en el celular y el placeholder no se iba). Con 8 o menos,
    no hay caja de búsqueda visible pero el teclado igual soporta type-ahead
    (saltar a la primera opción que empieza con la letra tipeada) — mismo
    comportamiento que un `<select>` nativo. El umbral es automático
    (`count($options) > 8`) salvo que `searchable` lo fuerce. OJO: si las
    opciones las llena JS después de renderizar (selects en cascada), el conteo
    del servidor es 0 — hay que pasar `searchable` a mano.

    - searchable (bool|null, default null): `null` deja el umbral automático
      de arriba; `false` fuerza el modo type-ahead aunque haya más de 8
      opciones — lo usa el filtro de departamento de
      `comercial::pages.propiedades.index`, desde cuando la caja de búsqueda no
      dejaba escribir texto (corregido el 19/9/2026 con el `<input>` real; ese
      filtro se dejó como estaba). `true` fuerza el modo buscable aunque haya 8
      opciones o menos, o si las opciones las llena JS luego.

    Accesibilidad: sin búsqueda, un solo elemento enfocable hace de combobox
    durante toda la interacción (el `div[role="combobox"]`) y el type-ahead se
    captura por `keydown` sobre él. Con búsqueda, al abrir el foco pasa al
    `<input role="searchbox">` del desplegable, que es el dueño de
    `aria-activedescendant` mientras está abierto; al cerrar, el foco vuelve
    al combobox — ver resources/js/atoms/select.js.

    Limpiar («×»): la ofrece todo select NO obligatorio con un valor elegido. Un
    obligatorio no, salvo que el llamador pase `data-ag-select-clearable` (va al
    nativo, como todo `data-*`): para cuando quitar lo elegido es parte de la
    carga y el vacío lo frena la validación al guardar.

    LSP (`$attributes`, ver docs/diseno/guia_pantalla_panel.md §3): mismo
    criterio partido que `atoms/input` — la raíz (`<div class="ag-select">`)
    solo fusiona `class` (`->only('class')`), el `<select>` nativo recibe el
    resto (`->except('class')`) para que `data-*`, `wire:model`, etc. le
    sigan llegando al control real.
--}}
@props([
    'name',
    'id' => null,
    'label' => null,
    'options' => [],
    'value' => null,
    'placeholder' => null,
    'error' => null,
    'icon' => null,
    'help' => null,
    'required' => false,
    'disabled' => false,
    'actionIcon' => null,
    'actionHref' => null,
    'actionLabel' => null,
    'actionText' => null,
    'actionHidden' => false,
    'searchable' => null,
])

@php
    // $options llega como array plano o Collection según el llamador (varios
    // pasan directo una Collection Eloquent `id => etiqueta`, p. ej.
    // $clientesDisponibles) — se normaliza acá una sola vez porque
    // array_key_exists() exige un array real, a diferencia de count()/foreach,
    // que aceptan cualquier Traversable/Countable.
    $options = $options instanceof \Illuminate\Support\Collection ? $options->all() : $options;
    $selectId = $id ?? $name;
    $helpId = $help ? "{$selectId}-help" : null;
    $errorId = $error ? "{$selectId}-error" : null;
    $describedBy = trim(($helpId ?? '').' '.($errorId ?? ''));
    $listboxId = "{$selectId}-listbox";
    $esBuscable = $searchable ?? (count($options) > 8);
    $etiquetaActual = $value !== null && array_key_exists($value, $options) ? $options[$value] : null;
@endphp

<div
    {{ $attributes->class(['ag-select'])->only('class') }}
    data-ag-select
    data-ag-select-buscable="{{ $esBuscable ? '1' : '0' }}"
    data-label-search="{{ __('ui.select.search_placeholder') }}"
    data-label-no-results="{{ __('ui.select.no_results') }}"
    data-label-clear="{{ __('ui.select.clear') }}"
    data-label-placeholder="{{ $placeholder }}"
>
    @if ($label)
        <label for="{{ $selectId }}" class="ag-select__label" id="{{ $selectId }}-label">
            {{ $label }}
            @if ($required)
                <span class="ag-select__required" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <div class="ag-select__control {{ $error ? 'ag-select__control--error' : '' }}">
        @if ($icon)
            <x-atoms.icon :name="$icon" size="sm" class="ag-select__icon" />
        @endif

        <select
            name="{{ $name }}"
            id="{{ $selectId }}"
            class="ag-select__native"
            @if ($required) required @endif
            @if ($disabled) disabled @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->except('class') }}
        >
            @if ($placeholder)
                {{-- Placeholder seleccionado cuando NO hay valor: `null` o `''` (los
                     formularios de alta pasan `old('campo', '')`). Con solo `=== null`,
                     un `''` dejaba el placeholder sin marcar y el navegador elegía la
                     PRIMERA opción real — el select mostraba "Beni" sin que nadie lo
                     hubiera elegido. --}}
                <option value="" @selected($value === null || $value === '') disabled hidden>{{ $placeholder }}</option>
            @endif
            @foreach ($options as $optValue => $optLabel)
                <option value="{{ $optValue }}" @selected((string) $value === (string) $optValue)>{{ $optLabel }}</option>
            @endforeach
        </select>

        {{-- Combobox armado por resources/js/atoms/select.js. Oculto por
             defecto (`hidden`): sin JS, el <select> nativo de arriba es el
             único control visible y funcional. --}}
        <div
            class="ag-select__trigger"
            data-ag-select-trigger
            role="combobox"
            tabindex="{{ $disabled ? '-1' : '0' }}"
            aria-haspopup="listbox"
            aria-expanded="false"
            aria-controls="{{ $listboxId }}"
            @if ($label)
                aria-labelledby="{{ $selectId }}-label"
            @elseif ($placeholder)
                aria-label="{{ $placeholder }}"
            @endif
            @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            @if ($required) aria-required="true" @endif
            aria-disabled="{{ $disabled ? 'true' : 'false' }}"
            hidden
        >
            <span class="ag-select__value" data-ag-select-value>{{ $etiquetaActual ?? $placeholder }}</span>

            <button
                type="button"
                class="ag-select__clear"
                data-ag-select-clear
                tabindex="-1"
                aria-hidden="true"
                hidden
            >
                <x-atoms.icon name="close" size="sm" />
            </button>

            <x-atoms.icon name="arrow_drop_down" size="sm" class="ag-select__arrow" />
        </div>

        @if ($actionIcon && $actionHref)
            <a
                href="{{ $actionHref }}"
                class="ag-select__action"
                data-bs-toggle="tooltip"
                data-bs-placement="top"
                data-ag-link-accent
                aria-label="{{ $actionLabel }}"
                title="{{ $actionLabel }}"
                @if ($actionHidden) hidden @endif
            >
                <x-atoms.icon :name="$actionIcon" size="md" />
                @if ($actionText)
                    <span class="ag-select__action-label">{{ $actionText }}</span>
                @endif
            </a>
        @endif
    </div>

    <ul
        class="ag-select__listbox"
        id="{{ $listboxId }}"
        role="listbox"
        data-ag-select-listbox
        @if ($label) aria-label="{{ $label }}" @endif
        hidden
    ></ul>

    @if ($help)
        <p id="{{ $helpId }}" class="ag-select__help">{{ $help }}</p>
    @endif

    @if ($error)
        <p id="{{ $errorId }}" class="ag-select__error" role="alert">{{ $error }}</p>
    @endif
</div>
