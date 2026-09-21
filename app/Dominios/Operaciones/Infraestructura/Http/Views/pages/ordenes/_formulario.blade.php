{{--
    Partial: formulario de orden de aplicación, compartido por
    create.blade.php y edit.blade.php (reforma Entrega 1, 18/9/2026).
    Arquetipo Formulario, §6.3 de docs/diseno/guia_pantalla_panel.md.

    Espera:
    - $orden (OrdenAplicacion|null): null en alta; el modelo en edición.
    - $contratosDisponibles (Collection<int, string>): id => label.
    - $datosContrato (array<int, array>): cliente, logo_url, propiedades,
      aplicaciones_previstas, hectareas_contratadas, fecha_inicio, fecha_fin,
      contactos (SOLO los del cliente del contrato, cada uno con su `label`),
      lotes (en orden natural), siguiente_nro, contrato_edit_url.
    - $categoriasInsumoDisponibles (Collection<int, CategoriaInsumo>).
    - $contratoIdPreseleccionado (int|null, SOLO en create()).
    - $pasosEstado (list<array>|null), $ayudaEstado (string|null): solo en
      edición, los pasos de `molecules/step-arrow` y el párrafo que los
      acompaña (`PasosDeOrden`). Los modales que abren viven en
      `_orden-modales.blade.php`, fuera de este `<form>`.
    - $resumenRelacionado (list|null): solo en edición, las tarjetas del aside
      (órdenes de trabajo, asignación de equipos y estadías en hacienda).
    - $exigeMotivo (bool, default false): solo en edición, la orden ya está
      publicada (`vigente`/`pausada`) y corregirla pide un motivo, que queda en la
      bitácora junto con lo que cambia.
    - $insumoBloqueado (bool, default false): solo en edición, la orden ya tiene
      trabajos: el tipo de insumo, la categoría y la dosis se ven pero no se
      cambian (`PoliticaEdicionOrden`); el servidor lo exige igual.

    La orden cubre TODOS los lotes del contrato (no se elige cada uno).
    El número de aplicación lo calcula el servidor (correlativo por contrato).
    En edición, contrato/número/lotes son fijos (no editables).

    Los datos del contrato (la tarjeta con cliente, propiedades, aplicaciones,
    hectáreas y fechas), los contactos y los lotes salen de `$datosContrato` del
    contrato elegido: el servidor los pinta ya en el primer render —en alta con
    contrato preseleccionado y siempre en edición— y `ordenes-form.js` los
    repinta al cambiar el contrato. El `<select>` de contacto nunca recibe los de
    otros clientes.
--}}
@php
    $esEdicion = $orden !== null;
    $exigeMotivo ??= false;
    $insumoBloqueado ??= false;
    $accion = $esEdicion ? route('panel.ordenes.update', $orden) : route('panel.ordenes.store');
    $tituloPagina = $esEdicion ? __('operaciones.ordenes.titulo_editar') : __('operaciones.ordenes.titulo_crear');
    $urlActual = $esEdicion ? route('panel.ordenes.edit', $orden) : route('panel.ordenes.create');
    $valor = fn (string $campo, mixed $porDefecto = '') => old($campo, $orden?->{$campo} ?? $porDefecto);

    $contratoId = old('contrato_id', $orden?->contrato_id ?? $contratoIdPreseleccionado ?? null);
    $contactoId = old('emitida_por_contacto_id', $orden?->emitida_por_contacto_id);
    $fechaEmision = old('fecha_emision', $orden?->fecha_emision?->toDateString() ?? now()->toDateString());
    $tipoAplicacion = old('tipo_aplicacion', $orden?->tipo_aplicacion?->value ?? \App\Dominios\Operaciones\Dominio\TipoAplicacion::Desarrollo->value);
    $cantidadEquiposNecesarios = old('cantidad_equipos_necesarios', $orden?->cantidad_equipos_necesarios ?? 1);
    $opcionesTipoAplicacion = collect(\App\Dominios\Operaciones\Dominio\TipoAplicacion::cases())
        ->mapWithKeys(fn ($caso) => [$caso->value => __('operaciones.tipo_aplicacion.'.$caso->value)]);
    $categoriaInsumoId = old('categoria_insumo_id', $orden?->categoria_insumo_id ?? '');
    $categoriasInsumoOptions = $categoriasInsumoDisponibles->mapWithKeys(fn ($categoria) => [$categoria->id => $categoria->nombre]);
    $mapaCategoriaInsumoTipo = $categoriasInsumoDisponibles->mapWithKeys(fn ($categoria) => [$categoria->id => $categoria->tipo_insumo->value]);
    $tipoInsumoSeleccionado = old('tipo_insumo_filtro', $mapaCategoriaInsumoTipo->get((int) $categoriaInsumoId) ?? '');
    $opcionesTipoInsumo = collect(\App\Dominios\Operaciones\Dominio\TipoInsumo::cases())
        ->mapWithKeys(fn ($caso) => [$caso->value => __('operaciones.tipo_insumo.'.$caso->value)]);

    // Script con datos de contratos para el JS
    $datosContratoJson = json_encode($datosContrato, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    // Contrato elegido (en edición, el de la orden): de acá salen la tarjeta del
    // contrato, los contactos de su cliente, los lotes que la orden cubre y el
    // "Aplicación N de M". El JS los repinta al cambiar el contrato.
    $datosDelContrato = $datosContrato[$contratoId] ?? null;
    $lotes = $datosDelContrato['lotes'] ?? [];
    $contactosDelContrato = collect($datosDelContrato['contactos'] ?? [])->mapWithKeys(fn (array $contacto) => [$contacto['id'] => $contacto['label']]);
    $nroAplicacion = $esEdicion ? $orden->nro_aplicacion : ($datosDelContrato['siguiente_nro'] ?? null);
    $textoNroAplicacion = $datosDelContrato !== null && $nroAplicacion !== null
        ? __('operaciones.ordenes.nro_aplicacion_display', ['nro' => $nroAplicacion, 'total' => $datosDelContrato['aplicaciones_previstas']])
        : '—';

    // Lotes por página (`paginador-cliente.js`): mismas 20 filas que la tabla de
    // lotes del contrato. Las de otras páginas van con `hidden`, no se quitan.
    $lotesPorPagina = 20;
@endphp

<form
    method="POST"
    action="{{ $accion }}"
    class="ag-ordenes-form"
    novalidate
    data-ag-ordenes-form
    data-url-origen="{{ $urlActual }}"
    data-etiqueta-origen="{{ $tituloPagina }}"
>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$tituloPagina"
        :subtitle="__('operaciones.ordenes.subtitulo_form')"
    >
        <x-slot:actions>
            <x-molecules.boton-volver :href="route('panel.ordenes.index')" :label="__('operaciones.ordenes.volver')" />
        </x-slot:actions>
    </x-organisms.page-header>

    @if (session('estado'))
        <x-molecules.alert-strip variant="success" icon="check_circle">
            {{ session('estado') }}
        </x-molecules.alert-strip>
    @endif

    @if ($esEdicion)
        {{-- Estado de la orden como pasos (`PasosDeOrden`): cada paso accionable
             abre el mismo modal de confirmación que las acciones del listado. --}}
        <x-molecules.step-arrow
            :steps="$pasosEstado"
            :label="__('operaciones.ordenes.estado_pasos_aria')"
            :help="$ayudaEstado"
        />

        @if ($errors->has('estado'))
            <x-molecules.alert-strip variant="danger" icon="error">
                {{ $errors->first('estado') }}
            </x-molecules.alert-strip>
        @endif

        @if ($exigeMotivo)
            {{-- Corregir una orden ya publicada: pide motivo y queda en la bitácora. --}}
            <x-molecules.alert-strip variant="warning" icon="edit_note">
                {{ __('operaciones.ordenes.correccion_aviso', ['estado' => mb_strtolower(__('operaciones.estado.'.$orden->estado->value))]) }}
            </x-molecules.alert-strip>
        @endif
    @endif

    <x-molecules.form-layout>
    {{-- Sección 1: Datos del contrato --}}
    <x-molecules.form-section :title="__('operaciones.ordenes.seccion_datos_contrato')">
        <script type="application/json" data-ag-datos-contrato>
            {!! $datosContratoJson !!}
        </script>

        {{-- En edición, contrato es fijo (no editable) --}}
        @if ($esEdicion)
            <div class="ag-form-section__field">
                <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_contrato') }}</p>
                <p class="ag-ordenes-detalle__campo-valor">{{ $contratosDisponibles[$contratoId] ?? "#{$contratoId}" }}</p>
                <input type="hidden" name="contrato_id" value="{{ $contratoId }}" data-ag-orden-contrato-fijo>
            </div>
        @else
            {{-- En alta, contrato es editable (select buscable) --}}
            <x-atoms.select
                name="contrato_id"
                id="contrato_id"
                :label="__('operaciones.ordenes.campo_contrato')"
                :options="$contratosDisponibles"
                :value="$contratoId"
                :placeholder="__('operaciones.ordenes.campo_contrato_placeholder')"
                :help="__('operaciones.ordenes.campo_contrato_ayuda')"
                searchable
                required
                :error="$errors->first('contrato_id')"
                data-ag-orden-contrato
            />
        @endif

        {{-- Contacto: solo los del cliente del contrato; autoseleccionado si es único --}}
        <div data-ag-contacto-wrap @if ($errors->has('emitida_por_contacto_id')) data-tiene-error @endif>
            <x-atoms.select
                name="emitida_por_contacto_id"
                id="emitida_por_contacto_id"
                :label="__('operaciones.ordenes.campo_contacto')"
                :options="$contactosDelContrato"
                :value="$contactoId"
                :placeholder="__('operaciones.ordenes.campo_contacto_placeholder')"
                :help="__('operaciones.ordenes.campo_contacto_ayuda')"
                :disabled="! $esEdicion && $datosDelContrato === null"
                :error="$errors->first('emitida_por_contacto_id')"
                data-ag-orden-contacto
            />
        </div>

        {{-- Resumen del contrato: ya pintado por el servidor cuando hay contrato
             (siempre en edición); el JS lo repinta al cambiar el contrato. --}}
        <div class="ag-form-section__field--full ag-ordenes-form__resumen-contrato" data-ag-resumen-contrato @if ($datosDelContrato === null) hidden @endif>
            <img
                class="ag-ordenes-form__logo"
                data-ag-cliente-logo
                data-logo-placeholder="{{ asset('images/logo-placeholder.png') }}"
                src="{{ $datosDelContrato['logo_url'] ?? asset('images/logo-placeholder.png') }}"
                alt=""
            >
            <div class="ag-form-section__body ag-ordenes-form__resumen-datos">
                <div class="ag-ordenes-detalle__campo">
                    <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_contrato_cliente') }}</p>
                    <p class="ag-ordenes-detalle__campo-valor" data-ag-cliente-nombre>{{ $datosDelContrato['cliente'] ?? '—' }}</p>
                </div>
                <div class="ag-ordenes-detalle__campo">
                    <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_contrato_propiedades') }}</p>
                    <p class="ag-ordenes-detalle__campo-valor" data-ag-propiedades-nombres>{{ implode(', ', $datosDelContrato['propiedades'] ?? []) ?: '—' }}</p>
                </div>
                <div class="ag-ordenes-detalle__campo">
                    <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_contrato_aplicaciones') }}</p>
                    <p class="ag-ordenes-detalle__campo-valor" data-ag-aplicaciones-previstas>{{ $datosDelContrato['aplicaciones_previstas'] ?? '—' }}</p>
                </div>
                <div class="ag-ordenes-detalle__campo">
                    <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_contrato_hectareas') }}</p>
                    <p class="ag-ordenes-detalle__campo-valor" data-ag-hectareas-contratadas>{{ $datosDelContrato !== null ? $datosDelContrato['hectareas_contratadas'].' ha' : '—' }}</p>
                </div>
                <div class="ag-ordenes-detalle__campo">
                    <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_contrato_fecha_inicio') }}</p>
                    <p class="ag-ordenes-detalle__campo-valor" data-ag-fecha-inicio>{{ $datosDelContrato['fecha_inicio'] ?? '—' }}</p>
                </div>
                <div class="ag-ordenes-detalle__campo">
                    <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_contrato_fecha_fin') }}</p>
                    <p class="ag-ordenes-detalle__campo-valor" data-ag-fecha-fin data-texto-sin-definir="{{ __('operaciones.ordenes.valor_sin_definir') }}">{{ $datosDelContrato !== null ? ($datosDelContrato['fecha_fin'] ?? __('operaciones.ordenes.valor_sin_definir')) : '—' }}</p>
                </div>
            </div>
        </div>
    </x-molecules.form-section>

    {{-- Sección 2: Datos de la orden --}}
    <x-molecules.form-section
        :title="__('operaciones.ordenes.seccion_datos')"
        :count="__('operaciones.ordenes.campos_contador', ['cantidad' => 8 + ($exigeMotivo ? 1 : 0)])"
    >
        {{-- Número de aplicación: solo lectura, lo calcula el servidor (ADR 0022) --}}
        <div class="ag-form-section__field">
            <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_nro_aplicacion') }}</p>
            <p
                class="ag-ordenes-detalle__campo-valor"
                data-ag-nro-aplicacion
                data-texto-plantilla="{{ __('operaciones.ordenes.nro_aplicacion_display', ['nro' => ':nro', 'total' => ':total']) }}"
            >{{ $textoNroAplicacion }}</p>
        </div>

        {{-- Tipo de insumo (filtro de presentación) --}}
        <x-atoms.select
            name="tipo_insumo_filtro"
            id="tipo_insumo_filtro"
            :label="__('operaciones.ordenes.campo_tipo_insumo')"
            :options="$opcionesTipoInsumo"
            :value="$tipoInsumoSeleccionado"
            :placeholder="__('operaciones.ordenes.campo_tipo_insumo_placeholder')"
            required
            :disabled="$insumoBloqueado"
            data-ag-orden-tipo-insumo
        />

        {{-- Categoría de insumo --}}
        <x-atoms.select
            name="categoria_insumo_id"
            id="categoria_insumo_id"
            :label="__('operaciones.ordenes.campo_categoria_insumo')"
            :options="$categoriasInsumoOptions"
            :value="$categoriaInsumoId"
            :placeholder="__('operaciones.ordenes.campo_categoria_insumo_placeholder')"
            required
            :disabled="$insumoBloqueado"
            :error="$errors->first('categoria_insumo_id')"
            data-ag-orden-categoria-insumo
            data-mapa-categoria-insumo-tipo="{{ $mapaCategoriaInsumoTipo->toJson() }}"
        />
        @if ($insumoBloqueado)
            {{-- Un `<select>` deshabilitado no se envía: el valor viaja aparte (y el servidor
                 comprueba que no cambió). --}}
            <input type="hidden" name="categoria_insumo_id" value="{{ $categoriaInsumoId }}">
        @endif

        {{-- Dosis según tipo de insumo --}}
        <div data-ag-orden-campo-tipo="liquido" @if ($tipoInsumoSeleccionado !== 'liquido') hidden @endif>
            <x-atoms.input
                type="number"
                name="litros_ha"
                :label="__('operaciones.ordenes.campo_litros_ha')"
                :value="$valor('litros_ha')"
                min="0.01"
                step="0.01"
                :readonly="$insumoBloqueado"
                :error="$errors->first('litros_ha')"
            />
        </div>

        <div data-ag-orden-campo-tipo="solido" @if ($tipoInsumoSeleccionado !== 'solido') hidden @endif>
            <x-atoms.input
                type="number"
                name="kilos_por_vuelo"
                :label="__('operaciones.ordenes.campo_kilos_por_vuelo')"
                :value="$valor('kilos_por_vuelo')"
                min="0.01"
                step="0.01"
                :readonly="$insumoBloqueado"
                :error="$errors->first('kilos_por_vuelo')"
            />
        </div>

        {{-- Tipo de aplicación --}}
        <x-atoms.select
            name="tipo_aplicacion"
            id="tipo_aplicacion"
            :label="__('operaciones.ordenes.campo_tipo_aplicacion')"
            :options="$opcionesTipoAplicacion"
            :value="$tipoAplicacion"
            required
            :error="$errors->first('tipo_aplicacion')"
        />

        {{-- Cantidad de equipos (la asignación de equipos se abre desde "Relacionado") --}}
        <x-atoms.input
            type="number"
            name="cantidad_equipos_necesarios"
            :label="__('operaciones.ordenes.campo_cantidad_equipos')"
            :value="$cantidadEquiposNecesarios"
            min="1"
            step="1"
            required
            :error="$errors->first('cantidad_equipos_necesarios')"
        />

        {{-- Fecha de emisión --}}
        <x-atoms.date
            name="fecha_emision"
            :label="__('operaciones.ordenes.campo_fecha_emision')"
            :value="$fechaEmision"
            required
            :error="$errors->first('fecha_emision')"
        />

        @if ($insumoBloqueado)
            <div class="ag-form-section__field--full">
                <x-molecules.alert-strip variant="info" icon="lock">
                    {{ __('operaciones.ordenes.insumo_bloqueado_ayuda') }}
                </x-molecules.alert-strip>
            </div>
        @endif

        {{-- Observaciones (ancho completo) --}}
        <div class="ag-form-section__field--full">
            <x-atoms.textarea
                name="observaciones"
                :label="__('operaciones.ordenes.campo_observaciones')"
                :value="$valor('observaciones')"
                :error="$errors->first('observaciones')"
            />
        </div>

        @if ($exigeMotivo)
            {{-- Motivo de la corrección (ancho completo): obligatorio en una orden publicada. --}}
            <div class="ag-form-section__field--full">
                <x-atoms.textarea
                    name="motivo_correccion"
                    :label="__('operaciones.ordenes.campo_motivo_correccion')"
                    :placeholder="__('operaciones.ordenes.campo_motivo_correccion_placeholder')"
                    :value="old('motivo_correccion')"
                    :rows="3"
                    required
                    :error="$errors->first('motivo_correccion')"
                />
            </div>
        @endif
    </x-molecules.form-section>

    {{-- Sección 3: Lotes de la orden — lista de SOLO LECTURA: la orden cubre TODOS los lotes del contrato --}}
    <x-molecules.form-section
        accent="info"
        :title="__('operaciones.ordenes.seccion_lotes')"
        :count="trans_choice('operaciones.ordenes.lotes_contador_form', count($lotes), ['cantidad' => count($lotes)])"
        data-ag-lotes-seccion
        data-texto-lotes-cero="{{ trans_choice('operaciones.ordenes.lotes_contador_form', 0, ['cantidad' => ':cantidad']) }}"
        data-texto-lotes-uno="{{ trans_choice('operaciones.ordenes.lotes_contador_form', 1, ['cantidad' => ':cantidad']) }}"
        data-texto-lotes-varios="{{ trans_choice('operaciones.ordenes.lotes_contador_form', 2, ['cantidad' => ':cantidad']) }}"
    >
        <div class="ag-form-section__field--full">
            <div data-ag-lotes-vacio @if (! empty($lotes)) hidden @endif>
                <x-molecules.empty-state
                    icon="inventory_2"
                    :title="__('operaciones.ordenes.lotes_vacio_titulo')"
                    :detail="__('operaciones.ordenes.lotes_vacio_detalle')"
                />
            </div>

            {{-- Cultivo, etapa y terreno (21/9/2026): lo que dice qué trabajo pide cada
                 lote. Un lote sin cultivo registrado no es un error —es lo normal con
                 el terreno limpio, antes de una aplicación de sólidos—, por eso va en
                 texto apagado y no como aviso. `ordenes-form.js` redibuja estas mismas
                 celdas al cambiar de contrato; los textos le llegan por `data-texto-*`. --}}
            <x-molecules.index-table
                columns="minmax(4rem, 0.6fr) 1fr 1.2fr 1.2fr 9rem"
                data-ag-lotes-tabla
                data-texto-sin-cultivo="{{ __('operaciones.ordenes.lotes_sin_cultivo') }}"
                data-texto-sin-etapa="{{ __('operaciones.ordenes.lotes_sin_etapa') }}"
                data-texto-desnivel="{{ __('operaciones.ordenes.lotes_terreno_desnivel') }}"
                data-texto-limpieza="{{ __('operaciones.ordenes.lotes_terreno_limpieza') }}"
                data-texto-sin-terreno="{{ __('operaciones.ordenes.lotes_sin_terreno') }}"
                :hidden="empty($lotes)"
            >
                <x-slot:head>
                    <span role="columnheader">{{ __('operaciones.ordenes.lotes_columna_codigo') }}</span>
                    <span role="columnheader">{{ __('operaciones.ordenes.lotes_columna_propiedad') }}</span>
                    <span role="columnheader">{{ __('operaciones.ordenes.lotes_columna_cultivo') }}</span>
                    <span role="columnheader">{{ __('operaciones.ordenes.lotes_columna_terreno') }}</span>
                    <span role="columnheader">{{ __('operaciones.ordenes.lotes_columna_hectareas_lote') }}</span>
                </x-slot:head>

                @foreach ($lotes as $indice => $lote)
                    <div class="ag-index-table__row" role="row" @if ($indice >= $lotesPorPagina) hidden @endif>
                        <span role="cell">{{ $lote['codigo'] }}</span>
                        <span role="cell">{{ $lote['propiedad'] }}</span>
                        <span role="cell" class="ag-ordenes__celda-doble">
                            @if (($lote['cultivo'] ?? null) !== null)
                                <span>{{ $lote['cultivo'] }}</span>
                                <span class="ag-ordenes__celda-detalle">{{ $lote['etapa_label'] ?? __('operaciones.ordenes.lotes_sin_etapa') }}</span>
                            @else
                                <span class="ag-ordenes__celda-detalle">{{ __('operaciones.ordenes.lotes_sin_cultivo') }}</span>
                            @endif
                        </span>
                        <span role="cell" class="ag-ordenes__celda-doble">
                            @if (($lote['desnivel_label'] ?? null) === null && ($lote['limpieza_label'] ?? null) === null)
                                <span class="ag-ordenes__celda-detalle">{{ __('operaciones.ordenes.lotes_sin_terreno') }}</span>
                            @else
                                @if (($lote['desnivel_label'] ?? null) !== null)
                                    <span class="ag-ordenes__celda-detalle">{{ __('operaciones.ordenes.lotes_terreno_desnivel', ['valor' => $lote['desnivel_label']]) }}</span>
                                @endif
                                @if (($lote['limpieza_label'] ?? null) !== null)
                                    <span class="ag-ordenes__celda-detalle">{{ __('operaciones.ordenes.lotes_terreno_limpieza', ['valor' => $lote['limpieza_label']]) }}</span>
                                @endif
                            @endif
                        </span>
                        <span role="cell" class="ag-ordenes__mono">{{ number_format((float) $lote['hectareas'], 2, ',', '.') }}</span>
                    </div>
                @endforeach
            </x-molecules.index-table>

            {{-- Paginación de 20 lotes por página (`paginador-cliente.js`, lo maneja
                 ordenes-form.js), igual que la tabla de lotes del contrato. --}}
            <div
                class="ag-paginador"
                data-ag-lotes-paginador
                hidden
                data-label-aria="{{ __('operaciones.ordenes.lotes_paginacion_aria') }}"
                data-label-anterior="{{ __('ui.paginador.anterior') }}"
                data-label-siguiente="{{ __('ui.paginador.siguiente') }}"
                data-label-pagina="{{ __('ui.paginador.pagina') }}"
                data-label-resumen="{{ __('operaciones.ordenes.lotes_paginacion_resumen') }}"
            ></div>
        </div>
    </x-molecules.form-section>

    {{-- Acciones --}}
    <x-organisms.form-actions-bar :status="__('operaciones.ordenes.estado_form')">
        <x-slot:actions>
            <x-molecules.boton-volver :href="route('panel.ordenes.index')" cancelar />
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.form-actions-bar>

    {{-- Resumen relacionado (solo en edición; arquetipo Formulario, §6.3.1 de la guía,
         mismo molde que `clientes/_formulario`): una tarjeta por cada cosa que cuelga
         de la orden —órdenes de trabajo, asignación de equipos, estadías en hacienda—,
         con sus conteos (`summary-card`) o su vacío compacto, y el acceso a su
         pantalla. Lo resuelve `OrdenesController::resumenRelacionado()`. --}}
    @if ($esEdicion && ($resumenRelacionado ?? []) !== [])
        <x-slot:aside>
            @foreach ($resumenRelacionado as $resumen)
                @if ($resumen['tieneDatos'])
                    <x-molecules.summary-card :title="$resumen['titulo']" :items="$resumen['items']">
                        @if ($resumen['mostrarAccion'])
                            <x-slot:action>
                                <x-atoms.button :href="$resumen['accion']['href']" variant="outline" :icon="$resumen['accion']['icono']" block>
                                    {{ $resumen['accion']['label'] }}
                                </x-atoms.button>
                            </x-slot:action>
                        @endif
                    </x-molecules.summary-card>
                @else
                    <x-molecules.empty-state :icon="$resumen['icono']" :title="$resumen['vacioTitulo']" :detail="$resumen['vacioDetalle']">
                        @if ($resumen['mostrarAccion'])
                            <x-slot:action>
                                <x-atoms.button :href="$resumen['accion']['href']" variant="outline" :icon="$resumen['accion']['icono']">
                                    {{ $resumen['accion']['label'] }}
                                </x-atoms.button>
                            </x-slot:action>
                        @endif
                    </x-molecules.empty-state>
                @endif
            @endforeach
        </x-slot:aside>
    @endif
    </x-molecules.form-layout>
</form>
