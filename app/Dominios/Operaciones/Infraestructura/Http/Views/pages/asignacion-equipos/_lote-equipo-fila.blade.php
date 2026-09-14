{{--
    Partial: fila de lote DENTRO de un equipo, en el formulario de reparto en
    bloque (HU-92, tarea 107) — nivel interno del repetible anidado: cada
    equipo (`_equipo-bloque.blade.php`) trae su propia lista de pares
    lote+hectareas, mismo patrón que `campos/_lote-fila.blade.php` pero un
    nivel más adentro (`equipos[{indiceEquipo}][lotes][{indiceLote}]`).

    Espera:
    - $indiceEquipo (int|string): posición del equipo — `__INDICE_EQUIPO__`
      cuando este partial se renderiza dentro de la plantilla clonable del
      equipo.
    - $indiceLote (int|string): posición de esta fila dentro de los lotes del
      equipo — `__INDICE_LOTE__` en la plantilla clonable propia.
    - $lote (array{lote_id?: int|string, hectareas?: string}): vacío en una
      fila nueva.
    - $etiquetasLote (array<int, string>): SOLO los lotes de ESTA ORDEN
      (`ope_orden_lotes`), ya resueltos por el controlador — nunca el
      universo completo de lotes (ADR 0003 regla 3, mismo criterio que
      `OrdenesController::lotesDisponibles()`).
--}}
@php
    $prefijo = "equipos[{$indiceEquipo}][lotes][{$indiceLote}]";
    $idBase = str_replace(['[', ']'], ['-', ''], $prefijo);
    $erroresPrefijo = str_replace(['[', ']'], ['.', ''], $prefijo);
@endphp
<div class="ag-form-section__body ag-asignacion-equipos-ficha__lote-fila" data-ag-lote-equipo-fila>
    <x-atoms.select
        name="{{ $prefijo }}[lote_id]"
        id="{{ $idBase }}-lote"
        label="{{ __('operaciones.asignacion_equipos.campo_lote') }}"
        :options="$etiquetasLote"
        :value="$lote['lote_id'] ?? ''"
        placeholder="{{ __('operaciones.asignacion_equipos.campo_lote_placeholder') }}"
        required
        error="{{ $errors->first($erroresPrefijo.'.lote_id') }}"
    />

    <x-atoms.input
        type="number"
        name="{{ $prefijo }}[hectareas]"
        id="{{ $idBase }}-hectareas"
        label="{{ __('operaciones.asignacion_equipos.campo_hectareas') }}"
        value="{{ $lote['hectareas'] ?? '' }}"
        min="0.01"
        step="0.01"
        required
        error="{{ $errors->first($erroresPrefijo.'.hectareas') }}"
    />

    <div class="ag-form-section__field--full ag-asignacion-equipos-ficha__lote-fila-pie">
        <x-atoms.button type="button" variant="text" size="sm" icon="delete" data-ag-lote-equipo-quitar>
            {{ __('operaciones.asignacion_equipos.lote_quitar') }}
        </x-atoms.button>
    </div>
</div>
