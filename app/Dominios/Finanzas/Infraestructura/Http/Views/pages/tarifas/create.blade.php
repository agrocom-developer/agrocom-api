{{--
    Page: tarifas/create (GET /panel/tarifas/crear, panel.tarifas.create)
    Alta de una tarifa (ADR 0023): el formulario real vive en
    `_formulario.blade.php`, compartido con `edit.blade.php`.

    Datos esperados (ver TarifasController::create()): solo la cáscara de
    CascaraPanel.

    Gateada por `finanzas.tarifa.crear`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('finanzas.tarifas.crear_titulo')" :tema="$tema">
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
        :vista-actual="__('finanzas.tarifas.crear_titulo')"
    >
        @include('finanzas::pages.tarifas._formulario', ['tarifa' => null])
    </x-templates.panel-layout>
</x-templates.panel-shell>
