{{--
    Page: proveedores/create (GET /panel/proveedores/crear, panel.proveedores.create)
    Alta a mano de un proveedor independiente o tercero para quien llega a la
    oficina: el formulario real vive en `_formulario.blade.php`.

    Datos esperados (ver ProveedoresController::create()): la cáscara de
    CascaraPanel, más $tipos (list<TipoProveedor>).

    Gateada por `proveedores.proveedor.crear`, verificado server-side.
--}}
<x-templates.panel-shell :title="__('proveedores.alta_manual.proveedor_titulo')" :tema="$tema">
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
        :vista-actual="__('proveedores.alta_manual.proveedor_titulo')"
    >
        @include('proveedores::pages.proveedores._formulario')
    </x-templates.panel-layout>
</x-templates.panel-shell>
