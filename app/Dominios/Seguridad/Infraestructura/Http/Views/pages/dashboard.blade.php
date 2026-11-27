{{--
    Page: tablero del panel (GET /panel/dashboard, panel.dashboard) — arquetipo
    Tablero (§6.1 de docs/diseno/guia_pantalla_panel.md).

    Muestra cómo está el mercado hoy, de un vistazo: KPIs, cuatro gráficas (solicitudes por
    estado, actividad semanal, hectáreas en el mercado por departamento y precio por hectárea) y
    la cola de lo que espera una decisión del administrador. El detalle por período (embudo,
    tiempos, rondas, departamentos) vive en «Métricas del mercado». Acá no se calcula nada:
    todo llega resuelto de `LecturaTableroDeMercado`.

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
    $semanas = collect($tablero['por_semana']);
    $etiquetasSemanas = $semanas->map(fn (array $fila): string => \Illuminate\Support\Carbon::parse($fila['semana'])->format('d/m'))->all();
    // Solo los departamentos con hectáreas esperando oferta, de más a menos.
    $hectareasPorDepartamento = collect($tablero['por_departamento'])
        ->filter(fn (array $fila): bool => (float) $fila['hectareas'] > 0)
        ->sortByDesc(fn (array $fila): float => (float) $fila['hectareas'])
        ->values();
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
                <div class="ag-grafico-grilla">
                    <x-molecules.grafico
                        :titulo="__('seguridad.dashboard.por_estado_titulo')"
                        :ayuda="__('seguridad.dashboard.por_estado_ayuda')"
                        :horizontal="true"
                        :categoria="__('seguridad.dashboard.col_estado')"
                        :etiquetas="$porEstado->map(fn (array $fila): string => __('solicitudes.estado.'.$fila['estado']))->all()"
                        :series="[['nombre' => __('seguridad.dashboard.col_cantidad'), 'valores' => $porEstado->pluck('cantidad')->all(), 'color' => 'info']]"
                    >
                        @if ($puedeVerSolicitudes)
                            <x-slot:actions>
                                <a class="ag-dash__link" href="{{ route('panel.solicitudes.index') }}">{{ __('seguridad.dashboard.ver_solicitudes') }}</a>
                            </x-slot:actions>
                        @endif
                    </x-molecules.grafico>

                    <x-molecules.grafico
                        :titulo="__('seguridad.dashboard.semanal_titulo', ['semanas' => count($semanas)])"
                        :ayuda="__('seguridad.dashboard.semanal_ayuda')"
                        tipo="lineas"
                        :categoria="__('seguridad.dashboard.col_semana')"
                        :etiquetas="$etiquetasSemanas"
                        :series="[
                            ['nombre' => __('seguridad.dashboard.serie_publicadas'), 'valores' => $semanas->pluck('publicadas')->all(), 'color' => 'info'],
                            ['nombre' => __('seguridad.dashboard.serie_adjudicadas'), 'valores' => $semanas->pluck('adjudicadas')->all(), 'color' => 'success'],
                        ]"
                    />

                    <x-molecules.grafico
                        :titulo="__('seguridad.dashboard.hectareas_departamento_titulo')"
                        :ayuda="__('seguridad.dashboard.hectareas_departamento_ayuda')"
                        :horizontal="true"
                        :categoria="__('seguridad.dashboard.col_departamento')"
                        :etiquetas="$hectareasPorDepartamento->map(fn (array $fila): string => $fila['departamento'] ?? __('seguridad.dashboard.departamento_sin_dato'))->all()"
                        :series="[['nombre' => __('seguridad.dashboard.col_hectareas'), 'valores' => $hectareasPorDepartamento->pluck('hectareas')->all(), 'color' => 'success']]"
                        :formato="['decimales' => 2, 'sufijo' => 'ha']"
                        :vacio="__('seguridad.dashboard.hectareas_departamento_vacio')"
                    />

                    <x-molecules.grafico
                        :titulo="__('seguridad.dashboard.precio_titulo')"
                        :ayuda="__('seguridad.dashboard.precio_ayuda')"
                        tipo="lineas"
                        :categoria="__('seguridad.dashboard.col_semana')"
                        :etiquetas="$etiquetasSemanas"
                        :series="[['nombre' => __('seguridad.dashboard.serie_precio'), 'valores' => $semanas->pluck('precio_ha_medio')->all(), 'color' => 'distintivo-1']]"
                        :formato="['decimales' => 2]"
                        :vacio="__('seguridad.dashboard.precio_vacio')"
                    />
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
