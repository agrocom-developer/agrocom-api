{{--
    Page: lotes/create (GET /panel/lotes/crear, panel.lotes.create)
    Alta de un lote suelto (tarea 77, HU-54, etapa 2): el formulario real
    vive en `_formulario.blade.php`, compartido con `edit.blade.php`.

    Datos esperados (ver LotesController::create()): la cáscara de
    CascaraPanel, más `$clientesDisponibles` y `$propiedadesDisponibles`.

    Gateada por `comercial.lote.crear`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('comercial.lotes.titulo_crear')" :tema="$tema">
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
        :vista-actual="__('comercial.lotes.titulo_crear')"
    >
        @include('comercial::pages.lotes._formulario', ['lote' => null])
    </x-templates.panel-layout>
</x-templates.panel-shell>
