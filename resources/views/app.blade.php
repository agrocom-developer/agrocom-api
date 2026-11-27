<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'DronAgro') }}</title>

    <x-atoms.tema-inicial />

    @vite('resources/css/app.css')
</head>
<body>
    {{ $slot }}

    @vite('resources/js/app.js')
</body>
</html>
