{{--
    Page: facturas/create (GET /panel/facturas/crear, panel.facturas.create)
    Emisión de una factura (HU-31, tarea 45): el formulario real vive en
    `_formulario.blade.php`.

    Datos esperados (ver FacturasController::create()): la cáscara de
    CascaraPanel, más $actasDisponibles (ver el partial).

    Gateada por `comercial.factura.crear`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('comercial.facturas.titulo_crear')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :zona-horaria="$zonaHoraria ?? null"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :version="$version"
        :vista-actual="__('comercial.facturas.titulo_crear')"
    >
        @include('comercial::pages.facturas._formulario')
    </x-templates.panel-layout>
</x-templates.panel-shell>
