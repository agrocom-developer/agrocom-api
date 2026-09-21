{{--
    Partial: bloque de equipo del formulario de Orden de Trabajo (reforma
    18/9/2026; ajustado el 19/9/2026). Un "equipo" acá es un casillero de la
    orden —"Equipo 1", "Equipo 2"— que se cubre con una CUADRILLA: el piloto, su
    ayudante y el dron que ya se armaron de antemano. Por eso el bloque no se
    agrega ni se quita: cuántos hay lo definió la orden de aplicación, y lo
    único que se decide acá es qué cuadrilla lo cubre y qué lotes le tocan.

    Cada bloque contiene:
    - Select de cuadrilla, con acceso rápido «Crear cuadrilla» (mismo criterio
      que «Nuevo cliente» en el formulario de contratos) para cuando ninguna
      vigente sirve. La que se elige en un bloque deja de ofrecerse en los
      demás (`ordenes-trabajo-form.js`; el servidor igual exige `distinct`).
    - Lista repetible de lotes + hectáreas + turno + horas.

    Espera:
    - $indiceEquipo (int): posición dentro de `equipos[]`.
    - $equipo (array{equipo_trabajo_id?, lotes?: list<array>}): vacío en un
      bloque sin datos previos.
    - $obligatorio (bool): solo el primero lo es; el resto puede quedar en
      blanco si la tanda sale con menos equipos.
    - $lotesOrden (list<array{lote_id, label, restantes}>): lotes de la orden.
    - $equiposDisponibles (Collection<int, string>): cuadrillas vigentes hoy.
    - $urlCrearCuadrilla (string): alta de cuadrilla, con retorno a esta pantalla.
    - $puedeCrearCuadrilla (bool): si el rol activo puede dar de alta una
      cuadrilla — sin el permiso, el acceso rápido no se dibuja.
--}}
@php
    $prefijo = "equipos[{$indiceEquipo}]";
    $idBase = str_replace(['[', ']'], ['-', ''], $prefijo);
    $erroresPrefijo = str_replace(['[', ']'], ['.', ''], $prefijo);
    $lotesFila = $equipo['lotes'] ?? [[]];
    $lotesFila = $lotesFila === [] ? [[]] : $lotesFila;

    $etiquetasLote = collect($lotesOrden)->mapWithKeys(fn (array $lote): array => [
        $lote['lote_id'] => __('operaciones.ordenes_trabajo.lote_opcion', [
            'lote' => $lote['label'],
            'restantes' => number_format((float) $lote['restantes'], 2, ',', '.'),
        ]),
    ])->all();
@endphp
<fieldset class="ag-ordenes-trabajo-form__equipo-bloque" data-ag-equipo-bloque>
    <legend class="ag-ordenes-trabajo-form__equipo-titulo">
        {{ __('operaciones.ordenes_trabajo.equipo_titulo', ['numero' => $indiceEquipo + 1]) }}
        @unless ($obligatorio)
            <span class="ag-ordenes-trabajo-form__equipo-opcional">{{ __('operaciones.ordenes_trabajo.equipo_opcional') }}</span>
        @endunless
    </legend>

    <x-atoms.select
        name="{{ $prefijo }}[equipo_trabajo_id]"
        id="{{ $idBase }}-equipo"
        :label="__('operaciones.ordenes_trabajo.campo_cuadrilla')"
        :options="$equiposDisponibles"
        :value="$equipo['equipo_trabajo_id'] ?? ''"
        :placeholder="__('operaciones.ordenes_trabajo.campo_cuadrilla_placeholder')"
        :required="$obligatorio"
        :error="$errors->first($erroresPrefijo.'.equipo_trabajo_id')"
        :action-icon="$puedeCrearCuadrilla ? 'add' : null"
        :action-href="$puedeCrearCuadrilla ? $urlCrearCuadrilla : null"
        :action-label="__('operaciones.ordenes_trabajo.cuadrilla_crear')"
        :action-text="__('operaciones.ordenes_trabajo.cuadrilla_crear_corto')"
        data-ag-equipo-selector
    />

    <div class="ag-ordenes-trabajo-form__equipo-lotes" data-ag-equipo-lotes-lista>
        @foreach ($lotesFila as $indiceLote => $lote)
            @include('operaciones::pages.ordenes-trabajo._lote-equipo-fila', [
                'indiceEquipo' => $indiceEquipo,
                'indiceLote' => $indiceLote,
                'lote' => $lote,
                'etiquetasLote' => $etiquetasLote,
                'obligatorio' => $obligatorio,
                'mostrarQuitar' => $indiceLote > 0,
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
            'obligatorio' => $obligatorio,
            'mostrarQuitar' => true,
        ])
    </template>
</fieldset>
