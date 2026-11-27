{{--
    Page: roles/create (GET /panel/roles/crear, panel.roles.create)
    Alta de un rol: el formulario real vive en `_formulario.blade.php`, compartido con
    `edit.blade.php`. El rol nace sin permisos y el guardado aterriza en su matriz.

    Datos esperados (ver RolesController::create()): la cáscara de CascaraPanel, más
    `$rol` (null), `$esRolActivo` y `$esDelSistema` (false).

    Gateada por `seguridad.rol.crear`, verificado server-side en el controlador.
--}}
<x-templates.panel-shell :title="__('seguridad.roles.crear_titulo')" :tema="$tema">
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
        :vista-actual="__('seguridad.roles.crear_titulo')"
    >
        @include('seguridad::pages.roles._formulario')
    </x-templates.panel-layout>
</x-templates.panel-shell>
