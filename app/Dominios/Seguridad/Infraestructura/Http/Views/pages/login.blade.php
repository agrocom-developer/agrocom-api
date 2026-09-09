{{--
    Page: login (GET /login, login.form)
    Estructura: layout HTML + auth-layout + login-form. El submit es interceptado
    por resources/js/pages/login.js que hace fetch a POST /login (JSON), no un
    submit tradicional.

    Datos: ninguno necesario — el formulario no prepopula nada.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Agrocom') }} — Ingreso</title>

    <x-atoms.tema-inicial />

    {{-- `transicion-vista.css`: mitad SALIENTE del salto login → selección de rol
         (resources/js/pages/login.js redirige con `window.location.href`). Las dos
         páginas del salto tienen que declarar `@view-transition`; ninguna otra
         pantalla lo hace — ver resources/css/transicion-vista.css. --}}
    @vite(['resources/css/app.css', 'resources/css/transicion-vista.css'])
</head>
<body>
    <x-templates.auth-layout>
        <x-organisms.login-form
            :action="route('login')"
            :csrf="csrf_token()"
            :recuperar-action="route('recuperar.store')"
            :recuperar-email-value="old('email')"
            :recuperar-email-error="$errors->first('email')"
            :recuperar-estado="session('estado')"
            data-ag-login-form
        />
    </x-templates.auth-layout>

    @vite('resources/js/app.js')
</body>
</html>
