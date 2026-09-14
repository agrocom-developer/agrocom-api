{{--
    Partial: formulario de orden de aplicación, compartido por
    create.blade.php y edit.blade.php (HU-25, tarea 38) — arquetipo
    Formulario, §6.3 de docs/diseno/guia_pantalla_panel.md. Mismo patrón que
    `contratos/_formulario.blade.php`, sin sub-entidad repetible.

    Espera:
    - $orden (OrdenAplicacion|null): null en alta; el modelo en edición.
    - $lotesOrden (Collection<int, OrdenLote>|null, SOLO en edición): los
      lotes ya cargados de la orden — en alta ni siquiera existe la
      variable (`edit.blade.php` la pasa, `create.blade.php` no), por eso el
      valor por defecto de abajo usa `?? null`.
    - $contratosDisponibles / $lotesDisponibles / $contactosDisponibles
      (Collection<int, string>): id => etiqueta ya resuelta por el
      controlador (ver OrdenesController) — la vista no conoce los modelos
      de Comercial (ADR 0003 regla 3). `$lotesDisponibles` es el universo
      completo de lotes (HU-92, tarea 107): cada fila de la sección "Lotes"
      elige el suyo ahí, independiente de las demás filas.
    - $categoriasInsumoDisponibles (Collection<int, CategoriaInsumo>, HU-79,
      tarea 110): a diferencia de las anteriores, acá SÍ llega la colección
      de modelos (Operaciones es dueño de `ope_categorias_insumo`, ADR 0003
      regla 3 solo exige lectura directa cruzando módulos) — la vista arma
      el `<select>` y el mapa id→tipo_insumo para que
      `resources/js/pages/ordenes-form.js` muestre "Litros por hectárea" o
      "Kilos por vuelo" según la categoría elegida, mismo patrón que
      cliente→propiedad de `campos/_formulario.blade.php`.
    - $mapaContratoCliente (array<int, int>, contrato_id => cliente_id) /
      $mapaLoteCliente (array<int, int>, lote_id => cliente_id): consistencia
      de negocio (no se arma un contrato del cliente A con un lote del
      cliente B) — el contrato es el QUIÉN, la orden es el CÓMO. JS filtra el
      `<select>` de cada fila de lote al cliente del contrato elegido; el
      server la exige igual en `withValidator()` — el filtro es presentación,
      no la única guarda.

    `estado` NUNCA es un campo de este formulario: lo fija la máquina de
    estados al crear, y lo cambia `panel.ordenes.activar` (otra pantalla,
    otra responsabilidad — invariante 7). En edición, el formulario solo se
    ofrece con sentido para una orden `emitida` (ver docblock de
    `Aplicacion/ActualizarOrden`) — el link para llegar acá ya queda oculto
    para cualquier otro estado en `ordenes/index.blade.php`; si de todos
    modos se llega con una orden no editable, el submit vuelve con el error
    de dominio en `withErrors(['estado' => ...])`, nunca aplica el cambio.

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición.

    El aside pegajoso del arquetipo se omite a propósito, mismo criterio que
    drones/campos: ningún dato de solo lectura justifica hoy la columna
    lateral.
--}}
@php
    $esEdicion = $orden !== null;
    $accion = $esEdicion ? route('panel.ordenes.update', $orden) : route('panel.ordenes.store');
    $valor = fn (string $campo, mixed $porDefecto = '') => old($campo, $orden?->{$campo} ?? $porDefecto);
    $contratoId = old('contrato_id', $orden?->contrato_id ?? '');
    $contactoId = old('emitida_por_contacto_id', $orden?->emitida_por_contacto_id ?? '');
    $fechaEmision = old('fecha_emision', $orden?->fecha_emision?->toDateString() ?? '');
    $tipoAplicacion = old('tipo_aplicacion', $orden?->tipo_aplicacion?->value ?? \App\Dominios\Operaciones\Dominio\TipoAplicacion::Desarrollo->value);
    $cantidadEquiposNecesarios = old('cantidad_equipos_necesarios', $orden?->cantidad_equipos_necesarios ?? 1);
    $opcionesTipoAplicacion = collect(\App\Dominios\Operaciones\Dominio\TipoAplicacion::cases())
        ->mapWithKeys(fn ($caso) => [$caso->value => __('operaciones.tipo_aplicacion.'.$caso->value)]);
    $categoriaInsumoId = old('categoria_insumo_id', $orden?->categoria_insumo_id ?? '');
    $categoriasInsumoOptions = $categoriasInsumoDisponibles->mapWithKeys(fn ($categoria) => [
        $categoria->id => $categoria->nombre,
    ]);
    $mapaCategoriaInsumoTipo = $categoriasInsumoDisponibles->mapWithKeys(fn ($categoria) => [$categoria->id => $categoria->tipo_insumo->value]);
    // "Tipo" (Sólido/Líquido) es un select de PRESENTACIÓN (sin `name`
    // validado por el server): solo filtra "Categoría de insumo" — la
    // categoría elegida es la única fuente de verdad de qué tipo es la orden
    // (ver docblock de `create_ope_categorias_insumo_table`, no se repite acá).
    $tipoInsumoSeleccionado = old('tipo_insumo_filtro', $mapaCategoriaInsumoTipo->get((int) $categoriaInsumoId) ?? '');
    $opcionesTipoInsumo = collect(\App\Dominios\Operaciones\Dominio\TipoInsumo::cases())
        ->mapWithKeys(fn ($caso) => [$caso->value => __('operaciones.tipo_insumo.'.$caso->value)]);
    $lotesPorDefecto = ($lotesOrden ?? null) !== null
        ? $lotesOrden->map(fn ($lote) => ['lote_id' => $lote->lote_id, 'hectareas_solicitadas' => $lote->hectareas_solicitadas])->all()
        : [[]];
    $lotesIniciales = old('lotes', $lotesPorDefecto);
