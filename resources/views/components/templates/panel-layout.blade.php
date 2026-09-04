{{--
    Template: panel-layout (docs/diseno/sistema_diseno_panel.md §4.8 — quinta
    vuelta, layout de TRES NIVELES de las maquetas aprobadas 4a/5a/5b/5c):

    - Nivel 1 — riel de módulos (organisms/module-rail): 74px, superficie
      oliva oscura en ambos temas, solo los módulos que el rol activo puede
      ver (el árbol ya llega filtrado).
    - Nivel 2 — sidebar de ítems del módulo activo
      (organisms/module-sidebar): 252px, solo ≥1200px.
    - Nivel 3 — pestañas dentro del contenido: NO son de este template (las
      arma cada página, p. ej. el dashboard con Resumen/Sesiones/Pausas).

    Responsive (breakpoints 768/1200, ver panel-layout.css):
    - ≥1200: riel 74px + sidebar 252px + header completo.
    - 768–1199 (tablet, maqueta 5a): riel 64px, el sidebar desaparece y sus
      ítems pasan a la banda de píldoras horizontales bajo el header.
    - <768 (móvil, maqueta 5b): riel y header estándar desaparecen; header
      oscuro con hamburguesa que abre el drawer de módulos (offcanvas
      nativo de Bootstrap) + banda de breadcrumb. Web, no app: sin barra
      inferior ni botón flotante.

    Este template NORMALIZA el árbol de menú (resuelve `route()`, marca el
    ítem/módulo activo contra la ruta actual, cuelga los badges de demo) y
    reparte arrays planos a los organisms — que siguen siendo adaptadores
    delgados de presentación (ADR 0008). `ObtenerMenuPorRolActivo` no sabe
    de rutas resueltas ni de badges; acá termina esa responsabilidad.

    Props:
    - menu (list<ItemMenu>|array): árbol filtrado para el rol activo. Las
      raíces son los módulos del riel; sus hijos, los ítems del sidebar.
    - roles (list, default []): roles vivos del usuario — con 2+, el pie del
      sidebar y el menú de usuario muestran "Cambiar de rol" (link a la
      pantalla de selección con `?cambiar=1`).
    - activeRoleLabel / userName (nullable string).
    - notifications (list, default []): ver organisms/topbar.
    - menuBadges (array<string, array{numero: string, texto: string}>,
      default []): contadores de pendientes por clave `label` de sec_menu
      (demo — DatosDemoPanel). `numero` es lo que pinta el badge; `texto`,
      la frase completa que se resuelve como tooltip en menu-item.
    - campana / periodo / version (nullable string): chips del header y pie.
    - vistaActual (nullable string): segundo tramo del breadcrumb
      ("Módulo › Vista"), ya traducido por la página. Default: el label del
      ítem activo.

    Slot (default): contenido de la página, dentro de <main>.
--}}
@props([
    'menu' => [],
    'roles' => [],
    'rolActivoId' => null,
    'activeRoleLabel' => null,
    'userName' => null,
    'notifications' => [],
    'menuBadges' => [],
    'campana' => null,
    'periodo' => null,
    'version' => null,
    'vistaActual' => null,
])

