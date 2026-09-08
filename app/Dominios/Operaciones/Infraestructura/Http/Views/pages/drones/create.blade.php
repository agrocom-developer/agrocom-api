{{--
    Page: drones/create (GET /panel/drones/crear, panel.drones.create)
    Alta de un dron (HU-27, tarea 36): el formulario real vive en
    `_formulario.blade.php`, compartido con `edit.blade.php`.

    Datos esperados (ver DronesController::create()): solo la cáscara de
    CascaraPanel.

    Gateada por `operaciones.dron.crear`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('operaciones.drones.titulo_crear')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :campaniaActiva="$campaniaActiva"
        :periodo="$periodo"
        :version="$version"
        :vista-actual="__('operaciones.drones.titulo_crear')"
    >
        @include('operaciones::pages.drones._formulario', ['dron' => null])
    </x-templates.panel-layout>
</x-templates.panel-shell>
