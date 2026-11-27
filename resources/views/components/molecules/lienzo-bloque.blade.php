{{--
    Molecule: lienzo-bloque
    Una pieza del lienzo (templates/lienzo). Envuelve una tarjeta, una gráfica, una tira de KPIs u
    otro lienzo, y hace que llene el espacio que le tocó por fracción. No pinta fondo ni borde:
    eso es de la tarjeta de dentro (`.ag-card`). `etiqueta` agrega un nombre de región para
    lectores de pantalla cuando el bloque no trae un encabezado propio.
--}}
@props(['etiqueta' => null])

<div {{ $attributes->class(['ag-lienzo__bloque']) }} @if ($etiqueta) role="region" aria-label="{{ $etiqueta }}" @endif>
    {{ $slot }}
</div>
