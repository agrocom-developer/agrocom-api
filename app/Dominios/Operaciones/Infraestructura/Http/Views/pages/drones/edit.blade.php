{{--
    Page: drones/edit (GET /panel/drones/{dron}/editar, panel.drones.edit)
    Edición de un dron (HU-27, tarea 36): el formulario real vive en
    `_formulario.blade.php`, compartido con `create.blade.php`.

    Datos esperados (ver DronesController::edit()): la cáscara de
    CascaraPanel, más $dron (Dron).

    Gateada por `operaciones.dron.editar`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('operaciones.drones.titulo_editar')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :zona-horaria="$zonaHoraria ?? null"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :campaniaActiva="$campaniaActiva"
        :periodo="$periodo"
        :version="$version"
        :vista-actual="__('operaciones.drones.titulo_editar')"
    >
        @include('operaciones::pages.drones._formulario', ['dron' => $dron])
    </x-templates.panel-layout>
</x-templates.panel-shell>
