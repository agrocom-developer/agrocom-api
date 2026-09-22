{{--
    Page: tarifas/edit (GET /panel/tarifas/{tarifa}/editar, panel.tarifas.edit)
    Edición de una tarifa (ADR 0023): el formulario real vive en
    `_formulario.blade.php`, compartido con `create.blade.php`.

    Datos esperados (ver TarifasController::edit()): la cáscara de
    CascaraPanel, más $tarifa (Tarifa).

    Gateada por `finanzas.tarifa.editar`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('finanzas.tarifas.editar_titulo')" :tema="$tema">
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
        :vista-actual="__('finanzas.tarifas.editar_titulo')"
    >
        @include('finanzas::pages.tarifas._formulario', ['tarifa' => $tarifa])
    </x-templates.panel-layout>
</x-templates.panel-shell>
