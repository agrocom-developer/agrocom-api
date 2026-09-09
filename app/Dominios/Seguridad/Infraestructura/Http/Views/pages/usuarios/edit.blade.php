{{--
    Page: usuarios/edit (GET /panel/usuarios/{usuario}/editar, panel.usuarios.edit)
    Edición de una cuenta de usuario interna (HU-45, tarea 39): el formulario
    real vive en `_formulario.blade.php`, compartido con `create.blade.php`.

    Datos esperados (ver UsuariosController::edit()): la cáscara de
    CascaraPanel, más $usuario (SecUser), $rolesAsignados (list<int>),
    $rolesDisponibles (Collection<int, SecRole>) y $personasDisponibles
    (Collection<int, string>).

    Gateada por `seguridad.usuario.editar`, verificado server-side en el
    controlador.
--}}
<x-templates.panel-shell :title="__('seguridad.usuarios.titulo_editar')" :tema="$tema">
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
        :vista-actual="__('seguridad.usuarios.titulo_editar')"
    >
        @include('seguridad::pages.usuarios._formulario', ['usuario' => $usuario])
    </x-templates.panel-layout>
</x-templates.panel-shell>
