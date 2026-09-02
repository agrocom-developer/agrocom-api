{{--
    Page: campos/edit (GET /panel/campos/{campo}/editar, panel.campos.edit)
    Edición de un campo con sus lotes (HU-24, tarea 35): el formulario real
    vive en `_formulario.blade.php`, compartido con `create.blade.php`.

    Datos esperados (ver CamposController::edit()): la cáscara de
    CascaraPanel, más:
    - $campo (Campo, con `lotes` cargada).
    - $clientesDisponibles (Collection<int, string>).

    Gateada por `comercial.campo.editar`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('comercial.campos.titulo_editar')" :tema="$tema">
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
        :vista-actual="__('comercial.campos.titulo_editar')"
    >
        @include('comercial::pages.campos._formulario', ['campo' => $campo])
    </x-templates.panel-layout>
</x-templates.panel-shell>
