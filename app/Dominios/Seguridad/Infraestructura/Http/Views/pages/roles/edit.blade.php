{{--
    Page: roles/edit (GET /panel/roles/{rol}/editar, panel.roles.edit)
    Edición del nombre, la descripción y el estado de un rol. El formulario
    real vive en `_formulario.blade.php`, compartido con `create.blade.php`.
    Los permisos NO se editan acá: `permisos.blade.php`, otra ruta y otro
    permiso.

    Datos esperados (ver RolesController::edit()): la cáscara de
    CascaraPanel, más $rol (SecRole), $esRolActivo y $esDelSistema (bool) y
    $resumenRelacionado (tarjetas del aside).

    Gateada por `seguridad.rol.editar`, verificado server-side.
--}}
@php
    $nombreLegible = \App\Dominios\Seguridad\Infraestructura\Http\Presentacion\PresentadorRol::nombreLegible($rol);
@endphp
<x-templates.panel-shell :title="__('seguridad.roles.editar_titulo', ['rol' => $nombreLegible])" :tema="$tema">
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
        :vista-actual="__('seguridad.roles.editar_titulo', ['rol' => $nombreLegible])"
    >
        @include('seguridad::pages.roles._formulario')
    </x-templates.panel-layout>
</x-templates.panel-shell>
