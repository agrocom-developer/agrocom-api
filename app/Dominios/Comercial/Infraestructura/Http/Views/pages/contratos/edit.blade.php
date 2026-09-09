{{--
    Page: contratos/edit (GET /panel/contratos/{contrato}/editar, panel.contratos.edit)
    Edición de un contrato con sus ventanas de aplicación (HU-23, tarea 34):
    el formulario real vive en `_formulario.blade.php`, compartido con
    `create.blade.php`.

    Datos esperados (ver ContratosController::edit()): la cáscara de
    CascaraPanel, más:
    - $contrato (Contrato, con `ventanas` cargada).
    - $clientesDisponibles (Collection<int, string>).

    Gateada por `comercial.contrato.editar`, verificado server-side en el
    controlador. El cambio de ESTADO no vive acá — es otra pantalla
    (`panel.contratos.cambiar-estado`, invocada desde el listado).
--}}
<x-templates.panel-shell :title="__('comercial.contratos.titulo_editar')" :tema="$tema">
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
        :vista-actual="__('comercial.contratos.titulo_editar')"
    >
        @include('comercial::pages.contratos._formulario', ['contrato' => $contrato])
    </x-templates.panel-layout>
</x-templates.panel-shell>
