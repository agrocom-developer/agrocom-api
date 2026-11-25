{{--
    Partial: formulario de alta a mano de un proveedor (independiente o tercero).
    Arquetipo Formulario, §6.3 de docs/diseno/guia_pantalla_panel.md. Solo alta.

    Espera: $tipos (list<TipoProveedor>) y $zonas (array<int, string>, id =>
    nombre). Los datos de la persona y de la licencia los pide el mismo registro
    que la app; la licencia es obligatoria para un independiente (es su propio
    piloto) y se ignora en un tercero. Tras un error de validación, `old()`
    repuebla los campos; `password` nunca.
--}}
@php
    $opcionesTipo = collect($tipos)->mapWithKeys(fn ($tipo) => [$tipo->value => __('proveedores.tipo.'.$tipo->value)])->all();
@endphp

<form method="POST" action="{{ route('panel.proveedores.store') }}" class="ag-proveedores" novalidate>
    @csrf

    <x-organisms.page-header
        :title="__('proveedores.alta_manual.proveedor_titulo')"
        :subtitle="__('proveedores.alta_manual.proveedor_subtitulo')"
    >
        <x-slot:actions>
            <x-molecules.boton-volver :href="route('panel.proveedores.index')" :label="__('proveedores.proveedores.volver')" />
        </x-slot:actions>
    </x-organisms.page-header>

    @if (session('estado'))
        <x-molecules.alert-strip variant="success" icon="check_circle">
            {{ session('estado') }}
        </x-molecules.alert-strip>
    @endif

    @if ($errors->has('estado'))
        <x-molecules.alert-strip variant="danger" icon="error">
            {{ $errors->first('estado') }}
        </x-molecules.alert-strip>
    @endif

    <x-molecules.form-layout>
        <x-molecules.form-section
            accent="primary-2"
            :title="__('proveedores.alta_manual.seccion_proveedor')"
            :count="__('proveedores.campos_contador', ['cantidad' => 6])"
        >
            <x-atoms.radio-group
                name="tipo"
                :label="__('proveedores.alta_manual.campo_tipo')"
                :options="$opcionesTipo"
                :value="old('tipo', 'independiente')"
                :help="__('proveedores.alta_manual.tipo_ayuda')"
                :error="$errors->first('tipo')"
                class="ag-form-section__field--full"
                required
            />
            <x-atoms.input type="text" name="razon_social" :label="__('proveedores.alta_manual.campo_razon_social')" :value="old('razon_social', '')" :error="$errors->first('razon_social')" maxlength="150" required autocomplete="off" />
            <x-atoms.input type="text" name="documento_fiscal" :label="__('proveedores.alta_manual.campo_documento_fiscal')" :value="old('documento_fiscal', '')" :error="$errors->first('documento_fiscal')" maxlength="30" required autocomplete="off" />
            <x-atoms.input type="tel" name="telefono" :label="__('proveedores.alta_manual.campo_telefono')" :value="old('telefono', '')" :error="$errors->first('telefono')" maxlength="30" required autocomplete="off" />
            <x-atoms.select
                name="zona_base_id"
                id="zona-base"
                :label="__('proveedores.alta_manual.campo_zona')"
                :options="$zonas"
                :value="old('zona_base_id')"
                :placeholder="__('proveedores.alta_manual.campo_zona_placeholder')"
                :error="$errors->first('zona_base_id')"
            />
            <x-atoms.input type="text" name="radio_operacion_km" :label="__('proveedores.alta_manual.campo_radio')" :value="old('radio_operacion_km', '')" :help="__('proveedores.alta_manual.campo_radio_ayuda')" :error="$errors->first('radio_operacion_km')" inputmode="decimal" autocomplete="off" />
        </x-molecules.form-section>

        <x-molecules.form-section
            accent="distintivo-1"
            :title="__('proveedores.alta_manual.seccion_persona')"
            :count="__('proveedores.campos_contador', ['cantidad' => 4])"
        >
            <x-atoms.input type="text" name="nombres" :label="__('proveedores.alta_manual.campo_nombres')" :value="old('nombres', '')" :error="$errors->first('nombres')" maxlength="80" required autocomplete="off" />
            <x-atoms.input type="text" name="apellidos" :label="__('proveedores.alta_manual.campo_apellidos')" :value="old('apellidos', '')" :error="$errors->first('apellidos')" maxlength="80" required autocomplete="off" />
            <x-atoms.input type="text" name="documento_identidad" :label="__('proveedores.alta_manual.campo_documento_identidad')" :value="old('documento_identidad', '')" :error="$errors->first('documento_identidad')" maxlength="30" required autocomplete="off" />
            <x-atoms.date name="fecha_nacimiento" :label="__('proveedores.alta_manual.campo_fecha_nacimiento')" :value="old('fecha_nacimiento')" :help="__('proveedores.alta_manual.campo_fecha_nacimiento_ayuda')" :error="$errors->first('fecha_nacimiento')" required />
        </x-molecules.form-section>

        <x-molecules.form-section
            accent="warning"
            :title="__('proveedores.alta_manual.seccion_licencia')"
            :count="__('proveedores.campos_contador', ['cantidad' => 3])"
        >
            <p class="ag-form-section__field--full ag-proveedores__ayuda">{{ __('proveedores.alta_manual.licencia_ayuda') }}</p>
            <x-atoms.input type="text" name="licencia_numero" :label="__('proveedores.alta_manual.campo_licencia_numero')" :value="old('licencia_numero', '')" :error="$errors->first('licencia_numero')" maxlength="40" autocomplete="off" />
            <x-atoms.date name="licencia_vence" :label="__('proveedores.alta_manual.campo_licencia_vence')" :value="old('licencia_vence')" :error="$errors->first('licencia_vence')" />
            <x-atoms.input type="text" name="categoria_habilitacion" :label="__('proveedores.alta_manual.campo_categoria')" :value="old('categoria_habilitacion', '')" :error="$errors->first('categoria_habilitacion')" maxlength="40" autocomplete="off" />
        </x-molecules.form-section>

        <x-molecules.form-section
            accent="info"
            :title="__('proveedores.alta_manual.seccion_cuenta')"
            :count="__('proveedores.campos_contador', ['cantidad' => 4])"
        >
            <x-atoms.input type="text" name="nombre" :label="__('proveedores.alta_manual.campo_nombre')" :value="old('nombre', '')" :error="$errors->first('nombre')" maxlength="150" required autocomplete="off" />
            <x-atoms.input type="text" name="username" :label="__('proveedores.alta_manual.campo_usuario')" :value="old('username', '')" :help="__('proveedores.alta_manual.campo_usuario_ayuda')" :error="$errors->first('username')" maxlength="50" required autocomplete="off" />
            <x-atoms.input type="email" name="email" :label="__('proveedores.alta_manual.campo_correo')" :value="old('email', '')" :error="$errors->first('email')" maxlength="150" autocomplete="off" />
            <x-atoms.input type="password" name="password" :label="__('proveedores.alta_manual.campo_password')" :help="__('proveedores.alta_manual.campo_password_ayuda')" :error="$errors->first('password')" required autocomplete="new-password" />
        </x-molecules.form-section>

        <x-organisms.form-actions-bar :status="__('proveedores.alta_manual.estado_form')">
            <x-slot:actions>
                <x-molecules.boton-volver :href="route('panel.proveedores.index')" cancelar />
                <x-atoms.button type="submit" variant="primary">{{ __('proveedores.alta_manual.guardar') }}</x-atoms.button>
            </x-slot:actions>
        </x-organisms.form-actions-bar>
    </x-molecules.form-layout>
</form>
