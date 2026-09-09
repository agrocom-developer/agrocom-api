{{--
    Page: repuestos/edit (GET /panel/repuestos/{repuesto}/editar, panel.repuestos.edit)
    Edición de un repuesto (HU-36, tarea 52): el formulario real vive en
    `_formulario.blade.php`, compartido con `create.blade.php`.

    Datos esperados (ver RepuestosController::edit()): la cáscara de
    CascaraPanel, más $repuesto (Repuesto).

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
        :campaniaActiva="$campaniaActiva"
        :periodo="$periodo"
        :version="$version"
        :vista-actual="__('inventario.repuestos.titulo_editar')"
    >
        @include('inventario::pages.repuestos._formulario', ['repuesto' => $repuesto])
    </x-templates.panel-layout>
</x-templates.panel-shell>
