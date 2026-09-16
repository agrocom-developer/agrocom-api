{{--
    Page: clientes/create (GET /panel/clientes/crear, panel.clientes.create)
    Alta de un cliente con sus contactos (HU-22, tarea 33): el formulario
    real vive en `_formulario.blade.php`, compartido con `edit.blade.php`.

    Datos esperados (ver ClientesController::create()): la cáscara de
    CascaraPanel, más `$tiposContacto` (list<TipoContactoCliente>).

    Gateada por `comercial.cliente.crear`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('comercial.clientes.titulo_crear')" :tema="$tema">
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
        :vista-actual="__('comercial.clientes.titulo_crear')"
    >
        @include('comercial::pages.clientes._formulario', ['cliente' => null, 'volverA' => $volverA ?? null])
    </x-templates.panel-layout>
</x-templates.panel-shell>
