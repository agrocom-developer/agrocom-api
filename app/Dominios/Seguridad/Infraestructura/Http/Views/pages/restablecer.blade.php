{{--
    Page: restablecer (GET/POST /restablecer y /portal/restablecer)
    Tarea 66: pantalla de "elegir contraseña nueva" llegada desde el enlace
    del correo de recuperar acceso — compartida por panel y portal
    (RestablecerContrasenaController / RestablecerContrasenaPortalController
    pasan `$accion`/`$volverA` distintos, mismo formulario).

    Sobre `auth-layout` (mismo layout que login) — quien llega acá nunca
    tiene sesión: es un formulario público, sin cáscara de panel/portal.

    Datos esperados: $accion (URL del POST), $volverA (URL del link "Volver
    al ingreso"), $token, $email (prefill, editable — el usuario puede
    corregirlo si el enlace le llegó reenviado con otro destinatario).
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Agrocom') }} — {{ __('seguridad.restablecer.titulo') }}</title>

    @vite('resources/css/app.css')
</head>
<body>
    <x-templates.auth-layout>
        <div class="ag-login-form">
            <div class="ag-login-form__panel">
                <div class="ag-login-form__header">
                    <h1 class="ag-login-form__title">{{ __('seguridad.restablecer.titulo') }}</h1>
                    <p class="ag-login-form__subtitle">{{ __('seguridad.restablecer.subtitulo') }}</p>
                </div>

                @if ($errors->any())
                    <div class="ag-login-form__error" role="alert">
                        <x-atoms.icon name="error" size="sm" />
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <form class="ag-login-form__form" method="POST" action="{{ $accion }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">

                    <x-atoms.input
                        variant="line"
                        type="email"
                        name="email"
                        :label="__('seguridad.restablecer.campo_email')"
                        icon="mail"
                        autocomplete="email"
                        :value="old('email', $email)"
                        :required="true"
                    />

                    <x-atoms.input
                        variant="line"
                        type="password"
                        name="password"
                        :label="__('seguridad.restablecer.campo_password')"
                        icon="lock"
                        autocomplete="new-password"
                        :error="$errors->first('password')"
                        :required="true"
                    />

                    <x-atoms.input
                        variant="line"
                        type="password"
                        name="password_confirmation"
                        :label="__('seguridad.restablecer.campo_password_confirmacion')"
                        icon="lock"
                        autocomplete="new-password"
                        :required="true"
                    />

                    <x-atoms.button
                        type="submit"
                        variant="primary"
                        size="lg"
                        :block="true"
                        icon="arrow_forward"
                        iconPosition="end"
                    >
                        {{ __('seguridad.restablecer.boton_confirmar') }}
                    </x-atoms.button>
                </form>

                <a href="{{ $volverA }}" class="ag-login-form__link-back">
                    {{ __('seguridad.recuperar.volver') }}
                </a>
            </div>
        </div>
    </x-templates.auth-layout>

    @vite('resources/js/app.js')
</body>
</html>
