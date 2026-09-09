{{--
    Template: portal-layout (HU-41, tarea 55)
    Cáscara MÍNIMA del portal del cliente (ADR 0002 punto 6: "reutiliza el
    mismo layout con un guard de autenticación separado, sin acceso a los
    menús internos"): a diferencia de `panel-layout` (riel de módulos +
    sidebar, armados desde `sec_menu` del rol activo), acá no hay rol ni
    menú que listar — una cuenta de portal no tiene `sec_user_role`. Por eso
    este NO es panel-layout con `menu=[]`: es un header simple de una sola
    fila (marca + 3 links fijos + tema + cerrar sesión) en vez del layout de
    tres niveles, que quedaría vacío/roto sin `sec_menu` que resolver.

    Props:
    - userName (nullable string): nombre de la cuenta de portal
      (AutorizacionPortalCliente::cascara()).
    - vistaActual (requerido): link activo del nav, ya traducido — se resalta
      comparando contra la ruta actual, mismo criterio que `panel-layout`
      pero sin árbol que normalizar (son 3 links fijos).

    Slot (default): contenido de la página, dentro de <main>.
--}}
@props([
    'userName' => null,
    'vistaActual' => null,
])

@php
    $rutaActual = \Illuminate\Support\Facades\Route::currentRouteName();

    $links = [
        ['ruta' => 'portal.avance.index', 'icono' => 'trending_up', 'label' => __('portal.chrome.nav_avance')],
        ['ruta' => 'portal.actas.index', 'icono' => 'description', 'label' => __('portal.chrome.nav_actas')],
        ['ruta' => 'portal.reportes.index', 'icono' => 'summarize', 'label' => __('portal.chrome.nav_reportes')],
    ];
@endphp

<div class="ag-portal">
    <header class="ag-portal__header">
        <div class="ag-portal__brand">
            <x-atoms.logo size="sm" />
            <span class="ag-portal__brand-label">{{ __('portal.chrome.titulo') }}</span>
        </div>

        <nav class="ag-portal__nav" aria-label="{{ __('portal.chrome.nav_aria_label') }}">
            @foreach ($links as $link)
                <a
                    href="{{ route($link['ruta']) }}"
                    class="ag-portal__nav-link {{ str_starts_with((string) $rutaActual, $link['ruta']) ? 'is-active' : '' }}"
                    @if (str_starts_with((string) $rutaActual, $link['ruta'])) aria-current="page" @endif
                >
                    <x-atoms.icon :name="$link['icono']" size="sm" />
                    {{ $link['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="ag-portal__actions">
            @if ($userName)
                <a href="{{ route('portal.perfil.edit') }}" class="ag-portal__user" title="{{ __('seguridad.perfil.menu_item') }}">
                    <x-atoms.icon name="person" size="sm" />
                    {{ $userName }}
                </a>
            @endif

            <x-molecules.theme-toggle />

            <button
                type="button"
                class="ag-portal__logout"
                data-ag-logout
                data-ag-logout-url="{{ route('portal.logout') }}"
                data-ag-logout-redirect="{{ route('portal.login.form') }}"
            >
                <x-atoms.icon name="logout" size="sm" />
                {{ __('portal.chrome.cerrar_sesion') }}
            </button>
        </div>
    </header>

    <main class="ag-portal__content">
        {{ $slot }}
    </main>

    <footer class="ag-portal__footer">
        <span>{{ __('ui.footer.copyright', ['year' => date('Y')]) }}</span>
    </footer>
</div>
