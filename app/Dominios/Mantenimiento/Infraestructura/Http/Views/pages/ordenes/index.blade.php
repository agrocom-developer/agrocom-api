{{--
    Page: ordenes/index (GET /panel/ordenes-mantenimiento, panel.ordenes-mantenimiento.index)
    Listado de órdenes de mantenimiento (HU-37, tarea 53): arquetipo Listado,
    §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera → filtros → tabla →
    paginación. Mismo molde que vehiculos/index.blade.php, con filtros por
    estado y tipo de equipo en vez de búsqueda libre (no hay identificador de
    texto propio de la orden).

    Datos esperados (ver OrdenesMantenimientoController::index()): la
    cáscara de CascaraPanel, más:
    - $ordenes (LengthAwarePaginator<OrdenMantenimiento>): fecha de apertura
      descendente.
    - $etiquetasEquipo (array{dron: array<int,string>, vehiculo: array<int,string>}):
      identificador por id, ya resuelto por el controlador (`DB::table(...)`
      — OrdenMantenimiento no tiene relación Eloquent hacia Dron/Vehiculo,
      son de módulos distintos vía `equipo_tipo`+`equipo_id` sin FK real). Un
      id sin etiqueta (equipo borrado) cae al `#id` crudo.
    - $filtros (array{estado: ?string, equipo_tipo: ?string}): filtros
      aplicados, para dejar los campos con su valor tras el submit.
    - $puedeCrear (bool): gatea el botón "Nueva orden" (presentación, no
      autorización — el servidor revalida en el controlador).

    Gateada por `mantenimiento.orden.ver`, verificado server-side en el
    controlador. Sin acción de eliminar: una orden de mantenimiento no se
    borra (invariante 8), solo se abre y se cierra.

    Estilos en resources/css/pages/ordenes-mantenimiento.css — cero color
    hardcodeado (CLAUDE.md invariante 11). El badge de estado usa el eje
    neutral↔success (abierta=neutral, cerrada=success), mismo criterio que el
    resto del panel: nunca ámbar para un chip que se repite fila a fila.
--}}
@php
    $variantePorEstado = [
        'abierta' => 'neutral',
        'cerrada' => 'success',
    ];
