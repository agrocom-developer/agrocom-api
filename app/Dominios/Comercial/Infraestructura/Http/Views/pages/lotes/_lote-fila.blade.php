{{--
    Partial: fila de lote del formulario de lote (tarea 77, HU-54) — mismo
    patrón que `clientes/_contacto-fila.blade.php` (tarea 33): una fila
    repetible dentro de la sección "Lote" de `_formulario.blade.php` (o dentro
    de un array de lotes en un formulario de propiedad), reusada para pintar
    lotes existentes, para repetir `old('lotes')` tras un error de validación,
    y como plantilla que clona JavaScript al apretar "Agregar lote" o similar.

    Espera:
    - $indice (int|string): posición dentro del array `lotes[]` — en la
      plantilla clonable viene el placeholder literal `__INDICE__`, que el JS
      reemplaza por el próximo número al clonar. No hace falta si se pasa
      `$prefijo` explícito (ver abajo).
    - $lote (array{id?: int, codigo?: string, hectareas?: string,
      geometria?: string, restricciones?: string, desnivel?: string,
      limpieza?: string}): vacío en una fila nueva.
    - $prefijo (string, opcional): prefijo de los `name` de los campos —
      por defecto `lotes[{indice}]` (el caso de siempre: fila dentro del
      array del formulario de propiedad). La ficha de un lote suelto
      (`pages/lotes/_formulario.blade.php`, tarea 77) pasa `lote` a secas,
      así que sus campos viajan como `lote[codigo]`, `lote[hectareas]`, etc.
      — el mismo partial, sin envolver un único lote en un array de uno.
    - $mostrarQuitar (bool, opcional): `true` por defecto. La ficha de un
      lote suelto no tiene botón "Quitar" — la baja de ESE lote es la acción
      "Eliminar" de su propia página, no "sacarlo de esta lista".

    El mapa del lote (`geometria`) vive en su propia sección, partial
    aparte (`lotes/_lote-mapa.blade.php`, 16/9/2026) — no en esta fila de
    campos.
--}}
@php
    $prefijo ??= "lotes[{$indice}]";
    $idBase = str_replace(['[', ']'], ['-', ''], $prefijo);
    // El error bag usa notación con punto (`lotes.0.codigo`), el `name` del
    // input usa corchetes (`lotes[0][codigo]`) — mismo prefijo, otra sintaxis.
    $erroresPrefijo = str_replace(['[', ']'], ['.', ''], $prefijo);
    $mostrarQuitar ??= true;
@endphp
<div class="ag-form-section__body ag-campos-form__lote" data-ag-lote-fila>
    @if (! empty($lote['id']))
        <input type="hidden" name="{{ $prefijo }}[id]" value="{{ $lote['id'] }}">
    @endif

    <x-atoms.input
        type="text"
        name="{{ $prefijo }}[codigo]"
        label="{{ __('comercial.lotes.lote_codigo') }}"
        value="{{ $lote['codigo'] ?? '' }}"
        required
        error="{{ $errors->first($erroresPrefijo.'.codigo') }}"
    />

    <x-atoms.input
        type="number"
        name="{{ $prefijo }}[hectareas]"
        label="{{ __('comercial.lotes.lote_hectareas') }}"
        value="{{ $lote['hectareas'] ?? '' }}"
        min="0.01"
        step="0.01"
        required
        error="{{ $errors->first($erroresPrefijo.'.hectareas') }}"
    />

    @include('comercial::pages.lotes._lote-terreno', ['lote' => $lote, 'prefijo' => $prefijo, 'idBase' => $idBase, 'erroresPrefijo' => $erroresPrefijo])

    @if ($mostrarQuitar)
        <div class="ag-form-section__field--full ag-campos-form__lote-pie">
            <x-atoms.button type="button" variant="text" size="sm" icon="delete" data-ag-lote-quitar>
                {{ __('comercial.lotes.lote_quitar') }}
            </x-atoms.button>
        </div>
    @endif
</div>
