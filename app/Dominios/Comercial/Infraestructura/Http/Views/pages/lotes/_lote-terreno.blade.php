{{--
    Partial: atributos de terreno de un lote (desnivel + limpieza), extraído
    de `_lote-fila.blade.php` (16/9/2026) para reusarlo también en el
    generador masivo (`propiedades/lotes-generar.blade.php`) — ese no tiene
    código/hectáreas/mapa propios (se resuelven aparte, ver
    `CrearLotesMasivo`), solo estos dos atributos + restricciones.

    Espera (mismas variables que ya resuelve el llamador — `_lote-fila` o
    `lotes-generar`):
    - $lote (array{desnivel?: string, limpieza?: string, restricciones?: string}).
    - $prefijo (string): mismo prefijo de `name` que el resto de la fila.
    - $idBase, $erroresPrefijo (string): derivados de `$prefijo`, ya
      calculados por el llamador (mismo criterio en los dos lugares, no se
      recalculan acá para no repetir el `str_replace`).
--}}
<x-atoms.select
    name="{{ $prefijo }}[desnivel]"
    id="{{ $idBase }}-desnivel"
    label="{{ __('comercial.lotes.lote_desnivel') }}"
    :options="[
        'ninguno' => __('comercial.lotes.lote_desnivel_ninguno'),
        'algunos' => __('comercial.lotes.lote_desnivel_algunos'),
        'varios' => __('comercial.lotes.lote_desnivel_varios'),
        'empinado' => __('comercial.lotes.lote_desnivel_empinado'),
    ]"
    :value="$lote['desnivel'] ?? ''"
    placeholder="{{ __('comercial.lotes.lote_desnivel_placeholder') }}"
    error="{{ $errors->first($erroresPrefijo.'.desnivel') }}"
/>

@php
    // El switch decide entre 'limpio' y un grado de obstáculos (16/9/2026)
    // — la columna sigue siendo un único string `limpieza`, combinado por
    // LotesController::normalizarDatos()/CrearLotesMasivo. $limpio arranca
    // marcado por default en una fila nueva (sin valor previo): un lote
    // recién creado se asume limpio hasta que se diga lo contrario.
    $limpiezaActual = $lote['limpieza'] ?? null;
    $limpio = old($erroresPrefijo.'.limpio', $limpiezaActual === null ? true : $limpiezaActual === 'limpio');
    $gradoObstaculos = old($erroresPrefijo.'.grado_obstaculos', in_array($limpiezaActual, ['pocos_obstaculos', 'algunos_obstaculos', 'muchos_obstaculos'], true) ? $limpiezaActual : '');
@endphp
{{-- Rótulo al nivel de los demás campos (`ag-input__label`, arriba), no el
     propio label inline de `atoms/switch` (a la derecha del control,
     pensado para filas sueltas tipo "Activo") — así el switch queda a la
     altura del INPUT de la celda, no del label. El label inline del switch
     se reusa para la respuesta "Sí"/"No": el color solo (primario/gris) no
     alcanza para leer el estado, hace falta el texto — `lotes-form.js`
     (o `lotes-generar.js`) lo actualiza al togglear, con los dos textos ya
     traducidos en los `data-*` para no hardcodear español en JS. --}}
<div class="ag-input">
    <span class="ag-input__label">{{ __('comercial.lotes.lote_limpio') }}</span>
    <input type="hidden" name="{{ $prefijo }}[limpio]" value="0">
    <x-atoms.switch
        name="{{ $prefijo }}[limpio]"
        id="{{ $idBase }}-limpio"
        value="1"
        :label="$limpio ? __('comercial.lotes.lote_limpio_si') : __('comercial.lotes.lote_limpio_no')"
        :checked="(bool) $limpio"
        data-ag-lote-limpio
        data-ag-lote-limpio-texto-si="{{ __('comercial.lotes.lote_limpio_si') }}"
        data-ag-lote-limpio-texto-no="{{ __('comercial.lotes.lote_limpio_no') }}"
    />
</div>

{{-- Celda propia del grid de 2 columnas (col-6), no --field--full: va al
     lado del switch. Oculto con el atributo HTML `hidden` (no CSS): así el
     estado inicial es correcto sin esperar a que corra el JS, que solo
     alterna esta misma marca al togglear el switch. --}}
<div data-ag-lote-grado-obstaculos-wrap @if ($limpio) hidden @endif>
    <x-atoms.select
        name="{{ $prefijo }}[grado_obstaculos]"
        id="{{ $idBase }}-grado-obstaculos"
        label="{{ __('comercial.lotes.lote_grado_obstaculos') }}"
        :options="[
            'pocos_obstaculos' => __('comercial.lotes.lote_limpieza_pocos_obstaculos'),
            'algunos_obstaculos' => __('comercial.lotes.lote_limpieza_algunos_obstaculos'),
            'muchos_obstaculos' => __('comercial.lotes.lote_limpieza_muchos_obstaculos'),
        ]"
        :value="$gradoObstaculos"
        placeholder="{{ __('comercial.lotes.lote_grado_obstaculos_placeholder') }}"
        error="{{ $errors->first($erroresPrefijo.'.grado_obstaculos') }}"
    />
</div>

<x-atoms.textarea
    name="{{ $prefijo }}[restricciones]"
    id="{{ $idBase }}-restricciones"
    label="{{ __('comercial.lotes.lote_restricciones') }}"
    value="{{ $lote['restricciones'] ?? '' }}"
    placeholder="{{ __('comercial.lotes.lote_restricciones_placeholder') }}"
    rows="2"
/>
