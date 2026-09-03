{{--
    Page: vehiculos/create (GET /panel/vehiculos/crear, panel.vehiculos.create)
    Alta de un vehículo (HU-40, tarea 50): el formulario real vive en
    `_formulario.blade.php`, compartido con `edit.blade.php`.

    Datos esperados (ver VehiculosController::create()): la cáscara de
    CascaraPanel, más $basesDisponibles (Collection<int, string>).

    Gateada por `mantenimiento.vehiculo.crear`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('mantenimiento.vehiculos.titulo_crear')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :campana="$campana"
        :periodo="$periodo"
        :version="$version"
        :vista-actual="__('mantenimiento.vehiculos.titulo_crear')"
    >
        @include('mantenimiento::pages.vehiculos._formulario', ['vehiculo' => null, 'basesDisponibles' => $basesDisponibles, 'estados' => $estados])
    </x-templates.panel-layout>
</x-templates.panel-shell>
