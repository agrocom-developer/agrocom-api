{{--
    Page: rendiciones/edit (GET /panel/rendiciones/{rendicion}/editar, panel.rendiciones.edit)
    Edición de la cabecera de una rendición (tarea 134): el formulario real
    vive en `_formulario.blade.php`, compartido con `create.blade.php`.

    Datos esperados (ver RendicionesController::edit()): la cáscara de
    CascaraPanel, más $rendicion (Rendicion) y $basesDisponibles /
    $personasDisponibles (Collection<int, string>) — todo lo que el
    `@include` hereda tal cual.

    Gateada por `finanzas.rendicion.presentar` (reusado, tarea 134: no existe
    `.editar`) Y `Dominio/PoliticaEdicionRendicion::admiteEdicion()` — si la
    rendición ya no está `Abierta`, el controlador redirige a la ficha antes
    de llegar acá.
--}}
<x-templates.panel-shell :title="__('finanzas.rendiciones.titulo_editar')" :tema="$tema">
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
        :vista-actual="__('finanzas.rendiciones.titulo_editar')"
    >
        @include('finanzas::pages.rendiciones._formulario', ['rendicion' => $rendicion])
    </x-templates.panel-layout>
</x-templates.panel-shell>
