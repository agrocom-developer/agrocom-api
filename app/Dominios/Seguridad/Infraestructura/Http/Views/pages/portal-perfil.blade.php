{{--
    Page: portal-perfil (GET/PUT /portal/perfil, portal.perfil.edit/update)
    Tarea 66: mismo formulario que `pages/perfil/index.blade.php`, para el
    guard `cliente` — sobre `portal-layout` (sin menú de módulos: una cuenta
    de portal no tiene `sec_user_role`). Vive en Seguridad (no en Portal):
    toca `sec_user`, la tabla del módulo dueño.

    Datos esperados (ver PerfilPortalController::edit()): $userName, $tema
    (AutorizacionPortalCliente::cascara()) más $usuario (SecUser).
--}}
<x-templates.panel-shell :title="__('seguridad.perfil.titulo')" :tema="$tema" :tema-url="route('portal.preferencias.tema')" :zona-horaria-url="route('portal.preferencias.zona-horaria')">
    <x-templates.portal-layout :user-name="$userName" :zona-horaria="$zonaHoraria ?? null">
        @php
            $name = old('name', $usuario->name);
            $email = old('email', $usuario->email ?? '');
        @endphp

        <form method="POST" action="{{ route('portal.perfil.update') }}" class="ag-perfil-form" novalidate>
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
    </x-templates.portal-layout>
</x-templates.panel-shell>
