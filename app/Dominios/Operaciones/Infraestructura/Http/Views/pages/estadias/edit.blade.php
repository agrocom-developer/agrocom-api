{{--
    Page: estadias/edit (GET /panel/estadias/{estadia}/editar, panel.estadias.edit)
    Ficha de una estadía en hacienda — arquetipo Formulario, §6.3 de
    docs/diseno/guia_pantalla_panel.md. Cáscara delgada: el formulario vive en
    `_formulario.blade.php`, compartido con create.

    El cambio de estado (En curso → Finalizada) lo piden los pasos de
    `molecules/step-arrow` del formulario; su `<form>` y su modal viven en
    `_cambio-estado.blade.php`, DESPUÉS del formulario y no adentro: un
    `<form>` no puede anidarse en otro (§6.3.4).

    Datos esperados: ver EstadiasHaciendaController::edit().
    Gateada por `operaciones.estadia.editar`, verificado en el controlador.
--}}
<x-templates.panel-shell :title="__('operaciones.estadias.titulo_editar')" :tema="$tema">
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
        :vista-actual="__('operaciones.estadias.titulo_editar')"
    >
        @include('operaciones::pages.estadias._formulario', ['estadia' => $estadia])
        @include('operaciones::pages.estadias._cambio-estado', ['estadia' => $estadia, 'pasosEstado' => $pasosEstado])
    </x-templates.panel-layout>
</x-templates.panel-shell>
