{{--
    Page: dashboard del panel (GET /panel/dashboard, panel.dashboard).

    Desde la tarea 67 esta página NO tiene contenido propio: es la cáscara
    (encabezado + pestañas) y decide qué parciales incluir a partir de
    `$secciones`, que `ArmarDashboard` armó según los permisos del ROL
    ACTIVO. Un piloto y un dueño abren la misma ruta y reciben distintas
    claves en ese arreglo — no hay una Blade por rol que mantener en
    paralelo.

    Cada pestaña y cada sección viven en su propio parcial bajo
    pages/dashboard/, con una responsabilidad cada uno. Acá no se calcula
    nada: ni porcentajes, ni tonos, ni totales — todo llega resuelto.

    Datos esperados: la cáscara de CascaraPanel (menu/roles/…/tema/periodo/
    version) + `secciones` (array<string, mixed>) y `visibles` (list<string>)
    de ArmarDashboard.
--}}
@php
    $tabs = collect([
        ['id' => 'resumen', 'label' => __('seguridad.dashboard.tab_resumen'), 'visible' => (bool) array_intersect($visibles, [
            'alertas', 'distribucion_sesiones', 'hectareas_por_dia', 'cola_validacion',
            'mis_sesiones', 'mis_equipos', 'mi_liquidacion', 'pausas', 'stock', 'avance_clientes',
        ])],
        ['id' => 'mapa', 'label' => __('seguridad.dashboard.tab_mapa'), 'visible' => isset($secciones['mapa'])],
        ['id' => 'lotes', 'label' => __('seguridad.dashboard.tab_resumen_lote'), 'visible' => isset($secciones['resumen_por_lote'])],
        ['id' => 'multimedia', 'label' => __('seguridad.dashboard.tab_multimedia'), 'visible' => isset($secciones['multimedia'])],
    ])->where('visible')->values();
@endphp

<x-templates.panel-shell :title="__('seguridad.dashboard.titulo')" :tema="$tema">
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
        :vista-actual="__('seguridad.dashboard.tab_resumen')"
    >
        <div class="ag-dash">
            @include('seguridad::pages.dashboard._encabezado', ['rol' => $activeRoleLabel])

            @if ($tabs->isEmpty())
                @include('seguridad::pages.dashboard._sin-secciones')
            @else
                {{-- La barra de pestañas se omite cuando hay una sola: un
                     tablero de piloto con "Resumen" como única pestaña es una
                     pestaña decorativa, no navegación. --}}
                @if ($tabs->count() > 1)
                    <div class="ag-tabs" role="tablist" aria-label="{{ __('seguridad.dashboard.tabs_aria') }}">
                        @foreach ($tabs as $indice => $tab)
                            <button
                                type="button"
                                class="ag-tabs__tab {{ $indice === 0 ? 'active' : '' }}"
                                data-bs-toggle="tab"
                                data-bs-target="#ag-tab-{{ $tab['id'] }}"
                                role="tab"
                                aria-controls="ag-tab-{{ $tab['id'] }}"
                                aria-selected="{{ $indice === 0 ? 'true' : 'false' }}"
                            >{{ $tab['label'] }}</button>
                        @endforeach
                    </div>
                @endif

                <div class="tab-content ag-dash__panes">
                    @foreach ($tabs as $indice => $tab)
                        <div
                            class="tab-pane fade {{ $indice === 0 ? 'show active' : '' }}"
                            id="ag-tab-{{ $tab['id'] }}"
                            role="tabpanel"
                            tabindex="0"
                        >
                            @include('seguridad::pages.dashboard._tab-'.$tab['id'], ['secciones' => $secciones])
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
