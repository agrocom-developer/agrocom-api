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
    `resources/js/pages/reparto-cuadrillas-form.js`.

    Espera:
    - $indiceEquipo (int|string): posición dentro de `equipos[]` —
      `__INDICE_EQUIPO__` en la plantilla clonable del formulario.
    - $lotesFila (list<array{lote_id?: int|string, hectareas?: string}>): las
      filas de lote ya cargadas para este equipo (por defecto, todos los
      lotes de la orden con sus hectáreas restantes — ver
      `RepartoCuadrillasController::mostrar()` — o `old()` tras un error).
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
<div class="ag-reparto-cuadrillas-ficha__equipo-fila" data-ag-equipo-fila>
    <div class="ag-reparto-cuadrillas-ficha__equipo-cabecera">
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


    <div class="ag-reparto-cuadrillas-ficha__equipo-lotes" data-ag-equipo-lotes-lista>
        @foreach ($lotesFila as $indiceLote => $lote)
            @include('operaciones::pages.reparto-cuadrillas._lote-equipo-fila', [
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
         `reparto-cuadrillas-form.js` al clonar. Un `<template>` nunca se
         renderiza ni se envía con el form — sobrevive intacto también
         cuando ESTE equipo-fila es, a su vez, clonado desde la plantilla
         externa (`__INDICE_EQUIPO__` no colisiona con `__INDICE_LOTE__`). --}}
    <template data-ag-equipo-lote-template>
        @include('operaciones::pages.reparto-cuadrillas._lote-equipo-fila', [
            'indiceEquipo' => $indiceEquipo,
            'indiceLote' => '__INDICE_LOTE__',
            'lote' => [],
        ])
    </template>
</div>
