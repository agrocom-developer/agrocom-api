{{--
    Parcial: pestaña "Multimedia" — las últimas sesiones que dejaron evidencia
    gráfica, agrupadas por sesión.

    La vista de galería es la única que quedó: el carrusel y la tabla de la
    maqueta mostraban las mismas capturas de tres formas distintas, y las tres
    se alimentaban del mismo mock. Ver runs/67.md.

    Espera: $secciones['multimedia'] (list).
--}}
<div class="ag-dash__stack">
    @include('seguridad::pages.dashboard._multimedia-galeria', ['sesiones' => $secciones['multimedia']])
</div>
