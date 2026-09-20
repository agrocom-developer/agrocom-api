{{--
    Page: repuestos/edit (GET /panel/repuestos/{repuesto}/editar, panel.repuestos.edit)
    Edición de un repuesto (HU-36, tarea 52): el formulario real vive en
    `_formulario.blade.php`, compartido con `create.blade.php`.

    Datos esperados (ver RepuestosController::edit()): la cáscara de
    CascaraPanel, más $repuesto (Repuesto) y $resumenRelacionado (las tarjetas
    del aside, que el partial toma del ámbito de esta vista).

    Gateada por `inventario.repuesto.editar`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('inventario.repuestos.titulo_editar')" :tema="$tema">
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
        :vista-actual="__('inventario.repuestos.titulo_editar')"
    >
        @include('inventario::pages.repuestos._formulario', ['repuesto' => $repuesto])
    </x-templates.panel-layout>
</x-templates.panel-shell>
