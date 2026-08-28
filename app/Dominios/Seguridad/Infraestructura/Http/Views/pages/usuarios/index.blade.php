{{--
    Page: usuarios/index (GET /panel/usuarios, panel.usuarios.index)
    Placeholder mínimo — la gestión real de usuarios (CRUD) es una HU futura,
    fuera de alcance de HU-02. Este archivo solo evita que el ítem de menú
    "Usuarios" (Seguridad › Usuarios en sec_menu) sea un link roto.

    Datos esperados (ver UsuariosController::index()): la cáscara completa
    de CascaraPanel (menu/roles/…/tema/campana/periodo/version).
--}}
<x-templates.panel-shell :title="__('seguridad.usuarios.titulo')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :campana="$campana"
        :periodo="$periodo"
        :version="$version"
    >
        <h1>{{ __('seguridad.usuarios.titulo') }}</h1>
        <p>{{ __('seguridad.usuarios.proximamente') }}</p>
    </x-templates.panel-layout>
</x-templates.panel-shell>
