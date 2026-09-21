{{--
    Page: estadias/create (GET /panel/estadias/crear, panel.estadias.create)
    Alta de una estadía en hacienda desde la oficina — arquetipo Formulario,
    §6.3 de docs/diseno/guia_pantalla_panel.md. Cáscara delgada: el
    formulario en sí vive en `_formulario.blade.php`, compartido con edit.

    Datos esperados: ver EstadiasHaciendaController::create().
    Gateada por `operaciones.estadia.crear`, verificado en el controlador.
--}}
<x-templates.panel-shell :title="__('operaciones.estadias.titulo_crear')" :tema="$tema">
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
        :vista-actual="__('operaciones.estadias.titulo_crear')"
    >
        @include('operaciones::pages.estadias._formulario', ['estadia' => null])
    </x-templates.panel-layout>
</x-templates.panel-shell>
