{{--
    Page: roles/create (GET /panel/roles/crear, panel.roles.create)
    Alta de un rol: el formulario real vive en `_formulario.blade.php`,
    compartido con `edit.blade.php`.

    Datos esperados (ver RolesController::create()): solo la cáscara de
    CascaraPanel — el alta no necesita catálogos.

    Gateada por `seguridad.rol.crear`, verificado server-side en el
    controlador. Al guardar redirige a la matriz de permisos, no al listado.
--}}
<x-templates.panel-shell :title="__('seguridad.roles.crear_titulo')" :tema="$tema">
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
        :vista-actual="__('seguridad.roles.crear_titulo')"
    >
        @include('seguridad::pages.roles._formulario', ['rol' => null, 'esRolActivo' => false])
    </x-templates.panel-layout>
</x-templates.panel-shell>
