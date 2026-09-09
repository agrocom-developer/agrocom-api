{{--
    Page: ordenes/create (GET /panel/ordenes/crear, panel.ordenes.create)
    Alta de una orden de aplicación (HU-25, tarea 38): el formulario real
    vive en `_formulario.blade.php`, compartido con `edit.blade.php`.

    Datos esperados (ver OrdenesController::create()): la cáscara de
    CascaraPanel, más $contratosDisponibles/$lotesDisponibles/$contactosDisponibles.

    Gateada por `operaciones.orden.crear`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('operaciones.ordenes.titulo_crear')" :tema="$tema">
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
        :vista-actual="__('operaciones.ordenes.titulo_crear')"
    >
        @include('operaciones::pages.ordenes._formulario', ['orden' => null])
    </x-templates.panel-layout>
</x-templates.panel-shell>
