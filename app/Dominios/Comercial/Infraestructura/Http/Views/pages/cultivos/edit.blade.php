{{--
    Page: cultivos/edit (GET /panel/cultivos/{cultivo}/editar, panel.cultivos.edit)
    Edición de un cultivo (HU-48, tarea 71): el formulario real vive en
    `_formulario.blade.php`, compartido con `create.blade.php`.

    Datos esperados (ver CultivosController::edit()): la cáscara de
    CascaraPanel, más $cultivo (Cultivo).

    Gateada por `comercial.cultivo.editar`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('comercial.cultivos.titulo_editar')" :tema="$tema">
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
        :vista-actual="__('comercial.cultivos.titulo_editar')"
    >
        @include('comercial::pages.cultivos._formulario', ['cultivo' => $cultivo])
    </x-templates.panel-layout>
</x-templates.panel-shell>
