{{--
    Partial: fila de siembra de un lote (HU-48, tarea 71, etapa 3) — una fila
    FIJA por cada lote de la propiedad (a diferencia de `_lote-fila.blade.php`, acá
    no se agregan ni quitan filas: los lotes ya existen, esto solo carga qué
    se sembró en cada uno para la campaña elegida).

    Espera:
    - $indice (int): posición dentro de $propiedad->lotes, para el name
      `lotes[{indice}][...]`.
    - $lote (Lote): el lote de esta fila.
    - $siembra (LoteCampania|null): su siembra en la campaña que se está
      mostrando, o null si ese lote todavía no se sembró esa campaña.
    - $cultivosDisponibles (Collection<int, string>): id => nombre.

    `cultivo_id` en blanco es una fila válida (lote sin sembrar esta
    campaña): por eso no lleva `required`, a diferencia del resto del
    formulario del panel.
--}}
{{--
    16/9/2026: los `error="{{ $errors->first(...) }}"` de abajo usan
    concatenación (`$prefijo.'.campo'`), nunca `"{$prefijo}.campo"` — una
    cadena de PHP con comillas dobles DENTRO de un atributo HTML también
    entre comillas dobles rompe el compilador de component tags de Blade
    (confunde dónde termina el atributo) y deja `<x-atoms.select>` sin
    compilar, como texto literal. Mismo criterio que ya usaba
    `_lote-fila.blade.php` con `$erroresPrefijo.'.codigo'` — acá no se había
    seguido, y el bug real (encontrado recién al probar la pantalla en vivo)
    era exactamente este.
--}}
@php
    $prefijo = "lotes[{$indice}]";
    $idBase = str_replace(['[', ']'], ['-', ''], $prefijo);
    $cultivoId = old("{$prefijo}.cultivo_id", $siembra?->cultivo_id);
    $hectareasSembradas = old("{$prefijo}.hectareas_sembradas", $siembra?->hectareas_sembradas);
    $fechaSiembra = old("{$prefijo}.fecha_siembra", $siembra?->fecha_siembra?->format('Y-m-d'));
    $fechaCosechaEstimada = old("{$prefijo}.fecha_cosecha_estimada", $siembra?->fecha_cosecha_estimada?->format('Y-m-d'));
@endphp
<div class="ag-form-section__body ag-siembra-form__lote" data-ag-siembra-fila>
    <input type="hidden" name="{{ $prefijo }}[lote_id]" value="{{ $lote->id }}">

    <div class="ag-siembra-form__lote-info">
        <span class="ag-siembra-form__lote-codigo">{{ $lote->codigo }}</span>
        <span class="ag-siembra-form__lote-hectareas">
            {{ __('comercial.siembra.lote_hectareas_valor', ['cantidad' => number_format((float) $lote->hectareas, 2, ',', '.')]) }}
        </span>
    </div>

    <x-atoms.select
        name="{{ $prefijo }}[cultivo_id]"
        id="{{ $idBase }}-cultivo"
        :label="__('comercial.siembra.campo_cultivo')"
        :options="$cultivosDisponibles"
        :value="$cultivoId"
        :placeholder="__('comercial.siembra.campo_cultivo_placeholder')"
        :error="$errors->first($prefijo.'.cultivo_id')"
    />

    <x-atoms.input
        type="number"
        name="{{ $prefijo }}[hectareas_sembradas]"
        id="{{ $idBase }}-hectareas"
        :label="__('comercial.siembra.campo_hectareas_sembradas')"
        :value="$hectareasSembradas"
        min="0.01"
        max="{{ $lote->hectareas }}"
        step="0.01"
        :error="$errors->first($prefijo.'.hectareas_sembradas')"
    />

    <x-atoms.date
        name="{{ $prefijo }}[fecha_siembra]"
        id="{{ $idBase }}-fecha-siembra"
        :label="__('comercial.siembra.campo_fecha_siembra')"
        :value="$fechaSiembra"
        :error="$errors->first($prefijo.'.fecha_siembra')"
    />

    <x-atoms.date
        name="{{ $prefijo }}[fecha_cosecha_estimada]"
        id="{{ $idBase }}-fecha-cosecha"
        :label="__('comercial.siembra.campo_fecha_cosecha_estimada')"
        :value="$fechaCosechaEstimada"
        :error="$errors->first($prefijo.'.fecha_cosecha_estimada')"
    />
</div>
