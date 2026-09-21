{{--
    Partial: fila de lote dentro de un equipo en la Orden de Trabajo (reforma
    18/9/2026). Basada en `reparto-cuadrillas/_lote-equipo-fila.blade.php` +
    agregado de turno + horas de inicio/fin (cada fila necesita estos campos).

    Espera:
    - $indiceEquipo (int|string): posición del equipo.
    - $indiceLote (int|string): posición del lote dentro de los lotes del equipo.
    - $lote (array{lote_id?, hectareas?, turno?, turno_hora_inicio?, turno_hora_fin?}):
      vacío en una fila nueva.
    - $etiquetasLote (array<int, string>): opciones de lote para este equipo.
    - $obligatorio (bool): falso en los bloques de equipo que pueden quedar en
      blanco — ahí ningún campo lleva `required`, o el navegador no dejaría
      enviar el formulario con ese bloque vacío.
    - $mostrarQuitar (bool): la primera fila de cada equipo no se quita (todo
      equipo que participa lleva al menos un lote); las agregadas sí. Dice
      «Quitar lote», no «Quitar» a secas: lo que sale es el lote del reparto,
      nunca una persona de la cuadrilla.
--}}
@php
    $prefijo = "equipos[{$indiceEquipo}][lotes][{$indiceLote}]";
    $idBase = str_replace(['[', ']'], ['-', ''], $prefijo);
    $erroresPrefijo = str_replace(['[', ']'], ['.', ''], $prefijo);
    $obligatorio ??= true;
    $mostrarQuitar ??= true;
@endphp
<div class="ag-form-section__body ag-ordenes-trabajo-form__lote-fila" data-ag-lote-equipo-fila>
    <x-atoms.select
        name="{{ $prefijo }}[lote_id]"
        id="{{ $idBase }}-lote"
        :label="__('operaciones.asignacion_equipos.campo_lote')"
        :options="$etiquetasLote"
        :value="$lote['lote_id'] ?? ''"
        :placeholder="__('operaciones.asignacion_equipos.campo_lote_placeholder')"
        :required="$obligatorio"
        :error="$errors->first($erroresPrefijo.'.lote_id')"
    />

    <x-atoms.input
        type="number"
        name="{{ $prefijo }}[hectareas]"
        id="{{ $idBase }}-hectareas"
        :label="__('operaciones.asignacion_equipos.campo_hectareas')"
        :value="$lote['hectareas'] ?? ''"
        min="0.01"
        step="0.01"
        :required="$obligatorio"
        :error="$errors->first($erroresPrefijo.'.hectareas')"
        data-ag-lote-hectareas
    />

    <x-atoms.select
        name="{{ $prefijo }}[turno]"
        id="{{ $idBase }}-turno"
        :label="__('operaciones.asignacion_equipos.campo_turno')"
        :options="[
            'manana' => __('operaciones.asignacion_equipos.turno_manana'),
            'noche' => __('operaciones.asignacion_equipos.turno_noche'),
            'todo_el_dia' => __('operaciones.asignacion_equipos.turno_todo_el_dia'),
        ]"
        :value="$lote['turno'] ?? ''"
        :placeholder="__('operaciones.asignacion_equipos.campo_turno')"
        :required="$obligatorio"
        :error="$errors->first($erroresPrefijo.'.turno')"
    />

    <x-atoms.input
        type="time"
        name="{{ $prefijo }}[turno_hora_inicio]"
        id="{{ $idBase }}-turno-inicio"
        :label="__('operaciones.asignacion_equipos.campo_turno_hora_inicio')"
        :value="$lote['turno_hora_inicio'] ?? ''"
        :required="$obligatorio"
        :error="$errors->first($erroresPrefijo.'.turno_hora_inicio')"
    />

    <x-atoms.input
        type="time"
        name="{{ $prefijo }}[turno_hora_fin]"
        id="{{ $idBase }}-turno-fin"
        :label="__('operaciones.asignacion_equipos.campo_turno_hora_fin')"
        :value="$lote['turno_hora_fin'] ?? ''"
        :required="$obligatorio"
        :error="$errors->first($erroresPrefijo.'.turno_hora_fin')"
    />

    @if ($mostrarQuitar)
        <div class="ag-form-section__field--full ag-ordenes-trabajo-form__lote-fila-pie">
            <x-atoms.button type="button" variant="text" size="sm" icon="delete" data-ag-lote-equipo-quitar>
                {{ __('operaciones.ordenes_trabajo.lote_quitar') }}
            </x-atoms.button>
        </div>
    @endif
</div>
