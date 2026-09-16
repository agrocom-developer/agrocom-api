{{--
    Partial: tabla de lotes ya agregados al contrato (tarea "contratos-lotes"),
    dentro de la sección "Propiedad y lotes" de `_formulario.blade.php` —
    extraído de ahí (16/9/2026) para que ese archivo no cargue con el detalle
    de cada fila. Agrupada por propiedad (una banda de título por grupo, no
    una columna más), con Código / Hectáreas / Día completo / Horario /
    Acciones por fila.

    Espera:
    - $lotesIniciales (array<int, array{propiedad_nombre: string, lotes:
      list<array{lote_id: int, codigo: string, hectareas: string,
      hora_inicio: ?string, hora_fin: ?string}>}>): agrupado por propiedad_id
      — mismo shape que arma `_formulario.blade.php` desde `$contrato->lotes`
      o repite `old('lotes_data')` tras un error de validación.

    $errors (heredado del scope de la página, Blade comparte variables con
    `@include`): `ViewErrorBag` global de Laravel, no un prop propio.

    "Día completo" (checkbox tildado por defecto cuando el lote no trae
    horario propio) deshabilita los dos `<input type="time">` de al lado sin
    ocultarlos — visibles-pero-deshabilitados a propósito (ver comentario en
    `resources/css/pages/contratos.css` sobre `.ag-contratos-form__lote-rango-horas`):
    si se ocultaran, esa fila perdería el ancho de columna y la tabla
    quedaría dentada entre filas con/sin horario propio. El toggle vive en
    `resources/js/pages/contratos-form.js`.
--}}
<div
    data-ag-lotes-lista-apilada
    class="ag-form-section__field--full ag-contratos-form__lotes-list"
    data-texto-quitar-lote="{{ __('comercial.contratos.lotes_quitar') }}"
>
    @if ($errors->has('lotes'))
        <p class="ag-input__error" role="alert">{{ $errors->first('lotes') }}</p>
    @endif

    @php $hayLotes = collect($lotesIniciales)->sum(fn ($g) => count($g['lotes'] ?? [])) > 0; @endphp
    <div class="ag-contratos-form__lotes-tabla" data-ag-lotes-tabla @if (!$hayLotes) hidden @endif>
        <div class="ag-contratos-form__lotes-tabla-head">
            <span>{{ __('comercial.lotes.lote_codigo') }}</span>
            <span>{{ __('comercial.lotes.lote_hectareas') }}</span>
            <span>{{ __('comercial.contratos.ventana_dia_completo') }}</span>
            <span>{{ __('comercial.contratos.lotes_col_horario') }}</span>
            <span>{{ __('comercial.contratos.lotes_col_acciones') }}</span>
        </div>
        <div data-ag-lotes-agrupados>
            @php $indiceGlobal = 0; @endphp
            @foreach ($lotesIniciales as $propiedadId => $grupo)
                <div data-ag-lote-grupo="propiedad-{{ $propiedadId }}" class="ag-contratos-form__lote-group">
                    <div class="ag-contratos-form__lote-group-title">
                        {{ $grupo['propiedad_nombre'] }}
                    </div>
                    <div data-ag-lote-contenedor>
                        @foreach ($grupo['lotes'] as $lote)
                            @php $esDiaCompleto = !$lote['hora_inicio'] && !$lote['hora_fin']; @endphp
                            <div class="ag-contratos-form__lote-row" data-lote-id="{{ $lote['lote_id'] }}">
                                <input type="hidden" name="lotes[{{ $indiceGlobal }}][lote_id]" value="{{ $lote['lote_id'] }}">
                                <strong class="ag-contratos-form__lote-code">{{ $lote['codigo'] }}</strong>
                                <span class="ag-contratos-form__lote-hectareas">
                                    {{ number_format((float) $lote['hectareas'], 2, ',', '.') }} ha
                                </span>
                                <label class="ag-contratos-form__lote-dia-completo-celda">
                                    <input
                                        type="checkbox"
                                        class="ag-checkbox-group__input"
                                        data-ag-lote-dia-completo="{{ $lote['lote_id'] }}"
                                        @checked($esDiaCompleto)
                                    >
                                    <span class="ag-checkbox-group__box" aria-hidden="true">
                                        <span class="material-symbols-rounded ag-icon ag-icon--sm ag-checkbox-group__check">check</span>
                                    </span>
                                </label>
                                <div class="ag-contratos-form__lote-rango-horas">
                                    <input
                                        type="time"
                                        class="ag-contratos-form__input-hora"
                                        name="lotes[{{ $indiceGlobal }}][hora_inicio]"
                                        value="{{ $lote['hora_inicio'] ?? '' }}"
                                        @disabled($esDiaCompleto)
                                    >
                                    <span aria-hidden="true">–</span>
                                    <input
                                        type="time"
                                        class="ag-contratos-form__input-hora"
                                        name="lotes[{{ $indiceGlobal }}][hora_fin]"
                                        value="{{ $lote['hora_fin'] ?? '' }}"
                                        @disabled($esDiaCompleto)
                                    >
                                </div>
                                <x-atoms.button
                                    type="button"
                                    variant="text"
                                    size="sm"
                                    class="ag-contratos-form__lote-quitar-btn"
                                    icon="delete"
                                    data-ag-lote-quitar
                                >
                                    {{ __('comercial.contratos.lotes_quitar') }}
                                </x-atoms.button>
                            </div>
                            @php $indiceGlobal++; @endphp
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
