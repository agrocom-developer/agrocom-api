{{--
    Template: panel-shell (quinta vuelta — layout de tres niveles)
    Esqueleto HTML compartido por TODAS las páginas del panel (dashboard,
    selección de rol, usuarios, organización): antes cada página duplicaba el
    <!DOCTYPE html> completo con `data-bs-theme="light"` hardcodeado — ahora
    el tema sale de la preferencia persistida del usuario
    (`sec_user_preferencia.tema`, resuelta por el controlador) y el toggle
    del header la actualiza vía POST `panel.preferencias.tema` (ver
    resources/js/molecules/theme-toggle.js).

    Props:
    - title (nullable string): sufijo del <title>, ya traducido.
    - tema ("light"|"dark", default "light"): valor inicial de
      `data-bs-theme`, resuelto por el controlador desde la preferencia.
      `data-ag-theme-preference` arranca en el mismo valor — el enum del
      backend (`sec_user_preferencia.tema`) solo conoce claro/oscuro, nunca
      "sistema"; si el navegador tiene "sistema" guardado en localStorage,
      theme-toggle.js lo resuelve y sobreescribe ambos atributos al cargar
      (auditoría visual externa, obs. #9).

    Slot (default): el cuerpo completo de la página.
--}}
@props([
    'title' => null,
    'tema' => 'light',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="{{ $tema }}" data-ag-theme-preference="{{ $tema }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- URL de persistencia del tema: presente solo en páginas autenticadas
         del panel — theme-toggle.js postea acá al cambiar de tema. --}}
    <meta name="ag-preferencias-tema-url" content="{{ route('panel.preferencias.tema') }}">
    <title>{{ config('app.name', 'Agrocom') }}{{ $title ? ' — '.$title : '' }}</title>

    @vite('resources/css/app.css')
    @livewireStyles
</head>
<body>
    {{ $slot }}

    @vite('resources/js/app.js')
    @livewireScripts
</body>
</html>
