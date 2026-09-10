{{--
    Page: baterias/index (GET /panel/baterias, panel.baterias.index)
    Listado del catálogo de baterías (HU-39, tarea 51): arquetipo Listado,
    §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera → filtros → tabla →
    paginación. Mismo molde que vehiculos/index.blade.php (tarea 50), con
    una columna adicional (ciclos) y la alerta de retiro por fila.

    Datos esperados (ver BateriasController::index()): la cáscara de
    CascaraPanel, más:
    - $baterias (LengthAwarePaginator<Bateria>): identificador ascendente.
      Cada fila ya trae `alerta` (bool) y `alerta_motivo` (string|null)
      calculados por ListarBaterias — la vista no evalúa ningún umbral.
    - $etiquetasBase (array<int, string>): nombre de base por id, ya resuelto
      por el controlador (Bateria no tiene relación Eloquent hacia PerBase,
      son de módulos distintos). Un id sin etiqueta (base borrada) cae al
      `#id` crudo.
    - $basesDisponibles (Collection<int, string>): opciones del filtro/select
      de base.
    - $filtros (array{q: string, base_id: ?int, estado: ?string}): filtros
      aplicados, para dejar los campos con su valor tras el submit.

    Gateada por `mantenimiento.bateria.ver`, verificado server-side en el
    controlador. Los botones "Nueva batería"/"Editar"/"Eliminar" se ocultan
    con `@puede` (presentación, no autorización — el servidor revalida en
    BateriasController).

    Estilos en resources/css/pages/baterias.css — cero color hardcodeado
    (CLAUDE.md invariante 11). El badge de estado usa el eje gris↔verde
    (activa=success, retirada=neutral); el de alerta usa "warning" (ámbar),
    reservado a esta columna — nunca para el estado, que no es un problema
    en sí (sistema_diseno_panel.md §8).
--}}
@php
    $variantePorEstado = [
        'activa' => 'success',
        'retirada' => 'neutral',
    ];
