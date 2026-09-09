{{--
    Atom: checkbox-group
    Selección MÚLTIPLE dentro de una lista de opciones, con
    `<input type="checkbox">` reales agrupados en un `<fieldset>`/`<legend>`
    — dimensionado para listas largas (repuestos, personas, lotes: lo
    consume la tarea 80, repuestos por casillas). Sin lógica de negocio: no
    valida, no resuelve el catálogo de opciones — el llamador ya le pasa
    `options` como `id => etiqueta`, traducida (ADR 0013).

    Distinto de `atoms/select`: acá NO hace falta simular nada con
    `role="combobox"`/`aria-activedescendant` — cada casilla ya es un
    control nativo, real, tabulable y con su propio `<label>`, así que el
    teclado (Tab entre casillas, Espacio para marcar) es gratis del
    navegador. Lo único que agrega `resources/js/atoms/checkbox-group.js` es
    un filtro de texto que oculta/muestra filas — mismo umbral que
    `atoms/select` ("más de 8 opciones" → aparece la búsqueda), pero es un
    patrón de referencia, no de código: acá se filtran filas visibles de
    controles reales, no se arma una lista falsa.

    Progressive enhancement: sin JS, la caja de búsqueda ni siquiera se
    muestra (`hidden` de fábrica en el marcado) — una caja de búsqueda que
    no filtra nada es peor que no tenerla. Sin JS, la lista completa de
    casillas sigue ahí, visible y usable.

    Props:
    - name (requerido): cada casilla se envía como `{{ name }}[]`.
    - id (default = name).
    - options (array, default []): `valor => etiqueta`, ya traducida.
    - value (array, default []): valores actualmente marcados (deben existir
      como clave de `options`).
    - label: texto ya traducido, usado como `<legend>` (mismo motivo que
      `atoms/radio-group`: sin `label` no hay a quién reenviarle un
      `aria-label` del llamador, porque no hay un único control nativo).
    - help, error: strings ya traducidos por el llamador. Los textos propios
      del chrome de búsqueda ("Buscar…"/"Sin resultados") sí son del átomo,
      igual que en `atoms/select`, y viven en `lang/es/ui.php`.
    - required, disabled (bool, default false): se aplican a cada casilla.
      "Al menos una marcada" no tiene validación nativa de HTML para un
      grupo de checkboxes — si la pantalla lo exige, se valida en el
      backend (regla `required` de Laravel sobre el array), este átomo solo
      expone `aria-required` a nivel de grupo.
    - extraPorOpcion (array, default []): `valor => HTML ya armado` (string,
      típicamente `view(...)->render()` del llamador), inyectado dentro del
      `<li>` de esa opción, después de la etiqueta. Pensado para un control
      asociado a la opción marcada (p. ej. la cantidad de la tarea 80,
      repuestos por casillas) — el átomo no sabe qué es ese contenido ni
      cuándo mostrarse/ocultarse: mostrarlo (CSS `:has(:checked)` o JS del
      llamador) y habilitar sus campos (`disabled` cuando la casilla está
      sin marcar, para que no viajen en el POST) es responsabilidad de quien
      lo pasa, no de este átomo. Sigue "sin lógica de negocio": es una bolsa
      de HTML opaca, indexada por el mismo valor que ya usa `options`.

    Búsqueda automática: con más de 8 opciones aparece el filtro de texto
    (substring, sin distinguir mayúsculas ni tildes) dentro del propio
    grupo. El umbral no es un prop, es automático (`count($options) > 8`),
    mismo criterio ISP que `atoms/select`.

    LSP (`$attributes`, ver docs/diseno/guia_pantalla_panel.md §3): mismo
    criterio partido que `atoms/radio-group` — la raíz
    (`<fieldset class="ag-checkbox-group">`) solo fusiona `class`
    (`->only('class')`); el resto del bag (`->except('class')`, p. ej.
    `wire:model`) se reenvía a CADA `<input type="checkbox">` del grupo.
--}}
@props([
    'name',
    'id' => null,
    'label' => null,
    'options' => [],
    'value' => [],
    'error' => null,
    'help' => null,
    'required' => false,
    'disabled' => false,
    'extraPorOpcion' => [],
])

