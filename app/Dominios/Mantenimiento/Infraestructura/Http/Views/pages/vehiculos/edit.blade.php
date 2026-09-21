{{--
    Page: vehiculos/edit (GET /panel/vehiculos/{vehiculo}/editar, panel.vehiculos.edit)
    Edición de un vehículo (HU-40, tarea 50): el formulario real vive en
    `_formulario.blade.php`, compartido con `create.blade.php`.

    Datos esperados (ver VehiculosController::edit()): la cáscara de
    CascaraPanel, más $vehiculo (Vehiculo), $basesDisponibles
    (Collection<int, string>), $estados, $combustibles, $tipos y
    $resumenRelacionado (tarjetas del aside, resueltas por el controlador).

    Gateada por `mantenimiento.vehiculo.editar`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('mantenimiento.vehiculos.titulo_editar')" :tema="$tema">
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
        :vista-actual="__('mantenimiento.vehiculos.titulo_editar')"
    >
        @include('mantenimiento::pages.vehiculos._formulario', ['vehiculo' => $vehiculo, 'basesDisponibles' => $basesDisponibles, 'estados' => $estados, 'resumenRelacionado' => $resumenRelacionado])
    </x-templates.panel-layout>
</x-templates.panel-shell>
