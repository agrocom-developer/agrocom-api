{{--
    Page: drones/index (GET /panel/drones, panel.drones.index)
    Listado de la flota de drones (HU-27, tarea 36): arquetipo Listado, §6.2
    de docs/diseno/guia_pantalla_panel.md — cabecera → filtros → tabla →
    paginación. Mismo molde que campos/index.blade.php (tarea 35), sin
    sub-entidad: un dron no tiene lotes ni contactos.

    Columna "Capacidad de carga" (HU-81, tarea 96): un dron puede tener
    litros, kilos, ambos o ninguno — se muestran las dos cifras que existan
    separadas por "·", sin agregar columna nueva.

    Datos esperados (ver DronesController::index()): la cáscara de
    CascaraPanel, más:
    - $drones (LengthAwarePaginator<Dron>): identificador ascendente.
    - $filtros (array{q: string}): búsqueda aplicada, para dejar el campo
      con el valor tras el submit.

    Gateada por `operaciones.dron.ver`, verificado server-side en el
    controlador. Los botones "Nuevo dron"/"Editar"/"Eliminar" se ocultan con
    `@puede` (presentación, no autorización — el servidor revalida en
    DronesController).

    Estilos en resources/css/pages/drones.css — cero color hardcodeado
    (CLAUDE.md invariante 11).
--}}
<x-templates.panel-shell :title="__('operaciones.drones.titulo')" :tema="$tema">
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
        :vista-actual="__('operaciones.drones.titulo')"
    >
        <div class="ag-drones">
            <x-organisms.page-header
                :title="__('operaciones.drones.titulo')"
                :subtitle="__('operaciones.drones.subtitulo')"
            >
                @puede('operaciones.dron.crear')
                    <x-slot:actions>
                        <x-atoms.button href="{{ route('panel.drones.create') }}" variant="primary" icon="add">
                            {{ __('operaciones.drones.nuevo') }}
                        </x-atoms.button>
                    </x-slot:actions>
                @endpuede
            </x-organisms.page-header>

            @if (session('estado'))
                <x-molecules.alert-strip variant="success" icon="check_circle" class="ag-drones__aviso">
                    {{ session('estado') }}
                </x-molecules.alert-strip>
            @endif

            <form method="GET" action="{{ route('panel.drones.index') }}" class="ag-filtros ag-drones__filtros">
                <div class="ag-input">
                    <label for="filtro-q" class="ag-input__label">{{ __('operaciones.drones.filtro_busqueda') }}</label>
                    <div class="ag-input__control">
                        <input
                            type="search"
                            name="q"
                            id="filtro-q"
                            class="ag-input__field"
                            value="{{ $filtros['q'] }}"
                            placeholder="{{ __('operaciones.drones.filtro_busqueda_placeholder') }}"
                        >
                    </div>
                </div>

                <div class="ag-filtros__acciones ag-drones__filtros-acciones">
                    {{-- outline, no primary: "Nuevo dron" ya es el único botón
                         sólido del pliegue (§5 de la guía de pantalla). --}}
                    <x-atoms.button type="submit" variant="outline" size="md" icon="search">
                        {{ __('operaciones.drones.filtrar') }}
                    </x-atoms.button>

                    @if ($filtros['q'] !== '')
                        <x-atoms.button href="{{ route('panel.drones.index') }}" variant="text" size="md">
                            {{ __('operaciones.drones.limpiar_filtro') }}
                        </x-atoms.button>
                    @endif
                </div>
            </form>

            @if ($drones->isEmpty())
                <x-molecules.alert-strip variant="info" icon="airplanemode_active" class="ag-drones__aviso">
                    {{ __($filtros['q'] !== '' ? 'operaciones.drones.filtro_vacio' : 'operaciones.drones.vacio') }}
                </x-molecules.alert-strip>
            @else
                <div class="ag-drones__tabla" role="table">
                    <div class="ag-drones__head" role="row">
                        <span role="columnheader">{{ __('operaciones.drones.col_identificador') }}</span>
                        <span role="columnheader">{{ __('operaciones.drones.col_modelo') }}</span>
                        <span role="columnheader">{{ __('operaciones.drones.col_capacidad') }}</span>
                        <span role="columnheader" aria-hidden="true"></span>
                    </div>

                    @foreach ($drones as $dron)
                        <div class="ag-drones__fila" role="row">
                            <span role="cell" class="ag-drones__identificador">{{ $dron->identificador }}</span>
                            <span role="cell">{{ $dron->modelo ?? __('operaciones.drones.sin_modelo') }}</span>
                            <span role="cell" class="ag-drones__capacidad">
                                @php
                                    $capacidades = array_filter([
                                        $dron->capacidad_l !== null ? __('operaciones.drones.capacidad_valor', ['cantidad' => (int) $dron->capacidad_l]) : null,
                                        $dron->capacidad_kg !== null ? __('operaciones.drones.capacidad_kg_valor', ['cantidad' => number_format((float) $dron->capacidad_kg, 2, ',', '.')]) : null,
                                    ]);
                                @endphp
                                {{ $capacidades !== [] ? implode(' · ', $capacidades) : __('operaciones.drones.sin_capacidad') }}
                            </span>

                            <span role="cell" class="ag-drones__acciones">
                                @puede('operaciones.dron.editar')
                                    <x-atoms.button href="{{ route('panel.drones.edit', $dron) }}" variant="warning-outline" size="sm" icon="edit">
                                        {{ __('operaciones.drones.editar') }}
                                    </x-atoms.button>
                                @endpuede

                                @puede('operaciones.dron.eliminar')
                                    <form
                                        method="POST"
                                        action="{{ route('panel.drones.destroy', $dron) }}"
                                        onsubmit="return confirm('{{ __('operaciones.drones.confirmar_baja') }}')"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <x-atoms.button type="submit" variant="danger-outline" size="sm" icon="delete">
                                            {{ __('operaciones.drones.eliminar_accion') }}
                                        </x-atoms.button>
                                    </form>
                                @endpuede
                            </span>
                        </div>
                    @endforeach
                </div>

                @if ($drones->hasPages())
                    <nav class="ag-drones__paginacion" aria-label="{{ __('operaciones.drones.paginacion_aria') }}">
                        @if (! $drones->onFirstPage())
                            <x-atoms.button href="{{ $drones->previousPageUrl() }}" variant="outline" size="sm" icon="chevron_left">
                                {{ __('operaciones.drones.paginacion_anterior') }}
                            </x-atoms.button>
                        @endif

                        <span class="ag-drones__paginacion-info">
                            {{ __('operaciones.drones.paginacion_info', ['actual' => $drones->currentPage(), 'total' => $drones->lastPage()]) }}
                        </span>

                        @if ($drones->hasMorePages())
                            <x-atoms.button href="{{ $drones->nextPageUrl() }}" variant="outline" size="sm" icon="chevron_right" iconPosition="end">
                                {{ __('operaciones.drones.paginacion_siguiente') }}
                            </x-atoms.button>
                        @endif
                    </nav>
                @endif
            @endif
        </div>
    </x-templates.panel-layout>
</x-templates.panel-shell>
