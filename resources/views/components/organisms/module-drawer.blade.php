{{--
    Organism: module-drawer (quinta vuelta — móvil <768px, maqueta 5b)
    Off-canvas desde la izquierda con TODOS los módulos y sus ítems — es la
    navegación completa del panel en móvil (el riel y el sidebar de nivel 2
    no existen en ese breakpoint). Mecanismo: `offcanvas` NATIVO de
    Bootstrap 5.3 (JS ya cargado); cada módulo es un
    organisms/collapsible-menu-group (auto-abre el que contiene el ítem
    activo). Web, no app: sin barra inferior de navegación.

    Props:
    - modulos (list): normalizados por panel-layout (ver module-rail).
    - id (default "ag-module-drawer"): ancla del botón hamburguesa de
      organisms/mobile-topbar.
    - cambiarRolHref (nullable string): "Cambiar de rol" al pie.
--}}
@props([
    'modulos' => [],
    'id' => 'ag-module-drawer',
    'cambiarRolHref' => null,
])

<aside
    class="ag-drawer offcanvas offcanvas-start"
    tabindex="-1"
    id="{{ $id }}"
    aria-labelledby="{{ $id }}-label"
>
    <div class="ag-drawer__header">
        <span class="ag-drawer__brand">
            <x-atoms.logo size="sm" />
            <span class="ag-drawer__brand-name" id="{{ $id }}-label">{{ __('ui.logo.alt') }}</span>
        </span>

        <button
            type="button"
            class="ag-drawer__close"
            data-bs-dismiss="offcanvas"
            aria-label="{{ __('ui.drawer.cerrar') }}"
        >
            <x-atoms.icon name="close" size="sm" />
        </button>
    </div>

    <nav class="ag-drawer__nav" aria-label="{{ __('ui.rail.aria') }}">
        @foreach ($modulos as $index => $modulo)
            <x-organisms.collapsible-menu-group
                :label="$modulo['label']"
                :icon="$modulo['icono']"
                :items="collect($modulo['items'])->map(fn ($item) => [
                    'label' => $item['label'],
                    'icon' => $item['icono'],
                    'href' => $item['href'],
                    'active' => $item['active'],
                    'badge' => $item['badge'],
                ])->all()"
                :stagger-index="$index"
            />
        @endforeach
    </nav>

    @if ($cambiarRolHref)
        <div class="ag-drawer__footer">
            <a href="{{ $cambiarRolHref }}" class="ag-module-sidebar__role-link">
                <x-atoms.icon name="swap_horiz" size="sm" />
                <span>{{ __('seguridad.rol.switch_trigger') }}</span>
            </a>
        </div>
    @endif
</aside>
