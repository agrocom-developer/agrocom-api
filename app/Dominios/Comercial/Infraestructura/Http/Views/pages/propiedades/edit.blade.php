{{--
    Page: propiedades/edit (GET /panel/propiedades/{propiedad}/editar, panel.propiedades.edit)
    Edición de una propiedad (ADR 0018): el formulario real vive en
    `_formulario.blade.php`, compartido con `create.blade.php`.

    Datos esperados (ver PropiedadesController::edit()): la cáscara de
    CascaraPanel, más:
    - $propiedad (Propiedad).
    - $clientesDisponibles (Collection<int, string>).

    Gateada por `comercial.propiedad.editar`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('comercial.propiedades.titulo_editar')" :tema="$tema">
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
        :vista-actual="__('comercial.propiedades.titulo_editar')"
    >
        @include('comercial::pages.propiedades._formulario', ['propiedad' => $propiedad])
    </x-templates.panel-layout>
</x-templates.panel-shell>
