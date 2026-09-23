{{--
    Page: combustible/create (GET /panel/combustible/crear, panel.combustible.create)
    Alta de una carga de combustible (HU-35, tarea 49; reescrita por la tarea
    73, HU-50): el formulario real vive en `_formulario.blade.php`,
    compartido con `edit.blade.php` (tarea 134).

    Datos esperados (ver CombustibleController::create()): la cáscara de
    CascaraPanel, más los del formulario ($basesDisponibles,
    $equiposDisponibles, $campaniasDisponibles, $equipoTrabajoIdSeleccionado,
    $fechaSeleccionada, $recursosDisponibles) — todo lo que el `@include`
    hereda tal cual.

    Gateada por `finanzas.combustible.crear`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('finanzas.combustible.titulo_crear')" :tema="$tema">
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
        :vista-actual="__('finanzas.combustible.titulo_crear')"
    >
        @include('finanzas::pages.combustible._formulario')
    </x-templates.panel-layout>
</x-templates.panel-shell>
