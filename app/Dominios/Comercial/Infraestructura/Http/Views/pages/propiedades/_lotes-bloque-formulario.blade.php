{{--
    Partial: formulario de lotes en bloque, compartido por
    `lotes-generar.blade.php` (modo "crear") y `lotes-editar-bloque.blade.php`
    (modo "editar") — mismo patrón que `_formulario` de propiedades: las dos
    páginas arman el MISMO formulario; cambia a qué URL postea, con qué
    valores arranca y qué avisos muestra.

    Espera:
    - $modo ('crear'|'editar').
    - $propiedad (Propiedad, con `cliente` cargado).
    - $valores (array{prefijo: string, cantidad: int, hectareas: string,
      terreno: array}): con lo que arranca el formulario; `old()` lo pisa
      tras un error de validación. `terreno` trae `desnivel`, `limpieza` y
      `restricciones`, como los guarda el lote.
    - $lotesBase (int): lotes que se suman a `cantidad` para el total sobre el
      que se reparte la superficie — en "crear" los que la propiedad ya tiene;
      en "editar", 0 (allí `cantidad` ya es el total).
    - $cantidadMin, $cantidadMax (int).
    - Solo "editar": $codigos (list<string>, en orden de alta: los últimos son
      los que se quitan al bajar la cantidad) y $variables (list<string>,
      atributos que no son iguales en todos los lotes).

    Las hectáreas por lote llevan una ayuda en vivo, mismo patrón que
    "Adelanto Solicitado" de contratos (`data-plantilla-ayuda*` en el propio
    input, `lotes-generar.js` la recalcula al escribir): en el acento de marca
    propone la superficie de la propiedad repartida entre el total de lotes, y
    pasa al color de alerta si lo escrito, por el total de lotes, supera esa
    superficie. Solo informa, no bloquea el guardado. Sin superficie cargada
    avisa que hace falta. Al bajar la cantidad en "editar", el mismo script
    muestra qué lotes se quitan y pide confirmación en el modal del panel antes
    de enviar.
--}}
@php
    $esEdicion = $modo === 'editar';
    $variables = $variables ?? [];
    $codigos = $codigos ?? [];
    $hectareasVarian = in_array('hectareas', $variables, true);
    $terrenoVariable = array_values(array_intersect($variables, ['desnivel', 'limpieza', 'restricciones']));
    $superficie = $propiedad->hectareas;
    $formularioId = 'lotes-bloque-form';
    $modalQuitarId = 'lotes-bloque-confirmar-quitar';
@endphp

@if ($esEdicion && $hectareasVarian)
    <x-molecules.alert-strip variant="warning" icon="info">
        {{ __('comercial.propiedades.lotes_bloque_aviso_hectareas') }}
    </x-molecules.alert-strip>
@endif

@if ($esEdicion && $terrenoVariable !== [])
    <x-molecules.alert-strip variant="warning" icon="info">
        {{ __('comercial.propiedades.lotes_bloque_aviso_terreno', ['atributos' => collect($terrenoVariable)->map(fn ($atributo) => __("comercial.propiedades.lotes_bloque_atributo_{$atributo}"))->implode(', ')]) }}
    </x-molecules.alert-strip>
@endif

<form
    method="POST"
    action="{{ $esEdicion ? route('panel.propiedades.lotes.bloque.guardar', $propiedad) : route('panel.propiedades.lotes.generar.guardar', $propiedad) }}"
    id="{{ $formularioId }}"
    class="ag-lotes-generar-form"
    novalidate
    data-ag-lotes-generar-form
    data-ag-lotes-base="{{ $lotesBase }}"
    @if ($superficie !== null)
        data-ag-lotes-superficie="{{ $superficie }}"
    @endif
    @if ($esEdicion)
        data-ag-lotes-codigos="{{ json_encode($codigos) }}"
        data-ag-lotes-modal-quitar="{{ $modalQuitarId }}"
        data-ag-lotes-quitar-varios="{{ __('comercial.propiedades.lotes_bloque_quitar_varios') }}"
        data-ag-lotes-quitar-uno="{{ __('comercial.propiedades.lotes_bloque_quitar_uno') }}"
        data-ag-lotes-quitar-mas="{{ __('comercial.propiedades.lotes_bloque_quitar_mas') }}"
    @endif
