{{--
    Page: baterias/create (GET /panel/baterias/crear, panel.baterias.create)
    Alta de una batería (HU-39, tarea 51): el formulario real vive en
    `_formulario.blade.php`, compartido con `edit.blade.php`.

    Datos esperados (ver BateriasController::create()): la cáscara de
    CascaraPanel, más $basesDisponibles (Collection<int, string>) y
    $estados (list<EstadoBateria>).

    Gateada por `mantenimiento.bateria.crear`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('mantenimiento.baterias.titulo_crear')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :zona-horaria="$zonaHoraria ?? null"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :campaniaActiva="$campaniaActiva"
        :periodo="$periodo"
        :version="$version"
        :vista-actual="__('mantenimiento.baterias.titulo_crear')"
    >
        @include('mantenimiento::pages.baterias._formulario', ['bateria' => null, 'basesDisponibles' => $basesDisponibles, 'estados' => $estados])
    </x-templates.panel-layout>
</x-templates.panel-shell>
