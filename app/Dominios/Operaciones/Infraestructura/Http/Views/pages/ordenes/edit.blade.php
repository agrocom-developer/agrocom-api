{{--
    Page: ordenes/edit (GET /panel/ordenes/{orden}/editar, panel.ordenes.edit)
    Edición de una orden de aplicación (HU-25, tarea 38): el formulario real
    vive en `_formulario.blade.php`, compartido con `create.blade.php`.

    Datos esperados (ver OrdenesController::edit()): la cáscara de
    CascaraPanel, más $orden y $contratosDisponibles/$lotesDisponibles/$contactosDisponibles.

    Gateada por `operaciones.orden.editar`, verificado server-side en el
    controlador. Que la orden siga siendo editable (solo `emitida`) lo
    exige `Aplicacion/ActualizarOrden`, no esta vista.
--}}
<x-templates.panel-shell :title="__('operaciones.ordenes.titulo_editar')" :tema="$tema">
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
        :vista-actual="__('operaciones.ordenes.titulo_editar')"
    >
        @include('operaciones::pages.ordenes._formulario', ['orden' => $orden])
    </x-templates.panel-layout>
</x-templates.panel-shell>
