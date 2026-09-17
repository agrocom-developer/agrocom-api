{{--
    Page: vehiculos/index (GET /panel/vehiculos, panel.vehiculos.index)
    Listado de la flota de vehículos (HU-40, tarea 50): arquetipo Listado,
    §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera → filtros → tabla →
    paginación. Mismo molde que drones/index.blade.php (tarea 36), con dos
    filtros adicionales (base, estado) en vez de uno solo.

    Datos esperados (ver VehiculosController::index()): la cáscara de
    CascaraPanel, más:
    - $vehiculos (LengthAwarePaginator<Vehiculo>): identificador ascendente.
    - $etiquetasBase (array<int, string>): nombre de base por id, ya resuelto
      por el controlador (`DB::table('per_bases')` — Vehiculo no tiene
      relación Eloquent hacia PerBase, son de módulos distintos). Un id sin
      etiqueta (base borrada) cae al `#id` crudo.
    - $basesDisponibles (Collection<int, string>): opciones del filtro/select
      de base.
    - $filtros (array{q: string, base_id: ?int, estado: ?string}): filtros
      aplicados, para dejar los campos con su valor tras el submit.

    Gateada por `mantenimiento.vehiculo.ver`, verificado server-side en el
    controlador. Los botones "Nuevo vehículo"/"Editar"/"Eliminar" se ocultan
    con `@puede` (presentación, no autorización — el servidor revalida en
    VehiculosController).

    Estilos en resources/css/pages/vehiculos.css — cero color hardcodeado
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
<x-templates.panel-shell :title="__('mantenimiento.vehiculos.titulo')" :tema="$tema">
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
        :vista-actual="__('mantenimiento.vehiculos.titulo')"
    >
        <div class="ag-vehiculos">
            <x-organisms.page-header
                :title="__('mantenimiento.vehiculos.titulo')"
                :subtitle="__('mantenimiento.vehiculos.subtitulo')"
            >
                @puede('mantenimiento.vehiculo.crear')
                    <x-slot:actions>
                        <x-atoms.button :href="route('panel.vehiculos.create')" variant="primary" icon="add">
                            {{ __('mantenimiento.vehiculos.nuevo') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-vehiculos__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            @php
                $hayFiltrosActivos = collect($filtros)->contains(fn ($valor) => $valor !== null && $valor !== '');
            @endphp

            @if ($hayFiltrosActivos || $vehiculos->isNotEmpty())
                <div class="ag-table-toolbar">
                    <x-molecules.table-search
                        :action="route('panel.vehiculos.index')"
                        :value="$filtros['q']"
                        :placeholder="__('mantenimiento.vehiculos.filtro_busqueda_placeholder')"
                        :clear-label="__('ui.tabla.buscador_limpiar')"
                    />
                </div>
            @endif

            @if ($hayFiltrosActivos || $vehiculos->isNotEmpty())
                <form method="GET" action="{{ route('panel.vehiculos.index') }}" class="ag-filtros ag-vehiculos__filtros">
                    <input type="hidden" name="q" value="{{ $filtros['q'] }}">
                    <x-atoms.select
                        name="base_id"
                        id="filtro-base"
                        :label="__('mantenimiento.vehiculos.filtro_base')"
                        :options="$basesDisponibles"
                        :value="$filtros['base_id']"
                        :placeholder="__('mantenimiento.vehiculos.filtro_todos')"
                    />

                    @php
                        $opcionesEstado = collect($variantePorEstado)->mapWithKeys(fn ($_, $valor) => [
                            $valor => __('mantenimiento.estado.'.$valor)
                        ])->all();
                    @endphp
                    <x-atoms.select
                        name="estado"
                        id="filtro-estado"
                        :label="__('mantenimiento.vehiculos.filtro_estado')"
                        :options="$opcionesEstado"
                        :value="$filtros['estado']"
                        :placeholder="__('mantenimiento.vehiculos.filtro_todos')"
                    />

                    <div class="ag-filtros__acciones ag-vehiculos__filtros-acciones">
                        <x-atoms.button type="submit" variant="primary" size="md" icon="search">
                            {{ __('mantenimiento.vehiculos.filtrar') }}
                        </x-atoms.button>

                        @if ($hayFiltrosActivos)
                            <x-atoms.button :href="route('panel.vehiculos.index')" variant="text" size="md">
                                {{ __('mantenimiento.vehiculos.limpiar_filtro') }}
                            </x-atoms.button>
                        @endif
                    </div>
                </form>
            @endif

            @if ($vehiculos->isEmpty())
                @if ($hayFiltrosActivos)
                    <x-molecules.alert-strip variant="info" icon="local_shipping" class="ag-vehiculos__aviso">
                        {{ __('mantenimiento.vehiculos.filtro_vacio') }}
                    </x-molecules.alert-strip>
                @else
                    <x-molecules.empty-state
                        icon="local_shipping"
                        :title="__('mantenimiento.vehiculos.vacio_titulo')"
                        :detail="__('mantenimiento.vehiculos.vacio_detalle')"
                    />
                @endif
            @else
                <div class="ag-vehiculos__tabla" role="table">
                    <div class="ag-vehiculos__head" role="row">
                        <span role="columnheader" class="ag-vehiculos__indice">{{ __('ui.tabla.col_indice') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.vehiculos.col_identificador') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.vehiculos.col_base') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.vehiculos.col_estado') }}</span>
                        <span role="columnheader" aria-hidden="true"></span>
                    </div>

                    @foreach ($vehiculos as $vehiculo)
                        <div class="ag-vehiculos__fila" role="row">
                            <span role="cell" class="ag-vehiculos__indice">
                                {{ ($vehiculos->currentPage() - 1) * $vehiculos->perPage() + $loop->iteration }}
                            </span>
                            <span role="cell" class="ag-vehiculos__identificador">{{ $vehiculo->identificador }}</span>
                            <span role="cell">
                                {{ $vehiculo->base_id !== null ? ($etiquetasBase[$vehiculo->base_id] ?? "#{$vehiculo->base_id}") : __('mantenimiento.vehiculos.sin_base') }}
                            </span>
                            <span role="cell">
                                <x-atoms.badge :variant="$variantePorEstado[$vehiculo->estado]">
                                    {{ __('mantenimiento.estado.'.$vehiculo->estado) }}
                                </x-atoms.badge>
                            </span>

                            <span role="cell" class="ag-vehiculos__acciones">
                                @puede('mantenimiento.vehiculo.editar')
                                    <x-atoms.button :href="route('panel.vehiculos.edit', $vehiculo)" variant="warning-outline" size="sm" icon="edit">
                                        {{ __('mantenimiento.vehiculos.editar') }}
                                    </x-atoms.button>
                                @endpuede

                                @puede('mantenimiento.vehiculo.eliminar')
                                    <form
                                        method="POST"
                                        action="{{ route('panel.vehiculos.destroy', $vehiculo) }}"
                                        onsubmit="return confirm('{{ __('mantenimiento.vehiculos.confirmar_baja') }}')"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <x-atoms.button type="submit" variant="danger-outline" size="sm" icon="delete">
                                            {{ __('mantenimiento.vehiculos.eliminar_accion') }}
                                        </x-atoms.button>
                                    </form>
                                @endpuede
                            </span>
                        </div>
                    @endforeach
                </div>

                <x-molecules.pagination :paginator="$vehiculos" :aria-label="__('mantenimiento.vehiculos.paginacion_aria')" />
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
