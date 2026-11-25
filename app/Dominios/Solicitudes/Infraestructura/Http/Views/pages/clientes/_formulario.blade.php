{{--
    Partial: formulario de alta a mano de un cliente (persona o empresa con sus
    contactos de referencia). Arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md. Solo alta: el cliente no se edita desde
    el panel.

    Espera: $tipos (list<TipoCliente>) y $filasDeContacto (int, las filas de
    contacto que ofrece el formulario). Tras un error de validación, `old()`
    repuebla los campos; `password` nunca.

    Las filas de contacto son fijas (sin JS): las vacías se descartan al guardar.
    Una empresa necesita al menos una; cuál es la principal se elige con un
    único grupo de opciones.
--}}
@php
    $tipoActual = old('tipo', 'persona');
    $opcionesTipo = collect($tipos)->mapWithKeys(fn ($tipo) => [$tipo->value => __('solicitudes.clientes.tipo.'.$tipo->value)])->all();
    $opcionesPrincipal = collect(range(0, $filasDeContacto - 1))->mapWithKeys(fn ($indice) => [$indice => __('solicitudes.clientes.contacto_n', ['numero' => $indice + 1])])->all();
@endphp

<form method="POST" action="{{ route('panel.clientes.store') }}" class="ag-solicitudes" novalidate>
    @csrf

    <x-organisms.page-header
        :title="__('solicitudes.clientes.registrar_titulo')"
        :subtitle="__('solicitudes.clientes.registrar_subtitulo')"
    >
        <x-slot:actions>
            <x-molecules.boton-volver :href="route('panel.clientes.index')" :label="__('solicitudes.clientes.volver')" />
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
            :title="__('solicitudes.clientes.seccion_cliente')"
            :count="__('solicitudes.panel.campos_contador', ['cantidad' => 4])"
        >
            <x-atoms.radio-group
                name="tipo"
                :label="__('solicitudes.clientes.campo_tipo')"
                :options="$opcionesTipo"
                :value="$tipoActual"
                :error="$errors->first('tipo')"
                class="ag-form-section__field--full"
                required
            />
            <x-atoms.input
                type="text"
                name="razon_social"
                :label="__('solicitudes.clientes.campo_razon_social_form')"
                :value="old('razon_social', '')"
                :help="__('solicitudes.clientes.campo_razon_social_ayuda')"
                :error="$errors->first('razon_social')"
                maxlength="150"
                autocomplete="off"
            />
            <x-atoms.input
                type="text"
                name="documento_fiscal"
                :label="__('solicitudes.clientes.campo_documento_form')"
                :value="old('documento_fiscal', '')"
                :help="__('solicitudes.clientes.campo_documento_ayuda')"
                :error="$errors->first('documento_fiscal')"
                maxlength="30"
                autocomplete="off"
            />
            <x-atoms.input
                type="tel"
                name="telefono"
                :label="__('solicitudes.clientes.campo_telefono_form')"
                :value="old('telefono', '')"
                :error="$errors->first('telefono')"
                maxlength="30"
                required
                autocomplete="off"
            />
        </x-molecules.form-section>

        <x-molecules.form-section
            accent="info"
            :title="__('solicitudes.clientes.seccion_cuenta')"
            :count="__('solicitudes.panel.campos_contador', ['cantidad' => 4])"
        >
            <x-atoms.input
                type="text"
                name="nombre"
                :label="__('solicitudes.clientes.campo_nombre')"
                :value="old('nombre', '')"
                :error="$errors->first('nombre')"
                maxlength="150"
                required
                autocomplete="off"
            />
            <x-atoms.input
                type="text"
                name="username"
                :label="__('solicitudes.clientes.campo_usuario')"
                :value="old('username', '')"
                :help="__('solicitudes.clientes.campo_usuario_ayuda')"
                :error="$errors->first('username')"
                maxlength="50"
                required
                autocomplete="off"
            />
            <x-atoms.input
                type="email"
                name="email"
                :label="__('solicitudes.clientes.campo_correo')"
                :value="old('email', '')"
                :error="$errors->first('email')"
                maxlength="150"
                autocomplete="off"
            />
            <x-atoms.input
                type="password"
                name="password"
                :label="__('solicitudes.clientes.campo_password')"
                :help="__('solicitudes.clientes.campo_password_ayuda')"
                :error="$errors->first('password')"
                required
                autocomplete="new-password"
            />
        </x-molecules.form-section>

        <p class="ag-solicitudes__sub">{{ __('solicitudes.clientes.contactos_ayuda') }}</p>
        @if ($errors->has('contactos'))
            <x-molecules.alert-strip variant="danger" icon="error">
                {{ $errors->first('contactos') }}
            </x-molecules.alert-strip>
        @endif

        @foreach (range(0, $filasDeContacto - 1) as $indice)
            <x-molecules.form-section
                accent="distintivo-1"
                :title="__('solicitudes.clientes.contacto_n', ['numero' => $indice + 1])"
                :count="__('solicitudes.panel.campos_contador', ['cantidad' => 3])"
            >
                <x-atoms.input
                    type="text"
                    name="contactos[{{ $indice }}][nombre]"
                    id="contacto-{{ $indice }}-nombre"
                    :label="__('solicitudes.clientes.campo_contacto_nombre')"
                    :value="old('contactos.'.$indice.'.nombre', '')"
                    :error="$errors->first('contactos.'.$indice.'.nombre')"
                    maxlength="150"
                    autocomplete="off"
                />
                <x-atoms.input
                    type="tel"
                    name="contactos[{{ $indice }}][telefono]"
                    id="contacto-{{ $indice }}-telefono"
                    :label="__('solicitudes.clientes.campo_contacto_telefono')"
                    :value="old('contactos.'.$indice.'.telefono', '')"
                    :error="$errors->first('contactos.'.$indice.'.telefono')"
                    maxlength="30"
                    autocomplete="off"
                />
                <x-atoms.input
                    type="text"
                    name="contactos[{{ $indice }}][cargo]"
                    id="contacto-{{ $indice }}-cargo"
                    :label="__('solicitudes.clientes.campo_contacto_cargo')"
                    :value="old('contactos.'.$indice.'.cargo', '')"
                    :help="__('solicitudes.clientes.campo_contacto_cargo_ayuda')"
                    :error="$errors->first('contactos.'.$indice.'.cargo')"
                    maxlength="80"
                    autocomplete="off"
                />
            </x-molecules.form-section>
        @endforeach

        <x-molecules.form-section accent="distintivo-1" :title="__('solicitudes.clientes.campo_contacto_principal')">
            <x-atoms.radio-group
                name="contacto_principal"
                :label="__('solicitudes.clientes.campo_contacto_principal')"
                :options="$opcionesPrincipal"
                :value="old('contacto_principal', '0')"
                :help="__('solicitudes.clientes.campo_contacto_principal_ayuda')"
                class="ag-form-section__field--full"
            />
        </x-molecules.form-section>

        <x-organisms.form-actions-bar :status="__('solicitudes.clientes.estado_form')">
            <x-slot:actions>
                <x-molecules.boton-volver :href="route('panel.clientes.index')" cancelar />
                <x-atoms.button type="submit" variant="primary">{{ __('solicitudes.clientes.guardar') }}</x-atoms.button>
            </x-slot:actions>
        </x-organisms.form-actions-bar>
    </x-molecules.form-layout>
</form>
