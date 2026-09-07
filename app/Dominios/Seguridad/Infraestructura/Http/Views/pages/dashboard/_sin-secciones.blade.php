{{--
    Parcial: estado vacío del dashboard completo — el rol activo tiene el
    permiso de entrada (`seguridad.dashboard.ver`) pero ninguna sección
    habilitada, o ninguna con datos todavía.

    Existe para que ese caso sea una pantalla que explica en vez de una
    página en blanco: es exactamente lo que ve un rol recién creado antes de
    que se le asignen permisos.
--}}
<div class="ag-card ag-card--padded ag-dash__vacio">
    <x-atoms.icon name="dashboard" size="lg" />
    <h2 class="ag-card__title">{{ __('seguridad.dashboard.vacio_titulo') }}</h2>
    <p class="ag-dash__subtitle">{{ __('seguridad.dashboard.vacio_detalle') }}</p>
</div>
