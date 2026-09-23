{{--
    Partial: condición de pago de UN equipo, en la edición de la Orden de
    Trabajo (tarea 127). A diferencia de `_equipo-bloque.blade.php` (alta:
    cuadrilla + turno + horario + hectáreas + pago), acá solo se edita el
    PAGO — el reparto (equipo, lotes, hectáreas, turno) no se toca en esta
    pantalla (aviso aparte, en `edit.blade.php`).

    Un equipo con algún trabajo no `abierto` (cerrado o validado) muestra la
    condición de SOLO LECTURA con el porqué (`PoliticaEdicionOrdenTrabajo::admiteCondicion()`):
    ese trabajo ya tiene sesiones que se van a devengar (o ya se devengaron)
    con la condición vigente al validar.

    Espera:
    - $equipo (array{equipo_trabajo_id: int, etiqueta: string, admiteCondicion: bool, condicion: array{tarifa_id: int|null, negociado: bool, modalidad: string, monto_piloto: string, monto_auxiliar: string, motivo: string|null}|null}).
    - $tarifasDisponibles (list<array{id, nombre, modalidad, monto_piloto, monto_auxiliar, predeterminada}>).
    - $modalidadesPago (array<string, string>).
--}}
@php
    $equipoId = $equipo['equipo_trabajo_id'];
    $prefijo = "equipos[{$equipoId}]";
    $idBase = "equipos-{$equipoId}";
    $erroresPrefijo = "equipos.{$equipoId}";
    $condicion = $equipo['condicion'] ?? ['negociado' => false, 'tarifa_id' => null, 'modalidad' => '', 'monto_piloto' => '', 'monto_auxiliar' => '', 'motivo' => null];

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
<fieldset class="ag-ordenes-trabajo-form__equipo-bloque" data-ag-equipo-bloque>
    <legend class="ag-ordenes-trabajo-form__equipo-titulo">
        <x-atoms.icon name="groups" size="sm" />
        {{ $equipo['etiqueta'] }}
    </legend>

    @unless ($equipo['admiteCondicion'])
        <x-molecules.alert-strip variant="info" icon="lock">
            {{ __('operaciones.ordenes_trabajo.condicion_bloqueada_ayuda') }}
        </x-molecules.alert-strip>

        @if ($equipo['condicion'] !== null)
            <p class="ag-ordenes-trabajo-detalle__pago-texto">
                {{ __('operaciones.ordenes_trabajo.pago_resumen', [
                    'modalidad' => $modalidadesPago[$condicion['modalidad']] ?? $condicion['modalidad'],
                    'piloto' => number_format((float) $condicion['monto_piloto'], 2, ',', '.'),
                    'auxiliar' => number_format((float) $condicion['monto_auxiliar'], 2, ',', '.'),
                ]) }}
            </p>
        @else
            <p class="ag-ordenes-trabajo-detalle__pago-texto">{{ __('operaciones.ordenes_trabajo.pago_sin_condicion') }}</p>
        @endif
    @else
        <div class="ag-ordenes-trabajo-form__pago-campos">
            @if (count($tarifasDisponibles) > 0)
                <x-atoms.select
                    name="{{ $prefijo }}[pago][tarifa_id]"
                    id="{{ $idBase }}-tarifa"
                    class="ag-ordenes-trabajo-form__campo-ancho"
                    :label="__('operaciones.ordenes_trabajo.campo_tarifa')"
                    :placeholder="__('operaciones.ordenes_trabajo.campo_tarifa_placeholder')"
                    :value="old($prefijo.'.pago.tarifa_id', $condicion['tarifa_id'] ?? '')"
                    :options="$opcionesTarifa"
                    :required="!old($prefijo.'.pago.negociado', $condicion['negociado'])"
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
                    :checked="old($prefijo.'.pago.negociado', $condicion['negociado'])"
                    :help="__('operaciones.ordenes_trabajo.campo_pago_negociado_ayuda')"
                    data-ag-equipo-negociado
                />
            </div>

            <x-atoms.select
                name="{{ $prefijo }}[pago][modalidad]"
                id="{{ $idBase }}-modalidad"
                class="ag-ordenes-trabajo-form__campo-ancho"
                :label="__('operaciones.ordenes_trabajo.campo_pago_modalidad')"
                :placeholder="__('operaciones.ordenes_trabajo.campo_pago_modalidad_placeholder')"
                :value="old($prefijo.'.pago.modalidad', $condicion['modalidad'] ?? '')"
                :options="$modalidadesPago"
                :disabled="!old($prefijo.'.pago.negociado', $condicion['negociado'])"
                :error="$errors->first($erroresPrefijo.'.pago.modalidad')"
                data-ag-equipo-modalidad
            />

            <x-atoms.input
                type="number"
                name="{{ $prefijo }}[pago][monto_piloto]"
                id="{{ $idBase }}-monto-piloto"
                :label="__('operaciones.ordenes_trabajo.campo_pago_monto_piloto')"
                :value="old($prefijo.'.pago.monto_piloto', $condicion['monto_piloto'] ?? '')"
                min="0"
                step="0.01"
                :disabled="!old($prefijo.'.pago.negociado', $condicion['negociado'])"
                :error="$errors->first($erroresPrefijo.'.pago.monto_piloto')"
                data-ag-equipo-monto-piloto
            />

            <x-atoms.input
                type="number"
                name="{{ $prefijo }}[pago][monto_auxiliar]"
                id="{{ $idBase }}-monto-auxiliar"
                :label="__('operaciones.ordenes_trabajo.campo_pago_monto_auxiliar')"
                :value="old($prefijo.'.pago.monto_auxiliar', $condicion['monto_auxiliar'] ?? '')"
                min="0"
                step="0.01"
                :disabled="!old($prefijo.'.pago.negociado', $condicion['negociado'])"
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
                :value="old($prefijo.'.pago.motivo', $condicion['motivo'] ?? '')"
                maxlength="255"
                :disabled="!old($prefijo.'.pago.negociado', $condicion['negociado'])"
                :error="$errors->first($erroresPrefijo.'.pago.motivo')"
                data-ag-equipo-motivo
            />
        </div>
    @endunless
</fieldset>
