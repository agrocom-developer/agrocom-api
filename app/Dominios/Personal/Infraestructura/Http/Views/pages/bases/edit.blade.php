{{--
    Page: bases/edit (GET /panel/bases/{base}/editar, panel.bases.edit)
    Edición de una base (HU-26, tarea 37): el formulario real vive en
    `_formulario.blade.php`, compartido con `create.blade.php`.

    Datos esperados (ver BasesController::edit()): la cáscara de
    CascaraPanel, más $base (PerBase).

    Gateada por `personal.base.editar`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('personal.bases.titulo_editar')" :tema="$tema">
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
        :vista-actual="__('personal.bases.titulo_editar')"
    >
        @include('personal::pages.bases._formulario', ['base' => $base])
    </x-templates.panel-layout>
</x-templates.panel-shell>
