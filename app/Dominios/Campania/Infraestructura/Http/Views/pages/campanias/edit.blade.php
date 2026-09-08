{{--
    Page: campanias/edit (GET /panel/campanias/{campania}/editar, panel.campanias.edit)
    Edición de una campaña (ADR 0015 punto 1, tarea 69): el formulario real
    vive en `_formulario.blade.php`, compartido con `create.blade.php`.

    Datos esperados (ver CampaniasController::edit()): la cáscara de
    CascaraPanel, más $campania (Campania).

    Gateada por `campania.campania.editar`, verificado server-side en el
    controlador. El cambio de ESTADO no vive acá — es
    `panel.campanias.cambiar-estado`, otra pantalla, otra responsabilidad
    (invariante 7), mismo criterio que `contratos/edit.blade.php`.
--}}
<x-templates.panel-shell :title="__('campania.campanias.titulo_editar')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :campaniaActiva="$campaniaActiva"
        :periodo="$periodo"
        :version="$version"
        :vista-actual="__('campania.campanias.titulo_editar')"
    >
        @include('campania::pages.campanias._formulario', ['campania' => $campania])
    </x-templates.panel-layout>
</x-templates.panel-shell>
