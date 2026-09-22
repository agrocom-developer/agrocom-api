{{--
    Partial: un sector de siembra (21/9/2026, pedido directo) — un cultivo, su
    etapa y sus fechas, más los lotes que lo comparten. Es una fila repetible:
    la pantalla dibuja una por cada sector guardado y `siembra-form.js` clona
    la del `<template>` al pulsar «Agregar sector» (ahí `$indice` es el
    marcador `__INDICE__`, que el JS reemplaza por el número que toca).

    Espera:
    - $indice (int|string): posición del sector, para `sectores[{indice}][...]`.
    - $sector (array): cultivo_id, etapa_cultivo, fecha_siembra,
      fecha_cosecha_estimada y lotes ("3,7,12"); vacío en un sector nuevo.
    - $cultivosDisponibles (Collection<int, string>): id => nombre.
    - $etapasDisponibles (Collection<string, string>): valor => etiqueta.

    Los lotes no son campos del formulario: viajan en UN `<input type="hidden">`
    con los ids separados por coma (ver `GuardarSiembraRequest`). Las fichas
    con los códigos, el contador y el botón «Elegir lotes» los pinta y los
    maneja `siembra-form.js`, que abre el modal de la página.

    Los `error` usan concatenación (`$prefijo.'.campo'`), nunca
    `"{$prefijo}.campo"`: una cadena con comillas dobles dentro de un atributo
    también entre comillas dobles rompe el compilador de component tags.
--}}
@php
    $prefijo = "sectores[{$indice}]";
    $claveErrores = "sectores.{$indice}";
    $idBase = "sector-{$indice}";
@endphp
<div class="ag-form-section__body ag-siembra-form__sector" data-ag-siembra-sector>
    <div class="ag-form-section__field--full ag-siembra-form__sector-cabecera">
        <h3 class="ag-siembra-form__sector-titulo" data-ag-siembra-sector-titulo></h3>

        <x-atoms.button type="button" variant="danger-outline" size="sm" icon="delete" data-ag-siembra-sector-quitar>
            {{ __('comercial.siembra.sector_quitar') }}
        </x-atoms.button>
    </div>

    <x-atoms.select
        name="{{ $prefijo }}[cultivo_id]"
        id="{{ $idBase }}-cultivo"
        :label="__('comercial.siembra.campo_cultivo')"
        :options="$cultivosDisponibles"
        :value="$sector['cultivo_id'] ?? null"
        :placeholder="__('comercial.siembra.campo_cultivo_placeholder')"
        :error="$errors->first($claveErrores.'.cultivo_id')"
        required
    />

    <x-atoms.select
        name="{{ $prefijo }}[etapa_cultivo]"
        id="{{ $idBase }}-etapa"
        :label="__('comercial.siembra.campo_etapa')"
        :help="__('comercial.siembra.campo_etapa_ayuda')"
        :options="$etapasDisponibles"
        :value="$sector['etapa_cultivo'] ?? null"
        :placeholder="__('comercial.siembra.campo_etapa_placeholder')"
        :error="$errors->first($claveErrores.'.etapa_cultivo')"
    />

    <x-atoms.date
        name="{{ $prefijo }}[fecha_siembra]"
        id="{{ $idBase }}-fecha-siembra"
        :label="__('comercial.siembra.campo_fecha_siembra')"
        :value="$sector['fecha_siembra'] ?? null"
        :error="$errors->first($claveErrores.'.fecha_siembra')"
    />

    <x-atoms.date
        name="{{ $prefijo }}[fecha_cosecha_estimada]"
        id="{{ $idBase }}-fecha-cosecha"
        :label="__('comercial.siembra.campo_fecha_cosecha_estimada')"
        :value="$sector['fecha_cosecha_estimada'] ?? null"
        :error="$errors->first($claveErrores.'.fecha_cosecha_estimada')"
    />

    <div class="ag-form-section__field--full ag-siembra-form__sector-lotes">
        <input type="hidden" name="{{ $prefijo }}[lotes]" value="{{ $sector['lotes'] ?? '' }}" data-ag-siembra-sector-lotes>

        <div class="ag-siembra-form__sector-lotes-cabecera">
            <span class="ag-input__label">{{ __('comercial.siembra.sector_lotes') }}</span>
            <span class="ag-siembra-form__sector-resumen" data-ag-siembra-sector-resumen aria-live="polite"></span>
        </div>

        <div class="ag-siembra-form__fichas" data-ag-siembra-sector-fichas></div>

        <div>
            <x-atoms.button type="button" variant="outline" size="sm" icon="checklist" data-ag-siembra-sector-elegir>
                {{ __('comercial.siembra.sector_elegir_lotes') }}
            </x-atoms.button>
        </div>

        @if ($errors->has($claveErrores.'.lotes'))
            <p class="ag-input__error" role="alert">{{ $errors->first($claveErrores.'.lotes') }}</p>
        @endif
    </div>
</div>
