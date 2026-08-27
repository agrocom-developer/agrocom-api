{{--
    Page: usuarios/index (GET /panel/usuarios, panel.usuarios.index)
    Placeholder mínimo — la gestión real de usuarios (CRUD) es una HU futura,
    fuera de alcance de HU-02. Este archivo solo evita que el ítem de menú
    "Usuarios" (ya sembrado en sec_menu) sea un link roto.

    Datos esperados (ver UsuariosController::index()): igual forma que
    pages/dashboard.blade.php (menu/roles/rolActivoId/activeRoleLabel/
    userName) — misma cáscara de panel-layout, sin wrapper Livewire.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Agrocom') }} — Usuarios</title>

    @vite('resources/css/app.css')
    @livewireStyles
</head>
<body>
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
    >
        <h1>{{ __('seguridad.usuarios.titulo') }}</h1>
        <p>{{ __('seguridad.usuarios.proximamente') }}</p>
    </x-templates.panel-layout>

    @vite('resources/js/app.js')
    @livewireScripts
</body>
</html>


