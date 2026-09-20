{{--
    Page: cuadrillas/create (GET /panel/cuadrillas/crear, panel.cuadrillas.create)
    Alta de un equipo de trabajo (tarea 72, HU-49): el formulario real vive
    en `_formulario.blade.php`, compartido con `edit.blade.php`.

    Datos esperados (ver CuadrillasController::create()): la cáscara de
    CascaraPanel, más $basesDisponibles y $estados.

    Gateada por `personal.equipo_trabajo.crear`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('personal.equipos_trabajo.titulo_crear')" :tema="$tema">
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
        :vista-actual="__('personal.equipos_trabajo.titulo')"
    >
        @include('personal::pages.cuadrillas._formulario', ['equipo' => null])
    </x-templates.panel-layout>
</x-templates.panel-shell>
