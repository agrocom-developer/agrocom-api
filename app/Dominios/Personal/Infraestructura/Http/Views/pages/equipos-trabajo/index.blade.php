{{--
    Page: equipos-trabajo/index (GET /panel/equipos-trabajo, panel.equipos-trabajo.index)
    Listado de equipos de trabajo (tarea 72, HU-49): arquetipo Listado, §6.2
    de docs/diseno/guia_pantalla_panel.md — cabecera → filtros → tabla →
    paginación. Mismo molde que generadores/index.blade.php.

    Datos esperados (ver EquiposTrabajoController::index()): la cáscara de
    CascaraPanel, más:
    - $equipos (LengthAwarePaginator<EquipoTrabajo>): código ascendente.
    - $etiquetasBase (array<int, string>): nombre de base por id.
    - $basesDisponibles (Collection<int, string>): opciones del filtro de base.
    - $filtros (array{q: string, base_id: ?int, estado: ?string}).

    Gateada por `personal.equipo_trabajo.ver`. Los botones
    "Nuevo equipo"/"Editar"/"Eliminar" se ocultan con `@puede` (presentación,
    no autorización — el servidor revalida en el controlador). "Ver ficha"
    solo exige `.ver` (ya verificado para entrar acá), así que no lleva
    `@puede` propio.

    Estilos en resources/css/pages/equipos-trabajo.css — cero color
    hardcodeado (CLAUDE.md invariante 11).
--}}
@php
    $variantePorEstado = [
        'activo' => 'success',
        'inactivo' => 'neutral',
    ];
