{{--
    Page: equipos-trabajo/edit (GET /panel/equipos-trabajo/{equipoTrabajo}/editar, panel.equipos-trabajo.edit)
    Edición de los datos descriptivos de un equipo de trabajo (tarea 72,
    HU-49): el formulario real vive en `_formulario.blade.php`, compartido
    con `create.blade.php`. Integrantes y recursos se editan en la ficha
    (`panel.equipos-trabajo.show`), no acá.

    Datos esperados (ver EquiposTrabajoController::edit()): la cáscara de
    CascaraPanel, más $equipo, $basesDisponibles y $estados.

    Gateada por `personal.equipo_trabajo.editar`, verificado server-side en
    el controlador.
--}}
<x-templates.panel-shell :title="__('personal.equipos_trabajo.titulo_editar')" :tema="$tema">
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
        :vista-actual="__('personal.equipos_trabajo.titulo')"
    >
        @include('personal::pages.equipos-trabajo._formulario', ['equipo' => $equipo])
    </x-templates.panel-layout>
</x-templates.panel-shell>
