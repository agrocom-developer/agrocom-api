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

    @vite('resources/css/app.css')
</head>
<body>
    <x-templates.auth-layout>
        <x-organisms.login-form
            :action="route('login')"
            :csrf="csrf_token()"
            data-ag-login-form
        />
    </x-templates.auth-layout>

    @vite('resources/js/app.js')
</body>
</html>
