{{--
    Page: campanias/edit (GET /panel/campanias/{campania}/editar, panel.campanias.edit)
    Edición de una campaña (ADR 0015 punto 1, tarea 69): el formulario real
    vive en `_formulario.blade.php`, compartido con `create.blade.php`.

    Datos esperados (ver CampaniasController::edit()): la cáscara de
    CascaraPanel, más $campania (Campania), $pasosEstado (los pasos de
    `molecules/step-arrow`), $ayudaEstado (el párrafo que los acompaña) y
    $resumenCampania.

    Gateada por `campania.campania.editar`, verificado server-side en el
    controlador. El cambio de ESTADO no se procesa acá — es
    `panel.campanias.cambiar-estado`, otra pantalla, otra responsabilidad
    (invariante 7), mismo criterio que `contratos/edit.blade.php`: los pasos
    del formulario solo abren el modal de confirmación, cuyos `<form>` van en
    `_cambio-estado.blade.php`, después del formulario y no adentro.
--}}
<x-templates.panel-shell :title="__('campania.campanias.titulo_editar')" :tema="$tema">
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
        :vista-actual="__('campania.campanias.titulo_editar')"
    >
        @include('campania::pages.campanias._formulario', ['campania' => $campania])
        @include('campania::pages.campanias._cambio-estado', ['campania' => $campania, 'pasosEstado' => $pasosEstado])
    </x-templates.panel-layout>
</x-templates.panel-shell>
