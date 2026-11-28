{{--
    Page: tablero del panel (GET /panel/dashboard, panel.dashboard) — arquetipo
    Tablero (§6.1 de docs/diseno/guia_pantalla_panel.md).

    Muestra cómo está el mercado hoy, de un vistazo, en un lienzo (templates/lienzo): la actividad
    semanal como gráfica principal a todo el ancho y, debajo, solicitudes por estado, la cola de lo
    que espera una decisión, hectáreas por departamento, precio por hectárea y las tarjetas KPI. El detalle por período (embudo,
    tiempos, rondas, departamentos) vive en «Métricas del mercado». Acá no se calcula nada:
    todo llega resuelto de `LecturaTableroDeMercado`.

    Datos esperados: la cáscara de CascaraPanel (menu/roles/…/tema/version), más
    `tablero` (forma de LecturaTableroDeMercado::tablero()), `pendientes`
    (list<array{clave, cantidad, ruta, permiso}> ya filtrada por permiso del
    rol activo) y `puedeVerSolicitudes` (bool). El tablero es dinámico: cada bloque
    se pinta solo si la sesión tiene el permiso de lo que muestra (solicitudes por
    estado y departamento con `solicitudes.solicitud.ver`; cada pendiente con el
    permiso de su pantalla). No hay selector de vista.
--}}
@php
    $porEstado = collect($tablero['por_estado']);
    $enMercado = $porEstado->whereIn('estado', ['abierta', 'con_ofertas'])->sum('cantidad');
    $enCurso = $porEstado->whereIn('estado', ['adjudicada', 'en_ejecucion', 'en_reclamo'])->sum('cantidad');
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
            @include('seguridad::pages.dashboard._encabezado')

            @if (! $puedeVerSolicitudes && $pendientes === [])
                <x-molecules.empty-state
                    icon="dashboard"
                    :title="__('seguridad.dashboard.sin_bloques_titulo')"
                    :detail="__('seguridad.dashboard.sin_bloques_detalle')"
                />
            @endif

            @if ($puedeVerSolicitudes && $tablero['total_solicitudes'] > 0)
                {{--
                    Composición del tablero (templates/lienzo, §Lienzo de docs/diseno/sistema_diseno_panel.md):

                    +--------------------------------------------------+
                    |  PRINCIPAL: actividad semanal            (12)    |
                    +------------------+-------------------------------+
                    | Solicitudes por  | Hectáreas por   | Precio por  |
                    | estado      (7/12| departamento    | hectárea    |
                    +------------------+ (6)             | (6)         |
                    | Esperan tu       +-----------------+-------------+
                    | decisión   (5/12)| Tarjetas KPI (12)             |
                    +------------------+-------------------------------+
                --}}
                <x-templates.lienzo :raiz="true" direccion="columna" fracciones="4 8" :alto="21">
                    <x-molecules.lienzo-bloque>
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
                    </x-molecules.lienzo-bloque>

                    <x-molecules.lienzo-bloque>
                        <x-templates.lienzo direccion="fila" fracciones="4 8">
                            <x-molecules.lienzo-bloque>
                                <x-templates.lienzo direccion="columna" :fracciones="$pendientes === [] ? '12' : '7 5'">
                                    <x-molecules.lienzo-bloque>
                                        <x-molecules.grafico
                                            :titulo="__('seguridad.dashboard.por_estado_titulo')"
                                            :ayuda="__('seguridad.dashboard.por_estado_ayuda')"
                                            :horizontal="true"
                                            :categoria="__('seguridad.dashboard.col_estado')"
                                            :etiquetas="$porEstado->map(fn (array $fila): string => __('solicitudes.estado.'.$fila['estado']))->all()"
                                            :series="[['nombre' => __('seguridad.dashboard.col_cantidad'), 'valores' => $porEstado->pluck('cantidad')->all(), 'color' => 'info']]"
                                        >
                                            <x-slot:actions>
                                                <a class="ag-dash__link" href="{{ route('panel.solicitudes.index') }}">{{ __('seguridad.dashboard.ver_solicitudes') }}</a>
                                            </x-slot:actions>
                                        </x-molecules.grafico>
                                    </x-molecules.lienzo-bloque>

                                    @if ($pendientes !== [])
                                        <x-molecules.lienzo-bloque>
                                            @include('seguridad::pages.dashboard._pendientes')
                                        </x-molecules.lienzo-bloque>
                                    @endif
                                </x-templates.lienzo>
                            </x-molecules.lienzo-bloque>

                            <x-molecules.lienzo-bloque>
                                <x-templates.lienzo direccion="columna" fracciones="6 6">
                                    <x-molecules.lienzo-bloque>
                                        <x-templates.lienzo direccion="fila" fracciones="6 6">
                                            <x-molecules.lienzo-bloque>
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
                                            </x-molecules.lienzo-bloque>
                                            <x-molecules.lienzo-bloque>
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
                                            </x-molecules.lienzo-bloque>
                                        </x-templates.lienzo>
                                    </x-molecules.lienzo-bloque>

                                    <x-molecules.lienzo-bloque>
                                        @include('seguridad::pages.dashboard._kpis')
                                    </x-molecules.lienzo-bloque>
                                </x-templates.lienzo>
                            </x-molecules.lienzo-bloque>
                        </x-templates.lienzo>
                    </x-molecules.lienzo-bloque>
                </x-templates.lienzo>
            @else
                {{-- Sin permiso de solicitudes, o sin solicitudes todavía: no hay mercado que componer. --}}
                @include('seguridad::pages.dashboard._kpis')

                @if ($puedeVerSolicitudes)
                    <x-molecules.empty-state
                        icon="request_quote"
                        :title="__('seguridad.dashboard.vacio_titulo')"
                        :detail="__('seguridad.dashboard.vacio_detalle')"
                    />
                @endif

                @include('seguridad::pages.dashboard._pendientes')
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
