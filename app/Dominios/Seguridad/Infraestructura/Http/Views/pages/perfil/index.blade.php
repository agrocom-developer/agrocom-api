{{--
    Page: perfil/index (GET/PUT /panel/perfil, panel.perfil.edit/update)
    Tarea 66: autoservicio del guard `interno` — nombre, correo y cambio de
    contraseña propio (contraseña actual + nueva + confirmación). Un solo
    `<form>`, sin selector de tipo ni de roles (a diferencia de
    `usuarios/_formulario.blade.php`, que administra cuentas AJENAS): el
    sujeto es siempre quien está logueado.

    `password_actual`/`password`/`password_confirmation` NUNCA se repueblan
    con `old()` — igual criterio que el resto del panel con contraseñas.
--}}
<x-templates.panel-shell :title="__('seguridad.perfil.titulo')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :campaniaActiva="$campaniaActiva"
        :periodo="$periodo"
        :version="$version"
        :vista-actual="__('seguridad.perfil.titulo')"
    >
        @php
            $name = old('name', $usuario->name);
            $email = old('email', $usuario->email ?? '');
        @endphp

        <form method="POST" action="{{ route('panel.perfil.update') }}" class="ag-perfil-form" novalidate>
            @csrf
            @method('PUT')

            <x-organisms.page-header
                :title="__('seguridad.perfil.titulo')"
                :subtitle="__('seguridad.perfil.subtitulo')"
            >
                <x-slot:actions>
                    <x-atoms.button type="submit" variant="primary">
                        {{ __('ui.action.save') }}
                    </x-atoms.button>
                </x-slot:actions>
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            <x-molecules.form-section :title="__('seguridad.perfil.seccion_datos')">
                <x-atoms.input
                    type="text"
                    name="name"
                    label="{{ __('seguridad.perfil.campo_name') }}"
                    value="{{ $name }}"
                    required
                    error="{{ $errors->first('name') }}"
                />

                <x-atoms.input
                    type="email"
                    name="email"
                    label="{{ __('seguridad.perfil.campo_email') }}"
                    value="{{ $email }}"
                    error="{{ $errors->first('email') }}"
                />
            </x-molecules.form-section>

            <x-molecules.form-section :title="__('seguridad.perfil.seccion_password')">
                <p class="ag-form-section__field--full">{{ __('seguridad.perfil.seccion_password_ayuda') }}</p>

                <x-atoms.input
                    type="password"
                    name="password_actual"
                    label="{{ __('seguridad.perfil.campo_password_actual') }}"
                    error="{{ $errors->first('password_actual') }}"
                />

                <x-atoms.input
                    type="password"
                    name="password"
                    label="{{ __('seguridad.perfil.campo_password_nueva') }}"
                    help="{{ __('seguridad.perfil.campo_password_nueva_ayuda') }}"
                    error="{{ $errors->first('password') }}"
                />

                <x-atoms.input
                    type="password"
                    name="password_confirmation"
                    label="{{ __('seguridad.perfil.campo_password_confirmacion') }}"
                />
            </x-molecules.form-section>

            <x-organisms.form-actions-bar :status="__('seguridad.perfil.estado_form')">
                <x-slot:actions>
                    <x-atoms.button type="submit" variant="primary">
                        {{ __('ui.action.save') }}
                    </x-atoms.button>
                </x-slot:actions>
            </x-organisms.form-actions-bar>
        </form>
    </x-templates.panel-layout>
</x-templates.panel-shell>
