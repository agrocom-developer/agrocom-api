{{--
    Partial: línea repetible de repuesto consumido, dentro del formulario de
    cierre de `ordenes/edit.blade.php` (HU-37, tarea 53) — mismo patrón que
    `comercial::pages.contratos._ventana-fila`: repetida para pintar
    `old('repuestos')` tras un error de validación, y como plantilla que
    clona `resources/js/pages/ordenes-mantenimiento-form.js` al apretar
    "Agregar repuesto".

    Espera:
    - $indice (int|string): posición dentro de `repuestos[]` — en la
      plantilla clonable viene el placeholder literal `__INDICE__`.
    - $linea (array{repuesto_id?: int|string, base_id?: int|string, cantidad?: string}):
      vacía en una fila nueva.
    - $repuestosDisponibles (Collection<int, string>): id => "código —
      descripción".
    - $basesDisponibles (Collection<int, string>): id => nombre.
--}}
<div class="ag-ordenes-mantenimiento-form__repuesto" data-ag-repuesto-fila>
    <div class="ag-input">
        <label for="repuesto_id_{{ $indice }}" class="ag-input__label">{{ __('mantenimiento.ordenes.campo_repuesto') }}</label>
        <div class="ag-input__control">
            <select name="repuestos[{{ $indice }}][repuesto_id]" id="repuesto_id_{{ $indice }}" class="ag-input__field" required>
                <option value="" disabled @selected(empty($linea['repuesto_id']))>{{ __('mantenimiento.ordenes.campo_repuesto_placeholder') }}</option>
                @foreach ($repuestosDisponibles as $id => $etiqueta)
                    <option value="{{ $id }}" @selected(isset($linea['repuesto_id']) && (string) $linea['repuesto_id'] === (string) $id)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="ag-input">
        <label for="base_id_{{ $indice }}" class="ag-input__label">{{ __('mantenimiento.ordenes.campo_base') }}</label>
        <div class="ag-input__control">
            <select name="repuestos[{{ $indice }}][base_id]" id="base_id_{{ $indice }}" class="ag-input__field" required>
                <option value="" disabled @selected(empty($linea['base_id']))>{{ __('mantenimiento.ordenes.campo_base_placeholder') }}</option>
                @foreach ($basesDisponibles as $id => $nombreBase)
                    <option value="{{ $id }}" @selected(isset($linea['base_id']) && (string) $linea['base_id'] === (string) $id)>{{ $nombreBase }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <x-atoms.input
        type="number"
        name="repuestos[{{ $indice }}][cantidad]"
        label="{{ __('mantenimiento.ordenes.campo_cantidad') }}"
        value="{{ $linea['cantidad'] ?? '' }}"
        min="0.01"
        step="0.01"
        required
    />

    <div class="ag-ordenes-mantenimiento-form__repuesto-pie">
        <x-atoms.button type="button" variant="text" size="sm" icon="delete" data-ag-repuesto-quitar>
            {{ __('mantenimiento.ordenes.repuesto_quitar') }}
        </x-atoms.button>
    </div>
</div>
