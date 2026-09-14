{{--
    Partial: fila de lote del formulario de orden de aplicación (HU-92, tarea
    107) — mismo patrón que `campos/_lote-fila.blade.php`: una fila repetible
    dentro de la sección "Lotes" de `_formulario.blade.php`, reusada para
    pintar los lotes ya cargados (edición), para repetir `old('lotes')` tras
    un error de validación, y como plantilla que clona
    `resources/js/pages/ordenes-form.js` al apretar "Agregar lote".

    Espera:
    - $indice (int|string): posición dentro del array `lotes[]` — en la
      plantilla clonable viene el placeholder literal `__INDICE__`.
    - $lote (array{lote_id?: int|string, hectareas_solicitadas?: string}):
      vacío en una fila nueva.
--}}
@php
    $prefijo = "lotes[{$indice}]";
    $idBase = str_replace(['[', ']'], ['-', ''], $prefijo);
    $erroresPrefijo = str_replace(['[', ']'], ['.', ''], $prefijo);
@endphp
<div class="ag-form-section__body ag-ordenes-form__lote" data-ag-orden-lote-fila>
    <x-atoms.select
        name="{{ $prefijo }}[lote_id]"
        id="{{ $idBase }}-lote"
        label="{{ __('operaciones.ordenes.campo_lote') }}"
        :options="$lotesDisponibles"
        :value="$lote['lote_id'] ?? ''"
        :placeholder="__('operaciones.ordenes.campo_lote_placeholder')"
        required
        error="{{ $errors->first($erroresPrefijo.'.lote_id') }}"
    />

    <x-atoms.input
        type="number"
        name="{{ $prefijo }}[hectareas_solicitadas]"
        id="{{ $idBase }}-hectareas"
        label="{{ __('operaciones.ordenes.campo_lote_hectareas') }}"
        value="{{ $lote['hectareas_solicitadas'] ?? '' }}"
        min="0.01"
        step="0.01"
        required
        error="{{ $errors->first($erroresPrefijo.'.hectareas_solicitadas') }}"
    />

    <div class="ag-form-section__field--full ag-ordenes-form__lote-pie">
        <x-atoms.button type="button" variant="text" size="sm" icon="delete" data-ag-orden-lote-quitar>
            {{ __('operaciones.ordenes.lote_quitar') }}
        </x-atoms.button>
    </div>
</div>
