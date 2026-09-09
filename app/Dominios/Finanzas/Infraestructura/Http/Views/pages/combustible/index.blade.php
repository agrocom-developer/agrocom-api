{{--
    Page: combustible/index (GET /panel/combustible, panel.combustible.index)
    Listado de cargas de combustible (HU-35, tarea 49): arquetipo Listado,
    §6.2 de docs/diseno/guia_pantalla_panel.md — cabecera → filtros → tabla
    → paginación. Mismo molde que gastos/index.blade.php, con dos filtros
    (base, rango de fecha) y sin acción de editar (invariante de esta
    tarea: una carga es inmutable salvo baja, ver Aplicacion/CrearCombustible).

    Datos esperados (ver CombustibleController::index()): la cáscara de
    CascaraPanel, más:
    - $combustibles (LengthAwarePaginator<Combustible>): fecha descendente.
    - $etiquetasBase (array<int, string>): id => nombre.
    - $etiquetasRecurso (array<string, string>): clave compuesta
      `"{tipo}:{id}"` => etiqueta, acotada a la página actual (tarea 73,
      mismo criterio que `GastosController::etiquetasTrabajo()`).
    - $basesDisponibles / $equiposDisponibles / $campaniasDisponibles
      (Collection<int, string>): para los <select> de filtro.
      `$campaniasDisponibles` NO se filtra por estado (a diferencia de la
      del formulario de alta): una campaña `cerrada` sigue teniendo
      historial de combustible que filtrar.
    - $filtros (array{base_id, desde, hasta, equipo_trabajo_id,
      campania_id}): valores aplicados, para dejar los campos con el valor
      tras el submit.
    - $total (string|null): suma (`Brick\Math\BigDecimal`, nunca `SUM()` de
      SQL) del combustible filtrado — solo se calcula/muestra cuando hay un
      equipo elegido (tarea 73, punto 5: "un total por equipo").
    - $puedeEliminar (bool): gatea el botón "Eliminar" por fila.

    Gateada por `finanzas.combustible.ver`, verificado server-side en el
    controlador. El botón "Nueva carga" y "Eliminar" se ocultan con `@puede`
    (presentación, no autorización — el servidor revalida en
    CombustibleController).

    Estilos en resources/css/pages/combustible.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('finanzas.combustible.titulo')" :tema="$tema">
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
        :vista-actual="__('finanzas.combustible.titulo')"
    >
        <div class="ag-combustible">
            <x-organisms.page-header
                :title="__('finanzas.combustible.titulo')"
                :subtitle="__('finanzas.combustible.subtitulo')"
            >
                @puede('finanzas.combustible.crear')
                    <x-slot:actions>
                        <x-atoms.button href="{{ route('panel.combustible.create') }}" variant="primary" icon="add">
                            {{ __('finanzas.combustible.nuevo') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-combustible__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            <form method="GET" action="{{ route('panel.combustible.index') }}" class="ag-filtros ag-combustible__filtros">
                <x-atoms.select
                    name="base_id"
                    id="filtro-base"
                    label="{{ __('finanzas.combustible.filtro_base') }}"
                    :options="$basesDisponibles"
                    :value="(string) $filtros['base_id']"
                    placeholder="{{ __('finanzas.combustible.filtro_base_placeholder') }}"
                />

                <x-atoms.select
                    name="equipo_trabajo_id"
                    id="filtro-equipo"
                    label="{{ __('finanzas.combustible.filtro_equipo') }}"
                    :options="$equiposDisponibles"
                    :value="(string) $filtros['equipo_trabajo_id']"
                    placeholder="{{ __('finanzas.combustible.filtro_equipo_placeholder') }}"
                />

                <x-atoms.select
                    name="campania_id"
                    id="filtro-campania"
                    label="{{ __('finanzas.combustible.filtro_campania') }}"
                    :options="$campaniasDisponibles"
                    :value="(string) $filtros['campania_id']"
                    placeholder="{{ __('finanzas.combustible.filtro_campania_placeholder') }}"
                />

                <x-atoms.date
                    name="desde"
                    id="filtro-desde"
                    label="{{ __('finanzas.combustible.filtro_desde') }}"
                    value="{{ $filtros['desde'] }}"
                />

                <x-atoms.date
                    name="hasta"
                    id="filtro-hasta"
                    label="{{ __('finanzas.combustible.filtro_hasta') }}"
                    value="{{ $filtros['hasta'] }}"
                />

                <div class="ag-filtros__acciones ag-combustible__filtros-acciones">
                    <x-atoms.button type="submit" variant="outline" size="md" icon="search">
                        {{ __('finanzas.combustible.filtrar') }}
                    </x-atoms.button>

                    @if ($filtros['base_id'] !== null || $filtros['desde'] !== '' || $filtros['hasta'] !== '' || $filtros['equipo_trabajo_id'] !== null || $filtros['campania_id'] !== null)
                        <x-atoms.button href="{{ route('panel.combustible.index') }}" variant="text" size="md">
                            {{ __('finanzas.combustible.limpiar_filtro') }}
                        </x-atoms.button>
                    @endif
                </div>
            </form>

            @if ($total !== null)
                <x-molecules.alert-strip variant="info" icon="functions" class="ag-combustible__aviso">
                    {{ __('finanzas.combustible.total_equipo', ['monto' => $total]) }}
                </x-molecules.alert-strip>
            @endif

            @if ($combustibles->isEmpty())
                <x-molecules.alert-strip variant="info" icon="local_gas_station" class="ag-combustible__aviso">
                    {{ __(($filtros['base_id'] !== null || $filtros['desde'] !== '' || $filtros['hasta'] !== '' || $filtros['equipo_trabajo_id'] !== null || $filtros['campania_id'] !== null) ? 'finanzas.combustible.filtro_vacio' : 'finanzas.combustible.vacio') }}
                </x-molecules.alert-strip>
            @else
                <div class="ag-combustible__tabla" role="table">
                    <div class="ag-combustible__head" role="row">
                        <span role="columnheader">{{ __('finanzas.combustible.col_fecha') }}</span>
                        <span role="columnheader">{{ __('finanzas.combustible.col_base') }}</span>
                        <span role="columnheader">{{ __('finanzas.combustible.col_recurso') }}</span>
                        <span role="columnheader">{{ __('finanzas.combustible.col_litros') }}</span>
                        <span role="columnheader">{{ __('finanzas.combustible.col_monto') }}</span>
                        <span role="columnheader" aria-hidden="true"></span>
                    </div>

                    @foreach ($combustibles as $combustible)
                        <div class="ag-combustible__fila" role="row">
                            <span role="cell" class="ag-combustible__cifra">{{ $combustible->fecha->format('d/m/Y') }}</span>
                            <span role="cell">{{ $etiquetasBase[$combustible->base_id] ?? "#{$combustible->base_id}" }}</span>
                            <span role="cell">{{ $etiquetasRecurso["{$combustible->recurso_tipo}:{$combustible->recurso_id}"] ?? "{$combustible->recurso_tipo} #{$combustible->recurso_id}" }}</span>
                            <span role="cell" class="ag-combustible__cifra">{{ __('finanzas.combustible.litros_valor', ['litros' => $combustible->litros]) }}</span>
                            <span role="cell" class="ag-combustible__cifra">{{ __('finanzas.combustible.monto_valor', ['monto' => $combustible->monto]) }}</span>

                            <span role="cell" class="ag-combustible__acciones">
                                @if ($puedeEliminar)
                                    <form
                                        method="POST"
                                        action="{{ route('panel.combustible.destroy', $combustible) }}"
                                        onsubmit="return confirm('{{ __('finanzas.combustible.confirmar_baja') }}')"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <x-atoms.button type="submit" variant="danger-outline" size="sm" icon="delete">
                                            {{ __('finanzas.combustible.eliminar_accion') }}
                                        </x-atoms.button>
                                    </form>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>

                @if ($combustibles->hasPages())
                    <nav class="ag-combustible__paginacion" aria-label="{{ __('finanzas.combustible.paginacion_aria') }}">
                        @if (! $combustibles->onFirstPage())
                            <x-atoms.button href="{{ $combustibles->previousPageUrl() }}" variant="outline" size="sm" icon="chevron_left">
                                {{ __('finanzas.combustible.paginacion_anterior') }}
                            </x-atoms.button>
                        @endif

                        <span class="ag-combustible__paginacion-info">
                            {{ __('finanzas.combustible.paginacion_info', ['actual' => $combustibles->currentPage(), 'total' => $combustibles->lastPage()]) }}
                        </span>

                        @if ($combustibles->hasMorePages())
                            <x-atoms.button href="{{ $combustibles->nextPageUrl() }}" variant="outline" size="sm" icon="chevron_right" iconPosition="end">
                                {{ __('finanzas.combustible.paginacion_siguiente') }}
                            </x-atoms.button>
                        @endif
                    </nav>
                @endif
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
