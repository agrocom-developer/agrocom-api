{{--
    Page: pausas/index (GET /panel/pausas, panel.pausas.index)
    Listado de pausas + tablero agregado por causa (HU-44, tarea 58):
    arquetipo Listado, §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera
    → tablero agregado → toolbar (filtro de período) → tabla → paginación.
    Homogeneizado con el patrón de Estadías (tarea 113): `filter-panel` +
    `index-table` + `pagination`, y el tablero en `molecules/summary-card`
    (antes una tarjeta armada con clases del CSS del dashboard).

    Una pausa no se edita ni se da de baja (se registra de nuevo, ver
    PausasController): la tabla no tiene columna de acciones, y por eso no
    hay `row-actions` ni modal. Tampoco lleva buscador (el caso de uso solo
    filtra por período) ni columna de estado.

    El tablero suma TODAS las causas del catálogo, 0 incluido (ver
    AgregarPausasPorCausa): dice de un vistazo dónde se concentra el tiempo
    perdido. Con el total del período en 0 no hay nada que comparar entre
    causas, así que no se dibuja. No es una franja de KPI: son cinco causas
    de texto largo, que no caben en una tarjeta de cifra; el desglose gráfico
    vive además en el dashboard.

    Datos esperados (ver PausasController::index()): la cáscara de
    CascaraPanel, más:
    - $pausas (LengthAwarePaginator<Pausa>): inicio descendente.
    - $agregado (array{total_minutos: int, por_causa: array<string, int>}):
      el tablero — TODAS las causas del catálogo, incluidas las de 0 minutos.
    - $filtros (array{periodo}): valor aplicado, para dejar el campo con el
      valor tras el submit.
    - $puedeRegistrar (bool): gatea el botón "Nueva pausa".

    Gateada por `operaciones.pausa.ver`, verificado server-side en el
    controlador.

    Estilos en resources/css/pages/pausas.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
@php
    // Espacios sin corte: «0 h 45 min» no se parte en dos líneas en una columna angosta.
    $formatearDuracion = fn (int $minutos): string => str_replace(' ', "\u{00A0}", __('operaciones.pausas.duracion_valor', [
        'horas' => intdiv($minutos, 60),
        'minutos' => $minutos % 60,
    ]));

    // Filas del tablero: el total del período primero y una fila por causa.
    $filasTablero = [
        ['label' => __('operaciones.pausas.tablero_total'), 'value' => $formatearDuracion($agregado['total_minutos']), 'mono' => true, 'variant' => 'warning'],
        ...collect($agregado['por_causa'])
            ->map(fn (int $minutos, string $causa) => ['label' => __('operaciones.pausas.causa.'.$causa), 'value' => $formatearDuracion($minutos), 'mono' => true])
            ->values()
            ->all(),
    ];
@endphp
<x-templates.panel-shell :title="__('operaciones.pausas.titulo')" :tema="$tema">
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
        :vista-actual="__('operaciones.pausas.titulo')"
    >
        <div class="ag-pausas">
            <x-organisms.page-header
                :title="__('operaciones.pausas.titulo')"
                :subtitle="__('operaciones.pausas.subtitulo')"
            >
                @if ($puedeRegistrar)
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.pausas.create')" variant="primary" icon="add">
                            {{ __('operaciones.pausas.nuevo') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endif
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($agregado['total_minutos'] > 0)
                <x-molecules.summary-card :title="__('operaciones.pausas.tablero_titulo')" :items="$filasTablero" />
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
                $filtrosActivosCount = collect($filtros)->filter(fn ($valor) => $valor !== null && $valor !== '')->count();
            @endphp

            @if ($hayFiltrosActivos || $pausas->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-organisms.filter-panel
                        :action="route('panel.pausas.index')"
                        :active-count="$filtrosActivosCount"
                    >
                        <x-atoms.input
                            type="month"
                            name="periodo"
                            id="filtro-periodo"
                            :label="__('operaciones.pausas.filtro_periodo')"
                            :value="$filtros['periodo']"
                        />
                    </x-organisms.filter-panel>
                </div>
            @endif

            @if ($pausas->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.empty-state
                        icon="search_off"
                        :title="__('operaciones.pausas.filtro_vacio_titulo')"
                        :detail="__('operaciones.pausas.filtro_vacio_detalle')"
                    />
                @else
                    {{-- Sin botón adentro: el vacío de un listado solo explica. El
                         alta ya está en la cabecera, y es el único botón sólido. --}}
                    <x-molecules.empty-state
                        icon="pause_circle"
                        :title="__('operaciones.pausas.vacio_titulo')"
                        :detail="__('operaciones.pausas.vacio_detalle')"
                    />
                @endif
            @else
                <x-molecules.index-table columns="3rem minmax(0, 2fr) minmax(0, 1.4fr) minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr)">
                    <x-slot:head>
                        <span role="columnheader" class="ag-index-table__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('operaciones.pausas.col_sesion') }}</span>
                        <span role="columnheader">{{ __('operaciones.pausas.col_causa') }}</span>
                        <span role="columnheader">{{ __('operaciones.pausas.col_inicio') }}</span>
                        <span role="columnheader">{{ __('operaciones.pausas.col_fin') }}</span>
                        <span role="columnheader">{{ __('operaciones.pausas.col_duracion') }}</span>
                    </x-slot:head>

                    @foreach ($pausas as $pausa)
                        <div class="ag-index-table__row" role="row">
                            <span role="cell" class="ag-index-table__indice">
                                {{ ($pausas->currentPage() - 1) * $pausas->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell">{{ __('operaciones.pausas.sesion_etiqueta', ['id' => $pausa->sesion_id, 'trabajo' => $pausa->sesion->trabajo_id, 'secuencia' => $pausa->sesion->secuencia]) }}</span>
                            <span role="cell">{{ __('operaciones.pausas.causa.'.$pausa->causa->value) }}</span>
                            <span role="cell" class="ag-pausas__cifra">{{ $pausa->inicio->format('d/m/Y H:i') }}</span>
                            <span role="cell" class="ag-pausas__cifra">{{ $pausa->fin->format('d/m/Y H:i') }}</span>
                            <span role="cell" class="ag-pausas__cifra">{{ $formatearDuracion($pausa->duracion_minutos) }}</span>
                        </div>
                    @endforeach
                </x-molecules.index-table>

                <x-molecules.pagination :paginator="$pausas" :aria-label="__('operaciones.pausas.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
