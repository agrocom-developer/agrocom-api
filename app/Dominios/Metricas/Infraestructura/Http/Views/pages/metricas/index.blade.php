{{--
    Page: metricas/index (GET /panel/metricas, panel.metricas.index)
    Métricas del mercado (especificación §14): arquetipo Detalle de solo lectura,
    §6.4 de docs/diseno/guia_pantalla_panel.md — cabecera con el selector de período,
    KPIs agrupados en mercado, calidad, oferta e ingreso, las gráficas del período (embudo,
    departamentos, tiempo hasta adjudicar y rondas) y la tabla de proveedores con más servicios. Una tasa sin divisor se muestra como «sin datos», nunca como 0.

    Datos esperados (ver MetricasController::index()): la cáscara de CascaraPanel,
    más $metricas (el arreglo de CalcularMetricas) y $diasValidos (list<int>).

    Gateada por `metricas.panel.ver`, verificado server-side en el controlador.
--}}
@php
    $titulo = __('metricas.titulo');
    $m = $metricas;
    $sinDatos = __('metricas.sin_datos');
    $pct = fn (?string $tasa): string => $tasa === null ? $sinDatos : number_format((float) $tasa * 100, 1, ',', '.').' %';
    $min = fn (?string $minutos): string => $minutos === null ? $sinDatos : number_format((float) $minutos, 1, ',', '.').' min';
    $dinero = fn (?string $monto): string => $monto === null ? $sinDatos : number_format((float) $monto, 2, ',', '.');
    $entero = fn (int|string|null $n): string => number_format((int) $n, 0, ',', '.');
    $embudo = $m['mercado']['embudo'];
    $departamentos = collect($m['mercado']['departamentos']);