@endphp
<x-templates.panel-shell :title="__('mantenimiento.ordenes.titulo')" :tema="$tema">
    <x-templates.panel-layout
        :menu="$menu"
        :roles="$roles"
        :rol-activo-id="$rolActivoId"
        :active-role-label="$activeRoleLabel"
        :user-name="$userName"
        :notifications="$notifications"
        :menu-badges="$menuBadges"
        :campaniaActiva="$campaniaActiva"
        :periodo="$periodo"
        :version="$version"
        :vista-actual="__('mantenimiento.ordenes.titulo')"
    >
        <div class="ag-ordenes-mantenimiento">
            <x-organisms.page-header
                :title="__('mantenimiento.ordenes.titulo')"
                :subtitle="__('mantenimiento.ordenes.subtitulo')"
            >
                @if ($puedeCrear)
                    <x-slot:actions>
                        <x-atoms.button href="{{ route('panel.ordenes-mantenimiento.create') }}" variant="primary" icon="add">
                            {{ __('mantenimiento.ordenes.nuevo') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endif
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-ordenes-mantenimiento__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            <form method="GET" action="{{ route('panel.ordenes-mantenimiento.index') }}" class="ag-filtros ag-ordenes-mantenimiento__filtros">
                @php
                    $opcionesEstado = collect($variantePorEstado)->mapWithKeys(fn ($_, $valor) => [
                        $valor => __('mantenimiento.estado_orden.'.$valor)
                    ])->all();
                @endphp
                <x-atoms.select
                    name="estado"
                    id="filtro-estado"
                    label="{{ __('mantenimiento.ordenes.filtro_estado') }}"
                    :options="$opcionesEstado"
                    :value="$filtros['estado']"
                    placeholder="{{ __('mantenimiento.ordenes.filtro_todos') }}"
                />

                @php
                    $opcionesEquipoTipo = [
                        'dron' => __('mantenimiento.equipo_tipo.dron'),
                        'vehiculo' => __('mantenimiento.equipo_tipo.vehiculo'),
                    ];
                @endphp
                <x-atoms.select
                    name="equipo_tipo"
                    id="filtro-equipo-tipo"
                    label="{{ __('mantenimiento.ordenes.filtro_equipo_tipo') }}"
                    :options="$opcionesEquipoTipo"
                    :value="$filtros['equipo_tipo']"
                    placeholder="{{ __('mantenimiento.ordenes.filtro_todos') }}"
                />

                <div class="ag-filtros__acciones ag-ordenes-mantenimiento__filtros-acciones">
                    <x-atoms.button type="submit" variant="outline" size="md" icon="search">
                        {{ __('mantenimiento.ordenes.filtrar') }}
                    </x-atoms.button>

                    @if ($filtros['estado'] !== null || $filtros['equipo_tipo'] !== null)
                        <x-atoms.button href="{{ route('panel.ordenes-mantenimiento.index') }}" variant="text" size="md">
                            {{ __('mantenimiento.ordenes.limpiar_filtro') }}
                        </x-atoms.button>
                    @endif
                </div>
            </form>

            @if ($ordenes->isEmpty())
                <x-molecules.alert-strip variant="info" icon="build" class="ag-ordenes-mantenimiento__aviso">
                    {{ __(($filtros['estado'] !== null || $filtros['equipo_tipo'] !== null) ? 'mantenimiento.ordenes.filtro_vacio' : 'mantenimiento.ordenes.vacio') }}
                </x-molecules.alert-strip>
            @else
                <div class="ag-ordenes-mantenimiento__tabla" role="table">
                    <div class="ag-ordenes-mantenimiento__head" role="row">
                        <span role="columnheader">{{ __('mantenimiento.ordenes.col_equipo') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.ordenes.col_tipo') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.ordenes.col_estado') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.ordenes.col_fecha_apertura') }}</span>
                        <span role="columnheader">{{ __('mantenimiento.ordenes.col_fecha_cierre') }}</span>
                        <span role="columnheader" aria-hidden="true"></span>
                    </div>

                    @foreach ($ordenes as $orden)
                        <div class="ag-ordenes-mantenimiento__fila" role="row">
                            <span role="cell" class="ag-ordenes-mantenimiento__equipo">
                                {{ __('mantenimiento.equipo_tipo.'.$orden->equipo_tipo) }} · {{ $etiquetasEquipo[$orden->equipo_tipo][$orden->equipo_id] ?? "#{$orden->equipo_id}" }}
                            </span>
                            <span role="cell">{{ __('mantenimiento.tipo_orden.'.$orden->tipo) }}</span>
                            <span role="cell">
                                <x-atoms.badge :variant="$variantePorEstado[$orden->estado->value]">
                                    {{ __('mantenimiento.estado_orden.'.$orden->estado->value) }}
                                </x-atoms.badge>
                            </span>
                            <span role="cell">{{ $orden->fecha_apertura->format('d/m/Y') }}</span>
                            <span role="cell">{{ $orden->fecha_cierre?->format('d/m/Y') ?? __('mantenimiento.ordenes.sin_fecha_cierre') }}</span>

                            <span role="cell" class="ag-ordenes-mantenimiento__acciones">
                                <x-atoms.button href="{{ route('panel.ordenes-mantenimiento.edit', $orden) }}" variant="outline" size="sm" icon="visibility">
                                    {{ __('mantenimiento.ordenes.ver_accion') }}
                                </x-atoms.button>
                            </span>
                        </div>
                    @endforeach
                </div>

                @if ($ordenes->hasPages())
                    <nav class="ag-ordenes-mantenimiento__paginacion" aria-label="{{ __('mantenimiento.ordenes.paginacion_aria') }}">
                        @if (! $ordenes->onFirstPage())
                            <x-atoms.button href="{{ $ordenes->previousPageUrl() }}" variant="outline" size="sm" icon="chevron_left">
                                {{ __('mantenimiento.ordenes.paginacion_anterior') }}
                            </x-atoms.button>
                        @endif

                        <span class="ag-ordenes-mantenimiento__paginacion-info">
                            {{ __('mantenimiento.ordenes.paginacion_info', ['actual' => $ordenes->currentPage(), 'total' => $ordenes->lastPage()]) }}
                        </span>

                        @if ($ordenes->hasMorePages())
                            <x-atoms.button href="{{ $ordenes->nextPageUrl() }}" variant="outline" size="sm" icon="chevron_right" iconPosition="end">
                                {{ __('mantenimiento.ordenes.paginacion_siguiente') }}
                            </x-atoms.button>
                        @endif
                    </nav>
                @endif
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
