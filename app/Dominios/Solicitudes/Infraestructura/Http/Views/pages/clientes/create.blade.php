{{--
    Page: clientes/create (GET /panel/clientes/crear, panel.clientes.create)
    Alta a mano de un cliente para quien llega a la oficina: el formulario real
    vive en `_formulario.blade.php`.

    Datos esperados (ver ClientesController::create()): la cáscara de
    CascaraPanel, más $tipos (list<TipoCliente>) y $filasDeContacto (int).

    Gateada por `solicitudes.cliente.crear`, verificado server-side.
--}}
<x-templates.panel-shell :title="__('solicitudes.clientes.registrar_titulo')" :tema="$tema">
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
        :vista-actual="__('solicitudes.clientes.registrar_titulo')"
    >
        @include('solicitudes::pages.clientes._formulario')
    </x-templates.panel-layout>
</x-templates.panel-shell>
