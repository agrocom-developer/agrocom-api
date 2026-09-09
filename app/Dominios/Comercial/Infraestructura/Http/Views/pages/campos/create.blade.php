{{--
    Page: campos/create (GET /panel/campos/crear, panel.campos.create)
    Alta de un campo con sus lotes (HU-24, tarea 35): el formulario real vive
    en `_formulario.blade.php`, compartido con `edit.blade.php`.

    Datos esperados (ver CamposController::create()): la cáscara de
    CascaraPanel, más `$clientesDisponibles` (Collection<int, string>).

    Gateada por `comercial.campo.crear`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('comercial.campos.titulo_crear')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :zona-horaria="$zonaHoraria ?? null"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :campaniaActiva="$campaniaActiva"
        :periodo="$periodo"
        :version="$version"
        :vista-actual="__('comercial.campos.titulo_crear')"
    >
        @include('comercial::pages.campos._formulario', ['campo' => null])
    </x-templates.panel-layout>
</x-templates.panel-shell>
