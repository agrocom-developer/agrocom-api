{{--
    Page: planes/edit (GET /panel/planes-mantenimiento/{plan}/editar, panel.planes-mantenimiento.edit)
    Edición de un plan de mantenimiento preventivo (HU-38, tarea 54): el
    formulario real vive en `_formulario.blade.php`, compartido con
    `create.blade.php`.

    Datos esperados (ver PlanesMantenimientoController::edit()): la cáscara
    de CascaraPanel, más $plan (PlanMantenimiento).

    Gateada por `mantenimiento.plan.editar`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('mantenimiento.planes.titulo_editar')" :tema="$tema">
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
        :vista-actual="__('mantenimiento.planes.titulo_editar')"
    >
        @include('mantenimiento::pages.planes._formulario', ['plan' => $plan])
    </x-templates.panel-layout>
</x-templates.panel-shell>
