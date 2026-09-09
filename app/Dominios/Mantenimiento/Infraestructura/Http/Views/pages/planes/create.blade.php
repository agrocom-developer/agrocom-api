{{--
    Page: planes/create (GET /panel/planes-mantenimiento/crear, panel.planes-mantenimiento.create)
    Alta de un plan de mantenimiento preventivo (HU-38, tarea 54): el
    formulario real vive en `_formulario.blade.php`, compartido con
    `edit.blade.php`.

    Datos esperados (ver PlanesMantenimientoController::create()): la
    cáscara de CascaraPanel, sin datos adicionales — `modelo` es texto
    libre, sin catálogo que resolver.

    Gateada por `mantenimiento.plan.crear`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('mantenimiento.planes.titulo_crear')" :tema="$tema">
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
        :vista-actual="__('mantenimiento.planes.titulo_crear')"
    >
        @include('mantenimiento::pages.planes._formulario', ['plan' => null])
    </x-templates.panel-layout>
</x-templates.panel-shell>
