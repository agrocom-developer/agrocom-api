{{--
    Page: pilotos/create (GET /panel/pilotos/crear, panel.pilotos.create)
    Alta a mano de un piloto de un proveedor tercero que ya existe: el
    formulario real vive en `_formulario.blade.php`.

    Datos esperados (ver PilotosController::create()): la cáscara de
    CascaraPanel, más $proveedores (array<int, string>, id => razón social) y
    $proveedorElegido (?int).

    Gateada por `proveedores.piloto.crear`, verificado server-side.
--}}
<x-templates.panel-shell :title="__('proveedores.alta_manual.piloto_titulo')" :tema="$tema">
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
        :vista-actual="__('proveedores.alta_manual.piloto_titulo')"
    >
        @include('proveedores::pages.pilotos._formulario')
    </x-templates.panel-layout>
</x-templates.panel-shell>
