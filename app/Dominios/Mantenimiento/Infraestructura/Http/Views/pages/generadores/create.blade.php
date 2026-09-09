{{--
    Page: generadores/create (GET /panel/generadores/crear, panel.generadores.create)
    Alta de un generador (tarea 72, HU-49): el formulario real vive en
    `_formulario.blade.php`, compartido con `edit.blade.php`.

    Datos esperados (ver GeneradoresController::create()): la cáscara de
    CascaraPanel, más $basesDisponibles (Collection<int, string>) y $estados
    (list<EstadoGenerador>).

    Gateada por `mantenimiento.generador.crear`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('mantenimiento.generadores.titulo_crear')" :tema="$tema">
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
        :vista-actual="__('mantenimiento.generadores.titulo_crear')"
    >
        @include('mantenimiento::pages.generadores._formulario', ['generador' => null, 'basesDisponibles' => $basesDisponibles, 'estados' => $estados])
    </x-templates.panel-layout>
</x-templates.panel-shell>
