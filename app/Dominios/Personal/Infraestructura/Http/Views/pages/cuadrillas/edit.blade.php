{{--
    Page: cuadrillas/edit (GET /panel/cuadrillas/{equipoTrabajo}/editar, panel.cuadrillas.edit)
    Edición de los datos descriptivos de un equipo de trabajo (tarea 72,
    HU-49): el formulario real vive en `_formulario.blade.php`, compartido
    con `create.blade.php`. Integrantes y recursos se editan en la ficha
    (`panel.cuadrillas.show`), no acá.

    Datos esperados (ver CuadrillasController::edit()): la cáscara de
    CascaraPanel, más $equipo, $basesDisponibles, los pasos de estado
    ($pasosEstado, $ayudaEstado) y las tablas de detalle paginadas
    ($integrantes, $equipamiento y sus contadores/opciones —
    ver el docblock del controlador).

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
        @include('personal::pages.cuadrillas._formulario', ['equipo' => $equipo])
        @include('personal::pages.cuadrillas._cambio-estado', ['equipo' => $equipo, 'pasosEstado' => $pasosEstado, 'tonoPorEstado' => $tonoPorEstado])
        @include('personal::pages.cuadrillas._detalle-modales', ['equipo' => $equipo, 'roles' => $rolesEquipo])
    </x-templates.panel-layout>
</x-templates.panel-shell>