@endphp
<x-templates.panel-shell :title="__('mantenimiento.baterias.titulo')" :tema="$tema">
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
        :vista-actual="__('mantenimiento.baterias.titulo')"
    >
        <div class="ag-baterias">
            <x-organisms.page-header
                :title="__('mantenimiento.baterias.titulo')"
                :subtitle="__('mantenimiento.baterias.subtitulo')"
            >
                @puede('mantenimiento.bateria.crear')
                    <x-slot:actions>
                        <x-atoms.button href="{{ route('panel.baterias.create') }}" variant="primary" icon="add">
                            {{ __('mantenimiento.baterias.nuevo') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-baterias__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            <form method="GET" action="{{ route('panel.baterias.index') }}" class="ag-filtros ag-baterias__filtros">
                <div class="ag-input">
                    <label for="filtro-q" class="ag-input__label">{{ __('mantenimiento.baterias.filtro_busqueda') }}</label>
                    <div class="ag-input__control">
                        <input
                            type="search"
                            name="q"
                            id="filtro-q"
                            class="ag-input__field"
                            value="{{ $filtros['q'] }}"
                            placeholder="{{ __('mantenimiento.baterias.filtro_busqueda_placeholder') }}"
                        >
                    </div>
                </div>

                <x-atoms.select
                    name="base_id"
                    id="filtro-base"
                    label="{{ __('mantenimiento.baterias.filtro_base') }}"
                    :options="$basesDisponibles"
                    :value="$filtros['base_id']"
                    placeholder="{{ __('mantenimiento.baterias.filtro_todos') }}"
                />

                @php
                    $opcionesEstado = collect($variantePorEstado)->mapWithKeys(fn ($_, $valor) => [
                        $valor => __('mantenimiento.estado_bateria.'.$valor)
                    ])->all();
                @endphp
                <x-atoms.select
                    name="estado"
                    id="filtro-estado"
                    label="{{ __('mantenimiento.baterias.filtro_estado') }}"
                    :options="$opcionesEstado"
                    :value="$filtros['estado']"
                    placeholder="{{ __('mantenimiento.baterias.filtro_todos') }}"
                />

                <div class="ag-filtros__acciones ag-baterias__filtros-acciones">
                    {{-- outline, no primary: "Nueva batería" ya es el único
                         botón sólido del pliegue (§5 de la guía de pantalla). --}}
                    <x-atoms.button type="submit" variant="outline" size="md" icon="search">
                        {{ __('mantenimiento.baterias.filtrar') }}
                    </x-atoms.button>

                    @if ($filtros['q'] !== '' || $filtros['base_id'] !== null || $filtros['estado'] !== null)
                        <x-atoms.button href="{{ route('panel.baterias.index') }}" variant="text" size="md">
                            {{ __('mantenimiento.baterias.limpiar_filtro') }}
                        </x-atoms.button>
                    @endif
                </div>
            </form>

            @if ($baterias->isEmpty())
                <x-molecules.alert-strip variant="info" icon="battery_charging_full" class="ag-baterias__aviso">
                    {{ __(($filtros['q'] !== '' || $filtros['base_id'] !== null || $filtros['estado'] !== null) ? 'mantenimiento.baterias.filtro_vacio' : 'mantenimiento.baterias.vacio') }}
                </x-molecules.alert-strip>
            @else
                <div class="ag-baterias__tabla" role="table">
                    <div class="ag-baterias__head" role="row">
                        <span role="columnheader">{{ __('mantenimiento.baterias.col_identificador') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.baterias.col_base') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.baterias.col_ciclos') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.baterias.col_estado') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.baterias.col_alerta') }}</span>
                        <span role="columnheader" aria-hidden="true"></span>
                    </div>

                    @foreach ($baterias as $bateria)
                        <div class="ag-baterias__fila" role="row">
                            <span role="cell" class="ag-baterias__identificador">{{ $bateria->identificador }}</span>
                            <span role="cell">
                                {{ $bateria->base_id !== null ? ($etiquetasBase[$bateria->base_id] ?? "#{$bateria->base_id}") : __('mantenimiento.baterias.sin_base') }}
                            </span>
                            <span role="cell">{{ $bateria->ciclos_acumulados }}</span>
                            <span role="cell">
                                <x-atoms.badge :variant="$variantePorEstado[$bateria->estado]">
                                    {{ __('mantenimiento.estado_bateria.'.$bateria->estado) }}
                                </x-atoms.badge>
                            </span>
                            <span role="cell">
                                @if ($bateria->alerta)
                                    <x-atoms.badge variant="warning" icon="battery_alert" title="{{ __('mantenimiento.baterias.alerta_motivo.'.$bateria->alerta_motivo) }}">
                                        {{ __('mantenimiento.baterias.alerta_activa') }}
                                    </x-atoms.badge>
                                @else
                                    {{ __('mantenimiento.baterias.sin_alerta') }}
                                @endif
                            </span>

                            <span role="cell" class="ag-baterias__acciones">
                                @puede('mantenimiento.bateria.editar')
                                    <x-atoms.button href="{{ route('panel.baterias.edit', $bateria) }}" variant="warning-outline" size="sm" icon="edit">
                                        {{ __('mantenimiento.baterias.editar') }}
                                    </x-atoms.button>
                                @endpuede

                                @puede('mantenimiento.bateria.eliminar')
                                    <form
                                        method="POST"
                                        action="{{ route('panel.baterias.destroy', $bateria) }}"
                                        onsubmit="return confirm('{{ __('mantenimiento.baterias.confirmar_baja') }}')"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <x-atoms.button type="submit" variant="danger-outline" size="sm" icon="delete">
                                            {{ __('mantenimiento.baterias.eliminar_accion') }}
                                        </x-atoms.button>
                                    </form>
                                @endpuede
                            </span>
                        </div>
                    @endforeach
                </div>

                @if ($baterias->hasPages())
                    <nav class="ag-baterias__paginacion" aria-label="{{ __('mantenimiento.baterias.paginacion_aria') }}">
                        @if (! $baterias->onFirstPage())
                            <x-atoms.button href="{{ $baterias->previousPageUrl() }}" variant="outline" size="sm" icon="chevron_left">
                                {{ __('mantenimiento.baterias.paginacion_anterior') }}
                            </x-atoms.button>
                        @endif

                        <span class="ag-baterias__paginacion-info">
                            {{ __('mantenimiento.baterias.paginacion_info', ['actual' => $baterias->currentPage(), 'total' => $baterias->lastPage()]) }}
                        </span>

                        @if ($baterias->hasMorePages())
                            <x-atoms.button href="{{ $baterias->nextPageUrl() }}" variant="outline" size="sm" icon="chevron_right" iconPosition="end">
                                {{ __('mantenimiento.baterias.paginacion_siguiente') }}
                            </x-atoms.button>
                        @endif
                    </nav>
                @endif
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
