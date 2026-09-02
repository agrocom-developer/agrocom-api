{{--
    Page: contratos/create (GET /panel/contratos/crear, panel.contratos.create)
    Alta de un contrato con sus ventanas de aplicación (HU-23, tarea 34): el
    formulario real vive en `_formulario.blade.php`, compartido con
    `edit.blade.php`.

    Datos esperados (ver ContratosController::create()): la cáscara de
    CascaraPanel, más `$clientesDisponibles` (Collection<int, string>).

    Gateada por `comercial.contrato.crear`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('comercial.contratos.titulo_crear')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :campana="$campana"
        :periodo="$periodo"
        :version="$version"
        :vista-actual="__('comercial.contratos.titulo_crear')"
    >
        @include('comercial::pages.contratos._formulario', ['contrato' => null])
    </x-templates.panel-layout>
</x-templates.panel-shell>