@endphp
<x-templates.panel-shell :title="$titulo" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :zona-horaria="$zonaHoraria ?? null"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :version="$version"
        :vista-actual="$titulo"
    >
        <div class="ag-detalle">
            <x-organisms.page-header
                :title="$titulo"
                :subtitle="__('metricas.subtitulo', ['desde' => \Illuminate\Support\Carbon::parse($m['periodo']['desde'])->format('d/m/Y'), 'hasta' => \Illuminate\Support\Carbon::parse($m['periodo']['hasta'])->format('d/m/Y')])"
            >
                <x-slot:actions>
                    @foreach ($diasValidos as $dias)
                        <x-atoms.button
                            :href="route('panel.metricas.index', ['dias' => $dias])"
                            :variant="$m['periodo']['dias'] === $dias ? 'primary' : 'outline'"
                            size="sm"
                        >
                            {{ __('metricas.periodo_dias', ['dias' => $dias]) }}
                        </x-atoms.button>
                    @endforeach
                </x-slot:actions>
            </x-organisms.page-header>

            <h2 class="ag-detalle__campo-label">{{ __('metricas.seccion_mercado') }}</h2>
            <div class="ag-detalle__kpis">
                <x-molecules.stat-card :label="__('metricas.solicitudes')" icon="request_quote" :value="$entero($m['mercado']['solicitudes_publicadas'])" :foot="__('metricas.solicitudes_pie', ['abiertas' => $m['mercado']['abiertas']])" state="info" />
                <x-molecules.stat-card :label="__('metricas.tasa_adjudicacion')" icon="handshake" :value="$pct($m['mercado']['tasa_adjudicacion'])" :foot="__('metricas.tasa_adjudicacion_pie', ['adjudicadas' => $m['mercado']['adjudicadas']])" state="success" />
                <x-molecules.stat-card :label="__('metricas.tiempo_aceptacion')" icon="timer" :value="$min($m['mercado']['tiempo_aceptacion_promedio_min'])" :foot="__('metricas.tiempo_aceptacion_pie', ['mediana' => $min($m['mercado']['tiempo_aceptacion_mediana_min']), 'cantidad' => $m['mercado']['aceptaciones']])" state="distintivo-1" />
                <x-molecules.stat-card :label="__('metricas.ofertas_por_solicitud')" icon="forum" :value="$m['mercado']['ofertas_por_solicitud'] ?? $sinDatos" :foot="__('metricas.rondas_pie', ['rondas' => $m['mercado']['rondas_promedio'] ?? $sinDatos, 'cantidad' => $m['mercado']['negociaciones']])" state="primary-2" />
                <x-molecules.stat-card :label="__('metricas.hectareas')" icon="landscape" :value="number_format((float) $m['mercado']['hectareas_servidas'], 2, ',', '.')" value-suffix="ha" :foot="__('metricas.hectareas_pie', ['solicitadas' => number_format((float) $m['mercado']['hectareas_solicitadas'], 2, ',', '.')])" state="success" />
            </div>

            <h2 class="ag-detalle__campo-label">{{ __('metricas.seccion_graficas') }}</h2>
            <div class="ag-grafico-grilla">
                <x-molecules.grafico
                    :titulo="__('metricas.embudo_titulo')"
                    :ayuda="__('metricas.embudo_ayuda')"
                    :horizontal="true"
                    :categoria="__('metricas.col_paso')"
                    :etiquetas="[__('metricas.embudo.publicadas'), __('metricas.embudo.con_ofertas'), __('metricas.embudo.adjudicadas'), __('metricas.embudo.finalizadas')]"
                    :series="[['nombre' => __('metricas.solicitudes'), 'valores' => [$embudo['publicadas'], $embudo['con_ofertas'], $embudo['adjudicadas'], $embudo['finalizadas']], 'color' => 'info']]"
                    :vacio="__('metricas.graficas_vacio')"
                />

                <x-molecules.grafico
                    :titulo="__('metricas.departamentos_titulo')"
                    :ayuda="__('metricas.departamentos_ayuda')"
                    :horizontal="true"
                    :apilado="true"
                    :categoria="__('metricas.col_departamento')"
                    :etiquetas="$departamentos->map(fn (array $fila): string => $fila['nombre'] ?? __('metricas.departamento_sin_dato'))->all()"
                    :series="[
                        ['nombre' => __('metricas.grupo.abiertas'), 'valores' => $departamentos->pluck('abiertas')->all(), 'color' => 'info'],
                        ['nombre' => __('metricas.grupo.en_curso'), 'valores' => $departamentos->pluck('en_curso')->all(), 'color' => 'warning'],
                        ['nombre' => __('metricas.grupo.cerradas'), 'valores' => $departamentos->pluck('cerradas')->all(), 'color' => 'success'],
                        ['nombre' => __('metricas.grupo.canceladas'), 'valores' => $departamentos->pluck('canceladas')->all(), 'color' => 'danger'],
                    ]"
                    :vacio="__('metricas.graficas_vacio')"
                />

                <x-molecules.grafico
                    :titulo="__('metricas.tiempo_titulo')"
                    :ayuda="__('metricas.tiempo_ayuda', ['cantidad' => $m['mercado']['aceptaciones']])"
                    :categoria="__('metricas.col_tiempo')"
                    :etiquetas="[__('metricas.tiempo.hasta_1h'), __('metricas.tiempo.de_1_a_6h'), __('metricas.tiempo.de_6_a_24h'), __('metricas.tiempo.de_1_a_3d'), __('metricas.tiempo.mas_de_3d')]"
                    :series="[['nombre' => __('metricas.adjudicaciones'), 'valores' => $m['mercado']['distribucion_tiempo'], 'color' => 'distintivo-1']]"
                    :vacio="__('metricas.graficas_vacio')"
                />

                <x-molecules.grafico
                    :titulo="__('metricas.rondas_titulo')"
                    :ayuda="__('metricas.rondas_ayuda', ['cantidad' => $m['mercado']['negociaciones']])"
                    :categoria="__('metricas.col_rondas')"
                    :etiquetas="[__('metricas.rondas.una'), __('metricas.rondas.dos'), __('metricas.rondas.tres'), __('metricas.rondas.cuatro_o_mas')]"
                    :series="[['nombre' => __('metricas.negociaciones'), 'valores' => $m['mercado']['distribucion_rondas'], 'color' => 'distintivo-2']]"
                    :vacio="__('metricas.graficas_vacio')"
                />
            </div>

            <h2 class="ag-detalle__campo-label">{{ __('metricas.seccion_calidad') }}</h2>
            <div class="ag-detalle__kpis">
                <x-molecules.stat-card :label="__('metricas.calificacion')" icon="star" :value="$m['calidad']['calificacion_promedio'] ?? $sinDatos" :value-suffix="$m['calidad']['calificacion_promedio'] === null ? null : '/ 5'" :foot="__('metricas.calificacion_pie', ['conteo' => $m['calidad']['calificaciones_conteo'], 'reportadas' => $m['calidad']['calificaciones_reportadas']])" state="alert" />
                <x-molecules.stat-card :label="__('metricas.tasa_disputas')" icon="report" :value="$pct($m['calidad']['tasa_disputas'])" :foot="__('metricas.tasa_disputas_pie', ['abiertas' => $m['calidad']['disputas_abiertas'], 'ejecutados' => $m['calidad']['servicios_ejecutados']])" state="warning" />
                <x-molecules.stat-card :label="__('metricas.tasa_reembolso')" icon="undo" :value="$pct($m['calidad']['tasa_reembolso'])" :foot="__('metricas.tasa_reembolso_pie', ['monto' => $dinero($m['calidad']['monto_reembolsado'])])" state="danger" />
            </div>

            <h2 class="ag-detalle__campo-label">{{ __('metricas.seccion_oferta') }}</h2>
            <div class="ag-detalle__kpis">
                <x-molecules.stat-card :label="__('metricas.proveedores')" icon="groups" :value="$entero($m['oferta']['proveedores_aprobados'])" :foot="__('metricas.proveedores_pie', ['pendientes' => $m['oferta']['proveedores_pendientes']])" state="info" />
                <x-molecules.stat-card :label="__('metricas.drones')" icon="airplanemode_active" :value="$entero($m['oferta']['drones_aprobados'])" state="distintivo-2" />
                <x-molecules.stat-card :label="__('metricas.tasa_aceptacion')" icon="thumb_up" :value="$pct($m['oferta']['tasa_aceptacion'])" :foot="__('metricas.tasa_aceptacion_pie', ['respondidas' => $m['oferta']['ofertas_respondidas']])" state="success" />
                <x-molecules.stat-card :label="__('metricas.tasa_cancelacion')" icon="cancel" :value="$pct($m['oferta']['tasa_cancelacion'])" :foot="__('metricas.tasa_cancelacion_pie', ['cancelaciones' => $m['oferta']['cancelaciones_proveedor'], 'sanciones' => $m['oferta']['sanciones_vigentes']])" state="warning" />
            </div>

            <h2 class="ag-detalle__campo-label">{{ __('metricas.seccion_ingreso') }}</h2>
            <div class="ag-detalle__kpis">
                <x-molecules.stat-card :label="__('metricas.volumen')" icon="payments" :value="$dinero($m['ingreso']['volumen_bruto'])" :foot="__('metricas.volumen_pie', ['servicios' => $m['ingreso']['servicios_completados']])" state="success" />
                <x-molecules.stat-card :label="__('metricas.ingreso_hectarea')" icon="grass" :value="$dinero($m['ingreso']['ingreso_por_hectarea'])" :foot="__('metricas.ingreso_hectarea_pie')" state="distintivo-1" />
            </div>
            <p class="ag-solicitudes__sub">{{ __('metricas.sin_cobro') }}</p>

            <h2 class="ag-detalle__campo-label">{{ __('metricas.seccion_proveedores') }}</h2>
            @if (count($m['proveedores']) === 0)
                <x-molecules.empty-state
                    icon="groups"
                    :title="__('metricas.proveedores_vacio_titulo')"
                    :detail="__('metricas.proveedores_vacio_detalle')"
                />
            @else
                <x-molecules.index-table columns="3rem minmax(0, 1.6fr) minmax(0, 0.8fr) minmax(0, 0.8fr) minmax(0, 0.9fr) minmax(0, 0.9fr) minmax(0, 0.9fr)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('metricas.col_proveedor') }}</span>
                        <span role="columnheader">{{ __('metricas.col_servicios') }}</span>
                        <span role="columnheader">{{ __('metricas.col_calificacion') }}</span>
                        <span role="columnheader">{{ __('metricas.col_aceptacion') }}</span>
                        <span role="columnheader">{{ __('metricas.col_respuesta') }}</span>
                        <span role="columnheader">{{ __('metricas.col_tipo') }}</span>
                    </x-slot:head>

                    @foreach ($m['proveedores'] as $fila)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">{{ $loop->iteration }}</span>
                            <span role="cell">
                                <a class="ag-dash__link" href="{{ route('panel.proveedores.show', $fila['proveedor_id']) }}">{{ $fila['nombre'] }}</a>
                            </span>
                            <span role="cell" class="ag-solicitudes__cifra">{{ $entero($fila['servicios']) }}</span>
                            <span role="cell" class="ag-solicitudes__cifra">
                                @if ($fila['calificaciones_conteo'] > 0)
                                    {{ $fila['calificacion_promedio'] }} <span class="ag-solicitudes__sub">({{ $fila['calificaciones_conteo'] }})</span>
                                @else
                                    {{ $sinDatos }}
                                @endif
                            </span>
                            <span role="cell" class="ag-solicitudes__cifra">{{ $pct($fila['tasa_aceptacion']) }}</span>
                            <span role="cell" class="ag-solicitudes__cifra">{{ $min($fila['respuesta_promedio_min']) }}</span>
                            <span role="cell">{{ $fila['tipo'] === '' ? '—' : __('metricas.tipo.'.$fila['tipo']) }}</span>
                        </div>
                    @endforeach
                </x-molecules.index-table>
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
