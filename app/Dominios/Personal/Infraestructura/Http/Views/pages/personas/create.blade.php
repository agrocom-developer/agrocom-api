{{--
    Page: personas/create (GET /panel/personas/crear, panel.personas.create)
    Alta de una persona operativa (HU-26, tarea 37): el formulario real vive
    en `_formulario.blade.php`, compartido con `edit.blade.php`.

    Datos esperados (ver PersonasController::create()): la cáscara de
    CascaraPanel, más `$roles` (list<RolOperativoPersona>) y
    `$basesDisponibles` (Collection<int, string>).

    Gateada por `personal.persona.crear`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('personal.personas.titulo_crear')" :tema="$tema">
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
        :vista-actual="__('personal.personas.titulo_crear')"
    >
        @include('personal::pages.personas._formulario', ['persona' => null])
    </x-templates.panel-layout>
</x-templates.panel-shell>
