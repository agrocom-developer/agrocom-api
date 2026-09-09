{{--
    Page: baterias/edit (GET /panel/baterias/{bateria}/editar, panel.baterias.edit)
    Edición de una batería (HU-39, tarea 51): el formulario real vive en
    `_formulario.blade.php`, compartido con `create.blade.php`.

    Datos esperados (ver BateriasController::edit()): la cáscara de
    CascaraPanel, más $bateria (Bateria) y $basesDisponibles
    (Collection<int, string>).

    Gateada por `mantenimiento.bateria.editar`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('mantenimiento.baterias.titulo_editar')" :tema="$tema">
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
        :vista-actual="__('mantenimiento.baterias.titulo_editar')"
    >
        @include('mantenimiento::pages.baterias._formulario', ['bateria' => $bateria, 'basesDisponibles' => $basesDisponibles, 'estados' => $estados])
    </x-templates.panel-layout>
</x-templates.panel-shell>
