{{--
    Partial: bloque de equipo del formulario de Orden de Trabajo (reforma
    18/9/2026). Similar a `asignacion-equipos/_equipo-bloque.blade.php` pero
    sin los 8 campos de clima/vuelo (que ahora son compartidos en
    `create.blade.php`). Cada bloque contiene:
    - Select de equipo.
    - Lista repetible de lotes+hectáreas+turno+horas.

    Espera:
    - $indiceEquipo (int|string): posición dentro de `equipos[]`.
    - $equipo (array{lotes?: list<array>>}): vacío en un bloque nuevo.
    - $mostrarQuitarEquipo (bool): si se puede quitar este bloque.
    - $datosOrden, $ordenId, $equiposDisponibles: pasados por create.blade.php.
--}}
@php
    $prefijo = "equipos[{$indiceEquipo}]";
    $idBase = str_replace(['[', ']'], ['-', ''], $prefijo);
    $erroresPrefijo = str_replace(['[', ']'], ['.', ''], $prefijo);
    $lotesFila = $equipo['lotes'] ?? [[]];
    $mostrarQuitarEquipo ??= true;

    // Lotes disponibles de la orden elegida
    $lotesDisponibles = $datosOrden[$ordenId]['lotes'] ?? [];
    $etiquetasLote = collect($lotesDisponibles)->mapWithKeys(fn ($lote) => [
        $lote['lote_id'] => $lote['label']
    ])->all();

    // Función auxiliar para recuperar valores
    $valor = fn (string $campo, mixed $porDefecto = '') => old($campo, $porDefecto);
@endphp
<div class="ag-ordenes-trabajo-form__equipo-bloque" data-ag-equipo-bloque>
    <div class="ag-ordenes-trabajo-form__equipo-cabecera">
        <x-atoms.select
            name="{{ $prefijo }}[equipo_trabajo_id]"
            id="{{ $idBase }}-equipo"
            :label="__('operaciones.asignacion_equipos.campo_equipo')"
            :options="$equiposDisponibles"
            :placeholder="__('operaciones.asignacion_equipos.campo_equipo_placeholder')"
            required
            :error="$errors->first($erroresPrefijo.'.equipo_trabajo_id')"
            data-ag-equipo-selector
        />

        @if ($mostrarQuitarEquipo)
            <x-atoms.button type="button" variant="text" size="sm" icon="delete" data-ag-equipo-quitar>
                {{ __('operaciones.ordenes_trabajo.equipo_quitar') }}
            </x-atoms.button>
        @endif
    </div>

    <div class="ag-ordenes-trabajo-form__equipo-lotes" data-ag-equipo-lotes-lista>
        @foreach ($lotesFila as $indiceLote => $lote)
            @include('operaciones::pages.ordenes-trabajo._lote-equipo-fila', [
                'indiceEquipo' => $indiceEquipo,
                'indiceLote' => $indiceLote,
                'lote' => $lote,
                'etiquetasLote' => $etiquetasLote,
            ])
        @endforeach
    </div>

    <x-atoms.button type="button" variant="outline" size="sm" icon="add" data-ag-equipo-lote-agregar>
        {{ __('operaciones.ordenes_trabajo.lote_agregar') }}
    </x-atoms.button>

    <template data-ag-equipo-lote-template>
        @include('operaciones::pages.ordenes-trabajo._lote-equipo-fila', [
            'indiceEquipo' => $indiceEquipo,
            'indiceLote' => '__INDICE_LOTE__',
            'lote' => [],
            'etiquetasLote' => $etiquetasLote,
        ])
    </template>
</div>
