{{--
    Page: pausas/index (GET /panel/pausas, panel.pausas.index)
    Listado de pausas + tablero agregado por causa (HU-44, tarea 58):
    arquetipo Listado, §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera
    → tablero agregado → filtro de período → tabla → paginación. Mismo
    molde que gastos/index.blade.php, con el agregado por causa como CA
    propio de esta HU (mismo shape que la maqueta del dashboard,
    ahora con datos reales — adaptado a tabla en vez de barras, más robusto
    frente a un total variable de causas y minutos que las barras fijas del
    dashboard).

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
    $formatearDuracion = fn (int $minutos): string => __('operaciones.pausas.duracion_valor', [
        'horas' => intdiv($minutos, 60),
        'minutos' => $minutos % 60,
    ]);
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
                        <x-atoms.button href="{{ route('panel.pausas.create') }}" variant="primary" icon="add">
                            {{ __('operaciones.pausas.nuevo') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endif
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-pausas__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            <div class="ag-card ag-card--padded ag-pausas__tablero">
                <div class="ag-card__head ag-card__head--flush">
                    <h2 class="ag-card__title">{{ __('operaciones.pausas.tablero_titulo') }}</h2>
                    <span class="ag-pausas__tablero-total">
                        {{ __('operaciones.pausas.tablero_total') }}: {{ $formatearDuracion($agregado['total_minutos']) }}
                    </span>
                </div>

                <div class="ag-pausas__tablero-tabla" role="table">
                    @foreach ($agregado['por_causa'] as $causa => $minutos)
                        <div class="ag-pausas__tablero-fila" role="row">
                            <span role="cell">{{ __('operaciones.pausas.causa.'.$causa) }}</span>
                            <span role="cell" class="ag-pausas__cifra">{{ $formatearDuracion($minutos) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            @php $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== ''); @endphp

            @if ($hayFiltrosActivos || $pausas->isNotEmpty())
                <form method="GET" action="{{ route('panel.pausas.index') }}" class="ag-filtros ag-pausas__filtros">
                    <div class="ag-input">
                        <label for="filtro-periodo" class="ag-input__label">{{ __('operaciones.pausas.filtro_periodo') }}</label>
                        <div class="ag-input__control">
                            <input
                                type="month"
                                name="periodo"
                                id="filtro-periodo"
                                class="ag-input__field"
                                value="{{ $filtros['periodo'] }}"
                            >
                        </div>
                    </div>

                    <div class="ag-filtros__acciones ag-pausas__filtros-acciones">
                        <x-atoms.button type="submit" variant="outline" size="md" icon="search">
                            {{ __('operaciones.pausas.filtrar') }}
                        </x-atoms.button>

                        @if ($hayFiltrosActivos)
                            <x-atoms.button href="{{ route('panel.pausas.index') }}" variant="text" size="md">
                                {{ __('operaciones.pausas.limpiar_filtro') }}
                            </x-atoms.button>
                        @endif
                    </div>
                </form>
            @endif

            @if ($pausas->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.alert-strip variant="info" icon="pause_circle" class="ag-pausas__aviso">
                        {{ __('operaciones.pausas.filtro_vacio') }}
                    </x-molecules.alert-strip>
                @else
                    <x-molecules.empty-state
                        icon="pause_circle"
                        :title="__('operaciones.pausas.vacio_titulo')"
                        :detail="__('operaciones.pausas.vacio_detalle')"
                    />
                @endif
            @else
                <div class="ag-pausas__tabla" role="table">
                    <div class="ag-pausas__head" role="row">
                        <span role="columnheader">{{ __('operaciones.pausas.col_sesion') }}</span>
                        <span role="columnheader">{{ __('operaciones.pausas.col_causa') }}</span>
                        <span role="columnheader">{{ __('operaciones.pausas.col_inicio') }}</span>
                        <span role="columnheader">{{ __('operaciones.pausas.col_fin') }}</span>
                        <span role="columnheader">{{ __('operaciones.pausas.col_duracion') }}</span>
                    </div>

                    @foreach ($pausas as $pausa)
                        <div class="ag-pausas__fila" role="row">
                            <span role="cell">{{ __('operaciones.pausas.sesion_etiqueta', ['id' => $pausa->sesion_id, 'trabajo' => $pausa->sesion->trabajo_id, 'secuencia' => $pausa->sesion->secuencia]) }}</span>
                            <span role="cell">{{ __('operaciones.pausas.causa.'.$pausa->causa->value) }}</span>
                            <span role="cell" class="ag-pausas__cifra">{{ $pausa->inicio->format('d/m/Y H:i') }}</span>
                            <span role="cell" class="ag-pausas__cifra">{{ $pausa->fin->format('d/m/Y H:i') }}</span>
                            <span role="cell" class="ag-pausas__cifra">{{ $formatearDuracion($pausa->duracion_minutos) }}</span>
                        </div>
                    @endforeach
                </div>

                @if ($pausas->hasPages())
                    <nav class="ag-pausas__paginacion" aria-label="{{ __('operaciones.pausas.paginacion_aria') }}">
                        @if (! $pausas->onFirstPage())
                            <x-atoms.button href="{{ $pausas->previousPageUrl() }}" variant="outline" size="sm" icon="chevron_left">
                                {{ __('operaciones.pausas.paginacion_anterior') }}
                            </x-atoms.button>
                        @endif

                        <span class="ag-pausas__paginacion-info">
                            {{ __('operaciones.pausas.paginacion_info', ['actual' => $pausas->currentPage(), 'total' => $pausas->lastPage()]) }}
                        </span>

                        @if ($pausas->hasMorePages())
                            <x-atoms.button href="{{ $pausas->nextPageUrl() }}" variant="outline" size="sm" icon="chevron_right" iconPosition="end">
                                {{ __('operaciones.pausas.paginacion_siguiente') }}
                            </x-atoms.button>
                        @endif
                    </nav>
                @endif
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
