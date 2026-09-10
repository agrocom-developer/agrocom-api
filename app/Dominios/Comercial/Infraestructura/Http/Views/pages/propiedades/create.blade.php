{{--
    Page: propiedades/create (GET /panel/propiedades/crear, panel.propiedades.create)
    Alta de una propiedad (ADR 0018): el formulario real vive en
    `_formulario.blade.php`, compartido con `edit.blade.php`.

    Datos esperados (ver PropiedadesController::create()): la cáscara de
    CascaraPanel, más `$clientesDisponibles` (Collection<int, string>).

    Gateada por `comercial.propiedad.crear`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('comercial.propiedades.titulo_crear')" :tema="$tema">
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
        :vista-actual="__('comercial.propiedades.titulo_crear')"
    >
        @include('comercial::pages.propiedades._formulario', ['propiedad' => null])
    </x-templates.panel-layout>
</x-templates.panel-shell>
