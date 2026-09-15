{{--
    Page: generadores/index (GET /panel/generadores, panel.generadores.index)
    Listado del catálogo de generadores (tarea 72, HU-49): arquetipo Listado,
    §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera → filtros → tabla →
    paginación. Mismo molde que vehiculos/index.blade.php, con una columna
    adicional (modelo).

    Datos esperados (ver GeneradoresController::index()): la cáscara de
    CascaraPanel, más:
    - $generadores (LengthAwarePaginator<Generador>): identificador ascendente.
    - $etiquetasBase (array<int, string>): nombre de base por id, ya resuelto
      por el controlador (`DB::table('per_bases')` — Generador no tiene
      relación Eloquent hacia PerBase, son de módulos distintos). Un id sin
      etiqueta (base borrada) cae al `#id` crudo.
    - $basesDisponibles (Collection<int, string>): opciones del filtro/select
      de base.
    - $filtros (array{q: string, base_id: ?int, estado: ?string}): filtros
      aplicados, para dejar los campos con su valor tras el submit.

    Gateada por `mantenimiento.generador.ver`, verificado server-side en el
    controlador. Los botones "Nuevo generador"/"Editar"/"Eliminar" se ocultan
    con `@puede` (presentación, no autorización — el servidor revalida en
    GeneradoresController).

    Estilos en resources/css/pages/generadores.css — cero color hardcodeado
    (CLAUDE.md invariante 11). El badge de estado usa el eje gris↔verde
    (activo=success, taller=neutral, de_baja=danger) — nunca ámbar para un
    chip que se repite fila a fila (sistema_diseno_panel.md §8).
--}}
@php
    $variantePorEstado = [
        'activo' => 'success',
        'taller' => 'neutral',
        'de_baja' => 'danger',
    ];
