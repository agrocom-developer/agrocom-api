{{--
    Partial: formulario de orden de aplicación, compartido por
    create.blade.php y edit.blade.php (reforma Entrega 1, 18/9/2026).
    Arquetipo Formulario, §6.3 de docs/diseno/guia_pantalla_panel.md.

    Espera:
    - $orden (OrdenAplicacion|null): null en alta; el modelo en edición.
    - $contratosDisponibles (Collection<int, string>): id => label.
    - $datosContrato (array<int, array>): cliente, logo_url, propiedades,
      aplicaciones_previstas, hectareas_contratadas, fecha_inicio, fecha_fin,
      contactos, lotes, siguiente_nro, contrato_edit_url.
    - $categoriasInsumoDisponibles (Collection<int, CategoriaInsumo>).
    - $contratoIdPreseleccionado (int|null, SOLO en create()).
    - $resumenRelacionado (list<array>, SOLO en edición): asignación de equipos.

    La orden cubre TODOS los lotes del contrato (no se elige cada uno).
    El número de aplicación lo calcula el servidor (correlativo por contrato).
    En edición, contrato/número/lotes son fijos (no editables).
--}}
@php
    $esEdicion = $orden !== null;
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

    // Contrato elegido (en edición, el de la orden): de acá salen los lotes que la
    // orden cubre y el "Aplicación N de M". El JS los repinta al cambiar el contrato.
    $datosDelContrato = $datosContrato[$contratoId] ?? null;
    $lotes = $datosDelContrato['lotes'] ?? [];
    $nroAplicacion = $esEdicion ? $orden->nro_aplicacion : ($datosDelContrato['siguiente_nro'] ?? null);
    $textoNroAplicacion = $datosDelContrato !== null && $nroAplicacion !== null
        ? __('operaciones.ordenes.nro_aplicacion_display', ['nro' => $nroAplicacion, 'total' => $datosDelContrato['aplicaciones_previstas']])
        : '—';
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
                <input type="hidden" name="contrato_id" value="{{ $contratoId }}">
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

        {{-- Contacto: autoseleccionado si único --}}
        <div data-ag-contacto-wrap @if ($errors->has('emitida_por_contacto_id')) data-tiene-error @endif>
            <x-atoms.select
                name="emitida_por_contacto_id"
                id="emitida_por_contacto_id"
                :label="__('operaciones.ordenes.campo_contacto')"
                :options="$contactosDisponibles"
                :value="$contactoId"
                :placeholder="__('operaciones.ordenes.campo_contacto_placeholder')"
                :error="$errors->first('emitida_por_contacto_id')"
                data-ag-orden-contacto
            />
        </div>

        {{-- Resumen del contrato --}}
        <div class="ag-form-section__field--full ag-ordenes-form__resumen-contrato" data-ag-resumen-contrato hidden>
            <img
                class="ag-ordenes-form__logo"
                data-ag-cliente-logo
                data-logo-placeholder="{{ asset('images/logo-placeholder.png') }}"
                src="{{ asset('images/logo-placeholder.png') }}"
                alt=""
            >
            <div class="ag-form-section__body ag-ordenes-form__resumen-datos">
                <div class="ag-ordenes-detalle__campo">
                    <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_contrato_cliente') }}</p>
                    <p class="ag-ordenes-detalle__campo-valor" data-ag-cliente-nombre>—</p>
                </div>
                <div class="ag-ordenes-detalle__campo">
                    <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_contrato_propiedades') }}</p>
                    <p class="ag-ordenes-detalle__campo-valor" data-ag-propiedades-nombres>—</p>
                </div>
                <div class="ag-ordenes-detalle__campo">
                    <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_contrato_aplicaciones') }}</p>
                    <p class="ag-ordenes-detalle__campo-valor" data-ag-aplicaciones-previstas>—</p>
                </div>
                <div class="ag-ordenes-detalle__campo">
                    <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_contrato_hectareas') }}</p>
                    <p class="ag-ordenes-detalle__campo-valor" data-ag-hectareas-contratadas>—</p>
                </div>
                <div class="ag-ordenes-detalle__campo">
                    <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_contrato_fecha_inicio') }}</p>
                    <p class="ag-ordenes-detalle__campo-valor" data-ag-fecha-inicio>—</p>
                </div>
                <div class="ag-ordenes-detalle__campo">
                    <p class="ag-ordenes-detalle__campo-label">{{ __('operaciones.ordenes.campo_contrato_fecha_fin') }}</p>
                    <p class="ag-ordenes-detalle__campo-valor" data-ag-fecha-fin data-texto-sin-definir="{{ __('operaciones.ordenes.valor_sin_definir') }}">—</p>
                </div>
            </div>
        </div>
    </x-molecules.form-section>

    {{-- Sección 2: Datos de la orden --}}
    <x-molecules.form-section
        :title="__('operaciones.ordenes.seccion_datos')"
        :count="__('operaciones.ordenes.campos_contador', ['cantidad' => 8])"
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
            :error="$errors->first('categoria_insumo_id')"
            data-ag-orden-categoria-insumo
            data-mapa-categoria-insumo-tipo="{{ $mapaCategoriaInsumoTipo->toJson() }}"
        />

        {{-- Dosis según tipo de insumo --}}
        <div data-ag-orden-campo-tipo="liquido" @if ($tipoInsumoSeleccionado !== 'liquido') hidden @endif>
            <x-atoms.input
                type="number"
                name="litros_ha"
                :label="__('operaciones.ordenes.campo_litros_ha')"
                :value="$valor('litros_ha')"
                min="0.01"
                step="0.01"
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

        {{-- Cantidad de equipos con atajo a asignación --}}
        <div class="ag-ordenes-form__input-group">
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
            @if ($esEdicion)
                <x-atoms.button
                    :href="route('panel.asignacion-equipos.show', $orden)"
                    variant="outline"
                    icon="groups"
                    size="sm"
                    class="ag-ordenes-form__input-group-boton"
                >
                    {{ __('operaciones.ordenes.campo_cantidad_equipos_asignar') }}
                </x-atoms.button>
            @endif
        </div>

        {{-- Fecha de emisión --}}
        <x-atoms.date
            name="fecha_emision"
            :label="__('operaciones.ordenes.campo_fecha_emision')"
            :value="$fechaEmision"
            required
            :error="$errors->first('fecha_emision')"
        />

        {{-- Observaciones (ancho completo) --}}
        <div class="ag-form-section__field--full">
            <x-atoms.textarea
                name="observaciones"
                :label="__('operaciones.ordenes.campo_observaciones')"
                :value="$valor('observaciones')"
                :error="$errors->first('observaciones')"
            />
        </div>
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

            <x-molecules.index-table columns="1fr 1fr 9rem" data-ag-lotes-tabla :hidden="empty($lotes)">
                <x-slot:head>
                    <span role="columnheader">{{ __('operaciones.ordenes.lotes_columna_codigo') }}</span>
                    <span role="columnheader">{{ __('operaciones.ordenes.lotes_columna_propiedad') }}</span>
                    <span role="columnheader">{{ __('operaciones.ordenes.lotes_columna_hectareas_lote') }}</span>
                </x-slot:head>

                @foreach ($lotes as $lote)
                    <div class="ag-index-table__row" role="row">
                        <span role="cell">{{ $lote['codigo'] }}</span>
                        <span role="cell">{{ $lote['propiedad'] }}</span>
                        <span role="cell" class="ag-ordenes__mono">{{ number_format((float) $lote['hectareas'], 2, ',', '.') }}</span>
                    </div>
                @endforeach
            </x-molecules.index-table>
        </div>
    </x-molecules.form-section>

    {{-- Acciones --}}
    <x-organisms.form-actions-bar :status="__('operaciones.ordenes.estado_form')">
        <x-slot:actions>
            <x-atoms.button :href="route('panel.ordenes.index')" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.form-actions-bar>

    {{-- Aside con resumen relacionado (solo en edición) --}}
    @if ($esEdicion)
        <x-slot:aside>
            @foreach ($resumenRelacionado ?? [] as $resumen)
                @if ($resumen['tieneDatos'])
                    <x-molecules.summary-card :title="$resumen['titulo']" :items="$resumen['items']">
                        @if ($resumen['mostrarAccion'])
                            <x-slot:action>
                                <x-atoms.button :href="$resumen['accion']['href']" variant="outline" icon="groups" block>
                                    {{ $resumen['accion']['label'] }}
                                </x-atoms.button>
                            </x-slot:action>
                        @endif
                    </x-molecules.summary-card>
                @else
                    <x-molecules.empty-state :icon="$resumen['icono']" :title="$resumen['vacioTitulo']" :detail="$resumen['vacioDetalle']">
                        @if ($resumen['mostrarAccion'])
                            <x-slot:action>
                                <x-atoms.button :href="$resumen['accion']['href']" variant="outline" icon="groups">
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
