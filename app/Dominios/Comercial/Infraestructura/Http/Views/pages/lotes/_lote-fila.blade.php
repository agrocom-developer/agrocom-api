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
        // LotesController::normalizarDatos(). $limpio arranca marcado por
        // default en una fila nueva (sin valor previo): un lote recién creado
        // se asume limpio hasta que se diga lo contrario.
        $limpiezaActual = $lote['limpieza'] ?? null;
        $limpio = old($erroresPrefijo.'.limpio', $limpiezaActual === null ? true : $limpiezaActual === 'limpio');
        $gradoObstaculos = old($erroresPrefijo.'.grado_obstaculos', in_array($limpiezaActual, ['pocos_obstaculos', 'algunos_obstaculos', 'muchos_obstaculos'], true) ? $limpiezaActual : '');
    @endphp
    {{-- Rótulo al nivel de los demás campos (`ag-input__label`, arriba),
         no el propio label inline de `atoms/switch` (a la derecha del
         control, pensado para filas sueltas tipo "Activo") — así el switch
         queda a la altura del INPUT de la celda, no del label. El label
         inline del switch se reusa para la respuesta "Sí"/"No": el color
         solo (primario/gris) no alcanza para leer el estado, hace falta el
         texto — lotes-form.js lo actualiza al togglear, con los dos textos
         ya traducidos en los `data-*` para no hardcodear español en JS. --}}
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
         lado del switch. Oculto con el atributo HTML `hidden` (no CSS): así
         el estado inicial es correcto sin esperar a que corra
         `lotes-form.js`, que solo alterna esta misma marca al togglear el
         switch. --}}
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

    @if ($mostrarQuitar)
        <div class="ag-form-section__field--full ag-campos-form__lote-pie">
            <x-atoms.button type="button" variant="text" size="sm" icon="delete" data-ag-lote-quitar>
                {{ __('comercial.lotes.lote_quitar') }}
            </x-atoms.button>
        </div>
    @endif
</div>