@php
    $groupId = $id ?? $name;
    $helpId = $help ? "{$groupId}-help" : null;
    $errorId = $error ? "{$groupId}-error" : null;
    $describedBy = trim(($helpId ?? '').' '.($errorId ?? ''));
    $listId = "{$groupId}-list";
    $esBuscable = count($options) > 8;
    // $value puede llegar como Collection (p. ej. ->pluck('id') de una
    // relación) — array_map() exige un array real, mismo ajuste que
    // atoms/select con $options.
    $valoresMarcados = array_map('strval', $value instanceof \Illuminate\Support\Collection ? $value->all() : $value);
@endphp

<fieldset
    {{ $attributes->class(['ag-checkbox-group'])->only('class') }}
    data-ag-checkbox-group
    data-label-search="{{ __('ui.checkbox_group.search_placeholder') }}"
    data-label-no-results="{{ __('ui.checkbox_group.no_results') }}"
    @if ($required) aria-required="true" @endif
    @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
>
    @if ($label)
        <legend class="ag-checkbox-group__legend">
            {{ $label }}
            @if ($required)
                <span class="ag-checkbox-group__required" aria-hidden="true">*</span>
            @endif
        </legend>
    @endif

    @if ($esBuscable)
        {{-- Armado por resources/js/atoms/checkbox-group.js. Oculto por
             defecto: sin JS no filtra nada, así que no se muestra. --}}
        <div class="ag-checkbox-group__search" data-ag-checkbox-group-search-wrap hidden>
            <x-atoms.icon name="search" size="sm" class="ag-checkbox-group__search-icon" />
            <input
                type="search"
                class="ag-checkbox-group__search-input"
                data-ag-checkbox-group-search
                placeholder="{{ __('ui.checkbox_group.search_placeholder') }}"
                aria-label="{{ __('ui.checkbox_group.search_placeholder') }}"
                aria-controls="{{ $listId }}"
            >
        </div>
    @endif

    <p class="ag-checkbox-group__empty" data-ag-checkbox-group-empty hidden>
        {{ __('ui.checkbox_group.no_results') }}
    </p>

    <ul class="ag-checkbox-group__options" id="{{ $listId }}" data-ag-checkbox-group-list>
        @foreach ($options as $optValue => $optLabel)
            @php $optionId = "{$groupId}-opt-{$loop->index}"; @endphp
            <li class="ag-checkbox-group__item" data-ag-checkbox-group-item>
                <label for="{{ $optionId }}" class="ag-checkbox-group__option">
                    <input
                        type="checkbox"
                        name="{{ $name }}[]"
                        id="{{ $optionId }}"
                        value="{{ $optValue }}"
                        @checked(in_array((string) $optValue, $valoresMarcados, true))
                        @if ($required) required @endif
                        @if ($disabled) disabled @endif
                        class="ag-checkbox-group__input"
                        {{ $attributes->except('class') }}
                    >

                    <span class="ag-checkbox-group__box" aria-hidden="true">
                        <x-atoms.icon name="check" size="sm" class="ag-checkbox-group__check" />
                    </span>

                    <span class="ag-checkbox-group__option-label">{{ $optLabel }}</span>
                </label>

                @if (isset($extraPorOpcion[$optValue]))
                    <div class="ag-checkbox-group__option-extra" data-ag-checkbox-group-option-extra>
                        {!! $extraPorOpcion[$optValue] !!}
                    </div>
                @endif
            </li>
        @endforeach
    </ul>

    @if ($help)
        <p id="{{ $helpId }}" class="ag-checkbox-group__help">{{ $help }}</p>
    @endif

    @if ($error)
        <p id="{{ $errorId }}" class="ag-checkbox-group__error" role="alert">{{ $error }}</p>
    @endif
</fieldset>
