{{--
    Page: dashboard "Operación de hoy" (GET /panel/dashboard, panel.dashboard)
    Quinta vuelta — maquetas aprobadas 4a (escritorio), 5a (tablet) y 5b
    (móvil): título en display + acciones, pestañas de NIVEL 3 (Resumen /
    Sesiones / Pausas, mecanismo `tab` nativo de Bootstrap con skin
    ag-tabs), franja de ventana volable, 4 KPI, grilla 1.55fr/1fr con la
    programación del día y pausas/stock, y alerta de RC al pie.

    Los DATOS son demo (DatosDemoPanel — nunca hardcodeados acá); el copy
    fijo vive en lang/es/seguridad.php. Las variantes responsivas de la
    tabla (lista de dos líneas en tablet, fichas en móvil) son los parciales
    de pages/dashboard/.

    Datos esperados (ver DashboardController::index()): la cáscara de
    CascaraPanel (menu/roles/…/tema/campana/periodo/version) + fechaBajada,
    ventana, kpis, kpiMovil, sesiones, pausas, stock, alertaRc,
    pausasSinCausa.
--}}
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
            <div class="ag-dash__header">
                <div class="ag-dash__heading">
                    <h1 class="ag-dash__title">{{ __('seguridad.dashboard.titulo') }}</h1>
                    <p class="ag-dash__subtitle">{{ __('seguridad.dashboard.bajada', ['fecha' => $fechaBajada]) }}</p>
                </div>
                <div class="ag-dash__actions">
                    <x-atoms.button variant="outline" icon="download">{{ __('seguridad.dashboard.exportar') }}</x-atoms.button>
                    <x-atoms.button variant="primary" icon="add">{{ __('seguridad.dashboard.programar_sesion') }}</x-atoms.button>
                </div>
            </div>

            {{-- Nivel 3: pestañas con subrayado ámbar (tabs.css). --}}
            <div class="ag-tabs" role="tablist" aria-label="{{ __('seguridad.dashboard.tabs_aria') }}">
                <button type="button" class="ag-tabs__tab active" data-bs-toggle="tab" data-bs-target="#ag-tab-resumen" role="tab" aria-controls="ag-tab-resumen" aria-selected="true">
                    {{ __('seguridad.dashboard.tab_resumen') }}
                </button>
                <button type="button" class="ag-tabs__tab" data-bs-toggle="tab" data-bs-target="#ag-tab-sesiones" role="tab" aria-controls="ag-tab-sesiones" aria-selected="false">
                    {{ __('seguridad.dashboard.tab_sesiones') }}
                </button>
                <button type="button" class="ag-tabs__tab" data-bs-toggle="tab" data-bs-target="#ag-tab-pausas" role="tab" aria-controls="ag-tab-pausas" aria-selected="false">
                    {{ __('seguridad.dashboard.tab_pausas') }}
                </button>
            </div>

            <div class="tab-content ag-dash__panes">
                {{-- ============ Pestaña Resumen (maquetas 4a/5a/5b) ============ --}}
                <div class="tab-pane fade show active" id="ag-tab-resumen" role="tabpanel" tabindex="0">
                    <div class="ag-dash__stack">
                        <x-molecules.alert-strip variant="accent" icon="wb_twilight">
                            <strong>{{ __('seguridad.dashboard.ventana_titulo', ['horario' => $ventana['horario']]) }}</strong>
                            {{ $ventana['detalle'] }}
                            <x-slot:action>
                                <x-atoms.button variant="accent" size="sm">{{ __('seguridad.dashboard.ventana_accion') }}</x-atoms.button>
                            </x-slot:action>
                        </x-molecules.alert-strip>

                        {{-- KPIs: 4 en escritorio, 2×2 en tablet (mismo bloque);
                             en móvil manda el bloque protagonista de abajo. --}}
                        <div class="ag-dash__kpis">
                            @foreach ($kpis as $kpi)
                                <x-molecules.stat-card
                                    :label="$kpi['label']"
                                    :icon="$kpi['icono']"
                                    :value="$kpi['valor']"
                                    :value-suffix="$kpi['sufijo']"
                                    :foot="$kpi['pie']"
                                    :foot-icon="$kpi['pieIcono']"
                                    :foot-tone="$kpi['pieTono']"
                                />
                            @endforeach
                        </div>

                        <div class="ag-dash__kpis-movil">
                            <x-molecules.stat-card
                                :label="$kpiMovil['label']"
                                :value="$kpiMovil['valor']"
                                :foot="$kpiMovil['pie']"
                                foot-tone="success"
                                :hero="true"
                            />
                            <div class="ag-dash__kpis-movil-grid">
                                @foreach ($kpis as $kpi)
                                    @if (isset($kpi['labelCorto']))
                                        <x-molecules.stat-card
                                            :label="$kpi['labelCorto']"
                                            :value="$kpi['valor']"
                                            :value-suffix="$kpi['sufijo']"
                                            :foot="$kpi['pie']"
                                            :foot-tone="$kpi['pieTono']"
                                        />
                                    @endif
                                @endforeach
                            </div>
                        </div>

                        {{-- Grilla 1.55fr/1fr (escritorio); apilada en tablet;
                             en móvil la reemplaza el bloque de fichas. --}}
                        <div class="ag-dash__grid">
                            <div class="ag-card ag-dash__programacion">
                                <div class="ag-card__head">
                                    <h2 class="ag-card__title">{{ __('seguridad.dashboard.programacion_titulo') }}</h2>
                                    <button type="button" class="ag-dash__link">{{ __('seguridad.dashboard.ver_todas') }}</button>
                                </div>
                                @include('seguridad::pages.dashboard._tabla-sesiones', ['sesiones' => $sesiones, 'limiteLista' => 4])
                            </div>

                            <div class="ag-dash__aside">
                                <div class="ag-card ag-card--padded">
                                    <div class="ag-card__head ag-card__head--flush">
                                        <h2 class="ag-card__title">{{ __('seguridad.dashboard.pausas_titulo') }}</h2>
                                        <span class="ag-dash__mono-note">{{ $pausas['total'] }}</span>
                                    </div>
                                    @include('seguridad::pages.dashboard._barras-pausas', ['causas' => $pausas['causas']])
                                </div>

                                <div class="ag-card ag-card--padded">
                                    <div class="ag-card__head ag-card__head--flush">
                                        <h2 class="ag-card__title ag-dash__stock-title">
                                            <x-atoms.icon name="inventory_2" size="sm" class="ag-dash__stock-icon" />
                                            {{ __('seguridad.dashboard.stock_titulo') }}
                                        </h2>
                                    </div>
                                    <div class="ag-dash__stock">
                                        @foreach ($stock as $item)
                                            <div class="ag-dash__stock-row">
                                                <span>{{ $item['item'] }}</span>
                                                <span class="ag-dash__stock-nivel ag-dash__stock-nivel--{{ $item['tono'] }}">{{ $item['nivel'] }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                    <button type="button" class="ag-dash__link ag-dash__stock-accion">{{ __('seguridad.dashboard.stock_accion') }}</button>
                                </div>
                            </div>
                        </div>

                        {{-- Móvil (5b): encabezado "Hoy · Ver todas" + fichas. --}}
                        <div class="ag-dash__hoy">
                            <div class="ag-dash__hoy-head">
                                <h2 class="ag-card__title">{{ __('seguridad.dashboard.hoy') }}</h2>
                                <button type="button" class="ag-dash__link">{{ __('seguridad.dashboard.ver_todas') }}</button>
                            </div>
                            @include('seguridad::pages.dashboard._fichas-sesiones', ['sesiones' => $sesiones])
                        </div>

                        <x-molecules.alert-strip variant="danger" icon="photo_camera">
                            <strong>{{ __('seguridad.dashboard.rc_alerta', ['cantidad' => $alertaRc['cantidad']]) }}</strong>
                            <span class="ag-dash__rc-detalle">{{ __('seguridad.dashboard.rc_detalle') }}</span>
                            <x-slot:action>
                                <x-atoms.button variant="danger" size="sm">{{ __('seguridad.dashboard.rc_resolver') }}</x-atoms.button>
                            </x-slot:action>
                        </x-molecules.alert-strip>
                    </div>
                </div>

                {{-- ============ Pestaña Sesiones (maqueta 4a) ============ --}}
                <div class="tab-pane fade" id="ag-tab-sesiones" role="tabpanel" tabindex="0">
                    <div class="ag-dash__stack">
                        <div class="ag-dash__filters">
                            <button type="button" class="ag-dash__filter is-active">
                                <x-atoms.icon name="filter_alt" size="sm" />
                                {{ __('seguridad.dashboard.filtro_sin_validar') }}
                            </button>
                            <button type="button" class="ag-dash__filter">
                                {{ __('seguridad.dashboard.filtro_pilotos') }}
                                <x-atoms.icon name="expand_more" size="sm" />
                            </button>
                            <button type="button" class="ag-dash__filter">
                                {{ __('seguridad.dashboard.filtro_drones') }}
                                <x-atoms.icon name="expand_more" size="sm" />
                            </button>
                            <button type="button" class="ag-dash__filter">
                                {{ __('seguridad.dashboard.filtro_evidencia') }}
                                <x-atoms.icon name="expand_more" size="sm" />
                            </button>
                        </div>

                        <div class="ag-card">
                            @include('seguridad::pages.dashboard._tabla-sesiones', ['sesiones' => $sesiones])
                        </div>

                        <div class="ag-dash__solo-movil">
                            @include('seguridad::pages.dashboard._fichas-sesiones', ['sesiones' => $sesiones, 'limite' => count($sesiones)])
                        </div>

                        <p class="ag-dash__nota">{{ __('seguridad.dashboard.sesiones_nota') }}</p>
                    </div>
                </div>

                {{-- ============ Pestaña Pausas (maqueta 4a) ============ --}}
                <div class="tab-pane fade" id="ag-tab-pausas" role="tabpanel" tabindex="0">
                    <div class="ag-dash__stack">
                        <div class="ag-card ag-card--padded">
                            <div class="ag-card__head ag-card__head--flush">
                                <h2 class="ag-card__title">{{ __('seguridad.dashboard.pausas_titulo_mes', ['periodo' => mb_strtolower($periodo ?? '')]) }}</h2>
                            </div>
                            @include('seguridad::pages.dashboard._barras-pausas', ['causas' => $pausas['causas'], 'grande' => true])
                        </div>

                        <x-molecules.alert-strip variant="accent" icon="help">
                            {{ __('seguridad.dashboard.pausas_sin_causa', ['horas' => $pausasSinCausa]) }}
                        </x-molecules.alert-strip>
                    </div>
                </div>
            </div>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
