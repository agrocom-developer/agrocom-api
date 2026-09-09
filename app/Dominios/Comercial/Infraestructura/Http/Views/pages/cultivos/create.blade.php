{{--
    Page: cultivos/create (GET /panel/cultivos/crear, panel.cultivos.create)
    Alta de un cultivo (HU-48, tarea 71): el formulario real vive en
    `_formulario.blade.php`, compartido con `edit.blade.php`.

    Datos esperados (ver CultivosController::create()): solo la cáscara de
    CascaraPanel.

    Gateada por `comercial.cultivo.crear`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('comercial.cultivos.titulo_crear')" :tema="$tema">
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
        :vista-actual="__('comercial.cultivos.titulo_crear')"
    >
        @include('comercial::pages.cultivos._formulario', ['cultivo' => null])
    </x-templates.panel-layout>
</x-templates.panel-shell>
