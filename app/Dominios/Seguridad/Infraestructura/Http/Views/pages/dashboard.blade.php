{{--
    Page: tablero de la torre de control (GET /panel/dashboard, panel.dashboard).

    Una pantalla para decidir: lo que espera una decisión (colas de aprobación,
    moderación y disputas), el pulso del mercado (publicadas, adjudicación,
    ofertas, tiempos), lo acordado en el período y dónde está la demanda abierta.
    Acá no se calcula nada: todo llega resuelto de `LecturaTablero`; una tasa sin
    base de cálculo se muestra «sin datos», nunca como 0.

    Datos esperados: la cáscara de CascaraPanel, más `tablero` (forma de
    LecturaTablero::tablero()), `diasValidos` (list<int>), `colas`
    (array<string, string>: URL de cada cola que el rol activo puede abrir),
    `puedeVerSolicitudes` y `puedeVerMetricas` (bool), `puedeVerComo` (bool) y
    `candidatosVerComo` (array|null) para el botón «Cambiar vista».
--}}
@php
    $t = $tablero;
    $sinDatos = __('torre.sin_datos');
    $entero = fn (int|string|null $n): string => number_format((int) $n, 0, ',', '.');
    $decimal = fn (?string $n, int $d = 2): string => $n === null ? $sinDatos : number_format((float) $n, $d, ',', '.');
    $pct = fn (?string $tasa): string => $tasa === null ? $sinDatos : number_format((float) $tasa * 100, 1, ',', '.').' %';
    $pendientes = collect($t['pendientes'])->filter(fn (int $n, string $clave): bool => $n > 0 && isset($colas[$clave]));
    $ordenEstados = ['borrador', 'abierta', 'con_ofertas', 'adjudicada', 'en_ejecucion', 'finalizada', 'calificada', 'disputada', 'expirada', 'cancelada'];
    $estados = collect($ordenEstados)->mapWithKeys(fn (string $e): array => [$e => (int) ($t['solicitudes_por_estado'][$e] ?? 0)])->filter();
    $titulo = __('torre.titulo');
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
        :vista-actual="__('menu.operacion.items.tablero')"
    >
        <div class="ag-detalle ag-dash">
            <x-organisms.page-header
                :title="$titulo"
                :subtitle="__('torre.bajada', ['fecha' => \Illuminate\Support\Str::ucfirst(now()->locale(app()->getLocale())->isoFormat('dddd D [de] MMMM'))])"
            >
                @if ($activeRoleLabel || $puedeVerComo)
                    <x-slot:chip>
                        @if ($activeRoleLabel)
                            <span class="ag-dash__ventana-chip">
                                <x-atoms.icon name="badge" size="sm" />
                                <span>{{ __('torre.viendo_como', ['rol' => $activeRoleLabel]) }}</span>
                            </span>
                        @endif

                        @if ($puedeVerComo)
                            <x-atoms.button
                                variant="outline"
                                size="sm"
                                icon="swap_horiz"
                                data-bs-toggle="modal"
                                data-bs-target="#ag-cambiar-vista-modal"
                            >
                                {{ __('seguridad.vista_como.selector_boton') }}
                            </x-atoms.button>
                        @endif
                    </x-slot:chip>
                @endif
                <x-slot:actions>
                    <div class="ag-dash__actions" role="group" aria-label="{{ __('torre.periodo_aria') }}">
                        @foreach ($diasValidos as $dias)
                            <x-atoms.button
                                :href="route('panel.dashboard', ['dias' => $dias])"
                                :variant="$t['periodo']['dias'] === $dias ? 'primary' : 'outline'"
                                size="sm"
                            >
                                {{ __('torre.periodo_dias', ['dias' => $dias]) }}
                            </x-atoms.button>
                        @endforeach
                    </div>
                </x-slot:actions>
            </x-organisms.page-header>

            @if ($puedeVerComo && $candidatosVerComo !== null)
                @include('seguridad::pages.dashboard._modal-cambiar-vista', ['candidatos' => $candidatosVerComo])
            @endif

            <section class="ag-card ag-card--padded" aria-labelledby="torre-pendientes">
                <header class="ag-card__head">
                    <h2 class="ag-card__title" id="torre-pendientes">{{ __('torre.pendientes_titulo') }}</h2>
                </header>
                @if ($pendientes->isEmpty())
                    <p class="ag-solicitudes__sub">{{ __('torre.pendientes_vacio') }}</p>
                @else
                    <ul class="ag-torre__pendientes">
                        @foreach ($pendientes as $clave => $cantidad)
                            <li>
                                <a class="ag-torre__pendiente" href="{{ $colas[$clave] }}">
                                    <span class="ag-torre__pendiente-cifra">{{ $entero($cantidad) }}</span>
                                    <span class="ag-torre__pendiente-texto">{{ __('torre.pendiente.'.$clave) }}</span>
                                    <x-atoms.icon name="arrow_forward" size="sm" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <h2 class="ag-detalle__campo-label">{{ __('torre.mercado_titulo') }}</h2>
            <div class="ag-detalle__kpis">
                <x-molecules.stat-card :label="__('torre.kpi_publicadas')" icon="request_quote" :value="$entero($t['adjudicacion']['publicadas'])" :foot="__('torre.kpi_publicadas_pie', ['ofertas' => $entero($t['ofertas']['del_periodo'])])" state="info" />
                <x-molecules.stat-card :label="__('torre.kpi_adjudicacion')" icon="handshake" :value="$pct($t['adjudicacion']['tasa'])" :foot="__('torre.kpi_adjudicacion_pie', ['adjudicadas' => $entero($t['adjudicacion']['adjudicadas']), 'publicadas' => $entero($t['adjudicacion']['publicadas'])])" state="success" />
                <x-molecules.stat-card :label="__('torre.kpi_ofertas')" icon="sell" :value="$entero($t['ofertas']['vigentes'])" :foot="__('torre.kpi_ofertas_pie', ['promedio' => $decimal($t['ofertas']['por_solicitud'], 1)])" state="primary-2" />
                <x-molecules.stat-card :label="__('torre.kpi_tiempo')" icon="timer" :value="$t['adjudicacion']['tiempo_mediana_min'] === null ? $sinDatos : __('torre.minutos', ['minutos' => $decimal($t['adjudicacion']['tiempo_mediana_min'], 1)])" :foot="__('torre.kpi_tiempo_pie')" state="distintivo-1" />
            </div>

            <h2 class="ag-detalle__campo-label">{{ __('torre.volumen_titulo') }}</h2>
            <div class="ag-detalle__kpis">
                <x-molecules.stat-card :label="__('torre.kpi_hectareas')" icon="landscape" :value="$decimal($t['volumen']['hectareas_acordadas'])" value-suffix="ha" state="success" />
                <x-molecules.stat-card :label="__('torre.kpi_monto')" icon="payments" :value="$decimal($t['volumen']['monto_acordado'])" :foot="__('torre.kpi_monto_pie')" state="success" />
                <x-molecules.stat-card :label="__('torre.kpi_precio')" icon="grass" :value="$decimal($t['volumen']['precio_acordado_promedio'])" :foot="__('torre.kpi_precio_pie', ['propuesto' => $decimal($t['volumen']['precio_propuesto_promedio'])])" state="distintivo-2" />
                <x-molecules.stat-card :label="__('torre.kpi_calificacion')" icon="star" :value="$t['calificaciones']['promedio'] ?? $sinDatos" :value-suffix="$t['calificaciones']['promedio'] === null ? null : '/ 5'" :foot="__('torre.kpi_calificacion_pie', ['conteo' => $t['calificaciones']['conteo'], 'reportadas' => $t['calificaciones']['reportadas']])" state="alert" />
            </div>

            <div class="ag-torre__dos">
                <section class="ag-card ag-card--padded" aria-labelledby="torre-estados">
                    <header class="ag-card__head">
                        <h2 class="ag-card__title" id="torre-estados">{{ __('torre.estados_titulo') }}</h2>
                    </header>
                    @if ($estados->isEmpty())
                        <p class="ag-solicitudes__sub">{{ __('torre.estados_vacio') }}</p>
                    @else
                        <x-molecules.index-table columns="minmax(0, 1fr) 6rem">
                            <x-slot:head>
                                <span role="columnheader">{{ __('torre.col_estado') }}</span>
                                <span role="columnheader">{{ __('torre.col_cantidad') }}</span>
                            </x-slot:head>
                            @foreach ($estados as $estado => $cantidad)
                                <div class="ag-index-table__row" role="row">
                                    <span role="cell">
                                        @if ($puedeVerSolicitudes)
                                            <a class="ag-dash__link" href="{{ route('panel.solicitudes.index', ['estado' => $estado]) }}">{{ __('solicitudes.estado.'.$estado) }}</a>
                                        @else
                                            {{ __('solicitudes.estado.'.$estado) }}
                                        @endif
                                    </span>
                                    <span role="cell" class="ag-solicitudes__cifra">{{ $entero($cantidad) }}</span>
                                </div>
                            @endforeach
                        </x-molecules.index-table>
                    @endif
                </section>

                <section class="ag-card ag-card--padded" aria-labelledby="torre-regiones">
                    <header class="ag-card__head">
                        <h2 class="ag-card__title" id="torre-regiones">{{ __('torre.regiones_titulo') }}</h2>
                    </header>
                    @if (count($t['mercado_por_macrorregion']) === 0)
                        <p class="ag-solicitudes__sub">{{ __('torre.regiones_vacio') }}</p>
                    @else
                        <x-molecules.index-table columns="minmax(0, 1.2fr) 6rem 8rem">
                            <x-slot:head>
                                <span role="columnheader">{{ __('torre.col_region') }}</span>
                                <span role="columnheader">{{ __('torre.col_solicitudes') }}</span>
                                <span role="columnheader">{{ __('torre.col_hectareas') }}</span>
                            </x-slot:head>
                            @foreach ($t['mercado_por_macrorregion'] as $fila)
                                <div class="ag-index-table__row" role="row">
                                    <span role="cell">{{ __('torre.macrorregion.'.$fila['macrorregion']) }}</span>
                                    <span role="cell" class="ag-solicitudes__cifra">{{ $entero($fila['solicitudes']) }}</span>
                                    <span role="cell" class="ag-solicitudes__cifra">{{ $decimal($fila['hectareas']) }}</span>
                                </div>
                            @endforeach
                        </x-molecules.index-table>
                    @endif
                </section>
            </div>

            <section class="ag-card ag-card--padded" aria-labelledby="torre-recientes">
                <header class="ag-card__head">
                    <h2 class="ag-card__title" id="torre-recientes">{{ __('torre.recientes_titulo') }}</h2>
                    @if ($puedeVerSolicitudes)
                        <a class="ag-dash__link" href="{{ route('panel.solicitudes.index') }}">{{ __('torre.ver_todas') }}</a>
                    @endif
                </header>
                @if (count($t['recientes']) === 0)
                    <p class="ag-solicitudes__sub">{{ __('torre.recientes_vacio') }}</p>
                @else
                    <x-molecules.index-table columns="5rem minmax(0, 1.6fr) minmax(0, 1fr) 7rem 7rem">
                        <x-slot:head>
                            <span role="columnheader">{{ __('torre.col_solicitud') }}</span>
                            <span role="columnheader">{{ __('torre.col_cliente') }}</span>
                            <span role="columnheader">{{ __('torre.col_estado') }}</span>
                            <span role="columnheader">{{ __('torre.col_hectareas') }}</span>
                            <span role="columnheader">{{ __('torre.col_precio') }}</span>
                        </x-slot:head>
                        @foreach ($t['recientes'] as $fila)
                            <div class="ag-index-table__row" role="row">
                                <span role="cell">
                                    @if ($puedeVerSolicitudes)
                                        <a class="ag-dash__link" href="{{ route('panel.solicitudes.show', $fila['id']) }}">#{{ $fila['id'] }}</a>
                                    @else
                                        #{{ $fila['id'] }}
                                    @endif
                                </span>
                                <span role="cell">{{ $fila['cliente'] }}</span>
                                <span role="cell">
                                    <x-atoms.badge :variant="\App\Dominios\Solicitudes\Dominio\EstadoSolicitud::from($fila['estado'])->tono()">{{ __('solicitudes.estado.'.$fila['estado']) }}</x-atoms.badge>
                                </span>
                                <span role="cell" class="ag-solicitudes__cifra">{{ $decimal($fila['hectareas']) }}</span>
                                <span role="cell" class="ag-solicitudes__cifra">{{ $fila['precio_ha'] === null ? '—' : $decimal($fila['precio_ha']) }}</span>
                            </div>
                        @endforeach
                    </x-molecules.index-table>
                @endif
            </section>

            @if ($puedeVerMetricas)
                <p><a class="ag-dash__link" href="{{ route('panel.metricas.index') }}">{{ __('torre.ver_metricas') }}</a></p>
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
