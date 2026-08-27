{{--
    Template: panel-layout (docs/diseno/sistema_diseno_panel.md §4.8)
    "Layout base (sidebar + content + footer)" que menciona ADR 0002 punto 1.
    Composición: sidebar-nav + topbar + <main> (slot) + footer.

    No decide qué pantalla se muestra (eso es `frontend`, por pantalla) — solo
    arma la cáscara y le pasa los mismos datos ya resueltos a sidebar-nav y
    topbar.

    Props:
    - menu (list, default []): ver sidebar-nav.
    - roles (list, default []): ver sidebar-nav/topbar.
    - rolActivoId (nullable).
    - activeRoleLabel (nullable string): ver topbar.
    - userName (nullable string): ver topbar.
    - sidebarId (default "ag-sidebar").

    Slot (default): contenido de la página, dentro de <main>.
--}}
@props([
    'menu' => [],
    'roles' => [],
    'rolActivoId' => null,
    'activeRoleLabel' => null,
    'userName' => null,
    'sidebarId' => 'ag-sidebar',
])

<div class="ag-panel-layout">
    <x-organisms.sidebar-nav :id="$sidebarId" :menu="$menu" :roles="$roles" :rol-activo-id="$rolActivoId" />

    <div class="ag-panel-layout__main">
        <x-organisms.topbar
            :sidebar-id="$sidebarId"
            :roles="$roles"
            :rol-activo-id="$rolActivoId"
            :active-role-label="$activeRoleLabel"
            :user-name="$userName"
        />

        <main class="ag-panel-layout__content">
            {{ $slot }}
        </main>

        <footer class="ag-panel-layout__footer">
            <span>{{ __('ui.footer.copyright', ['year' => date('Y')]) }}</span>
        </footer>
    </div>
</div>
