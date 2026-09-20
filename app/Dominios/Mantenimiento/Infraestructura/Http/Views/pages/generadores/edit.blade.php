{{--
    Page: generadores/edit (GET /panel/generadores/{generador}/editar, panel.generadores.edit)
    Edición de un generador (tarea 72, HU-49): el formulario real vive en
    `_formulario.blade.php`, compartido con `create.blade.php`.

    Datos esperados (ver GeneradoresController::edit()): la cáscara de
    CascaraPanel, más $generador (Generador), $basesDisponibles
    (Collection<int, string>), $estados (list<EstadoGenerador>) y
    $resumenRelacionado (tarjetas del aside, resueltas por el controlador).

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
        :zona-horaria="$zonaHoraria ?? null"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :version="$version"
        :vista-actual="__('mantenimiento.generadores.titulo_editar')"
    >
        @include('mantenimiento::pages.generadores._formulario', ['generador' => $generador, 'basesDisponibles' => $basesDisponibles, 'estados' => $estados, 'resumenRelacionado' => $resumenRelacionado])
    </x-templates.panel-layout>
</x-templates.panel-shell>
