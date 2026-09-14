{{--
    Page: fichas-dron/edit (GET /panel/fichas-dron/{fichaDron}/editar, panel.fichas-dron.edit)
    Edición de una ficha de inventario de dron (HU-82, tarea 97): el
    formulario real vive en `_formulario.blade.php`, compartido con
    `create.blade.php`.

    Datos esperados (ver FichasDronController::edit()): la cáscara de
    CascaraPanel, más $ficha (FichaDron).

    Gateada por `mantenimiento.ficha_dron.editar`, verificado server-side en
    el controlador.
--}}
<x-templates.panel-shell :title="__('mantenimiento.fichas_dron.titulo_editar')" :tema="$tema">
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
        :vista-actual="__('mantenimiento.fichas_dron.titulo_editar')"
    >
        @include('mantenimiento::pages.fichas-dron._formulario', ['ficha' => $ficha])
    </x-templates.panel-layout>
</x-templates.panel-shell>
