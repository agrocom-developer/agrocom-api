{{--
    Page: repuestos/create (GET /panel/repuestos/crear, panel.repuestos.create)
    Alta de un repuesto (HU-36, tarea 52): el formulario real vive en
    `_formulario.blade.php`, compartido con `edit.blade.php`.

    Datos esperados (ver RepuestosController::create()): la cáscara de
    CascaraPanel, sin datos propios adicionales.

    Gateada por `inventario.repuesto.crear`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('inventario.repuestos.titulo_crear')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :campana="$campana"
        :periodo="$periodo"
        :version="$version"
        :vista-actual="__('inventario.repuestos.titulo_crear')"
    >
        @include('inventario::pages.repuestos._formulario', ['repuesto' => null])
    </x-templates.panel-layout>
</x-templates.panel-shell>