@endphp
<x-templates.panel-shell :title="__('personal.equipos_trabajo.titulo')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :zona-horaria="$zonaHoraria ?? null"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :campaniaActiva="$campaniaActiva"
        :periodo="$periodo"
        :version="$version"
        :vista-actual="__('personal.equipos_trabajo.titulo')"
    >
        <div class="ag-equipos-trabajo">
            <x-organisms.page-header
                :title="__('personal.equipos_trabajo.titulo')"
                :subtitle="__('personal.equipos_trabajo.subtitulo')"
            >
                @puede('personal.equipo_trabajo.crear')
                    <x-slot:actions>
                        <x-atoms.button href="{{ route('panel.equipos-trabajo.create') }}" variant="primary" icon="add">
                            {{ __('personal.equipos_trabajo.nuevo') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-equipos-trabajo__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            <form method="GET" action="{{ route('panel.equipos-trabajo.index') }}" class="ag-filtros ag-equipos-trabajo__filtros">
                <div class="ag-input">
                    <label for="filtro-q" class="ag-input__label">{{ __('personal.equipos_trabajo.filtro_busqueda') }}</label>
                    <div class="ag-input__control">
                        <input
                            type="search"
                            name="q"
                            id="filtro-q"
                            class="ag-input__field"
                            value="{{ $filtros['q'] }}"
                            placeholder="{{ __('personal.equipos_trabajo.filtro_busqueda_placeholder') }}"
                        >
                    </div>
                </div>

                <x-atoms.select
                    name="base_id"
                    id="filtro-base"
                    label="{{ __('personal.equipos_trabajo.filtro_base') }}"
                    :options="$basesDisponibles"
                    :value="$filtros['base_id']"
                    placeholder="{{ __('personal.equipos_trabajo.filtro_todos') }}"
                />

                @php
                    $opcionesEstado = collect($variantePorEstado)->mapWithKeys(fn ($_, $valor) => [
                        $valor => __('personal.estado.'.$valor)
                    ])->all();
                @endphp
                <x-atoms.select
                    name="estado"
                    id="filtro-estado"
                    label="{{ __('personal.equipos_trabajo.filtro_estado') }}"
                    :options="$opcionesEstado"
                    :value="$filtros['estado']"
                    placeholder="{{ __('personal.equipos_trabajo.filtro_todos') }}"
                />

                <div class="ag-filtros__acciones ag-equipos-trabajo__filtros-acciones">
                    <x-atoms.button type="submit" variant="outline" size="md" icon="search">
                        {{ __('personal.equipos_trabajo.filtrar') }}
                    </x-atoms.button>

                    @if ($filtros['q'] !== '' || $filtros['base_id'] !== null || $filtros['estado'] !== null)
                        <x-atoms.button href="{{ route('panel.equipos-trabajo.index') }}" variant="text" size="md">
                            {{ __('personal.equipos_trabajo.limpiar_filtro') }}
                        </x-atoms.button>
                    @endif
                </div>
            </form>

            @if ($equipos->isEmpty())
                <x-molecules.alert-strip variant="info" icon="groups" class="ag-equipos-trabajo__aviso">
                    {{ __(($filtros['q'] !== '' || $filtros['base_id'] !== null || $filtros['estado'] !== null) ? 'personal.equipos_trabajo.filtro_vacio' : 'personal.equipos_trabajo.vacio') }}
                </x-molecules.alert-strip>
            @else
                <div class="ag-equipos-trabajo__tabla" role="table">
                    <div class="ag-equipos-trabajo__head" role="row">
                        <span role="columnheader">{{ __('personal.equipos_trabajo.col_codigo') }}</span>
                        <span role="columnheader">{{ __('personal.equipos_trabajo.col_nombre') }}</span>
                        <span role="columnheader">{{ __('personal.equipos_trabajo.col_base') }}</span>
                        <span role="columnheader">{{ __('personal.equipos_trabajo.col_vigencia') }}</span>
                        <span role="columnheader">{{ __('personal.equipos_trabajo.col_estado') }}</span>
                        <span role="columnheader" aria-hidden="true"></span>
                    </div>

                    @foreach ($equipos as $equipo)
                        <div class="ag-equipos-trabajo__fila" role="row">
                            <span role="cell" class="ag-equipos-trabajo__codigo">{{ $equipo->codigo }}</span>
                            <span role="cell">{{ $equipo->nombre ?? __('personal.equipos_trabajo.sin_nombre') }}</span>
                            <span role="cell">{{ $etiquetasBase[$equipo->base_id] ?? "#{$equipo->base_id}" }}</span>
                            <span role="cell">
                                {{ $equipo->desde->format('d/m/Y') }} – {{ $equipo->hasta?->format('d/m/Y') ?? __('personal.equipos_trabajo.vigente') }}
                            </span>
                            <span role="cell">
                                <x-atoms.badge :variant="$variantePorEstado[$equipo->estado->value]">
                                    {{ __('personal.estado.'.$equipo->estado->value) }}
                                </x-atoms.badge>
                            </span>

                            <span role="cell" class="ag-equipos-trabajo__acciones">
                                <x-atoms.button href="{{ route('panel.equipos-trabajo.show', $equipo) }}" variant="outline" size="sm" icon="visibility">
                                    {{ __('personal.equipos_trabajo.ver') }}
                                </x-atoms.button>

                                @puede('personal.equipo_trabajo.editar')
                                    <x-atoms.button href="{{ route('panel.equipos-trabajo.edit', $equipo) }}" variant="outline" size="sm" icon="edit">
                                        {{ __('personal.equipos_trabajo.editar') }}
                                    </x-atoms.button>
                                @endpuede

                                @puede('personal.equipo_trabajo.eliminar')
                                    <form
                                        method="POST"
                                        action="{{ route('panel.equipos-trabajo.destroy', $equipo) }}"
                                        onsubmit="return confirm('{{ __('personal.equipos_trabajo.confirmar_baja') }}')"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <x-atoms.button type="submit" variant="danger-outline" size="sm" icon="delete">
                                            {{ __('personal.equipos_trabajo.eliminar_accion') }}
                                        </x-atoms.button>
                                    </form>
                                @endpuede
                            </span>
                        </div>
                    @endforeach
                </div>

                @if ($equipos->hasPages())
                    <nav class="ag-equipos-trabajo__paginacion" aria-label="{{ __('personal.equipos_trabajo.paginacion_aria') }}">
                        @if (! $equipos->onFirstPage())
                            <x-atoms.button href="{{ $equipos->previousPageUrl() }}" variant="outline" size="sm" icon="chevron_left">
                                {{ __('personal.equipos_trabajo.paginacion_anterior') }}
                            </x-atoms.button>
                        @endif

                        <span class="ag-equipos-trabajo__paginacion-info">
                            {{ __('personal.equipos_trabajo.paginacion_info', ['actual' => $equipos->currentPage(), 'total' => $equipos->lastPage()]) }}
                        </span>

                        @if ($equipos->hasMorePages())
                            <x-atoms.button href="{{ $equipos->nextPageUrl() }}" variant="outline" size="sm" icon="chevron_right" iconPosition="end">
                                {{ __('personal.equipos_trabajo.paginacion_siguiente') }}
                            </x-atoms.button>
                        @endif
                    </nav>
                @endif
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
