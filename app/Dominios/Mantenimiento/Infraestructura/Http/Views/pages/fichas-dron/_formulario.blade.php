{{--
    Partial: formulario de ficha de inventario de dron, compartido por
    create.blade.php y edit.blade.php (HU-82, tarea 97) — arquetipo
    Formulario, §6.3 de docs/diseno/guia_pantalla_panel.md. Mismo patrón que
    `baterias/_formulario.blade.php`, sin sub-entidad ni select de base: solo
    campos de texto planos más los tres accesorios como checkboxes.

    Espera:
    - $ficha (FichaDron|null): null en alta; el modelo en edición.

    `identificador_dron` no es un select: viaja como texto libre y el
    Request lo valida contra `ope_drones` (`Rule::exists()`) — mismo
    criterio que `equipo_id`/`equipo_tipo` en
    `ordenes/_formulario.blade.php` para el resto de las FK cross-módulo,
    solo que acá no hay catálogo cargado en el propio formulario.

    Tras un error de validación, `old()` pisa los valores del modelo/vacíos
    — mismo criterio en alta y en edición.
--}}
@php
    $esEdicion = $ficha !== null;
    $accion = $esEdicion ? route('panel.fichas-dron.update', $ficha) : route('panel.fichas-dron.store');
    $identificadorDron = old('identificador_dron', $ficha?->identificador_dron ?? '');
    $numeroSerie = old('numero_serie', $ficha?->numero_serie ?? '');
    $chasis = old('chasis', $ficha?->chasis ?? '');
    $versionSoftware = old('version_software', $ficha?->version_software ?? '');
    $region = old('region', $ficha?->region ?? '');
    $serieControl = old('serie_control', $ficha?->serie_control ?? '');
    $tieneCargadorControl = (bool) old('tiene_cargador_control', $ficha?->tiene_cargador_control ?? false);
    $tieneModem = (bool) old('tiene_modem', $ficha?->tiene_modem ?? false);
    $tieneMaletin = (bool) old('tiene_maletin', $ficha?->tiene_maletin ?? false);
@endphp

<form method="POST" action="{{ $accion }}" class="ag-fichas-dron-form" novalidate data-ag-fichas-dron-form>
    @csrf
    @if ($esEdicion)
        @method('PUT')
    @endif

    <x-organisms.page-header
        :title="$esEdicion ? __('mantenimiento.fichas_dron.titulo_editar') : __('mantenimiento.fichas_dron.titulo_crear')"
        :subtitle="__('mantenimiento.fichas_dron.subtitulo_form')"
    >
        <x-slot:actions>
            <x-atoms.button :href="route('panel.fichas-dron.index')" variant="outline" icon="arrow_back">
                {{ __('mantenimiento.fichas_dron.volver') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.page-header>

    @if (session('estado'))
        <x-molecules.alert-strip variant="success" icon="check_circle">
            {{ session('estado') }}
        </x-molecules.alert-strip>
    @endif

    <x-molecules.form-section
        :title="__('mantenimiento.fichas_dron.seccion_datos')"
        :count="__('mantenimiento.fichas_dron.campos_contador', ['cantidad' => 6])"
    >
        <x-atoms.input
            type="text"
            name="identificador_dron"
            :label="__('mantenimiento.fichas_dron.campo_identificador')"
            :value="$identificadorDron"
            :help="__('mantenimiento.fichas_dron.campo_identificador_ayuda')"
            required
            :error="$errors->first('identificador_dron')"
        />

        <x-atoms.input
            type="text"
            name="numero_serie"
            :label="__('mantenimiento.fichas_dron.campo_numero_serie')"
            :value="$numeroSerie"
            :error="$errors->first('numero_serie')"
        />

        <x-atoms.input
            type="text"
            name="chasis"
            :label="__('mantenimiento.fichas_dron.campo_chasis')"
            :value="$chasis"
            :error="$errors->first('chasis')"
        />

        <x-atoms.input
            type="text"
            name="version_software"
            :label="__('mantenimiento.fichas_dron.campo_version_software')"
            :value="$versionSoftware"
            :error="$errors->first('version_software')"
        />

        <x-atoms.input
            type="text"
            name="region"
            :label="__('mantenimiento.fichas_dron.campo_region')"
            :value="$region"
            :error="$errors->first('region')"
        />

        <x-atoms.input
            type="text"
            name="serie_control"
            :label="__('mantenimiento.fichas_dron.campo_serie_control')"
            :value="$serieControl"
            :error="$errors->first('serie_control')"
        />
    </x-molecules.form-section>

    <x-molecules.form-section :title="__('mantenimiento.fichas_dron.seccion_accesorios')">
        <input type="hidden" name="tiene_cargador_control" value="0">
        <x-atoms.checkbox
            name="tiene_cargador_control"
            value="1"
            :label="__('mantenimiento.fichas_dron.campo_tiene_cargador_control')"
            :checked="$tieneCargadorControl"
        />

        <input type="hidden" name="tiene_modem" value="0">
        <x-atoms.checkbox
            name="tiene_modem"
            value="1"
            :label="__('mantenimiento.fichas_dron.campo_tiene_modem')"
            :checked="$tieneModem"
        />

        <input type="hidden" name="tiene_maletin" value="0">
        <x-atoms.checkbox
            name="tiene_maletin"
            value="1"
            :label="__('mantenimiento.fichas_dron.campo_tiene_maletin')"
            :checked="$tieneMaletin"
        />
    </x-molecules.form-section>

    <x-organisms.form-actions-bar :status="__('mantenimiento.fichas_dron.estado_form')">
        <x-slot:actions>
            <x-atoms.button :href="route('panel.fichas-dron.index')" variant="outline">
                {{ __('ui.action.cancel') }}
            </x-atoms.button>
            <x-atoms.button type="submit" variant="primary">
                {{ __('ui.action.save') }}
            </x-atoms.button>
        </x-slot:actions>
    </x-organisms.form-actions-bar>
</form>
