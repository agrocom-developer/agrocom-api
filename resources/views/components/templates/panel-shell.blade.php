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
      (`sec_user_preferencia.tema`). Si el navegador tiene otro tema guardado
      en localStorage, lo aplica el script inline `atoms/tema-inicial` de
      acá arriba — antes del primer pintado, no en `DOMContentLoaded`, que
      era exactamente el parpadeo de claro a oscuro que reportó el usuario el
      9/9/2026. `theme-toggle.js` se queda solo con el click y con persistir
      la divergencia (octava vuelta, 29/8/2026: ya no existe "sistema" ni el
      atributo `data-ag-theme-preference` que distinguía preferencia de tema
      resuelto — ver theme-toggle.js).
    - temaUrl (nullable string, default `route('panel.preferencias.tema')`):
      URL de persistencia del tema (HU-41, tarea 55) — el portal del cliente
      reusa este mismo shell (ADR 0002 punto 6) pero persiste contra
      `route('portal.preferencias.tema')`, guard `cliente`; se pasa
      explícito en vez de inferir el guard acá porque este template no sabe
      de sesiones.

    - transicionDeVista (bool, default false): incluye
      `resources/css/transicion-vista.css`, la hoja que declara
      `@view-transition`. Es opt-in y no parte del bundle porque esa regla
      es de DOCUMENTO (no se acota por selector): en `app.css` aplicaba a
      toda navegación same-origin, incluido cada clic del menú del panel —
      donde el fundido sostenía en pantalla el estado a medio cargar de la
      página entrante. Hoy la pide solo `pages/seleccionar-rol.blade.php`,
      la mitad entrante del salto login → selección de rol (la saliente,
      `pages/login.blade.php`, arma su propio <head> y la incluye ahí).

    Slot (default): el cuerpo completo de la página.
--}}
@props([
    'title' => null,
    'tema' => 'light',
    'temaUrl' => null,
    'zonaHorariaUrl' => null,
    'transicionDeVista' => false,
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
    {{-- URL de persistencia de la zona horaria: presente solo en páginas autenticadas
         del panel/portal — timezone-badge.js postea acá cuando la zona que informa
         el navegador no es la que tiene guardada el usuario. --}}
    <meta name="ag-preferencias-zona-horaria-url" content="{{ $zonaHorariaUrl ?? route('panel.preferencias.zona-horaria') }}">
    <title>{{ config('app.name', 'Agrocom') }}{{ $title ? ' — '.$title : '' }}</title>

    {{-- Antes de `@vite`: fija `data-bs-theme` desde el navegador sin esperar
         al primer pintado (ver el componente). --}}
    <x-atoms.tema-inicial />

    @vite($transicionDeVista
        ? ['resources/css/app.css', 'resources/css/transicion-vista.css']
        : 'resources/css/app.css')
    @livewireStyles
</head>
<body>
    {{ $slot }}

    @vite('resources/js/app.js')
    @livewireScripts
</body>
</html>
