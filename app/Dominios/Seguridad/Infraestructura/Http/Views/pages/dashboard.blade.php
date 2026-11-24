{{--
    Page: tablero del panel (GET /panel/dashboard, panel.dashboard) — arquetipo
    Tablero (§6.1 de docs/diseno/guia_pantalla_panel.md).

    Muestra cómo está el mercado: KPIs, solicitudes por estado, solicitudes por
    región y la cola de lo que espera una decisión del administrador. Acá no se
    calcula nada: todo llega resuelto de `LecturaTableroDeMercado`.

    Datos esperados: la cáscara de CascaraPanel (menu/roles/…/tema/version), más
    `tablero` (forma de LecturaTableroDeMercado::tablero()), `pendientes`
    (list<array{clave, cantidad, ruta, permiso}> ya filtrada por permiso del
    rol activo), `puedeVerSolicitudes` (bool), y `puedeVerComo` (bool) y
    `candidatosVerComo` (array|null) para el botón «Cambiar vista».
--}}
@php
    $porEstado = collect($tablero['por_estado']);
    $enMercado = $porEstado->whereIn('estado', ['abierta', 'con_ofertas'])->sum('cantidad');
    $enCurso = $porEstado->whereIn('estado', ['adjudicada', 'en_ejecucion', 'disputada'])->sum('cantidad');
    $totalPendientes = (int) collect($pendientes)->sum('cantidad');
    $hectareas = fn (string $valor): string => number_format((float) $valor, 2, ',', '.');
@endphp

<x-templates.panel-shell :title="__('seguridad.dashboard.titulo')" :tema="$tema">
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
        :vista-actual="__('seguridad.dashboard.titulo')"
    >
        <div class="ag-dash">
            @include('seguridad::pages.dashboard._encabezado', [
                'rol' => $activeRoleLabel,
                'puedeVerComo' => $puedeVerComo,
            ])

            @if ($puedeVerComo && $candidatosVerComo !== null)
                @include('seguridad::pages.dashboard._modal-cambiar-vista', ['candidatos' => $candidatosVerComo])
            @endif

            <div class="ag-tablero__kpis">
                <x-molecules.stat-card :label="__('seguridad.dashboard.kpi_solicitudes')" icon="request_quote" :value="number_format($tablero['total_solicitudes'], 0, ',', '.')" state="info" />
                <x-molecules.stat-card :label="__('seguridad.dashboard.kpi_en_mercado')" icon="storefront" :value="number_format($enMercado, 0, ',', '.')" state="success" />
                <x-molecules.stat-card :label="__('seguridad.dashboard.kpi_en_curso')" icon="flight" :value="number_format($enCurso, 0, ',', '.')" state="primary-2" />
                <x-molecules.stat-card :label="__('seguridad.dashboard.kpi_hectareas')" icon="landscape" :value="$hectareas($tablero['hectareas_en_mercado'])" value-suffix="ha" state="distintivo-1" />
                <x-molecules.stat-card :label="__('seguridad.dashboard.kpi_pendientes')" icon="pending_actions" :value="number_format($totalPendientes, 0, ',', '.')" :state="$totalPendientes > 0 ? 'warning' : null" />
            </div>

            @if ($tablero['total_solicitudes'] === 0)
                <x-molecules.empty-state
                    icon="request_quote"
                    :title="__('seguridad.dashboard.vacio_titulo')"
                    :detail="__('seguridad.dashboard.vacio_detalle')"
                />
            @else
                <div class="ag-tablero__columnas">
                    <section class="ag-card ag-card--padded">
                        <header class="ag-card__head">
                            <h2 class="ag-card__title">{{ __('seguridad.dashboard.por_estado_titulo') }}</h2>
                        </header>

                        <div class="ag-tablero__scroll">
                            <table class="ag-tablero__tabla">
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('seguridad.dashboard.col_estado') }}</th>
                                        <th scope="col" class="ag-tablero__num">{{ __('seguridad.dashboard.col_cantidad') }}</th>
                                        <th scope="col" class="ag-tablero__num">{{ __('seguridad.dashboard.col_hectareas') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($tablero['por_estado'] as $fila)
                                        <tr>
                                            <th scope="row">
                                                @if ($puedeVerSolicitudes)
                                                    <a class="ag-dash__link" href="{{ route('panel.solicitudes.index', ['estado' => $fila['estado']]) }}">{{ __('solicitudes.estado.'.$fila['estado']) }}</a>
                                                @else
                                                    {{ __('solicitudes.estado.'.$fila['estado']) }}
                                                @endif
                                            </th>
                                            <td class="ag-tablero__num">{{ $fila['cantidad'] }}</td>
                                            <td class="ag-tablero__num">{{ $hectareas($fila['hectareas']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="ag-card ag-card--padded">
                        <header class="ag-card__head">
                            <div>
                                <h2 class="ag-card__title">{{ __('seguridad.dashboard.por_region_titulo') }}</h2>
                                <p class="ag-tablero__ayuda">{{ __('seguridad.dashboard.por_region_ayuda') }}</p>
                            </div>
                        </header>

                        <div class="ag-tablero__scroll">
                            <table class="ag-tablero__tabla">
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('seguridad.dashboard.col_region') }}</th>
                                        <th scope="col" class="ag-tablero__num">{{ __('seguridad.dashboard.col_abiertas') }}</th>
                                        <th scope="col" class="ag-tablero__num">{{ __('seguridad.dashboard.col_en_curso') }}</th>
                                        <th scope="col" class="ag-tablero__num">{{ __('seguridad.dashboard.col_cerradas') }}</th>
                                        <th scope="col" class="ag-tablero__num">{{ __('seguridad.dashboard.col_canceladas') }}</th>
                                        <th scope="col" class="ag-tablero__num">{{ __('seguridad.dashboard.col_total') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($tablero['por_region'] as $fila)
                                        <tr>
                                            <th scope="row">{{ $fila['region'] === 'sin_region' ? __('seguridad.dashboard.region_sin_zona') : __('solicitudes.region.'.$fila['region']) }}</th>
                                            <td class="ag-tablero__num">{{ $fila['abiertas'] }}</td>
                                            <td class="ag-tablero__num">{{ $fila['en_curso'] }}</td>
                                            <td class="ag-tablero__num">{{ $fila['cerradas'] }}</td>
                                            <td class="ag-tablero__num">{{ $fila['canceladas'] }}</td>
                                            <td class="ag-tablero__num">{{ $fila['total'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            @endif

            @if ($pendientes !== [])
                <section class="ag-card ag-card--padded">
                    <header class="ag-card__head">
                        <h2 class="ag-card__title">{{ __('seguridad.dashboard.pendientes_titulo') }}</h2>
                    </header>

                    @if ($totalPendientes === 0)
                        <p class="ag-tablero__ayuda">{{ __('seguridad.dashboard.pendientes_vacio') }}</p>
                    @else
                        <ul class="ag-tablero__pendientes">
                            @foreach ($pendientes as $pendiente)
                                @continue($pendiente['cantidad'] === 0)
                                <li class="ag-tablero__pendiente">
                                    <span class="ag-tablero__pendiente-cifra">{{ $pendiente['cantidad'] }}</span>
                                    <span class="ag-tablero__pendiente-texto">{{ __('seguridad.dashboard.pendiente.'.$pendiente['clave']) }}</span>
                                    <a class="ag-dash__link" href="{{ route($pendiente['ruta']) }}">{{ __('seguridad.dashboard.pendientes_ver') }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
