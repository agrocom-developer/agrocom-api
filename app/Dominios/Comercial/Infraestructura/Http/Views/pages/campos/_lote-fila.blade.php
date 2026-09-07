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

    `geometria` se dibuja sobre un MAPA SATELITAL (Leaflet + Geoman): el
    perímetro de un lote se reconoce mirando la imagen, no tipeando pares de
    coordenadas. Antes era un `<textarea>` donde había que pegar el GeoJSON a
    mano — un editor de mapa es lo que la tarea 68 vino a reemplazar.

    El valor sigue viajando como el MISMO string JSON en un `<input hidden>`,
    así que el Form Request no cambia: valida la forma mínima
    (`type`/`coordinates`) igual que antes, y una geometría cargada por otra
    vía sigue siendo válida.
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

    <div class="ag-input ag-form-section__field--full ag-lote-mapa" data-ag-lote-mapa>
        <span class="ag-input__label">{{ __('comercial.campos.lote_geometria') }}</span>

        {{-- El valor real. Lo escribe el editor; queda en el DOM aunque el
             mapa no llegue a cargar, así que una geometría ya guardada nunca
             se pierde por un fallo del JS. --}}
        <input
            type="hidden"
            name="lotes[{{ $indice }}][geometria]"
            id="lotes-{{ $indice }}-geometria"
            value="{{ $lote['geometria'] ?? '' }}"
            data-ag-lote-geometria
        >

        <div class="ag-lote-mapa__lienzo" data-ag-lote-mapa-lienzo></div>

        <div class="ag-lote-mapa__pie">
            <p class="ag-input__help ag-lote-mapa__ayuda">{{ __('comercial.campos.lote_geometria_ayuda') }}</p>

            <div class="ag-lote-mapa__medida" data-ag-lote-medida hidden>
                <span data-ag-lote-medida-texto></span>
                {{-- Botón y no autocompletado: `hectareas` es la superficie
                     CONTRATADA, que puede no coincidir con el polígono
                     dibujado, y es la base de lo que se factura (invariante
                     6). La decisión de copiarla es de quien carga el campo. --}}
                <x-atoms.button type="button" variant="text" size="sm" data-ag-lote-usar-superficie>
                    {{ __('comercial.campos.lote_usar_superficie') }}
                </x-atoms.button>
            </div>
        </div>
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
