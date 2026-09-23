{{--
    Page: rendiciones/create (GET /panel/rendiciones/crear, panel.rendiciones.create)
    Alta de una rendición (HU-34, tarea 48): el formulario real vive en
    `_formulario.blade.php`, compartido con `edit.blade.php` (tarea 134).

    Datos esperados (ver RendicionesController::create()): la cáscara de
    CascaraPanel, más $basesDisponibles / $personasDisponibles
    (Collection<int, string>) — todo lo que el `@include` hereda tal cual.

    Estilos en resources/css/pages/rendiciones.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('finanzas.rendiciones.titulo_crear')" :tema="$tema">
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
        :vista-actual="__('finanzas.rendiciones.titulo_crear')"
    >
        @include('finanzas::pages.rendiciones._formulario', ['rendicion' => null])
    </x-templates.panel-layout>
</x-templates.panel-shell>
