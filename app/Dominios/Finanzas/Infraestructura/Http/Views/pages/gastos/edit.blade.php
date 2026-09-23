{{--
    Page: gastos/edit (GET /panel/gastos/{gasto}/editar, panel.gastos.edit)
    Edición de un gasto (tarea 134): el formulario real vive en
    `_formulario.blade.php`, compartido con `create.blade.php`.

    Datos esperados (ver GastosController::edit()): la cáscara de
    CascaraPanel, más $gasto (Gasto) y los del formulario
    ($rubrosConSubrubros, $equiposDisponibles, $basesDisponibles,
    $trabajosDisponibles, $campaniasDisponibles) — todo lo que el
    `@include` hereda tal cual.

    Gateada por `finanzas.gasto.eliminar` (reusado, tarea 134: no existe
    `.editar`) Y `Dominio/PoliticaEdicionGasto::admiteEdicion()` — si la
    rendición asociada ya congeló el monto, el controlador redirige antes de
    llegar acá.
--}}
<x-templates.panel-shell :title="__('finanzas.gastos.titulo_editar')" :tema="$tema">
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
        :vista-actual="__('finanzas.gastos.titulo_editar')"
    >
        @include('finanzas::pages.gastos._formulario', ['gasto' => $gasto])
    </x-templates.panel-layout>
</x-templates.panel-shell>
