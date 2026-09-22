{{--
    Partial: bloque de equipo del formulario de Orden de Trabajo (reforma
    18/9/2026; ajustado el 19/9 y el 21/9/2026). Un "equipo" acá es un casillero
    de la orden —"Equipo 1", "Equipo 2"— que se cubre con una CUADRILLA: el
    piloto, su ayudante y el dron que ya se armaron de antemano. Por eso el
    bloque no se agrega ni se quita: cuántos hay lo definió la orden de
    aplicación.

    Lo que se CARGA acá es qué cuadrilla lo cubre, en qué turno, entre qué
    horas —un solo campo de rango, `atoms/time-range`, sin validación de
    obligatorio— y CUÁNTAS HECTÁREAS se le asignan (pedido del dueño,
    21/9/2026). Ese campo de hectáreas está enlazado con el de los demás
    equipos: entre todos suman lo que a la orden le queda por repartir, así que
    subir uno baja a los otros (`ordenes-trabajo-form.js`). Los LOTES no se
    tipean: salen de esas hectáreas y del criterio elegido, y se muestran como
    texto bajo el campo; los `equipos[i][lotes][j][…]` que espera
    `CrearOrdenTrabajoRequest` viajan en campos ocultos que arma ese script
    (cada lote que le toca al equipo nace como un `Trabajo`, y desde la ficha se
    puede pasar a otro equipo). El turno y las horas del bloque se copian a cada
    uno de esos lotes; sus `name` propios (`equipos[i][turno]`…) no entran en
    la validación — sirven para reponer lo cargado tras un error.

    Espera:
    - $indiceEquipo (int|string): posición dentro de `equipos[]`. En el
      `<template>` que usa el JS para armar los bloques al elegir otra orden
      llega como `__INDICE_EQUIPO__`.
    - $numero (int|string): el número visible del equipo (`__NUMERO_EQUIPO__`
      en el template).
    - $equipo (array{equipo_trabajo_id?, turno?, turno_hora_inicio?,
      turno_hora_fin?, hectareas?, lotes?: list<array>}): vacío en un bloque
      sin datos previos.
    La cuadrilla lleva `data-ag-select-clearable`: también en el Equipo 1, que
    es obligatorio, se puede quitar la elegida con la «×» para elegir otra (si
    queda en blanco, guardar responde con el error del campo).

    - $obligatorio (bool): solo el primero lo es; el resto puede quedar en
      blanco si no participa.
    - $equiposDisponibles (Collection<int, string>): cuadrillas vigentes hoy.
    - $urlCrearCuadrilla (string): alta de cuadrilla, con retorno a esta pantalla.
    - $puedeCrearCuadrilla (bool): si el rol activo puede dar de alta una
      cuadrilla — sin el permiso, el acceso rápido no se dibuja.
--}}
@php
    $tarifasDisponibles = $tarifasDisponibles ?? [];
    $tarifaPredeterminadaId = $tarifaPredeterminadaId ?? null;
    $modalidadesPago = $modalidadesPago ?? [];
    $prefijo = "equipos[{$indiceEquipo}]";
    $idBase = str_replace(['[', ']'], ['-', ''], $prefijo);
    $erroresPrefijo = str_replace(['[', ']'], ['.', ''], $prefijo);
    $primerLote = $equipo['lotes'][0] ?? [];
    if (! isset($equipo['pago'])) {
        $equipo['pago'] = ['negociado' => false];
    }
    // Opciones del select de tarifa: nombre, modalidad (en etiqueta) y montos.
    $opcionesTarifa = [];
    foreach ($tarifasDisponibles as $tarifa) {
        $opcionesTarifa[$tarifa['id']] = __('operaciones.ordenes_trabajo.campo_tarifa_opcion', [
            'nombre' => $tarifa['nombre'],
            'modalidad' => $modalidadesPago[$tarifa['modalidad']] ?? $tarifa['modalidad'],
            'piloto' => number_format((float) $tarifa['monto_piloto'], 2, ',', '.'),
            'auxiliar' => number_format((float) $tarifa['monto_auxiliar'], 2, ',', '.'),
        ]);
    }
