{{--
    Page: personas/edit (GET /panel/personas/{persona}/editar, panel.personas.edit)
    Edición de una persona operativa (HU-26, tarea 37): el formulario real
    vive en `_formulario.blade.php`, compartido con `create.blade.php`.

    Datos esperados (ver PersonasController::edit()): la cáscara de
    CascaraPanel, más $persona (PerPersona), $roles (list<RolOperativoPersona>)
    y $basesDisponibles (Collection<int, string>).

    Gateada por `personal.persona.editar`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('personal.personas.titulo_editar')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :campaniaActiva="$campaniaActiva"
        :periodo="$periodo"
        :version="$version"
        :vista-actual="__('personal.personas.titulo_editar')"
    >
        @include('personal::pages.personas._formulario', ['persona' => $persona])
    </x-templates.panel-layout>
</x-templates.panel-shell>
