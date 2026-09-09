{{--
    Page: vehiculos/edit (GET /panel/vehiculos/{vehiculo}/editar, panel.vehiculos.edit)
    Edición de un vehículo (HU-40, tarea 50): el formulario real vive en
    `_formulario.blade.php`, compartido con `create.blade.php`.

    Datos esperados (ver VehiculosController::edit()): la cáscara de
    CascaraPanel, más $vehiculo (Vehiculo) y $basesDisponibles
    (Collection<int, string>).

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
        :campaniaActiva="$campaniaActiva"
        :periodo="$periodo"
        :version="$version"
        :vista-actual="__('mantenimiento.vehiculos.titulo_editar')"
    >
        @include('mantenimiento::pages.vehiculos._formulario', ['vehiculo' => $vehiculo, 'basesDisponibles' => $basesDisponibles, 'estados' => $estados])
    </x-templates.panel-layout>
</x-templates.panel-shell>