@endphp
<fieldset class="ag-ordenes-trabajo-form__equipo-bloque" data-ag-equipo-bloque data-ag-equipo-prefijo="{{ $prefijo }}">
    <legend class="ag-ordenes-trabajo-form__equipo-titulo">
        {{ __('operaciones.ordenes_trabajo.equipo_titulo', ['numero' => $numero]) }}
        @unless ($obligatorio)
            <span class="ag-ordenes-trabajo-form__equipo-opcional">{{ __('operaciones.ordenes_trabajo.equipo_opcional') }}</span>
        @endunless
    </legend>

    <div class="ag-ordenes-trabajo-form__equipo-campos">
        <x-atoms.select
            name="{{ $prefijo }}[equipo_trabajo_id]"
            id="{{ $idBase }}-equipo"
            class="ag-ordenes-trabajo-form__campo-ancho"
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
            data-ag-select-clearable
        />

        <x-atoms.select
            name="{{ $prefijo }}[turno]"
            id="{{ $idBase }}-turno"
            :label="__('operaciones.asignacion_equipos.campo_turno')"
            :options="[
                'todo_el_dia' => __('operaciones.asignacion_equipos.turno_todo_el_dia'),
                'manana' => __('operaciones.asignacion_equipos.turno_manana'),
                'noche' => __('operaciones.asignacion_equipos.turno_noche'),
            ]"
            :value="$equipo['turno'] ?? ($primerLote['turno'] ?? 'todo_el_dia')"
            required
            :error="$errors->first($erroresPrefijo.'.lotes.*.turno')"
            data-ag-equipo-turno
        />

        <x-atoms.time-range
            name-start="{{ $prefijo }}[turno_hora_inicio]"
            name-end="{{ $prefijo }}[turno_hora_fin]"
            id="{{ $idBase }}-horario"
            :label="__('operaciones.ordenes_trabajo.campo_horario')"
            :value-start="$equipo['turno_hora_inicio'] ?? ($primerLote['turno_hora_inicio'] ?? null)"
            :value-end="$equipo['turno_hora_fin'] ?? ($primerLote['turno_hora_fin'] ?? null)"
            :error="$errors->first($erroresPrefijo.'.lotes.*.turno_hora_inicio') ?: $errors->first($erroresPrefijo.'.lotes.*.turno_hora_fin')"
            data-ag-equipo-hora
        />

        <x-atoms.input
            type="number"
            name="{{ $prefijo }}[hectareas]"
            id="{{ $idBase }}-hectareas"
            class="ag-ordenes-trabajo-form__campo-ancho"
            :label="__('operaciones.ordenes_trabajo.campo_hectareas_equipo')"
            :value="$equipo['hectareas'] ?? ''"
            min="0"
            step="0.01"
            data-ag-equipo-hectareas
        />
    </div>

    <p class="ag-ordenes-trabajo-form__ayuda" data-ag-equipo-lotes></p>
    <p class="ag-ordenes-trabajo-form__ayuda" data-ag-equipo-sin-cuadrilla hidden>
        {{ __('operaciones.ordenes_trabajo.equipo_sin_cuadrilla') }}
    </p>

    {{-- Condición de pago por equipo (ADR 0023): tarifa del catálogo o
         negociación para este trabajo. --}}
    <div class="ag-ordenes-trabajo-form__pago-bloque">
        <h3 class="ag-ordenes-trabajo-form__pago-titulo">
            {{ __('operaciones.ordenes_trabajo.pago_titulo') }}
        </h3>

        <div class="ag-ordenes-trabajo-form__pago-campos">
            @if (count($tarifasDisponibles) > 0)
                <x-atoms.select
                    name="{{ $prefijo }}[pago][tarifa_id]"
                    id="{{ $idBase }}-tarifa"
                    class="ag-ordenes-trabajo-form__campo-ancho"
                    :label="__('operaciones.ordenes_trabajo.campo_tarifa')"
                    :placeholder="__('operaciones.ordenes_trabajo.campo_tarifa_placeholder')"
                    :value="$equipo['pago']['tarifa_id'] ?? $tarifaPredeterminadaId ?? ''"
                    :options="$opcionesTarifa"
                    :required="!($equipo['pago']['negociado'] ?? false)"
                    :error="$errors->first($erroresPrefijo.'.pago.tarifa_id')"
                    data-ag-equipo-tarifa
                />
            @else
                <div class="ag-ordenes-trabajo-form__tarifa-aviso">
                    {{ __('operaciones.ordenes_trabajo.campo_tarifa_sin_catalogo') }}
                </div>
            @endif

            <div class="ag-ordenes-trabajo-form__pago-negociado">
                <input type="hidden" name="{{ $prefijo }}[pago][negociado]" value="0">
                <x-atoms.checkbox
                    name="{{ $prefijo }}[pago][negociado]"
                    id="{{ $idBase }}-negociado"
                    :label="__('operaciones.ordenes_trabajo.campo_pago_negociado')"
                    :value="1"
                    :checked="$equipo['pago']['negociado'] ?? false"
                    :help="__('operaciones.ordenes_trabajo.campo_pago_negociado_ayuda')"
                    data-ag-equipo-negociado
                />
            </div>

            {{-- Campos de negociación: siempre dibujados pero deshabilitados mientras
                 no se marque "Negociado". Rellenados con los montos de la tarifa elegida. --}}
            <x-atoms.select
                name="{{ $prefijo }}[pago][modalidad]"
                id="{{ $idBase }}-modalidad"
                class="ag-ordenes-trabajo-form__campo-ancho"
                :label="__('operaciones.ordenes_trabajo.campo_pago_modalidad')"
                :placeholder="__('operaciones.ordenes_trabajo.campo_pago_modalidad_placeholder')"
                :value="$equipo['pago']['modalidad'] ?? ''"
                :options="$modalidadesPago"
                :disabled="!($equipo['pago']['negociado'] ?? false)"
                :error="$errors->first($erroresPrefijo.'.pago.modalidad')"
                data-ag-equipo-modalidad
            />

            <x-atoms.input
                type="number"
                name="{{ $prefijo }}[pago][monto_piloto]"
                id="{{ $idBase }}-monto-piloto"
                :label="__('operaciones.ordenes_trabajo.campo_pago_monto_piloto')"
                :value="$equipo['pago']['monto_piloto'] ?? ''"
                min="0"
                step="0.01"
                :disabled="!($equipo['pago']['negociado'] ?? false)"
                :error="$errors->first($erroresPrefijo.'.pago.monto_piloto')"
                data-ag-equipo-monto-piloto
            />

            <x-atoms.input
                type="number"
                name="{{ $prefijo }}[pago][monto_auxiliar]"
                id="{{ $idBase }}-monto-auxiliar"
                :label="__('operaciones.ordenes_trabajo.campo_pago_monto_auxiliar')"
                :value="$equipo['pago']['monto_auxiliar'] ?? ''"
                min="0"
                step="0.01"
                :disabled="!($equipo['pago']['negociado'] ?? false)"
                :error="$errors->first($erroresPrefijo.'.pago.monto_auxiliar')"
                data-ag-equipo-monto-auxiliar
            />

            <x-atoms.input
                type="text"
                name="{{ $prefijo }}[pago][motivo]"
                id="{{ $idBase }}-motivo"
                class="ag-ordenes-trabajo-form__campo-ancho"
                :label="__('operaciones.ordenes_trabajo.campo_pago_motivo')"
                :placeholder="__('operaciones.ordenes_trabajo.campo_pago_motivo_placeholder')"
                :value="$equipo['pago']['motivo'] ?? ''"
                maxlength="255"
                :disabled="!($equipo['pago']['negociado'] ?? false)"
                :error="$errors->first($erroresPrefijo.'.pago.motivo')"
                data-ag-equipo-motivo
            />
        </div>
    </div>

    {{-- `equipos[i][lotes][j][…]`: los arma el JS con el reparto. --}}
    <div data-ag-equipo-ocultos hidden></div>
</fieldset>
