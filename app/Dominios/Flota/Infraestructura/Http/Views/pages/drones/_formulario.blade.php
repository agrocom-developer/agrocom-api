{{--
    Partial: formulario de alta a mano del dron de un proveedor. Arquetipo
    Formulario, §6.3 de docs/diseno/guia_pantalla_panel.md. Solo alta.

    Espera: $proveedores (array<int, string>, id => razón social, solo los que
    admiten altas) y $proveedorElegido (?int). Las fotos y los documentos se
    cargan después; sin ellos el dron no se aprueba.
--}}
<form method="POST" action="{{ route('panel.drones-flota.store') }}" class="ag-drones" novalidate>
    @csrf

    <x-organisms.page-header
        :title="__('flota.alta_manual.titulo')"
        :subtitle="__('flota.alta_manual.subtitulo')"
    >
        <x-slot:actions>
            <x-molecules.boton-volver :href="route('panel.drones-flota.index')" :label="__('flota.drones.volver')" />
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
            :title="__('flota.alta_manual.seccion_dron')"
            :count="__('proveedores.campos_contador', ['cantidad' => 7])"
        >
            <x-atoms.select
                name="proveedor_id"
                id="dron-proveedor"
                :label="__('flota.alta_manual.campo_proveedor')"
                :options="$proveedores"
                :value="old('proveedor_id', $proveedorElegido)"
                :placeholder="__('flota.alta_manual.campo_proveedor_placeholder')"
                :error="$errors->first('proveedor_id')"
                class="ag-form-section__field--full"
                required
            />
            <x-atoms.input type="text" name="marca" :label="__('flota.alta_manual.campo_marca')" :value="old('marca', '')" :error="$errors->first('marca')" maxlength="60" required autocomplete="off" />
            <x-atoms.input type="text" name="modelo" :label="__('flota.alta_manual.campo_modelo')" :value="old('modelo', '')" :error="$errors->first('modelo')" maxlength="60" required autocomplete="off" />
            <x-atoms.input type="text" name="numero_serie" :label="__('flota.alta_manual.campo_serie')" :value="old('numero_serie', '')" :help="__('flota.alta_manual.campo_serie_ayuda')" :error="$errors->first('numero_serie')" maxlength="60" required autocomplete="off" />
            <x-atoms.input type="text" name="anio" :label="__('flota.alta_manual.campo_anio')" :value="old('anio', '')" :error="$errors->first('anio')" inputmode="numeric" maxlength="4" autocomplete="off" />
            <x-atoms.input type="text" name="capacidad_tanque_l" :label="__('flota.alta_manual.campo_capacidad')" :value="old('capacidad_tanque_l', '')" :error="$errors->first('capacidad_tanque_l')" inputmode="decimal" required autocomplete="off" />
            <x-atoms.input type="text" name="ancho_pasada_m" :label="__('flota.alta_manual.campo_ancho')" :value="old('ancho_pasada_m', '')" :error="$errors->first('ancho_pasada_m')" inputmode="decimal" required autocomplete="off" />
        </x-molecules.form-section>

        <x-organisms.form-actions-bar :status="__('flota.alta_manual.estado_form')">
            <x-slot:actions>
                <x-molecules.boton-volver :href="route('panel.drones-flota.index')" cancelar />
                <x-atoms.button type="submit" variant="primary">{{ __('flota.alta_manual.guardar') }}</x-atoms.button>
            </x-slot:actions>
        </x-organisms.form-actions-bar>
    </x-molecules.form-layout>
</form>
