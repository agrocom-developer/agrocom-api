{{--
    Page: bases/create (GET /panel/bases/crear, panel.bases.create)
    Alta de una base (HU-26, tarea 37): el formulario real vive en
    `_formulario.blade.php`, compartido con `edit.blade.php`.

    Datos esperados (ver BasesController::create()): solo la cáscara de
    CascaraPanel.

    Gateada por `personal.base.crear`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('personal.bases.titulo_crear')" :tema="$tema">
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
        :vista-actual="__('personal.bases.titulo_crear')"
    >
        @include('personal::pages.bases._formulario', ['base' => null])
    </x-templates.panel-layout>
</x-templates.panel-shell>
