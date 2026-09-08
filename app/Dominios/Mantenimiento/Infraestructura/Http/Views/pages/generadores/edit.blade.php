{{--
    Page: generadores/edit (GET /panel/generadores/{generador}/editar, panel.generadores.edit)
    Edición de un generador (tarea 72, HU-49): el formulario real vive en
    `_formulario.blade.php`, compartido con `create.blade.php`.

    Datos esperados (ver GeneradoresController::edit()): la cáscara de
    CascaraPanel, más $generador (Generador) y $basesDisponibles
    (Collection<int, string>) y $estados (list<EstadoGenerador>).

    Gateada por `mantenimiento.generador.editar`, verificado server-side en
    el controlador.
--}}
<x-templates.panel-shell :title="__('mantenimiento.generadores.titulo_editar')" :tema="$tema">
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
        :vista-actual="__('mantenimiento.generadores.titulo_editar')"
    >
        @include('mantenimiento::pages.generadores._formulario', ['generador' => $generador, 'basesDisponibles' => $basesDisponibles, 'estados' => $estados])
    </x-templates.panel-layout>
</x-templates.panel-shell>
