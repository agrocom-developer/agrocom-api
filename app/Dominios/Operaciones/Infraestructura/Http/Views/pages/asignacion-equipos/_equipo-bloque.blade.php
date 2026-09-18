{{--
    Partial: bloque de equipo del formulario de reparto en bloque (HU-92,
    tarea 107) — reemplaza el mini-formulario de un equipo a la vez de HU-70
    (tarea 85): un único submit confirma N equipos, cada uno con su propia
    lista repetible de lotes+hectareas (`_lote-equipo-fila.blade.php`).
    Nombrado "bloque", no "fila" (a diferencia de su nivel interno): no es un
    renglón plano de 2 columnas — contiene un select MÁS una lista repetible
    completa — así que queda fuera, a propósito, de la convención
    `_[a-z-]+-fila.blade.php` que exige compartir el grid de
    `.ag-form-section__body` (ver `tests/Unit/PulidoNavegacionPanelTest.php`,
    "toda fila repetible de un formulario comparte la clase
    ag-form-section__body"). Repetible (agregar/quitar) reusando el mismo
    patrón vanilla de `campos/_lote-fila.blade.php` — ver
    `resources/js/pages/asignacion-equipos-form.js`.

    Espera:
    - $indiceEquipo (int|string): posición dentro de `equipos[]` —
      `__INDICE_EQUIPO__` en la plantilla clonable del formulario.
    - $lotesFila (list<array{lote_id?: int|string, hectareas?: string}>): las
      filas de lote ya cargadas para este equipo (por defecto, todos los
      lotes de la orden con sus hectáreas restantes — ver
      `AsignacionEquiposController::mostrar()` — o `old()` tras un error).
    - $equiposDisponibles / $etiquetasLote (Collection|array): opciones ya
      resueltas por el controlador.
    - $mostrarQuitarEquipo (bool, opcional): `true` por defecto — el primer
      equipo (índice 0) también puede quitarse, mismo criterio que
      `campos/_lote-fila.blade.php` (ningún mínimo forzado: el Request exige
      al menos un equipo con `equipos.min:1`, así que la fila 0 vacía sin
      quitar es un estado inválido perfectamente detectable, no algo que la
      UI necesite prevenir a priori).
--}}
@php
    $prefijo = "equipos[{$indiceEquipo}]";
    $idBase = str_replace(['[', ']'], ['-', ''], $prefijo);
    $erroresPrefijo = str_replace(['[', ']'], ['.', ''], $prefijo);
    $lotesFila = $lotesFila ?? [[]];
    $mostrarQuitarEquipo ??= true;

    // Función auxiliar para recuperar valores (old() o vacío)
    $valor = fn (string $campo, mixed $porDefecto = '') => old($campo, $porDefecto);
@endphp
<div class="ag-asignacion-equipos-ficha__equipo-fila" data-ag-equipo-fila>
    <div class="ag-asignacion-equipos-ficha__equipo-cabecera">
        <x-atoms.select
            name="{{ $prefijo }}[equipo_trabajo_id]"
            id="{{ $idBase }}-equipo"
            :label="__('operaciones.asignacion_equipos.campo_equipo')"
            :options="$equiposDisponibles"
            :placeholder="__('operaciones.asignacion_equipos.campo_equipo_placeholder')"
            required
            :error="$errors->first($erroresPrefijo.'.equipo_trabajo_id')"
        />

        @if ($mostrarQuitarEquipo)
            <x-atoms.button type="button" variant="text" size="sm" icon="delete" data-ag-equipo-quitar>
                {{ __('operaciones.asignacion_equipos.equipo_quitar') }}
            </x-atoms.button>
        @endif
    </div>

    {{-- Subsección: Condiciones de vuelo por equipo (8 campos: humedad, viento, temperatura, velocidad, altura, ancho) --}}
    {{--
        `ag-form-section__body` por composición (mismo criterio que
        `campos/_lote-fila.blade.php` y demás filas repetibles anidadas, ver
        docblock de esa clase en components/form-section.css): el grid de
        dos columnas para estos 8 campos es el mismo de siempre, no uno
        propio — evita duplicar `grid-template-columns` (compuerta en
        tests/Unit/PulidoNavegacionPanelTest.php).
    --}}
    <div class="ag-form-section__body ag-asignacion-equipos-ficha__condiciones-vuelo">
        <div class="ag-form-section__field--full ag-asignacion-equipos-ficha__condiciones-titulo">
            {{ __('operaciones.asignacion_equipos.seccion_condiciones_vuelo') }}
        </div>
        <div class="ag-form-section__field--full ag-asignacion-equipos-ficha__condiciones-ayuda">
            {{ __('operaciones.asignacion_equipos.seccion_condiciones_vuelo_ayuda') }}
        </div>

        <x-atoms.input
            type="number"
            name="{{ $prefijo }}[humedad_min_pct]"
            id="{{ $idBase }}-humedad-min"
            :label="__('operaciones.asignacion_equipos.campo_humedad_min_pct')"
            :value="$valor($prefijo.'.humedad_min_pct')"
            min="0"
            max="100"
            step="0.01"
            :error="$errors->first($erroresPrefijo.'.humedad_min_pct')"
        />

        <x-atoms.input
            type="number"
            name="{{ $prefijo }}[humedad_max_pct]"
            id="{{ $idBase }}-humedad-max"
            :label="__('operaciones.asignacion_equipos.campo_humedad_max_pct')"
            :value="$valor($prefijo.'.humedad_max_pct')"
            min="0"
            max="100"
            step="0.01"
            :error="$errors->first($erroresPrefijo.'.humedad_max_pct')"
        />

        <x-atoms.input
            type="number"
            name="{{ $prefijo }}[viento_max_kmh]"
            id="{{ $idBase }}-viento-max"
            :label="__('operaciones.asignacion_equipos.campo_viento_max_kmh')"
            :value="$valor($prefijo.'.viento_max_kmh')"
            min="0.01"
            step="0.01"
            :error="$errors->first($erroresPrefijo.'.viento_max_kmh')"
        />

        <x-atoms.input
            type="number"
            name="{{ $prefijo }}[temperatura_max_c]"
            id="{{ $idBase }}-temperatura-max"
            :label="__('operaciones.asignacion_equipos.campo_temperatura_max_c')"
            :value="$valor($prefijo.'.temperatura_max_c')"
            step="0.01"
            :error="$errors->first($erroresPrefijo.'.temperatura_max_c')"
        />

        <x-atoms.input
            type="number"
            name="{{ $prefijo }}[velocidad_max_kmh]"
            id="{{ $idBase }}-velocidad-max"
            :label="__('operaciones.asignacion_equipos.campo_velocidad_max_kmh')"
            :value="$valor($prefijo.'.velocidad_max_kmh')"
            min="0.01"
            step="0.01"
            :error="$errors->first($erroresPrefijo.'.velocidad_max_kmh')"
        />

        <x-atoms.input
            type="number"
            name="{{ $prefijo }}[altura_vuelo_m]"
            id="{{ $idBase }}-altura-vuelo"
            :label="__('operaciones.asignacion_equipos.campo_altura_vuelo_m')"
            :value="$valor($prefijo.'.altura_vuelo_m')"
            min="0.01"
            step="0.01"
            :error="$errors->first($erroresPrefijo.'.altura_vuelo_m')"
        />

        <x-atoms.input
            type="number"
            name="{{ $prefijo }}[velocidad_vuelo_kmh]"
            id="{{ $idBase }}-velocidad-vuelo"
            :label="__('operaciones.asignacion_equipos.campo_velocidad_vuelo_kmh')"
            :value="$valor($prefijo.'.velocidad_vuelo_kmh')"
            min="0.01"
            step="0.01"
            :error="$errors->first($erroresPrefijo.'.velocidad_vuelo_kmh')"
        />

        <x-atoms.input
            type="number"
            name="{{ $prefijo }}[ancho_pasada_m]"
            id="{{ $idBase }}-ancho-pasada"
            :label="__('operaciones.asignacion_equipos.campo_ancho_pasada_m')"
            :value="$valor($prefijo.'.ancho_pasada_m')"
            min="0.01"
            step="0.01"
            :error="$errors->first($erroresPrefijo.'.ancho_pasada_m')"
        />
    </div>

    <div class="ag-asignacion-equipos-ficha__equipo-lotes" data-ag-equipo-lotes-lista>
        @foreach ($lotesFila as $indiceLote => $lote)
            @include('operaciones::pages.asignacion-equipos._lote-equipo-fila', [
                'indiceEquipo' => $indiceEquipo,
                'indiceLote' => $indiceLote,
                'lote' => $lote,
            ])
        @endforeach
    </div>

    <x-atoms.button type="button" variant="outline" size="sm" icon="add" data-ag-equipo-lote-agregar>
        {{ __('operaciones.asignacion_equipos.lote_agregar') }}
    </x-atoms.button>

    {{-- Plantilla clonable del NIVEL INTERNO (lotes de este equipo): el
         índice literal `__INDICE_LOTE__` lo reemplaza
         `asignacion-equipos-form.js` al clonar. Un `<template>` nunca se
         renderiza ni se envía con el form — sobrevive intacto también
         cuando ESTE equipo-fila es, a su vez, clonado desde la plantilla
         externa (`__INDICE_EQUIPO__` no colisiona con `__INDICE_LOTE__`). --}}
    <template data-ag-equipo-lote-template>
        @include('operaciones::pages.asignacion-equipos._lote-equipo-fila', [
            'indiceEquipo' => $indiceEquipo,
            'indiceLote' => '__INDICE_LOTE__',
            'lote' => [],
        ])
    </template>
</div>
