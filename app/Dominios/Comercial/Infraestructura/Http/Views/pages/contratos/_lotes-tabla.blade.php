{{--
    Partial: tabla de lotes ya agregados al contrato (tarea "contratos-lotes"),
    dentro de la sección "Propiedad y lotes" de `_formulario.blade.php` —
    extraído de ahí (16/9/2026) para que ese archivo no cargue con el detalle
    de cada fila: Código / Propiedad / Hectáreas / Acciones. Las filas siguen
    agrupadas por propiedad en el DOM (`data-ag-lote-grupo`, de ahí salen las
    pills y el modal), pero la propiedad se lee en su columna, no en una banda
    de título por grupo (21/9/2026, pedido directo).

    El contrato solo dice QUÉ lotes entran (21/9/2026): el día completo y el
    horario de cada lote se cargan en la orden de trabajo. Arriba de la tabla,
    un aviso informativo compara las hectáreas que suman los lotes elegidos con
    las hectáreas contratadas — orienta, no bloquea el guardado. El texto lo
    arma `contratos-form.js` (`actualizarResumenHectareas`) con las plantillas
    que recibe por `data-*`, porque cambia al agregar o quitar lotes y al
    escribir las hectáreas contratadas. Lo único que va en color alert es la
    diferencia («50,00 ha menos»); el resto del aviso conserva su color.

    Espera:
    - $lotesIniciales (array<int, array{propiedad_nombre: string, lotes:
      list<array{indice: int, lote_id: int, codigo: string, hectareas:
      string}>}>): agrupado por
      propiedad_id y, dentro de cada propiedad, en orden natural por código
      (L1, L2, … L10) — armado por `_formulario.blade.php` desde
      `$contrato->lotes` o, tras un error de validación, desde `old('lotes')`
      (ver el bloque de PHP embebido de ese archivo, corrección del
      16/9/2026). `indice` es
      la clave ORIGINAL del array `lotes[N][...]` que mandó el formulario —
      se usa acá para el `name="lotes[N][lote_id]"` de la fila, nunca un
      contador propio.

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
    {{-- Hectáreas de los lotes elegidos contra las contratadas: solo informa. --}}
    <x-molecules.alert-strip
        variant="info"
        icon="straighten"
        :hidden="! $hayLotes"
        data-ag-lotes-resumen
        :data-texto-contador="__('comercial.contratos.lotes_contador')"
        :data-texto-lotes-uno="__('comercial.contratos.lotes_resumen_uno')"
        :data-texto-lotes-varios="__('comercial.contratos.lotes_resumen_varios')"
        :data-texto-sin-contratadas="__('comercial.contratos.lotes_resumen_sin_contratadas')"
        :data-texto-igual="__('comercial.contratos.lotes_resumen_igual')"
        :data-texto-mas="__('comercial.contratos.lotes_resumen_mas')"
        :data-texto-mas-resaltado="__('comercial.contratos.lotes_resumen_mas_resaltado')"
        :data-texto-menos="__('comercial.contratos.lotes_resumen_menos')"
        :data-texto-menos-resaltado="__('comercial.contratos.lotes_resumen_menos_resaltado')"
    >
        <span data-ag-lotes-resumen-texto></span>
    </x-molecules.alert-strip>

    <div class="ag-contratos-form__lotes-tabla" data-ag-lotes-tabla @if (!$hayLotes) hidden @endif>
        <div class="ag-contratos-form__lotes-tabla-head">
            <span>{{ __('comercial.lotes.lote_codigo') }}</span>
            <span>{{ __('comercial.contratos.campo_propiedad') }}</span>
            <span>{{ __('comercial.lotes.lote_hectareas') }}</span>
            <span>{{ __('comercial.contratos.lotes_col_acciones') }}</span>
        </div>
        <div data-ag-lotes-agrupados>
            @foreach ($lotesIniciales as $propiedadId => $grupo)
                <div data-ag-lote-grupo="propiedad-{{ $propiedadId }}" class="ag-contratos-form__lote-group">
                    <div data-ag-lote-contenedor>
                        @foreach ($grupo['lotes'] as $lote)
                            @php
                                $indiceGlobal = $lote['indice'];
                            @endphp
                            <div class="ag-contratos-form__lote-row" data-lote-id="{{ $lote['lote_id'] }}" data-hectareas="{{ $lote['hectareas'] }}">
                                <input type="hidden" name="lotes[{{ $indiceGlobal }}][lote_id]" value="{{ $lote['lote_id'] }}">
                                <span class="ag-contratos-form__lote-code-cell">
                                    <strong class="ag-contratos-form__lote-code">{{ $lote['codigo'] }}</strong>
                                    @if (isset($conflictosPorLote[$lote['lote_id']]))
                                        <x-atoms.badge variant="alert" icon="warning">
                                            {{ __('comercial.contratos.lote_en_conflicto') }}
                                        </x-atoms.badge>
                                    @endif
                                </span>
                                <span class="ag-contratos-form__lote-propiedad">{{ $grupo['propiedad_nombre'] }}</span>
                                <span class="ag-contratos-form__lote-hectareas">
                                    {{ number_format((float) $lote['hectareas'], 2, ',', '.') }} ha
                                </span>
                                @php
                                    $tieneOrdenRegistrada = in_array($lote['lote_id'], $loteIdsConOrdenRegistrada, true);
                                    // Ni `@if`/`@endif` ni la DIRECTIVA `@disabled()` se pueden
                                    // usar dentro de la lista de atributos de un tag de
                                    // componente Blade (<x-...>) — su compilador de tags
                                    // confunde el límite del tag y rompe la compilación
                                    // ("unexpected token endif"), a diferencia de un tag HTML
                                    // plano, donde ambas sí funcionan (ver los <input> de
                                    // arriba). Dentro de un componente, el equivalente
                                    // soportado es el ATRIBUTO dinámico `:disabled="..."`
                                    // (mismo comportamiento: Laravel omite el atributo si el
                                    // valor es `false`/`null`, `ComponentAttributeBag::__toString()`)
                                    // — igual criterio para `:title`.
                                    $tituloQuitarBloqueado = $tieneOrdenRegistrada ? __('comercial.contratos.lotes_quitar_bloqueado_orden') : null;
                                @endphp
                                <div class="ag-contratos-form__lote-acciones">
                                    {{-- "Ver contrato" vive ACÁ (columna Acciones), no junto al
                                         código/badge de la primera columna (pedido explícito del
                                         usuario, 18/9/2026): un botón de más ancho variable ahí
                                         deformaba el grid de la fila (ver contratos.css) — acá
                                         la columna ya es `auto` y ya convive con "Quitar". --}}
                                    @if (isset($conflictosPorLote[$lote['lote_id']]))
                                        <x-atoms.button
                                            type="button"
                                            variant="text"
                                            size="sm"
                                            icon="visibility"
                                            class="ag-contratos-form__lote-ver-contrato"
                                            :title="__('comercial.contratos.lote_conflicto_ver')"
                                            :aria-label="__('comercial.contratos.lote_conflicto_ver')"
                                            data-ag-lote-conflicto-ver
                                            data-lote-id-conflicto="{{ $lote['lote_id'] }}"
                                        >
                                            {{ __('comercial.contratos.lote_conflicto_ver') }}
                                        </x-atoms.button>
                                    @endif
                                    <x-atoms.button
                                        type="button"
                                        variant="text"
                                        size="sm"
                                        class="ag-contratos-form__lote-quitar-btn"
                                        icon="delete"
                                        data-ag-lote-quitar
                                        :aria-label="__('comercial.contratos.lotes_quitar')"
                                        :disabled="$tieneOrdenRegistrada"
                                        :title="$tituloQuitarBloqueado"
                                    >
                                        {{ __('comercial.contratos.lotes_quitar') }}
                                    </x-atoms.button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Paginación de 20 lotes por página (`paginador-cliente.js`, lo maneja
         contratos-form.js): las filas de otras páginas se ocultan, no se quitan,
         así que sus campos siguen en el formulario y se envían igual. --}}
    <div
        class="ag-paginador"
        data-ag-lotes-paginador
        hidden
        data-label-aria="{{ __('comercial.contratos.lotes_paginacion_aria') }}"
        data-label-anterior="{{ __('ui.paginador.anterior') }}"
        data-label-siguiente="{{ __('ui.paginador.siguiente') }}"
        data-label-pagina="{{ __('ui.paginador.pagina') }}"
        data-label-resumen="{{ __('comercial.contratos.lotes_paginacion_resumen') }}"
    ></div>
</div>
