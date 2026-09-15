{{--
    Parcial: estado vacío del dashboard completo — el rol activo tiene el
    permiso de entrada (`seguridad.dashboard.ver`) pero ninguna sección
    habilitada, o ninguna con datos todavía.

    Existe para que ese caso sea una pantalla que explica en vez de una
    página en blanco: es exactamente lo que ve un rol recién creado antes de
    que se le asignen permisos.
--}}
<x-molecules.empty-state
    icon="dashboard"
    :title="__('seguridad.dashboard.vacio_titulo')"
    :detail="__('seguridad.dashboard.vacio_detalle')"
/>
