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
      `data-bs-theme`, resuelto por el controlador desde la preferencia
      (`sec_user_preferencia.tema`). Si el navegador tiene un tema distinto
      guardado en localStorage, theme-toggle.js lo aplica al cargar (octava
      vuelta, 29/8/2026: ya no existe "sistema" ni el atributo
      `data-ag-theme-preference` que distinguía preferencia de tema
      resuelto — ver theme-toggle.js).
    - temaUrl (nullable string, default `route('panel.preferencias.tema')`):
      URL de persistencia del tema (HU-41, tarea 55) — el portal del cliente
      reusa este mismo shell (ADR 0002 punto 6) pero persiste contra
      `route('portal.preferencias.tema')`, guard `cliente`; se pasa
      explícito en vez de inferir el guard acá porque este template no sabe
      de sesiones.

    Slot (default): el cuerpo completo de la página.
--}}
@props([
    'title' => null,
    'tema' => 'light',
    'temaUrl' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="{{ $tema }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- URL de persistencia del tema: presente solo en páginas autenticadas
         del panel/portal — theme-toggle.js postea acá al cambiar de tema. --}}
    <meta name="ag-preferencias-tema-url" content="{{ $temaUrl ?? route('panel.preferencias.tema') }}">
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
