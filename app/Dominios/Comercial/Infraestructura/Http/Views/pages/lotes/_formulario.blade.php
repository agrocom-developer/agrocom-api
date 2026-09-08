{{--
    Partial: formulario de lote suelto, compartido por create.blade.php y
    edit.blade.php (tarea 77, HU-54, etapa 2) — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md. Mismo patrón que
    `campos/_formulario.blade.php`: las dos páginas arman el MISMO
    formulario; lo único que cambia es contra qué URL/método postea y los
    valores iniciales.

    Espera:
    - $lote (Lote|null): null en alta; el modelo, con `campo` ya cargada, en
      edición.
    - $clientesDisponibles (Collection<int, string>): id => razón social —
      alimenta el select de cliente, que es solo un FILTRO del select de
      propiedad (no viaja al servidor como columna propia: un lote no tiene
      `cliente_id`, lo hereda de su campo).
    - $propiedadesDisponibles (Collection<int, Campo>): id => Campo (con
      `cliente_id`, `nombre` y `cliente` precargada) — arma las opciones del
      select de propiedad y el mapa propiedad→cliente que filtra en el
      cliente (`resources/js/pages/lotes-form.js`, mismo patrón que
      cliente→campaña en `contratos-form.js`).

    El código de campo/hectáreas/geometría/restricciones reusa el MISMO
    partial `campos/_lote-fila.blade.php` que arma cada fila del array de
    lotes del formulario de propiedad (tarea 77: `$prefijo` generaliza el
    nombre de los campos para que sirva también acá, sin envolver un único
    lote en un array de uno) — un solo lugar donde vive el editor de mapa y
    el resto de los campos, dos formularios que lo incluyen.

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición.
--}}
@php
    $esEdicion = $lote !== null;
    $accion = $esEdicion ? route('panel.lotes.update', $lote) : route('panel.lotes.store');
    $campoId = old('campo_id', $lote?->campo_id ?? '');
    $clienteId = old('cliente_id', $lote?->campo?->cliente_id ?? '');
    $datosLote = [
        'codigo' => old('codigo', $lote?->codigo ?? ''),
        'hectareas' => old('hectareas', $lote?->hectareas ?? ''),
        'geometria' => old('geometria', $lote?->geometria !== null ? json_encode($lote->geometria) : ''),
        'restricciones' => old('restricciones', $lote?->restricciones ?? ''),
    ];
    $mapaClientePropiedad = $propiedadesDisponibles->pluck('cliente_id', 'id');
    $propiedadesOptions = $propiedadesDisponibles->mapWithKeys(fn ($campo) => [
        $campo->id => __('comercial.lotes.campo_propiedad_opcion', ['nombre' => $campo->nombre, 'cliente' => $campo->cliente->razon_social]),
    ]);
@endphp

<form method="POST" action="{{ $accion }}" class="ag-lotes-form" novalidate data-ag-lotes-form>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('comercial.lotes.titulo_editar') : __('comercial.lotes.titulo_crear')"
        :subtitle="__('comercial.lotes.subtitulo_form')"
    >
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.lotes.index') }}" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.page-header>

    <x-molecules.form-section
        :title="__('comercial.lotes.seccion_datos')"
        :count="__('comercial.lotes.campos_contador', ['cantidad' => 2])"
    >
        <x-atoms.select
            name="cliente_id"
            id="cliente_id"
            label="{{ __('comercial.lotes.campo_cliente') }}"
            :options="$clientesDisponibles"
            :value="$clienteId"
            placeholder="{{ __('comercial.lotes.campo_cliente_placeholder') }}"
            help="{{ __('comercial.lotes.campo_cliente_ayuda') }}"
            data-ag-lote-cliente
        />

        <x-atoms.select
            name="campo_id"
            id="campo_id"
            label="{{ __('comercial.lotes.campo_propiedad') }}"
            :options="$propiedadesOptions"
            :value="$campoId"
            placeholder="{{ __('comercial.lotes.campo_propiedad_placeholder') }}"
            required
            error="{{ $errors->first('campo_id') }}"
            data-ag-lote-propiedad
            data-mapa-cliente-propiedad="{{ $mapaClientePropiedad->toJson() }}"
        />
    </x-molecules.form-section>

    <x-molecules.form-section :title="__('comercial.lotes.seccion_lote')">
        <div class="ag-form-section__field--full">
            @include('comercial::pages.campos._lote-fila', ['lote' => $datosLote, 'prefijo' => 'lote', 'mostrarQuitar' => false])
        </div>
    </x-molecules.form-section>

    <x-organisms.form-actions-bar :status="__('comercial.lotes.estado_form')">
        <x-slot:actions>
            <x-atoms.button href="{{ route('panel.lotes.index') }}" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.form-actions-bar>
</form>
