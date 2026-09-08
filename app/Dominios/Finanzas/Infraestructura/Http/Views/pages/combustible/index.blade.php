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
    - $basesDisponibles (Collection<int, string>): para el <select> de filtro.
    - $filtros (array{base_id, desde, hasta}): valores aplicados, para dejar
      los campos con el valor tras el submit.
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
                <div class="ag-input">
                    <label for="filtro-base" class="ag-input__label">{{ __('finanzas.combustible.filtro_base') }}</label>
                    <div class="ag-input__control">
                        <select name="base_id" id="filtro-base" class="ag-input__field">
                            <option value="">{{ __('finanzas.combustible.filtro_base_placeholder') }}</option>
                            @foreach ($basesDisponibles as $id => $nombre)
                                <option value="{{ $id }}" @selected((string) $filtros['base_id'] === (string) $id)>{{ $nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="ag-input">
                    <label for="filtro-desde" class="ag-input__label">{{ __('finanzas.combustible.filtro_desde') }}</label>
                    <div class="ag-input__control">
                        <input type="date" name="desde" id="filtro-desde" class="ag-input__field" value="{{ $filtros['desde'] }}">
                    </div>
                </div>

                <div class="ag-input">
                    <label for="filtro-hasta" class="ag-input__label">{{ __('finanzas.combustible.filtro_hasta') }}</label>
                    <div class="ag-input__control">
                        <input type="date" name="hasta" id="filtro-hasta" class="ag-input__field" value="{{ $filtros['hasta'] }}">
                    </div>
                </div>

                <div class="ag-filtros__acciones ag-combustible__filtros-acciones">
                    <x-atoms.button type="submit" variant="outline" size="md" icon="search">
                        {{ __('finanzas.combustible.filtrar') }}
                    </x-atoms.button>

                    @if ($filtros['base_id'] !== null || $filtros['desde'] !== '' || $filtros['hasta'] !== '')
                        <x-atoms.button href="{{ route('panel.combustible.index') }}" variant="text" size="md">
                            {{ __('finanzas.combustible.limpiar_filtro') }}
                        </x-atoms.button>
                    @endif
                </div>
            </form>

            @if ($combustibles->isEmpty())
                <x-molecules.alert-strip variant="info" icon="local_gas_station" class="ag-combustible__aviso">
                    {{ __(($filtros['base_id'] !== null || $filtros['desde'] !== '' || $filtros['hasta'] !== '') ? 'finanzas.combustible.filtro_vacio' : 'finanzas.combustible.vacio') }}
                </x-molecules.alert-strip>
            @else
                <div class="ag-combustible__tabla" role="table">
                    <div class="ag-combustible__head" role="row">
                        <span role="columnheader">{{ __('finanzas.combustible.col_fecha') }}</span>
                        <span role="columnheader">{{ __('finanzas.combustible.col_base') }}</span>
                        <span role="columnheader">{{ __('finanzas.combustible.col_destino') }}</span>
                        <span role="columnheader">{{ __('finanzas.combustible.col_litros') }}</span>
                        <span role="columnheader">{{ __('finanzas.combustible.col_monto') }}</span>
                        <span role="columnheader" aria-hidden="true"></span>
                    </div>

                    @foreach ($combustibles as $combustible)
                        <div class="ag-combustible__fila" role="row">
                            <span role="cell" class="ag-combustible__cifra">{{ $combustible->fecha->format('d/m/Y') }}</span>
                            <span role="cell">{{ $etiquetasBase[$combustible->base_id] ?? "#{$combustible->base_id}" }}</span>
                            <span role="cell">{{ __('finanzas.combustible.destino.'.$combustible->destino) }}</span>
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
