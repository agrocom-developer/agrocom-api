{{--
    Page: campanias/create (GET /panel/campanias/crear, panel.campanias.create)
    Alta de una campaña (ADR 0015 punto 1, tarea 69): el formulario real vive
    en `_formulario.blade.php`, compartido con `edit.blade.php`.

    Datos esperados (ver CampaniasController::create()): solo la cáscara de
    CascaraPanel.

    Gateada por `campania.campania.crear`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('campania.campanias.titulo_crear')" :tema="$tema">
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
        :vista-actual="__('campania.campanias.titulo_crear')"
    >
        @include('campania::pages.campanias._formulario', ['campania' => null])
    </x-templates.panel-layout>
</x-templates.panel-shell>
