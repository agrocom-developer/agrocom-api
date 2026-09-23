{{--
    Page: combustible/edit (GET /panel/combustible/{combustible}/editar, panel.combustible.edit)
    Edición de una carga de combustible (tarea 134): el formulario real vive
    en `_formulario.blade.php`, compartido con `create.blade.php`.

    Datos esperados (ver CombustibleController::edit()): la cáscara de
    CascaraPanel, más $combustible (Combustible) y los del formulario
    ($basesDisponibles, $equiposDisponibles, $campaniasDisponibles,
    $equipoTrabajoIdSeleccionado, $fechaSeleccionada, $recursoActual,
    $recursosDisponibles) — todo lo que el `@include` hereda tal cual.

    Gateada por `finanzas.combustible.eliminar` (reusado, tarea 134: no
    existe `.editar`) — sin `rendicion_id`, sin política de dominio
    adicional que bloquee el acceso.
--}}
<x-templates.panel-shell :title="__('finanzas.combustible.titulo_editar')" :tema="$tema">
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
        :vista-actual="__('finanzas.combustible.titulo_editar')"
    >
        @include('finanzas::pages.combustible._formulario')
    </x-templates.panel-layout>
</x-templates.panel-shell>