@php
    $rutaActual = \Illuminate\Support\Facades\Route::currentRouteName();

    $resolverHref = function ($ruta) {
        if (! $ruta) {
            return null;
        }

        return \Illuminate\Support\Facades\Route::has($ruta) ? route($ruta) : $ruta;
    };

    // Un ítem de sec_menu solo registra la ruta de SU LISTADO ("panel.clientes.index"),
    // nunca la de sus sub-pantallas (crear/editar/show/...) — sembrarlas todas
    // sería repetir el árbol por cada acción. Sin esto, cualquier pantalla que
    // no sea literalmente ese listado (p. ej. `panel.clientes.create`) no
    // igualaba ninguna ruta de sec_menu y el riel caía al primer módulo de la
    // lista por descarte — "Operación" quedaba resaltado por casualidad de
    // posición, no porque la pantalla fuera suya (bug real: tarea 33, HU-22,
    // se veía "Operación" al crear un Cliente). Un ítem "panel.<recurso>.<accion>"
    // (3+ segmentos) también se considera activo si la ruta actual comparte
    // el prefijo "panel.<recurso>." — "panel.dashboard" (2 segmentos, sin
    // sub-pantallas) sigue exigiendo match exacto.
    $itemEsActivo = function (?string $ruta) use ($rutaActual): bool {
        if ($ruta === null || $rutaActual === null) {
            return false;
        }

        if ($ruta === $rutaActual) {
            return true;
        }

        $segmentos = explode('.', $ruta);

        if (count($segmentos) < 3) {
            return false;
        }

        $prefijo = implode('.', array_slice($segmentos, 0, -1));

        return str_starts_with($rutaActual, "{$prefijo}.");
    };

    // Normalización: módulos (raíces) con sus ítems, href resuelto, activo
    // por comparación con la ruta actual, badge de demo por clave de label.
    $modulos = collect($menu)->map(function ($mod) use ($itemEsActivo, $resolverHref, $menuBadges) {
        $items = collect(data_get($mod, 'hijos', []))->map(function ($item) use ($itemEsActivo, $resolverHref, $menuBadges) {
            $ruta = data_get($item, 'ruta');
            $label = data_get($item, 'label');

            return [
                'label' => $label,
                'icono' => data_get($item, 'icono'),
                'href' => $resolverHref($ruta),
                'active' => $itemEsActivo($ruta),
                // OJO: acceso directo al array, NUNCA data_get() acá — $label
                // ya es en sí mismo la clave completa (p. ej.
                // "menu.operacion.items.programacion") y data_get()
                // interpreta sus puntos como un path anidado, no como parte
                // de la clave literal.
                'badge' => $menuBadges[$label]['numero'] ?? null,
                'badgeTitle' => $menuBadges[$label]['texto'] ?? null,
            ];
        })->values();

        return [
            'label' => data_get($mod, 'label'),
            'descripcion' => data_get($mod, 'descripcion'),
            'icono' => data_get($mod, 'icono'),
            'items' => $items,
            'active' => $items->contains(fn ($item) => $item['active']),
        ];
    })->values();

    // Módulo activo: el que contiene el ítem de la ruta actual; si ninguno
    // (pantalla fuera del menú, p. ej. organización vía engranaje), el
    // primero — el riel nunca queda sin contexto.
    $moduloActivo = $modulos->firstWhere('active', true) ?? $modulos->first();
    $itemActivo = $moduloActivo !== null ? collect($moduloActivo['items'])->firstWhere('active', true) : null;

    $vistaActual ??= $itemActivo !== null ? __($itemActivo['label']) : null;

    $tieneVariosRoles = count($roles) > 1;
    $cambiarRolHref = $tieneVariosRoles ? route('panel.rol-activo.selector', ['cambiar' => 1]) : null;
    // Tarea 62 (fuga 2): el engranaje ya no se ofrece a un rol sin
    // `seguridad.organizacion.ver` — antes era un atajo visible para
    // cualquiera hacia una pantalla que hoy exige ese permiso.
    $configuracionHref = \Illuminate\Support\Facades\Route::has('panel.organizacion.index') && \App\Dominios\Seguridad\Infraestructura\Http\Presentacion\PermisoVista::puede('seguridad.organizacion.ver')
        ? route('panel.organizacion.index')
        : null;
    $drawerId = 'ag-module-drawer';
@endphp

<div class="ag-panel">
    <x-organisms.module-rail
        :modulos="$modulos"
        :configuracion-href="$configuracionHref"
    />

    <x-organisms.module-sidebar
        :modulo="$moduloActivo"
        :cambiar-rol-href="$cambiarRolHref"
    />

    <div class="ag-panel__main">
        <x-organisms.topbar
            :modulo-label="$moduloActivo !== null ? __($moduloActivo['label']) : null"
            :vista-actual="$vistaActual"
            :campana="$campana"
            :periodo="$periodo"
            :notifications="$notifications"
            :active-role-label="$activeRoleLabel"
            :user-name="$userName"
            :cambiar-rol-href="$cambiarRolHref"
        />

        <x-organisms.mobile-topbar
            :modulo-icono="$moduloActivo['icono'] ?? null"
            :modulo-label="$moduloActivo !== null ? __($moduloActivo['label']) : null"
            :vista-actual="$vistaActual"
            :active-role-label="$activeRoleLabel"
            :campana="$campana"
            :notifications="$notifications"
            :user-name="$userName"
            :drawer-id="$drawerId"
        />

        {{-- Banda de módulo (solo tablet, maqueta 5a): nombre del módulo +
             ítems como píldoras horizontales. Ojo: estilo propio de píldora
             (flex:0 0 auto + nowrap), nunca el del ítem vertical del
             sidebar, que lleva width:100% y rompería la fila. --}}
        @if ($moduloActivo !== null)
            <div class="ag-module-band">
                <h2 class="ag-module-band__title">{{ __($moduloActivo['label']) }}</h2>
                <div class="ag-module-band__pills">
                    @foreach ($moduloActivo['items'] as $item)
                        @if ($item['href'])
                            <a
                                href="{{ $item['href'] }}"
                                class="ag-module-band__pill {{ $item['active'] ? 'is-active' : '' }}"
                                @if ($item['active']) aria-current="page" @endif
                            >
                                @if ($item['icono'])
                                    <x-atoms.icon :name="$item['icono']" size="sm" />
                                @endif
                                {{ __($item['label']) }}
                            </a>
                        @else
                            <button type="button" class="ag-module-band__pill">
                                @if ($item['icono'])
                                    <x-atoms.icon :name="$item['icono']" size="sm" />
                                @endif
                                {{ __($item['label']) }}
                            </button>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        <main class="ag-panel__content">
            {{ $slot }}
        </main>

        <footer class="ag-panel__footer">
            <span>{{ __('ui.footer.copyright', ['year' => date('Y')]) }}</span>
            @if ($version)
                <span>{{ $version }}</span>
            @endif
        </footer>
    </div>

    <x-organisms.module-drawer
        :modulos="$modulos"
        :id="$drawerId"
        :cambiar-rol-href="$cambiarRolHref"
    />
</div>
