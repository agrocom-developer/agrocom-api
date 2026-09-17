{{--
    Page: portal-login (GET /portal/login, portal.login.form)
    HU-41 (tarea 55): login del portal del cliente — mismo `auth-layout` +
    `login-form` que el login del panel interno (ADR 0002 punto 6: "el
    portal reutiliza el mismo layout"), apuntando a POST /portal/login
    (SesionPortalController, guard `cliente`) en vez de /login.

    `data-ag-login-redirect="/portal/avance"`: resources/js/pages/login.js
    intercepta el submit de CUALQUIER `[data-ag-login-form]` — sin esto,
    redirigiría al dashboard del panel interno tras un login de portal
    exitoso (default del script). El portal nunca responde
    `requiere_seleccion_rol` (SesionPortalController no lo devuelve, una
    cuenta de portal no elige rol), así que el script va directo a este
    destino.

    Datos: ninguno necesario — el formulario no prepopula nada.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Agrocom') }} — Portal del cliente</title>

    <x-atoms.tema-inicial />

    @vite('resources/css/app.css')
</head>
<body>
    <x-templates.auth-layout>
        <x-organisms.login-form
            :action="route('portal.login')"
            :csrf="csrf_token()"
            :titulo="__('portal.login.titulo')"
            :subtitulo="__('portal.login.subtitulo')"
            :recuperar-action="route('portal.recuperar.store')"
            :recuperar-email-value="old('email')"
            :recuperar-email-error="$errors->first('email')"
            :recuperar-estado="session('estado')"
            data-ag-login-form
            data-ag-login-redirect="/portal/avance"
        />
    </x-templates.auth-layout>

    @vite('resources/js/app.js')
</body>
</html>
