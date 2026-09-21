{{--
    Page: lotes/edit (GET /panel/lotes/{lote}/editar, panel.lotes.edit)
    Edición de un lote suelto (tarea 77, HU-54, etapa 2): el formulario real
    vive en `_formulario.blade.php`, compartido con `create.blade.php`.

    Datos esperados (ver LotesController::edit()): la cáscara de
    CascaraPanel, más:
    - $lote (Lote, con `campo` cargada).
    - $clientesDisponibles, $propiedadesDisponibles.

    Gateada por `comercial.lote.editar`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('comercial.lotes.titulo_editar')" :tema="$tema">
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
        :vista-actual="__('comercial.lotes.titulo_editar')"
    >
        @include('comercial::pages.lotes._formulario', ['lote' => $lote, 'propiedadIdPreseleccionado' => null, 'volverA' => $volverA ?? null])
    </x-templates.panel-layout>
</x-templates.panel-shell>
