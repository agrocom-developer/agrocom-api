{{--
    Page: gastos/create (GET /panel/gastos/crear, panel.gastos.create)
    Alta de un gasto (HU-33, tarea 47): el formulario real vive en
    `_formulario.blade.php`, compartido con `edit.blade.php` (tarea 134).

    Datos esperados (ver GastosController::create()): la cáscara de
    CascaraPanel, más los del formulario ($rubrosConSubrubros,
    $equiposDisponibles, $basesDisponibles, $trabajosDisponibles,
    $campaniasDisponibles) — todo lo que el `@include` hereda tal cual.

    Gateada por `finanzas.gasto.crear`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('finanzas.gastos.titulo_crear')" :tema="$tema">
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
        :vista-actual="__('finanzas.gastos.titulo_crear')"
    >
        @include('finanzas::pages.gastos._formulario', ['gasto' => null])
    </x-templates.panel-layout>
</x-templates.panel-shell>
