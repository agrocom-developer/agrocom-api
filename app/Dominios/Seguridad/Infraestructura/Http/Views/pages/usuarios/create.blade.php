{{--
    Page: usuarios/create (GET /panel/usuarios/crear, panel.usuarios.create)
    Alta de una cuenta de usuario interna (HU-45, tarea 39): el formulario
    real vive en `_formulario.blade.php`, compartido con `edit.blade.php`.

    Datos esperados (ver UsuariosController::create()): la cáscara de
    CascaraPanel, más `$rolesDisponibles` (Collection<int, SecRole>) y
    `$personasDisponibles` (Collection<int, string>).

    Gateada por `seguridad.usuario.crear`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('seguridad.usuarios.titulo_crear')" :tema="$tema">
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
        :vista-actual="__('seguridad.usuarios.titulo_crear')"
    >
        @include('seguridad::pages.usuarios._formulario', ['usuario' => null, 'rolesAsignados' => []])
    </x-templates.panel-layout>
</x-templates.panel-shell>
