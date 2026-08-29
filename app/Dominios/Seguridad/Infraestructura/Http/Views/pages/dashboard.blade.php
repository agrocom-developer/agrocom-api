{{--
    Page: dashboard "Operación de hoy" (GET /panel/dashboard, panel.dashboard)
    Pivote a panel visual/estadístico (novena vuelta): el dashboard deja de
    ser un ERP genérico de tablas — pestañas de NIVEL 3 (mecanismo `tab`
    nativo de Bootstrap, skin ag-tabs) ahora son Resumen / Mapa / Resumen
    por lote / Multimedia. Los tabs "Sesiones" y "Pausas" se eliminaron: su
    contenido esencial ya vivía dentro de Resumen (tabla de programación,
    barras de pausas por causa); las tarjetas KPI también se quitaron de
    esta página (el componente `stat-card` sigue en el catálogo, para
    páginas dedicadas futuras de cada módulo del menú).

    Los DATOS son demo (DatosDemoPanel y clases hermanas — nunca
    hardcodeados acá); el copy fijo vive en lang/es/seguridad.php. Las
    variantes responsivas de la tabla (lista de dos líneas en tablet,
    fichas en móvil) son los parciales de pages/dashboard/.

    Datos esperados (ver DashboardController::index()): la cáscara de
    CascaraPanel (menu/roles/…/tema/campana/periodo/version) + fechaBajada,
    ventana, distribucion, sesiones, pausas, stock, alertaRc.
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
                    <div class="ag-dash__subtitle-row">
                        <p class="ag-dash__subtitle">{{ __('seguridad.dashboard.bajada', ['fecha' => $fechaBajada]) }}</p>
                        {{-- Auditoría visual externa, obs. #3: la ventana volable era un
                             segundo banner de igual peso que el aviso de RC (bloqueante),
                             compitiendo por atención. Baja a tira compacta junto al
                             subtítulo — sigue siendo warning, ya no un alert-strip completo. --}}
                        <div class="ag-dash__ventana-chip">
                            <x-atoms.icon name="wb_twilight" size="sm" />
                            <span>{{ __('seguridad.dashboard.ventana_titulo', ['horario' => $ventana['horario']]) }}</span>
                            <button type="button" class="ag-dash__link">{{ __('seguridad.dashboard.ventana_accion') }}</button>
                        </div>
                    </div>
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
                <button type="button" class="ag-tabs__tab" data-bs-toggle="tab" data-bs-target="#ag-tab-mapa" role="tab" aria-controls="ag-tab-mapa" aria-selected="false">
                    {{ __('seguridad.dashboard.tab_mapa') }}
                </button>
                <button type="button" class="ag-tabs__tab" data-bs-toggle="tab" data-bs-target="#ag-tab-resumen-lote" role="tab" aria-controls="ag-tab-resumen-lote" aria-selected="false">
                    {{ __('seguridad.dashboard.tab_resumen_lote') }}
                </button>
                <button type="button" class="ag-tabs__tab" data-bs-toggle="tab" data-bs-target="#ag-tab-multimedia" role="tab" aria-controls="ag-tab-multimedia" aria-selected="false">
                    {{ __('seguridad.dashboard.tab_multimedia') }}
                </button>
            </div>

            <div class="tab-content ag-dash__panes">
                {{-- ============ Pestaña Resumen (maquetas 4a/5a/5b) ============ --}}
                <div class="tab-pane fade show active" id="ag-tab-resumen" role="tabpanel" tabindex="0">
                    <div class="ag-dash__stack">
                        {{-- Alertas primero, en orden de criticidad (danger > accent) —
                             Fase 5: antes el aviso de RC (el más crítico) estaba al final
                             de la página, después de todo lo demás. --}}
                        <x-molecules.alert-strip variant="danger" icon="photo_camera">
                            <strong>{{ __('seguridad.dashboard.rc_alerta', ['cantidad' => $alertaRc['cantidad']]) }}</strong>
                            <span class="ag-dash__rc-detalle">{{ __('seguridad.dashboard.rc_detalle') }}</span>
                            <x-slot:action>
                                <x-atoms.button variant="danger-outline" size="sm">{{ __('seguridad.dashboard.rc_resolver') }}</x-atoms.button>
                            </x-slot:action>
                        </x-molecules.alert-strip>

                        {{-- Gráficos ApexCharts: sesiones por estado (donut),
                             hectáreas aplicadas por día (area), avance de meta
                             del mes (radialBar). --}}
                        @php
                            $colorTokenPorTono = [
                                'success' => '--ag-color-success',
                                'warning' => '--ag-color-warning',
                                'info' => '--ag-color-info',
                                'neutral' => '--ag-color-text-faint',
                            ];
                        @endphp
                        <section class="ag-dash__cards-grid">
                            <div class="ag-card ag-card--padded">
                                <x-molecules.section-head :title="__('seguridad.dashboard.seccion_sesiones_estado')" />
                                <x-molecules.apex-chart
                                    type="donut"
                                    :series="array_column($distribucion['segmentos'], 'valor')"
                                    :labels="array_map(fn ($s) => __('operaciones.sesion.estado.'.$s['estado']), $distribucion['segmentos'])"
                                    :color-tokens="array_map(fn ($s) => $colorTokenPorTono[$s['tono']] ?? $colorTokenPorTono['neutral'], $distribucion['segmentos'])"
                                    :height="320"
                                />
                            </div>

                            <div class="ag-card ag-card--padded">
                                <x-molecules.section-head :title="__('seguridad.dashboard.seccion_hectareas_periodo')" />
                                <x-molecules.apex-chart
                                    type="area"
                                    :series="[['name' => __('seguridad.dashboard.seccion_hectareas_periodo'), 'data' => $hectareasPorDia['valores']]]"
                                    :labels="$hectareasPorDia['fechas']"
                                    :color-tokens="['--ag-color-primary']"
                                />
                            </div>

                            <div class="ag-card ag-card--padded">
                                <x-molecules.section-head :title="__('seguridad.dashboard.seccion_avance_meta')" />
                                <x-molecules.apex-chart
                                    type="radialBar"
                                    :series="[$avanceMeta['pct']]"
                                    :labels="[__('seguridad.dashboard.avance_meta_label')]"
                                    :color-tokens="['--ag-color-success']"
                                />
                                <p class="ag-dash__mono-note">{{ __('seguridad.dashboard.avance_meta_pie', ['valor' => number_format($avanceMeta['valor'], 0, ',', '.'), 'meta' => number_format($avanceMeta['meta'], 0, ',', '.')]) }}</p>
                            </div>
                        </section>

                        {{-- Detalle de clientes: actividad reciente + estado de
                             contrato combinados. --}}
                        <section>
                            <x-molecules.section-head :title="__('seguridad.dashboard.seccion_clientes')" />
                            <div class="ag-card">
                                @include('seguridad::pages.dashboard._detalle-clientes', ['clientes' => $detalleClientes])
                            </div>
                        </section>

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
                    </div>
                </div>

                {{-- ============ Pestaña Mapa (nueva) ============ --}}
                <div class="tab-pane fade" id="ag-tab-mapa" role="tabpanel" tabindex="0">
                    <div class="ag-dash__stack">
                        <div class="ag-dash__cards-grid">
                            <x-molecules.stat-card
                                :label="__('seguridad.dashboard.mapa_lotes_titulo')"
                                icon="layers"
                                :value="$resumenMapa['lotesEnMapa']"
                            />
                            <x-molecules.stat-card
                                :label="__('seguridad.dashboard.mapa_hectareas_titulo')"
                                icon="landscape"
                                :value="$resumenMapa['hectareasEnMapa']"
                            />
                            <x-molecules.stat-card
                                :label="__('seguridad.dashboard.mapa_sesiones_titulo')"
                                icon="share_location"
                                :value="$resumenMapa['sesionesGeorreferenciadas']"
                            />
                        </div>

                        <x-organisms.mapa-operativo :lotes="$mapaLotes" :sesiones="$mapaSesiones" />
                    </div>
                </div>

                {{-- ============ Pestaña Resumen por lote (nueva) ============ --}}
                <div class="tab-pane fade" id="ag-tab-resumen-lote" role="tabpanel" tabindex="0">
                    <div class="ag-dash__stack">
                        {{-- Distribución de sesiones, reubicada acá desde Resumen. --}}
                        <section>
                            <x-molecules.section-head :title="__('seguridad.dashboard.seccion_distribucion')" />
                            <div class="ag-card ag-card--padded">
                                <x-molecules.distribution-bar
                                    :segments="$distribucion['segmentos']"
                                    :total="$distribucion['total']"
                                    :center-label="__('seguridad.dashboard.distribucion_centro')"
                                />
                            </div>
                        </section>

                        <section>
                            <x-molecules.section-head :title="__('seguridad.dashboard.seccion_resumen_lote')" />
                            @include('seguridad::pages.dashboard._resumen-por-lote', ['lotes' => $resumenPorLote])
                        </section>
                    </div>
                </div>

                {{-- ============ Pestaña Multimedia (nueva) ============ --}}
                <div class="tab-pane fade" id="ag-tab-multimedia" role="tabpanel" tabindex="0">
                    <div class="ag-dash__stack">
                        {{-- 3 subvistas de las mismas capturas — mismo mecanismo
                             `tab` de Bootstrap que los tabs de nivel 3, con skin
                             de píldora en vez de subrayado. --}}
                        <div class="ag-multimedia-toggle" role="tablist" aria-label="{{ __('seguridad.dashboard.multimedia_vistas_aria') }}">
                            <button type="button" class="ag-multimedia-toggle__btn active" data-bs-toggle="tab" data-bs-target="#ag-multimedia-galeria" role="tab" aria-selected="true">
                                <x-atoms.icon name="grid_view" size="sm" />
                                {{ __('seguridad.dashboard.multimedia_vista_galeria') }}
                            </button>
                            <button type="button" class="ag-multimedia-toggle__btn" data-bs-toggle="tab" data-bs-target="#ag-multimedia-carrusel-pane" role="tab" aria-selected="false">
                                <x-atoms.icon name="view_carousel" size="sm" />
                                {{ __('seguridad.dashboard.multimedia_vista_carrusel') }}
                            </button>
                            <button type="button" class="ag-multimedia-toggle__btn" data-bs-toggle="tab" data-bs-target="#ag-multimedia-tabla-pane" role="tab" aria-selected="false">
                                <x-atoms.icon name="table_rows" size="sm" />
                                {{ __('seguridad.dashboard.multimedia_vista_tabla') }}
                            </button>
                        </div>

                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="ag-multimedia-galeria" role="tabpanel" tabindex="0">
                                @include('seguridad::pages.dashboard._multimedia-galeria', ['sesiones' => $capturasRc])
                            </div>
                            <div class="tab-pane fade" id="ag-multimedia-carrusel-pane" role="tabpanel" tabindex="0">
                                @include('seguridad::pages.dashboard._multimedia-carrusel', ['sesiones' => $capturasRc])
                            </div>
                            <div class="tab-pane fade" id="ag-multimedia-tabla-pane" role="tabpanel" tabindex="0">
                                @include('seguridad::pages.dashboard._multimedia-tabla', ['sesiones' => $capturasRc])
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
