{{--
    Page: campanias/index (GET /panel/campanias, panel.campanias.index)
    Listado de campañas (ADR 0015 punto 1, tarea 69): arquetipo Listado, §6.2
    de docs/diseno/guia_pantalla_panel.md — cabecera → filtros → tabla →
    paginación. Mismo molde que `comercial/contratos/index.blade.php`, con
    una máquina de estados más simple (dos transiciones, no cuatro).

    Datos esperados (ver CampaniasController::index()): la cáscara de
    CascaraPanel, más:
    - $campanias (LengthAwarePaginator<Campania>): fecha de inicio descendente.
    - $filtros (array{q: string}): búsqueda aplicada, para dejar el campo
      con el valor tras el submit.

    Gateada por `campania.campania.ver`, verificado server-side en el
    controlador. El botón "Nueva campaña" y las acciones de cambio de estado
    se ocultan con `@puede` (presentación, no autorización — el servidor
    revalida en CampaniasController). Las acciones de cambio de estado
    disponibles dependen del estado ACTUAL de cada fila: una campaña
    `planificada` solo ofrece "Abrir"; una `abierta`, "Cerrar"; una `cerrada`,
    ninguna (es terminal) — ver `TransicionesCampania`, que es la fuente real
    de esta regla; acá solo se refleja para no ofrecer un botón que el
    servidor va a rechazar. `.cambiar_estado` es exclusivo del rol `dueno`.

    Estilos en resources/css/pages/campanias.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('campania.campanias.titulo')" :tema="$tema">
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
        :vista-actual="__('campania.campanias.titulo')"
    >
        <div class="ag-campanias">
            <x-organisms.page-header
                :title="__('campania.campanias.titulo')"
                :subtitle="__('campania.campanias.subtitulo')"
            >
                @puede('campania.campania.crear')
                    <x-slot:actions>
                        <x-atoms.button href="{{ route('panel.campanias.create') }}" variant="primary" icon="add">
                            {{ __('campania.campanias.nueva') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-campanias__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @if ($errors->has('estado'))
                <x-molecules.alert-strip variant="danger" icon="error" class="ag-campanias__aviso">
                    {{ $errors->first('estado') }}
                </x-molecules.alert-strip>
            @endif

            <form method="GET" action="{{ route('panel.campanias.index') }}" class="ag-filtros ag-campanias__filtros">
                <div class="ag-input">
                    <label for="filtro-q" class="ag-input__label">{{ __('campania.campanias.filtro_busqueda') }}</label>
                    <div class="ag-input__control">
                        <input
                            type="search"
                            name="q"
                            id="filtro-q"
                            class="ag-input__field"
                            value="{{ $filtros['q'] }}"
                            placeholder="{{ __('campania.campanias.filtro_busqueda_placeholder') }}"
                        >
                    </div>
                </div>

                <div class="ag-filtros__acciones ag-campanias__filtros-acciones">
                    <x-atoms.button type="submit" variant="outline" size="md" icon="search">
                        {{ __('campania.campanias.filtrar') }}
                    </x-atoms.button>

                    @if ($filtros['q'] !== '')
                        <x-atoms.button href="{{ route('panel.campanias.index') }}" variant="text" size="md">
                            {{ __('campania.campanias.limpiar_filtro') }}
                        </x-atoms.button>
                    @endif
                </div>
            </form>

            @if ($campanias->isEmpty())
                <x-molecules.alert-strip variant="info" icon="calendar_month" class="ag-campanias__aviso">
                    {{ __($filtros['q'] !== '' ? 'campania.campanias.filtro_vacio' : 'campania.campanias.vacio') }}
                </x-molecules.alert-strip>
            @else
                <div class="ag-campanias__tabla" role="table">
                    <div class="ag-campanias__head" role="row">
                        <span role="columnheader">{{ __('campania.campanias.col_codigo') }}</span>
                        <span role="columnheader">{{ __('campania.campanias.col_nombre') }}</span>
                        <span role="columnheader">{{ __('campania.campanias.col_vigencia') }}</span>
                        <span role="columnheader">{{ __('campania.campanias.col_estado') }}</span>
                        <span role="columnheader" aria-hidden="true"></span>
                    </div>

                    @foreach ($campanias as $campania)
                        @php
                            $variantePorEstado = [
                                'planificada' => 'neutral',
                                'abierta' => 'success',
                                'cerrada' => 'info',
                            ];
                            $estadoValor = $campania->estado->value;
                        @endphp
                        <div class="ag-campanias__fila" role="row">
                            <span role="cell" class="ag-campanias__codigo">{{ $campania->codigo }}</span>
                            <span role="cell">{{ $campania->nombre ?? '—' }}</span>
                            <span role="cell" class="ag-campanias__mono">
                                {{ __('campania.campanias.vigencia', ['inicio' => $campania->fecha_inicio->format('d/m/Y'), 'fin' => $campania->fecha_fin->format('d/m/Y')]) }}
                            </span>
                            <span role="cell">
                                <x-atoms.badge :variant="$variantePorEstado[$estadoValor]">
                                    {{ __('campania.campania.estado.'.$estadoValor) }}
                                </x-atoms.badge>
                            </span>

                            <span role="cell" class="ag-campanias__acciones">
                                @puede('campania.campania.editar')
                                    <x-atoms.button href="{{ route('panel.campanias.edit', $campania) }}" variant="outline" size="sm" icon="edit">
                                        {{ __('campania.campanias.editar') }}
                                    </x-atoms.button>
                                @endpuede

                                @puede('campania.campania.cambiar_estado')
                                    @if ($estadoValor === 'planificada')
                                        <form
                                            method="POST"
                                            action="{{ route('panel.campanias.cambiar-estado', $campania) }}"
                                            onsubmit="return confirm('{{ __('campania.campanias.confirmar_abrir') }}')"
                                        >
                                            @csrf
                                            <input type="hidden" name="estado" value="abierta">
                                            <x-atoms.button type="submit" variant="outline" size="sm" icon="check_circle">
                                                {{ __('campania.campanias.accion_abrir') }}
                                            </x-atoms.button>
                                        </form>
                                    @elseif ($estadoValor === 'abierta')
                                        <form
                                            method="POST"
                                            action="{{ route('panel.campanias.cambiar-estado', $campania) }}"
                                            onsubmit="return confirm('{{ __('campania.campanias.confirmar_cerrar') }}')"
                                        >
                                            @csrf
                                            <input type="hidden" name="estado" value="cerrada">
                                            <x-atoms.button type="submit" variant="danger-outline" size="sm" icon="lock">
                                                {{ __('campania.campanias.accion_cerrar') }}
                                            </x-atoms.button>
                                        </form>
                                    @endif
                                @endpuede
                            </span>
                        </div>
                    @endforeach
                </div>

                @if ($campanias->hasPages())
                    <nav class="ag-campanias__paginacion" aria-label="{{ __('campania.campanias.paginacion_aria') }}">
                        @if (! $campanias->onFirstPage())
                            <x-atoms.button href="{{ $campanias->previousPageUrl() }}" variant="outline" size="sm" icon="chevron_left">
                                {{ __('campania.campanias.paginacion_anterior') }}
                            </x-atoms.button>
                        @endif

                        <span class="ag-campanias__paginacion-info">
                            {{ __('campania.campanias.paginacion_info', ['actual' => $campanias->currentPage(), 'total' => $campanias->lastPage()]) }}
                        </span>

                        @if ($campanias->hasMorePages())
                            <x-atoms.button href="{{ $campanias->nextPageUrl() }}" variant="outline" size="sm" icon="chevron_right" iconPosition="end">
                                {{ __('campania.campanias.paginacion_siguiente') }}
                            </x-atoms.button>
                        @endif
                    </nav>
                @endif
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
