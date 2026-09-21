{{--
    Page: fichas-dron/create (GET /panel/fichas-dron/crear, panel.fichas-dron.create)
    Alta de una ficha de inventario de dron (HU-82, tarea 97): el formulario
    real vive en `_formulario.blade.php`, compartido con `edit.blade.php`.

    Datos esperados (ver FichasDronController::create()): la cáscara de
    CascaraPanel, más $identificadorSugerido (string): el identificador del dron
    cuando se llega por el atajo «Crear ficha» de su ficha; vacío si no.

    Gateada por `mantenimiento.ficha_dron.crear`, verificado server-side en
    el controlador.
--}}
<x-templates.panel-shell :title="__('mantenimiento.fichas_dron.titulo_crear')" :tema="$tema">
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
        :vista-actual="__('mantenimiento.fichas_dron.titulo_crear')"
    >
        @include('mantenimiento::pages.fichas-dron._formulario', ['ficha' => null, 'identificadorSugerido' => $identificadorSugerido])
    </x-templates.panel-layout>
</x-templates.panel-shell>
