{{--
    Partial: fila de lote del formulario de campo (HU-24, tarea 35) — mismo
    patrón que `clientes/_contacto-fila.blade.php` (tarea 33): una fila
    repetible dentro de la sección "Lotes" de `_formulario.blade.php`, reusada
    para pintar los lotes existentes, para repetir `old('lotes')` tras un
    error de validación, y como plantilla que clona
    `resources/js/pages/campos-form.js` al apretar "Agregar lote".

    Espera:
    - $indice (int|string): posición dentro del array `lotes[]` — en la
      plantilla clonable viene el placeholder literal `__INDICE__`, que el JS
      reemplaza por el próximo número al clonar.
    - $lote (array{id?: int, codigo?: string, hectareas?: string,
      geometria?: string, restricciones?: string}): vacío en una fila nueva.

    `geometria` es un `<textarea>` de GeoJSON crudo (sin librería de mapas —
    prompt de la tarea): el usuario pega el JSON, el Form Request valida la
    forma mínima (`type`/`coordinates`).
--}}
<div class="ag-campos-form__lote" data-ag-lote-fila>
    @if (! empty($lote['id']))
        <input type="hidden" name="lotes[{{ $indice }}][id]" value="{{ $lote['id'] }}">
    @endif

    <x-atoms.input
        type="text"
        name="lotes[{{ $indice }}][codigo]"
        label="{{ __('comercial.campos.lote_codigo') }}"
        value="{{ $lote['codigo'] ?? '' }}"
        required
    />

    <x-atoms.input
        type="number"
        name="lotes[{{ $indice }}][hectareas]"
        label="{{ __('comercial.campos.lote_hectareas') }}"
        value="{{ $lote['hectareas'] ?? '' }}"
        min="0.01"
        step="0.01"
        required
    />

    <div class="ag-input ag-form-section__field--full">
        <label for="lotes-{{ $indice }}-geometria" class="ag-input__label">
            {{ __('comercial.campos.lote_geometria') }}
        </label>
        <div class="ag-input__control">
            <textarea
                name="lotes[{{ $indice }}][geometria]"
                id="lotes-{{ $indice }}-geometria"
                class="ag-input__field ag-campos-form__geometria"
                rows="3"
                placeholder="{{ __('comercial.campos.lote_geometria_placeholder') }}"
            >{{ $lote['geometria'] ?? '' }}</textarea>
        </div>
        <p class="ag-input__help">{{ __('comercial.campos.lote_geometria_ayuda') }}</p>
    </div>

    <div class="ag-input ag-form-section__field--full">
        <label for="lotes-{{ $indice }}-restricciones" class="ag-input__label">
            {{ __('comercial.campos.lote_restricciones') }}
        </label>
        <div class="ag-input__control">
            <textarea
                name="lotes[{{ $indice }}][restricciones]"
                id="lotes-{{ $indice }}-restricciones"
                class="ag-input__field"
                rows="2"
                placeholder="{{ __('comercial.campos.lote_restricciones_placeholder') }}"
            >{{ $lote['restricciones'] ?? '' }}</textarea>
        </div>
    </div>

    <div class="ag-form-section__field--full ag-campos-form__lote-pie">
        <x-atoms.button type="button" variant="text" size="sm" icon="delete" data-ag-lote-quitar>
            {{ __('comercial.campos.lote_quitar') }}
        </x-atoms.button>
    </div>
</div>
