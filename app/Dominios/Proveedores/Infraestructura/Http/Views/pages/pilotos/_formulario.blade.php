{{--
    Partial: formulario de alta a mano de un piloto de un proveedor tercero.
    Arquetipo Formulario, §6.3 de docs/diseno/guia_pantalla_panel.md. Solo alta.

    Espera: $proveedores (array<int, string>, id => razón social, solo terceros
    que admiten altas) y $proveedorElegido (?int). Sin proveedores terceros no
    hay a quién sumarle un piloto: se muestra el estado vacío con la salida.
--}}
<form method="POST" action="{{ route('panel.pilotos.store') }}" class="ag-proveedores" novalidate>
    @csrf

    <x-organisms.page-header
        :title="__('proveedores.alta_manual.piloto_titulo')"
        :subtitle="__('proveedores.alta_manual.piloto_subtitulo')"
    >
        <x-slot:actions>
            <x-molecules.boton-volver :href="route('panel.pilotos.index')" :label="__('proveedores.pilotos.volver')" />
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

    @if ($proveedores === [])
        <x-molecules.empty-state
            icon="local_shipping"
            :title="__('proveedores.alta_manual.sin_proveedores_terceros')"
            :detail="__('proveedores.alta_manual.sin_proveedores_terceros_detalle')"
        />
    @else
        <x-molecules.form-layout>
            <x-molecules.form-section
                accent="primary-2"
                :title="__('proveedores.alta_manual.seccion_piloto')"
                :count="__('proveedores.campos_contador', ['cantidad' => 4])"
            >
                <x-atoms.select
                    name="proveedor_id"
                    id="piloto-proveedor"
                    :label="__('proveedores.alta_manual.campo_proveedor')"
                    :options="$proveedores"
                    :value="old('proveedor_id', $proveedorElegido)"
                    :placeholder="__('proveedores.alta_manual.campo_proveedor_placeholder')"
                    :error="$errors->first('proveedor_id')"
                    class="ag-form-section__field--full"
                    required
                />
                <x-atoms.input type="text" name="licencia_numero" :label="__('proveedores.alta_manual.campo_licencia_numero')" :value="old('licencia_numero', '')" :error="$errors->first('licencia_numero')" maxlength="40" required autocomplete="off" />
                <x-atoms.date name="licencia_vence" :label="__('proveedores.alta_manual.campo_licencia_vence')" :value="old('licencia_vence')" :error="$errors->first('licencia_vence')" required />
                <x-atoms.input type="text" name="categoria_habilitacion" :label="__('proveedores.alta_manual.campo_categoria')" :value="old('categoria_habilitacion', '')" :error="$errors->first('categoria_habilitacion')" maxlength="40" required autocomplete="off" />
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
                    <x-molecules.boton-volver :href="route('panel.pilotos.index')" cancelar />
                    <x-atoms.button type="submit" variant="primary">{{ __('proveedores.alta_manual.guardar') }}</x-atoms.button>
                </x-slot:actions>
            </x-organisms.form-actions-bar>
        </x-molecules.form-layout>
    @endif
</form>
