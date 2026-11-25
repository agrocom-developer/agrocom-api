{{--
    Page: drones/create (GET /panel/drones-flota/crear, panel.drones-flota.create)
    Alta a mano del dron de un proveedor que ya existe: el formulario real
    vive en `_formulario.blade.php`.

    Datos esperados (ver DronesController::create()): la cáscara de
    CascaraPanel, más $proveedores (array<int, string>, id => razón social) y
    $proveedorElegido (?int).

    Gateada por `flota.dron.crear`, verificado server-side.
--}}
<x-templates.panel-shell :title="__('flota.alta_manual.titulo')" :tema="$tema">
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
        :vista-actual="__('flota.alta_manual.titulo')"
    >
        @include('flota::pages.drones._formulario')
    </x-templates.panel-layout>
</x-templates.panel-shell>
