{{--
    Page: zonas/edit (GET /panel/zonas/{zona}/editar)
    Edición de una zona: el formulario vive en `_formulario.blade.php`.
    Datos esperados: la cáscara de CascaraPanel, $zona (Zona|null) y $regiones.
    Gateada por `solicitudes.zona.gestionar`, verificado server-side.
--}}
@include('solicitudes::pages.zonas._formulario')
