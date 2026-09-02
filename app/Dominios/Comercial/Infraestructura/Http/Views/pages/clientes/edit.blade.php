{{--
    Page: clientes/edit (GET /panel/clientes/{cliente}/editar, panel.clientes.edit)
    Edición de un cliente con sus contactos (HU-22, tarea 33): el formulario
    real vive en `_formulario.blade.php`, compartido con `create.blade.php`.

    Datos esperados (ver ClientesController::edit()): la cáscara de
    CascaraPanel, más:
    - $cliente (Cliente, con `contactos` cargada).
    - $tiposContacto (list<TipoContactoCliente>).

    Gateada por `comercial.cliente.editar`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('comercial.clientes.titulo_editar')" :tema="$tema">
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
        :vista-actual="__('comercial.clientes.titulo_editar')"
    >
        @include('comercial::pages.clientes._formulario', ['cliente' => $cliente])
    </x-templates.panel-layout>
</x-templates.panel-shell>
