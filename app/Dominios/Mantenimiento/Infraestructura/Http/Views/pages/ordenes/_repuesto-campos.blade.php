{{--
    Partial: campos de una línea de repuesto elegida por casilla (HU-57,
    tarea 80) — reemplaza `_repuesto-linea.blade.php` (tarea 53, eliminado).
    Se inyecta vía `extraPorOpcion` de `x-atoms.checkbox-group` (ver docblock
    del átomo): HTML ya armado, uno por cada repuesto del catálogo completo
    — no hay "agregar línea", cada repuesto YA tiene su bloque de campos acá,
    deshabilitado hasta que la casilla se marca.

    Deshabilitado de fábrica (`disabled` en los tres campos reales): un input
    disabled no viaja en el POST, así que mientras la casilla no está
    marcada estos campos no envían nada. `resources/js/pages/
    ordenes-mantenimiento-form.js` los habilita/deshabilita en el `change`
    de la casilla hermana (mismo patrón que el toggle dron/vehículo de
    `ordenes/create.blade.php`) y sincroniza `base_id` con la base global de
    la orden salvo que esta línea tenga su propio override activo.

    `__repuestos_base_override[...]` es un campo transitorio (no
    `repuestos.*`, así que `CerrarOrdenMantenimientoRequest` lo ignora): solo
    existe para que el usuario elija una base distinta para ESTA línea; su
    valor lo copia el JS al `base_id` real, nunca viaja él mismo.

    Espera (todo ya resuelto por `edit.blade.php`, ninguna consulta acá):
    - $repuestoId (int).
    - $marcado (bool): si este repuesto ya viene elegido (repintado tras un
      error de validación) — determina si estos campos nacen habilitados.
      La casilla misma la marca `checkbox-group` con su prop `value`; este
      partial recibe el mismo booleano para no desalinearse.
    - $basesDisponibles (Collection<int, string>): mismas opciones que el
      selector global.
    - $baseGlobalId (int|string|null): base elegida para la orden.
    - $cantidadInicial (?string): old('repuestos.{id}.cantidad').
    - $baseInicialLinea (int|string|null): old('repuestos.{id}.base_id') —
      si difiere de $baseGlobalId, esta línea ya tenía un override propio
      (se repinta abierto tras un error de validación).
--}}
@php
    $baseLinea = $baseInicialLinea ?? $baseGlobalId;
    $tieneOverride = $baseInicialLinea !== null && (string) $baseInicialLinea !== (string) $baseGlobalId;
@endphp
<div class="ag-repuesto-campos" data-ag-repuesto-campos data-repuesto-id="{{ $repuestoId }}">
    <input
        type="hidden"
        name="repuestos[{{ $repuestoId }}][repuesto_id]"
        value="{{ $repuestoId }}"
        @disabled(! $marcado)
        data-ag-repuesto-campo="repuesto_id"
    >
    <input
        type="hidden"
        name="repuestos[{{ $repuestoId }}][base_id]"
        value="{{ $baseLinea }}"
        @disabled(! $marcado)
        data-ag-repuesto-campo="base_id"
    >

    <x-atoms.input
        type="number"
        name="repuestos[{{ $repuestoId }}][cantidad]"
        :label="__('mantenimiento.ordenes.campo_cantidad')"
        :value="$cantidadInicial"
        min="0.01"
        step="0.01"
        @disabled(! $marcado)
        class="ag-repuesto-campos__cantidad"
        data-ag-repuesto-campo="cantidad"
        :error="$errors->first(\"repuestos.{$repuestoId}.cantidad\")"
    />

    <p class="ag-repuesto-campos__disponibilidad" data-ag-repuesto-disponibilidad></p>

    <p class="ag-repuesto-campos__aviso" data-ag-repuesto-aviso hidden role="alert">
        <x-atoms.icon name="warning" size="sm" />
        <span data-ag-repuesto-aviso-texto></span>
    </p>

    <button
        type="button"
        class="ag-repuesto-campos__cambiar-base"
        data-ag-repuesto-cambiar-base
        @if ($tieneOverride) hidden @endif
    >
        {{ __('mantenimiento.ordenes.repuesto_cambiar_base') }}
    </button>

    {{-- Envoltorio propio para el hidden: la raíz de x-atoms.select solo
         fusiona `class` de $attributes (LSP del átomo, ver su docblock) — un
         `hidden` pasado acá le llegaría solo al elemento select nativo de
         adentro, no a la etiqueta ni al combobox armado por select.js, y
         quedaría a medio ocultar. --}}
    <div class="ag-repuesto-campos__base-override-wrap" data-ag-repuesto-base-override-wrap @if (! $tieneOverride) hidden @endif>
        <x-atoms.select
            name="__repuestos_base_override[{{ $repuestoId }}]"
            :label="__('mantenimiento.ordenes.campo_base')"
            :options="$basesDisponibles"
            :value="$baseLinea"
            class="ag-repuesto-campos__base-override"
            data-ag-repuesto-base-override
        />
    </div>
</div>
