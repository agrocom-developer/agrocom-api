{{--
    Partial: tabla de lotes ya agregados al contrato (tarea "contratos-lotes"),
    dentro de la sección "Propiedad y lotes" de `_formulario.blade.php` —
    extraído de ahí (16/9/2026) para que ese archivo no cargue con el detalle
    de cada fila. Agrupada por propiedad (una banda de título por grupo, no
    una columna más), con Código / Hectáreas / Día completo / Horario /
    Acciones por fila.

    Espera:
    - $lotesIniciales (array<int, array{propiedad_nombre: string, lotes:
      list<array{indice: int, lote_id: int, codigo: string, hectareas:
      string, hora_inicio: ?string, hora_fin: ?string}>}>): agrupado por
      propiedad_id — armado por `_formulario.blade.php` desde
      `$contrato->lotes` o, tras un error de validación, desde `old('lotes')`
      (ver el bloque de PHP embebido de ese archivo, corrección del
      16/9/2026). `indice` es
      la clave ORIGINAL del array `lotes[N][...]` que mandó el formulario —
      se usa acá tanto para el `name="lotes[N][...]"` de la fila como para
      ubicar el error de ESA fila (`lotes.N.hora_inicio`/`lotes.N.hora_fin`),
      nunca un contador propio: si no coinciden, el error de una fila
      aparecería en otra.

    $errors (heredado del scope de la página, Blade comparte variables con
    `@include`): `ViewErrorBag` global de Laravel, no un prop propio.

    - $loteIdsConOrdenRegistrada (list<int>, default []): lotes de este
      contrato que ya tienen una orden de aplicación registrada — su botón
      "Quitar" se deshabilita (se queda visible, con `title` explicando por
      qué: sin vista "show" de contrato, es la única forma de proteger un
      lote con historial real sin ocultar información).
    - $conflictosPorLote (array<int, array>, default []): lotes de este
      contrato que TAMBIÉN están en otro contrato vigente de la misma
      campaña — si el lote aparece acá, la fila suma un badge de alerta y un
      botón que abre `_modal-conflicto-lote.blade.php` con los datos de ese
      otro contrato (`data-ag-conflictos-lotes`, JSON embebido en
      `_formulario.blade.php`).

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

    @php
        $hayLotes = collect($lotesIniciales)->sum(fn ($g) => count($g['lotes'] ?? [])) > 0;
        $loteIdsConOrdenRegistrada ??= [];
        $conflictosPorLote ??= [];
    @endphp
    <div class="ag-contratos-form__lotes-tabla" data-ag-lotes-tabla @if (!$hayLotes) hidden @endif>
        <div class="ag-contratos-form__lotes-tabla-head">
            <span>{{ __('comercial.lotes.lote_codigo') }}</span>
            <span>{{ __('comercial.lotes.lote_hectareas') }}</span>
            <span>{{ __('comercial.contratos.ventana_dia_completo') }}</span>
            <span>{{ __('comercial.contratos.lotes_col_horario') }}</span>
            <span>{{ __('comercial.contratos.lotes_col_acciones') }}</span>
        </div>
        <div data-ag-lotes-agrupados>
            @foreach ($lotesIniciales as $propiedadId => $grupo)
                <div data-ag-lote-grupo="propiedad-{{ $propiedadId }}" class="ag-contratos-form__lote-group">
                    <div class="ag-contratos-form__lote-group-title">
                        {{ $grupo['propiedad_nombre'] }}
                    </div>
                    <div data-ag-lote-contenedor>
                        @foreach ($grupo['lotes'] as $lote)
                            @php
                                $indiceGlobal = $lote['indice'];
                                $esDiaCompleto = !$lote['hora_inicio'] && !$lote['hora_fin'];
                                $errorHorario = $errors->first("lotes.{$indiceGlobal}.hora_inicio") ?: $errors->first("lotes.{$indiceGlobal}.hora_fin");
                            @endphp
                            <div class="ag-contratos-form__lote-row" data-lote-id="{{ $lote['lote_id'] }}">
                                <input type="hidden" name="lotes[{{ $indiceGlobal }}][lote_id]" value="{{ $lote['lote_id'] }}">
                                <span class="ag-contratos-form__lote-code-cell">
                                    <strong class="ag-contratos-form__lote-code">{{ $lote['codigo'] }}</strong>
                                    @if (isset($conflictosPorLote[$lote['lote_id']]))
                                        <x-atoms.badge variant="alert" icon="warning">
                                            {{ __('comercial.contratos.lote_en_conflicto') }}
                                        </x-atoms.badge>
                                        <x-atoms.button
                                            type="button"
                                            variant="text"
                                            size="sm"
                                            icon="visibility"
                                            data-ag-lote-conflicto-ver
                                            data-lote-id-conflicto="{{ $lote['lote_id'] }}"
                                        >
                                            {{ __('comercial.contratos.lote_conflicto_ver') }}
                                        </x-atoms.button>
                                    @endif
                                </span>
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
                                @php $tieneOrdenRegistrada = in_array($lote['lote_id'], $loteIdsConOrdenRegistrada, true); @endphp
                                <div class="ag-contratos-form__lote-acciones">
                                    <x-atoms.button
                                        type="button"
                                        variant="text"
                                        size="sm"
                                        class="ag-contratos-form__lote-quitar-btn"
                                        icon="delete"
                                        data-ag-lote-quitar
                                        @disabled($tieneOrdenRegistrada)
                                        @if ($tieneOrdenRegistrada) title="{{ __('comercial.contratos.lotes_quitar_bloqueado_orden') }}" @endif
                                    >
                                        {{ __('comercial.contratos.lotes_quitar') }}
                                    </x-atoms.button>
                                </div>
                                @if ($errorHorario)
                                    <p class="ag-input__error" role="alert">{{ $errorHorario }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