>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-molecules.form-section
        :title="$esEdicion ? __('comercial.propiedades.lotes_bloque_seccion_destino') : __('comercial.propiedades.lotes_generar_seccion_destino')"
        :count="__('comercial.propiedades.campos_contador', ['cantidad' => 2])"
    >
        <div class="ag-input">
            <span class="ag-input__label">{{ __('comercial.propiedades.lotes_generar_cliente') }}</span>
            <p class="ag-lotes-generar__solo-lectura">{{ $propiedad->cliente->razon_social }}</p>
        </div>

        <div class="ag-input">
            <span class="ag-input__label">{{ __('comercial.propiedades.lotes_generar_propiedad') }}</span>
            <p class="ag-lotes-generar__solo-lectura">{{ $propiedad->nombre }}</p>
        </div>
    </x-molecules.form-section>

    <x-molecules.form-section
        :title="__('comercial.propiedades.lotes_generar_seccion_cuantos')"
        :count="__('comercial.propiedades.campos_contador', ['cantidad' => 3])"
    >
        <x-atoms.input
            type="text"
            name="prefijo"
            :label="__('comercial.propiedades.lotes_generar_prefijo')"
            :help="$esEdicion ? __('comercial.propiedades.lotes_bloque_prefijo_ayuda') : __('comercial.propiedades.lotes_generar_prefijo_ayuda')"
            :value="old('prefijo', $valores['prefijo'])"
            :required="! $esEdicion"
            :error="$errors->first('prefijo')"
        />

        <x-atoms.input
            type="number"
            name="cantidad"
            :label="__('comercial.propiedades.lotes_generar_cantidad')"
            :help="$esEdicion ? __('comercial.propiedades.lotes_bloque_cantidad_ayuda') : null"
            :value="old('cantidad', $valores['cantidad'])"
            :min="$cantidadMin"
            :max="$cantidadMax"
            required
            :error="$errors->first('cantidad')"
            data-ag-lotes-cantidad
        />

        <x-atoms.input
            type="number"
            name="hectareas"
            :label="__('comercial.propiedades.lotes_bloque_hectareas')"
            :placeholder="$hectareasVarian ? __('comercial.propiedades.lotes_bloque_hectareas_varian') : null"
            :help="$superficie === null ? __('comercial.propiedades.lotes_bloque_hectareas_sin_superficie') : __('comercial.propiedades.lotes_bloque_hectareas_ayuda')"
            help-tone="accent"
            data-plantilla-ayuda="{{ __('comercial.propiedades.lotes_bloque_hectareas_sugerido') }}"
            data-plantilla-ayuda-uno="{{ __('comercial.propiedades.lotes_bloque_hectareas_sugerido_uno') }}"
            data-plantilla-ayuda-exceso="{{ __('comercial.propiedades.lotes_bloque_hectareas_exceso') }}"
            data-plantilla-ayuda-exceso-uno="{{ __('comercial.propiedades.lotes_bloque_hectareas_exceso_uno') }}"
            :value="old('hectareas', $valores['hectareas'])"
            min="0.01"
            step="0.01"
            :required="! $esEdicion"
            :error="$errors->first('hectareas')"
            data-ag-lotes-hectareas
        />

        @if ($esEdicion)
            <x-molecules.alert-strip
                variant="warning"
                icon="delete"
                class="ag-form-section__field--full"
                hidden
                data-ag-lotes-quitar-aviso
            >
                <span data-ag-lotes-quitar-texto></span>
            </x-molecules.alert-strip>
        @endif
    </x-molecules.form-section>

    <x-molecules.form-section
        :title="__('comercial.propiedades.lotes_generar_seccion_terreno')"
        :count="__('comercial.propiedades.campos_contador', ['cantidad' => 3])"
    >
        <p class="ag-form-section__field--full ag-lotes-generar__ayuda-terreno">
            {{ $esEdicion ? __('comercial.propiedades.lotes_bloque_terreno_ayuda') : __('comercial.propiedades.lotes_generar_terreno_ayuda') }}
        </p>

        @include('comercial::pages.lotes._lote-terreno', [
            'lote' => old('terreno', $valores['terreno'] ?? []),
            'prefijo' => 'terreno',
            'idBase' => 'terreno',
            'erroresPrefijo' => 'terreno',
        ])
    </x-molecules.form-section>

    <x-organisms.form-actions-bar :status="$esEdicion ? __('comercial.propiedades.lotes_bloque_estado_form') : __('comercial.propiedades.lotes_generar_estado_form')">
        <x-slot:actions>
            <x-atoms.button :href="route('panel.propiedades.edit', $propiedad)" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary" :icon="$esEdicion ? 'save' : 'add'">
                {{ $esEdicion ? __('ui.action.save') : __('comercial.propiedades.lotes_generar_accion') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.form-actions-bar>
</form>

@if ($esEdicion)
    <x-molecules.confirm-modal
        :id="$modalQuitarId"
        :form-id="$formularioId"
        :title="__('comercial.propiedades.lotes_bloque_confirmar_titulo')"
        :message="__('comercial.propiedades.lotes_bloque_confirmar_mensaje')"
        :confirm-label="__('comercial.propiedades.lotes_bloque_confirmar_accion')"
        tone="danger"
    />
@endif