@endphp
<x-templates.panel-shell :title="__('mantenimiento.generadores.titulo')" :tema="$tema">
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
        :vista-actual="__('mantenimiento.generadores.titulo')"
    >
        <div class="ag-generadores">
            <x-organisms.page-header
                :title="__('mantenimiento.generadores.titulo')"
                :subtitle="__('mantenimiento.generadores.subtitulo')"
            >
                @puede('mantenimiento.generador.crear')
                    <x-slot:actions>
                        <x-atoms.button href="{{ route('panel.generadores.create') }}" variant="primary" icon="add">
                            {{ __('mantenimiento.generadores.nuevo') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-generadores__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
            @endphp

            @if ($hayFiltrosActivos || $generadores->isNotEmpty())
                <form method="GET" action="{{ route('panel.generadores.index') }}" class="ag-filtros ag-generadores__filtros">
                    <div class="ag-input">
                        <label for="filtro-q" class="ag-input__label">{{ __('mantenimiento.generadores.filtro_busqueda') }}</label>
                        <div class="ag-input__control">
                            <input
                                type="search"
                                name="q"
                                id="filtro-q"
                                class="ag-input__field"
                                value="{{ $filtros['q'] }}"
                                placeholder="{{ __('mantenimiento.generadores.filtro_busqueda_placeholder') }}"
                            >
                        </div>
                    </div>

                    <x-atoms.select
                        name="base_id"
                        id="filtro-base"
                        label="{{ __('mantenimiento.generadores.filtro_base') }}"
                        :options="$basesDisponibles"
                        :value="$filtros['base_id']"
                        placeholder="{{ __('mantenimiento.generadores.filtro_todos') }}"
                    />

                    @php
                        $opcionesEstado = collect($variantePorEstado)->mapWithKeys(fn ($_, $valor) => [
                            $valor => __('mantenimiento.estado.'.$valor)
                        ])->all();
                    @endphp
                    <x-atoms.select
                        name="estado"
                        id="filtro-estado"
                        label="{{ __('mantenimiento.generadores.filtro_estado') }}"
                        :options="$opcionesEstado"
                        :value="$filtros['estado']"
                        placeholder="{{ __('mantenimiento.generadores.filtro_todos') }}"
                    />

                    <div class="ag-filtros__acciones ag-generadores__filtros-acciones">
                        {{-- outline, no primary: "Nuevo generador" ya es el único
                             botón sólido del pliegue (§5 de la guía de pantalla). --}}
                        <x-atoms.button type="submit" variant="outline" size="md" icon="search">
                            {{ __('mantenimiento.generadores.filtrar') }}
                        </x-atoms.button>

                        @if ($hayFiltrosActivos)
                            <x-atoms.button href="{{ route('panel.generadores.index') }}" variant="text" size="md">
                                {{ __('mantenimiento.generadores.limpiar_filtro') }}
                            </x-atoms.button>
                        @endif
                    </div>
                </form>
            @endif

            @if ($generadores->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.alert-strip variant="info" icon="bolt" class="ag-generadores__aviso">
                        {{ __('mantenimiento.generadores.filtro_vacio') }}
                    </x-molecules.alert-strip>
                @else
                    <x-molecules.empty-state
                        icon="bolt"
                        :title="__('mantenimiento.generadores.vacio_titulo')"
                        :detail="__('mantenimiento.generadores.vacio_detalle')"
                    />
                @endif
            @else
                <div class="ag-generadores__tabla" role="table">
                    <div class="ag-generadores__head" role="row">
                        <span role="columnheader">{{ __('mantenimiento.generadores.col_identificador') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.generadores.col_modelo') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.generadores.col_base') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.generadores.col_estado') }}</span>
                        <span role="columnheader" aria-hidden="true"></span>
                    </div>

                    @foreach ($generadores as $generador)
                        <div class="ag-generadores__fila" role="row">
                            <span role="cell" class="ag-generadores__identificador">{{ $generador->identificador }}</span>
                            <span role="cell">{{ $generador->modelo ?? __('mantenimiento.generadores.sin_modelo') }}</span>
                            <span role="cell">
                                {{ $generador->base_id !== null ? ($etiquetasBase[$generador->base_id] ?? "#{$generador->base_id}") : __('mantenimiento.generadores.sin_base') }}
                            </span>
                            <span role="cell">
                                <x-atoms.badge :variant="$variantePorEstado[$generador->estado]">
                                    {{ __('mantenimiento.estado.'.$generador->estado) }}
                                </x-atoms.badge>
                            </span>

                            <span role="cell" class="ag-generadores__acciones">
                                @puede('mantenimiento.generador.editar')
                                    <x-atoms.button href="{{ route('panel.generadores.edit', $generador) }}" variant="warning-outline" size="sm" icon="edit">
                                        {{ __('mantenimiento.generadores.editar') }}
                                    </x-atoms.button>
                                @endpuede

                                @puede('mantenimiento.generador.eliminar')
                                    <form
                                        method="POST"
                                        action="{{ route('panel.generadores.destroy', $generador) }}"
                                        onsubmit="return confirm('{{ __('mantenimiento.generadores.confirmar_baja') }}')"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <x-atoms.button type="submit" variant="danger-outline" size="sm" icon="delete">
                                            {{ __('mantenimiento.generadores.eliminar_accion') }}
                                        </x-atoms.button>
                                    </form>
                                @endpuede
                            </span>
                        </div>
                    @endforeach
                </div>

                @if ($generadores->hasPages())
                    <nav class="ag-generadores__paginacion" aria-label="{{ __('mantenimiento.generadores.paginacion_aria') }}">
                        @if (! $generadores->onFirstPage())
                            <x-atoms.button href="{{ $generadores->previousPageUrl() }}" variant="outline" size="sm" icon="chevron_left">
                                {{ __('mantenimiento.generadores.paginacion_anterior') }}
                            </x-atoms.button>
                        @endif

                        <span class="ag-generadores__paginacion-info">
                            {{ __('mantenimiento.generadores.paginacion_info', ['actual' => $generadores->currentPage(), 'total' => $generadores->lastPage()]) }}
                        </span>

                        @if ($generadores->hasMorePages())
                            <x-atoms.button href="{{ $generadores->nextPageUrl() }}" variant="outline" size="sm" icon="chevron_right" iconPosition="end">
                                {{ __('mantenimiento.generadores.paginacion_siguiente') }}
                            </x-atoms.button>
                        @endif
                    </nav>
                @endif
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