@endphp

<form method="POST" action="{{ $accion }}" class="ag-ordenes-form" novalidate data-ag-ordenes-form>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('operaciones.ordenes.titulo_editar') : __('operaciones.ordenes.titulo_crear')"
        :subtitle="__('operaciones.ordenes.subtitulo_form')"
    >
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.ordenes.index') }}" variant="outline" icon="arrow_back">
                {{ __('operaciones.ordenes.volver') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.page-header>

    <x-molecules.form-section
        :title="__('operaciones.ordenes.seccion_datos')"
        :count="__('operaciones.ordenes.campos_contador', ['cantidad' => 11])"
    >
        <x-atoms.select
            name="contrato_id"
            id="contrato_id"
            label="{{ __('operaciones.ordenes.campo_contrato') }}"
            :options="$contratosDisponibles"
            :value="$contratoId"
            :placeholder="__('operaciones.ordenes.campo_contrato_placeholder')"
            required
            :error="$errors->first('contrato_id')"
            data-ag-orden-contrato
            data-mapa-contrato-cliente="{{ json_encode($mapaContratoCliente) }}"
        />

        <x-atoms.input
            type="number"
            name="nro_aplicacion"
            label="{{ __('operaciones.ordenes.campo_nro_aplicacion') }}"
            value="{{ $valor('nro_aplicacion') }}"
            min="1"
            step="1"
            required
            error="{{ $errors->first('nro_aplicacion') }}"
        />

        <x-atoms.input
            type="number"
            name="cantidad_equipos_necesarios"
            label="{{ __('operaciones.ordenes.campo_cantidad_equipos') }}"
            value="{{ $cantidadEquiposNecesarios }}"
            min="1"
            step="1"
            required
            error="{{ $errors->first('cantidad_equipos_necesarios') }}"
        />

        <x-atoms.select
            name="tipo_aplicacion"
            id="tipo_aplicacion"
            label="{{ __('operaciones.ordenes.campo_tipo_aplicacion') }}"
            :options="$opcionesTipoAplicacion"
            :value="$tipoAplicacion"
            required
            :error="$errors->first('tipo_aplicacion')"
        />

        {{--
            "Tipo" (Sólido/Líquido) es de PRESENTACIÓN: sin `name` validado
            por el server, solo filtra "Categoría de insumo" de abajo (mismo
            patrón cliente→propiedad de `campos/_formulario.blade.php`) y
            decide qué campo de dosis se ve. La fuente de verdad de qué tipo
            es la orden es SIEMPRE la categoría elegida, nunca este selector.
        --}}
        <x-atoms.select
            name="tipo_insumo_filtro"
            id="tipo_insumo_filtro"
            label="{{ __('operaciones.ordenes.campo_tipo_insumo') }}"
            :options="$opcionesTipoInsumo"
            :value="$tipoInsumoSeleccionado"
            :placeholder="__('operaciones.ordenes.campo_tipo_insumo_placeholder')"
            required
            data-ag-orden-tipo-insumo
        />

        <x-atoms.select
            name="categoria_insumo_id"
            id="categoria_insumo_id"
            label="{{ __('operaciones.ordenes.campo_categoria_insumo') }}"
            :options="$categoriasInsumoOptions"
            :value="$categoriaInsumoId"
            :placeholder="__('operaciones.ordenes.campo_categoria_insumo_placeholder')"
            required
            :error="$errors->first('categoria_insumo_id')"
            data-ag-orden-categoria-insumo
            data-mapa-categoria-insumo-tipo="{{ $mapaCategoriaInsumoTipo->toJson() }}"
        />

        {{--
            Cuál de los dos campos hace falta depende del tipo_insumo de la
            categoría elegida (HU-79, tarea 110) — `ordenes-form.js` oculta
            uno de los dos según `data-ag-orden-campo-tipo`. El render inicial
            ya respeta `$tipoInsumoSeleccionado` (old()/orden existente) para
            que no parpadee el campo equivocado antes de que cargue el JS.
        --}}
        <div data-ag-orden-campo-tipo="liquido" @if ($tipoInsumoSeleccionado !== 'liquido') hidden @endif>
            <x-atoms.input
                type="number"
                name="litros_ha"
                label="{{ __('operaciones.ordenes.campo_litros_ha') }}"
                value="{{ $valor('litros_ha') }}"
                min="0.01"
                step="0.01"
                error="{{ $errors->first('litros_ha') }}"
            />
        </div>

        <div data-ag-orden-campo-tipo="solido" @if ($tipoInsumoSeleccionado !== 'solido') hidden @endif>
            <x-atoms.input
                type="number"
                name="kilos_por_vuelo"
                label="{{ __('operaciones.ordenes.campo_kilos_por_vuelo') }}"
                value="{{ $valor('kilos_por_vuelo') }}"
                min="0.01"
                step="0.01"
                error="{{ $errors->first('kilos_por_vuelo') }}"
            />
        </div>

        <x-atoms.date
            name="fecha_emision"
            label="{{ __('operaciones.ordenes.campo_fecha_emision') }}"
            value="{{ $fechaEmision }}"
            required
            :error="$errors->first('fecha_emision')"
        />

        <x-atoms.select
            name="emitida_por_contacto_id"
            id="emitida_por_contacto_id"
            label="{{ __('operaciones.ordenes.campo_contacto') }}"
            :options="$contactosDisponibles"
            :value="$contactoId"
            :placeholder="__('operaciones.ordenes.campo_contacto_placeholder')"
            :error="$errors->first('emitida_por_contacto_id')"
        />

        <div class="ag-form-section__field--full">
            <x-atoms.input
                type="text"
                name="observaciones"
                label="{{ __('operaciones.ordenes.campo_observaciones') }}"
                value="{{ $valor('observaciones') }}"
                error="{{ $errors->first('observaciones') }}"
            />
        </div>
    </x-molecules.form-section>

    {{--
        Lotes de la orden (HU-92, tarea 107): selección múltiple con
        hectáreas por lote — reemplaza el `<select>` único de `lote_id`.
        Mismo patrón repetible (agregar/quitar, plantilla clonable) que
        `campos/_lote-fila.blade.php`, ver
        `resources/js/pages/ordenes-form.js`.
    --}}
    <x-molecules.form-section :title="__('operaciones.ordenes.seccion_lotes')" class="ag-ordenes-form__lotes-seccion">
        <div
            class="ag-form-section__field--full ag-ordenes-form__lotes"
            data-ag-orden-lotes
            data-mapa-lote-cliente="{{ json_encode($mapaLoteCliente) }}"
        >
            @if ($errors->has('lotes'))
                <p class="ag-input__error" role="alert">{{ $errors->first('lotes') }}</p>
            @endif

            <div data-ag-orden-lotes-lista>
                @foreach ($lotesIniciales as $indice => $lote)
                    @include('operaciones::pages.ordenes._lote-orden-fila', ['indice' => $indice, 'lote' => $lote])
                @endforeach
            </div>

            <x-atoms.button type="button" variant="outline" icon="add" data-ag-orden-lotes-agregar>
                {{ __('operaciones.ordenes.lote_agregar') }}
            </x-atoms.button>

            {{-- Plantilla clonable: el índice literal se reemplaza por el
                 próximo número al clonar (`ordenes-form.js`). Un `<template>`
                 nunca se renderiza ni se envía con el form. --}}
            <template data-ag-orden-lote-template>
                @include('operaciones::pages.ordenes._lote-orden-fila', ['indice' => '__INDICE__', 'lote' => []])
            </template>
        </div>
    </x-molecules.form-section>

    <x-molecules.form-section
        :title="__('operaciones.ordenes.seccion_limites')"
        :count="__('operaciones.ordenes.campos_contador', ['cantidad' => 5])"
    >
        <div class="ag-form-section__field--full ag-ordenes-form__ayuda">
            {{ __('operaciones.ordenes.seccion_limites_ayuda') }}
        </div>

        <x-atoms.input
            type="number"
            name="humedad_min_pct"
            label="{{ __('operaciones.ordenes.campo_humedad_min_pct') }}"
            value="{{ $valor('humedad_min_pct') }}"
            min="0"
            max="100"
            step="0.01"
            error="{{ $errors->first('humedad_min_pct') }}"
        />

        <x-atoms.input
            type="number"
            name="humedad_max_pct"
            label="{{ __('operaciones.ordenes.campo_humedad_max_pct') }}"
            value="{{ $valor('humedad_max_pct') }}"
            min="0"
            max="100"
            step="0.01"
            error="{{ $errors->first('humedad_max_pct') }}"
        />

        <x-atoms.input
            type="number"
            name="viento_max_kmh"
            label="{{ __('operaciones.ordenes.campo_viento_max_kmh') }}"
            value="{{ $valor('viento_max_kmh') }}"
            min="0.01"
            step="0.01"
            error="{{ $errors->first('viento_max_kmh') }}"
        />

        <x-atoms.input
            type="number"
            name="temperatura_max_c"
            label="{{ __('operaciones.ordenes.campo_temperatura_max_c') }}"
            value="{{ $valor('temperatura_max_c') }}"
            step="0.01"
            error="{{ $errors->first('temperatura_max_c') }}"
        />

        <x-atoms.input
            type="number"
            name="velocidad_max_kmh"
            label="{{ __('operaciones.ordenes.campo_velocidad_max_kmh') }}"
            value="{{ $valor('velocidad_max_kmh') }}"
            min="0.01"
            step="0.01"
            error="{{ $errors->first('velocidad_max_kmh') }}"
        />
    </x-molecules.form-section>

    <x-molecules.form-section
        :title="__('operaciones.ordenes.seccion_vuelo')"
        :count="__('operaciones.ordenes.campos_contador', ['cantidad' => 3])"
    >
        <x-atoms.input
            type="number"
            name="altura_vuelo_m"
            label="{{ __('operaciones.ordenes.campo_altura_vuelo_m') }}"
            value="{{ $valor('altura_vuelo_m') }}"
            min="0.01"
            step="0.01"
            error="{{ $errors->first('altura_vuelo_m') }}"
        />

        <x-atoms.input
            type="number"
            name="velocidad_vuelo_kmh"
            label="{{ __('operaciones.ordenes.campo_velocidad_vuelo_kmh') }}"
            value="{{ $valor('velocidad_vuelo_kmh') }}"
            min="0.01"
            step="0.01"
            error="{{ $errors->first('velocidad_vuelo_kmh') }}"
        />

        <x-atoms.input
            type="number"
            name="ancho_pasada_m"
            label="{{ __('operaciones.ordenes.campo_ancho_pasada_m') }}"
            value="{{ $valor('ancho_pasada_m') }}"
            min="0.01"
            step="0.01"
            error="{{ $errors->first('ancho_pasada_m') }}"
        />
    </x-molecules.form-section>

    <x-organisms.form-actions-bar :status="__('operaciones.ordenes.estado_form')">
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.ordenes.index') }}" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.form-actions-bar>
</form>
