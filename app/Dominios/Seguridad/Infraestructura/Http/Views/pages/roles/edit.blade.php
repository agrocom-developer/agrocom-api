{{--
    Page: roles/edit (GET /panel/roles/{rol}/editar, panel.roles.edit)
    Edición del nombre, la descripción y el estado de un rol. El formulario
    real vive en `_formulario.blade.php`, compartido con `create.blade.php`.
    Los permisos NO se editan acá: `permisos.blade.php`, otra ruta y otro
    permiso.

    Datos esperados (ver RolesController::edit()): la cáscara de
    CascaraPanel, más $rol (SecRole) y $esRolActivo (bool).

    Gateada por `seguridad.rol.editar`, verificado server-side.
--}}
<x-templates.panel-shell :title="__('seguridad.roles.editar_titulo', ['rol' => $rol->name])" :tema="$tema">
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
        :vista-actual="__('seguridad.roles.titulo')"
    >
        @include('seguridad::pages.roles._formulario')
    </x-templates.panel-layout>
</x-templates.panel-shell>
